<?php

declare(strict_types=1);

namespace Uptms\Redirects\Http\Controllers\Admin;

use App\Support\Http\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Redirects\Models\Redirect;
use Uptms\Redirects\Services\RedirectRules;

/** Figma 20. Everything here needs seo.manage (route middleware). */
class RedirectController
{
    public function __construct(private readonly RedirectRules $rules) {}

    /** `?search=&filter[type]=301|302|307|410&filter[source]=manual|slug_change|imported|not_found&per_page` */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:190'],
            'filter.type' => ['sometimes', 'nullable', Rule::in(Redirect::CODES)],
            'filter.source' => ['sometimes', 'nullable', Rule::in(['manual', 'slug_change', 'imported', 'not_found'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $filter = $validated['filter'] ?? [];
        $search = trim((string) ($validated['search'] ?? ''));

        $page = Redirect::query()
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('from_path', 'like', '%'.mb_strtolower($search).'%')->orWhere('to_path', 'like', '%'.$search.'%')))
            ->when(isset($filter['type']), fn (Builder $q) => $q->where('status_code', (int) $filter['type']))
            ->when(isset($filter['source']), fn (Builder $q) => $q->where('source', $filter['source']))
            ->orderByDesc('last_hit_at')->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 10))->withQueryString();

        /** @var list<Redirect> $items */
        $items = $page->items();
        $recent = DB::table('redirect_hits')->whereIn('redirect_id', array_map(fn (Redirect $r): int => $r->id, $items))
            ->where('day', '>', now()->subDays(30)->toDateString())
            ->groupBy('redirect_id')->selectRaw('redirect_id, sum(hits) as total')->pluck('total', 'redirect_id');

        return new JsonResponse([
            'success' => true,
            'message' => 'Redirects retrieved.',
            'data' => array_map(fn (Redirect $r): array => [...$this->row($r), 'hits_30d' => (int) ($recent[$r->id] ?? 0)], $items),
            'meta' => [
                'current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
                'last_page' => $page->lastPage(), 'from' => $page->firstItem(), 'to' => $page->lastItem(),
                'total_all' => Redirect::query()->count(),
            ],
            'links' => ['first' => $page->url(1), 'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl(), 'last' => $page->url($page->lastPage())],
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate(['from_path' => ['required', 'string', 'max:190'], 'to_path' => ['nullable', 'string', 'max:300'], 'id' => ['nullable', 'integer']]);

        return ApiResponse::success($this->rules->check($validated['from_path'], $validated['to_path'] ?? null, isset($validated['id']) ? (int) $validated['id'] : null));
    }

    public function store(Request $request): JsonResponse
    {
        $redirect = $this->rules->save(null, $this->validated($request), $this->actor($request));

        return ApiResponse::created($this->row($redirect), 'Redirect added. The website applies it within a minute.');
    }

    public function update(Request $request, Redirect $redirect): JsonResponse
    {
        return ApiResponse::success($this->row($this->rules->save($redirect, $this->validated($request), $this->actor($request))), 'Redirect saved.');
    }

    public function destroy(Redirect $redirect): JsonResponse
    {
        $redirect->delete();
        $this->rules->published();

        return ApiResponse::success(null, 'Redirect deleted.');
    }

    /** CSV with columns from,to[,type]. Rows that would loop or are invalid are reported, not saved. */
    public function import(Request $request, AuditRecorder $audit): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:1024']]);
        $handle = fopen((string) $request->file('file')?->getRealPath(), 'r');
        $imported = 0;
        $errors = [];
        $line = 0;

        while ($handle !== false && ($row = fgetcsv($handle, escape: '')) !== false) {
            $line++;
            [$from, $to, $type] = array_pad(array_map(fn (mixed $v): string => trim((string) $v), $row), 3, '');

            if ($line === 1 && in_array(mb_strtolower($from), ['from', 'from_path', 'source'], true)) {
                continue;
            }

            try {
                $this->rules->save(null, ['from_path' => $from, 'to_path' => $to === '' ? null : $to, 'status_code' => in_array((int) $type, Redirect::CODES, true) ? (int) $type : 301], $this->actor($request), 'imported');
                $imported++;
            } catch (Throwable $e) {
                $errors[] = "Line {$line}: ".$e->getMessage();
            }

            if ($line > 5000) {
                $errors[] = 'Stopped after 5,000 lines.';
                break;
            }
        }

        if ($handle !== false) {
            fclose($handle);
        }

        $audit->record('redirect.imported', null, null, ['imported' => $imported, 'errors' => count($errors)], label: 'Redirects');

        return ApiResponse::success(['imported' => $imported, 'errors' => array_slice($errors, 0, 50)], $imported === 1 ? '1 redirect imported.' : "{$imported} redirects imported.");
    }

    public function export(): StreamedResponse
    {
        return new StreamedResponse(function (): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['from', 'to', 'type', 'source', 'enabled', 'hits', 'last_hit'], escape: '');
            Redirect::query()->orderBy('from_path')->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $r) {
                    /** @var Redirect $r */
                    fputcsv($out, [$r->from_path, $r->to_path ?? '', $r->status_code, $r->source, $r->is_enabled ? 'yes' : 'no', $r->hits, $r->last_hit_at?->toDateString() ?? ''], escape: '');
                }
            });
            fclose($out);
        }, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="redirects-'.now()->format('Y-m-d').'.csv"']);
    }

    /** @return array{from_path: string, to_path?: string|null, status_code?: int, is_enabled?: bool, note?: string|null} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'from_path' => ['required', 'string', 'max:190', 'regex:/^(\/|https?:\/\/)/'],
            'to_path' => ['nullable', 'string', 'max:300', 'regex:/^(\/|https:\/\/)/'],
            'status_code' => ['required', Rule::in(Redirect::CODES)],
            'is_enabled' => ['sometimes', 'boolean'],
            'note' => ['sometimes', 'nullable', 'string', 'max:190'],
        ], ['to_path.regex' => 'Use a site path like /tms, or a full https:// address.']);

        return [
            'from_path' => (string) $data['from_path'],
            'to_path' => isset($data['to_path']) ? (string) $data['to_path'] : null,
            'status_code' => (int) $data['status_code'],
            'is_enabled' => (bool) ($data['is_enabled'] ?? true),
            'note' => isset($data['note']) ? (string) $data['note'] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function row(Redirect $r): array
    {
        return [
            'id' => $r->id,
            'from_path' => $r->from_path,
            'to_path' => $r->to_path,
            'status_code' => $r->status_code,
            'match_type' => $r->match_type,
            'is_enabled' => $r->is_enabled,
            'source' => $r->source,
            'note' => $r->note,
            'hits' => $r->hits,
            'last_hit_at' => $r->last_hit_at?->toIso8601String(),
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }

    private function actor(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) ? $id : null;
    }
}
