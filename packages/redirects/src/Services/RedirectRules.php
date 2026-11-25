<?php

declare(strict_types=1);

namespace Uptms\Redirects\Services;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Publishing\Events\ContentPublished;
use Uptms\Redirects\Models\Redirect;
use Uptms\Seo\Services\SeoSources;

/**
 * Redirect rules (Figma 20). Exact paths, or a trailing `*` whose remainder
 * fills `$1` in the target. Saving refuses loops, shortens chains (A→B→C
 * becomes A→C), and refuses to hide a live page behind a redirect.
 */
final readonly class RedirectRules
{
    /** Addresses the website serves besides CMS content. */
    private const array APP_ROUTES = ['/', '/resources'];

    private const int MAX_HOPS = 10;

    public function __construct(private SeoSources $sources, private AuditRecorder $audit) {}

    /** "/OCD-Treatment/" → "/ocd-treatment"; keeps a trailing "/*" for wildcards. */
    public static function normalise(string $path): string
    {
        $path = trim($path);
        $path = (string) preg_replace('#^https?://[^/]+#i', '', $path);
        $path = (string) strtok($path, '?#');
        $path = '/'.ltrim($path, '/');
        $path = mb_strtolower(rawurldecode($path));

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /** A target keeps its #anchor and query; site paths are normalised, outside URLs kept as written. */
    public static function normaliseTarget(?string $to): ?string
    {
        if ($to === null || trim($to) === '') {
            return null;
        }

        $to = trim($to);

        if (preg_match('#^https://#i', $to) === 1) {
            return $to;
        }

        $suffix = '';
        if (preg_match('/([?#].*)$/', $to, $m) === 1) {
            $suffix = $m[1];
            $to = substr($to, 0, -strlen($suffix));
        }

        return self::normalise($to === '' ? '/' : $to).$suffix;
    }

    /**
     * The target for a request path, or null.
     *
     * @return array{redirect: Redirect, to: string|null}|null
     */
    public function match(string $path): ?array
    {
        $path = self::normalise($path);
        $exact = Redirect::query()->where('is_enabled', true)->where('match_type', 'exact')->where('from_path', $path)->first();

        if ($exact instanceof Redirect) {
            return ['redirect' => $exact, 'to' => $exact->to_path];
        }

        foreach ($this->wildcards() as $rule) {
            $prefix = rtrim(substr($rule->from_path, 0, -1), '/');

            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                $rest = ltrim(substr($path, strlen($prefix)), '/');

                return ['redirect' => $rule, 'to' => $rule->to_path === null ? null : str_replace('$1', $rest, $rule->to_path)];
            }
        }

        return null;
    }

    /**
     * What happens if `from` sends to `to` (the green line under Figma 20's form).
     *
     * @return array{loop: bool, chain: list<string>, target: array{state: string, path: string|null}, shadows_content: bool}
     */
    public function check(string $from, ?string $to, ?int $ignoreId = null): array
    {
        $from = self::normalise($from);
        $chain = [];
        $loop = false;
        $current = self::normaliseTarget($to);

        for ($hop = 0; $current !== null && ! str_starts_with($current, 'https://') && $hop < self::MAX_HOPS; $hop++) {
            $path = self::normalise($current);

            if ($path === $from) {
                $loop = true;
                break;
            }

            $next = $this->match($path);

            if ($next === null || ($ignoreId !== null && $next['redirect']->id === $ignoreId)) {
                break;
            }

            $chain[] = $path;
            $current = $next['to'];
        }

        if ($hop >= self::MAX_HOPS) {
            $loop = true;
        }

        $final = $current === null ? null : (str_starts_with($current, 'https://') ? $current : self::normalise($current));

        return [
            'loop' => $loop,
            'chain' => $chain,
            'target' => [
                'state' => match (true) {
                    $to === null || trim((string) $to) === '' => 'gone',
                    $final !== null && str_starts_with($final, 'https://') => 'external',
                    $final !== null && $this->exists($final) => 'ok',
                    default => 'missing',
                },
                'path' => $final,
            ],
            'shadows_content' => ! str_ends_with($from, '*') && $this->exists($from),
        ];
    }

    /**
     * @param  array{from_path: string, to_path?: string|null, status_code?: int, is_enabled?: bool, note?: string|null}  $data
     */
    public function save(?Redirect $redirect, array $data, ?int $actorId, string $source = 'manual'): Redirect
    {
        $from = self::normalise($data['from_path']);
        $code = (int) ($data['status_code'] ?? 301);
        $to = $code === 410 ? null : self::normaliseTarget($data['to_path'] ?? null);
        $wildcard = str_ends_with($from, '/*');

        if ($code !== 410 && $to === null) {
            throw new GenericDomainException(ErrorCode::ValidationFailed, 'Choose where to send visitors.', ['to_path' => ['Choose where to send visitors.']]);
        }

        if ($to !== null && ! str_starts_with($to, 'https://') && self::normalise($to) === $from) {
            throw new GenericDomainException(ErrorCode::ValidationFailed, 'An address can’t redirect to itself.', ['to_path' => ['An address can’t redirect to itself.']]);
        }

        $check = $this->check($from, $to, $redirect?->id);

        if ($check['loop']) {
            throw new GenericDomainException(ErrorCode::Conflict, 'That would make a redirect loop.', ['to_path' => ['That would send visitors in a circle: '.implode(' → ', [$from, ...$check['chain'], $from]).'.']]);
        }

        if ($check['shadows_content'] && $source !== 'slug_change') {
            throw new GenericDomainException(ErrorCode::Conflict, 'A live page is at that address.', ['from_path' => ['A published page lives at this address. Unpublish or move it first.']]);
        }

        $redirect = DB::transaction(function () use ($redirect, $from, $to, $code, $wildcard, $data, $actorId, $source, $check): Redirect {
            $redirect ??= Redirect::query()->where('from_path', $from)->first() ?? new Redirect;
            $creating = ! $redirect->exists;
            // Point straight at the end of any chain.
            $target = $to !== null && $check['chain'] !== [] && $check['target']['path'] !== null && ! $wildcard ? $check['target']['path'] : $to;

            $redirect->fill([
                'from_path' => $from,
                'to_path' => $target,
                'status_code' => $code,
                'match_type' => $wildcard ? 'wildcard' : 'exact',
                'is_enabled' => $data['is_enabled'] ?? true,
                'note' => $data['note'] ?? $redirect->note,
            ]);

            if ($creating) {
                $redirect->source = $source;
                $redirect->created_by = $actorId;
            }

            $redirect->save();

            // Rules that pointed at this address now point past it.
            if ($target !== null && ! $wildcard) {
                Redirect::query()->whereKeyNot($redirect->id)->where('to_path', $from)->update(['to_path' => $target, 'updated_at' => now()]);
            }

            return $redirect;
        });

        $this->published();

        return $redirect;
    }

    /** A live page moved: 301 from the old address, and never a rule from its new one. */
    public function pathChanged(string $from, string $to): void
    {
        $from = self::normalise($from);
        $to = self::normalise($to);

        DB::transaction(function () use ($from, $to): void {
            // Moving back to an earlier address: that address's redirect must go.
            Redirect::query()->where('from_path', $to)->delete();

            $redirect = Redirect::query()->where('from_path', $from)->first() ?? new Redirect(['from_path' => $from]);
            $redirect->fill(['to_path' => $to, 'status_code' => 301, 'match_type' => 'exact', 'is_enabled' => true]);
            if (! $redirect->exists) {
                $redirect->source = 'slug_change';
            }
            $redirect->save();

            Redirect::query()->whereKeyNot($redirect->id)->where('to_path', $from)->update(['to_path' => $to, 'updated_at' => now()]);
            $this->audit->record('redirect.slug_change', $redirect, null, ['from' => $from, 'to' => $to]);
        });

        $this->published();
    }

    /** True when a published page, post or condition (or a site route) lives at the path. */
    public function exists(string $path): bool
    {
        $path = self::normalise($path);

        return in_array($path, self::APP_ROUTES, true) || in_array($path, $this->livePaths(), true);
    }

    /** @return list<string> */
    public function livePaths(): array
    {
        return array_values(array_unique(array_map(fn ($subject): string => self::normalise($subject->path), $this->sources->allPublished())));
    }

    /** @return list<string> every address the website serves */
    public function knownPaths(): array
    {
        return array_values(array_unique([...self::APP_ROUTES, ...$this->livePaths()]));
    }

    public function published(): void
    {
        ContentPublished::dispatch(['redirects']);
    }

    /** @return list<Redirect> longest prefix first */
    private function wildcards(): array
    {
        return array_values(Redirect::query()->where('is_enabled', true)->where('match_type', 'wildcard')->get()
            ->sortByDesc(fn (Redirect $r): int => strlen($r->from_path))->values()->all());
    }
}
