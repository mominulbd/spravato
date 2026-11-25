<?php

declare(strict_types=1);

namespace Uptms\Redirects\Http\Controllers\Public;

use App\Support\Http\ApiResponse;
use App\Support\Http\SiteClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Uptms\Publishing\Services\PublicCache;
use Uptms\Redirects\Models\Redirect;
use Uptms\Redirects\Services\NotFoundMonitor;
use Uptms\Redirects\Services\RedirectRules;

/**
 * What the website's proxy needs: the redirect rules (cached), and two
 * small writes — a redirect was used, an address was missing. The writes
 * are accepted only from the Next.js server (site token); outside
 * production they stay open while no token is configured.
 */
class SiteController
{
    public function rules(PublicCache $cache, RedirectRules $rules): JsonResponse
    {
        return ApiResponse::success($cache->remember('redirects', fn (): array => [
            'rules' => array_values(Redirect::query()->where('is_enabled', true)->orderBy('id')->get()->map(fn (Redirect $r): array => [
                'id' => $r->id, 'from' => $r->from_path, 'to' => $r->to_path, 'status' => $r->status_code, 'wildcard' => $r->match_type === 'wildcard',
            ])->all()),
            // Every address the website serves, so the proxy can answer real 404s for anything else.
            'paths' => $rules->knownPaths(),
        ]));
    }

    public function hit(Request $request, Redirect $redirect): JsonResponse
    {
        $this->assertSite($request);
        $today = now()->toDateString();

        DB::transaction(function () use ($redirect, $today): void {
            Redirect::query()->whereKey($redirect->id)->update(['hits' => DB::raw('hits + 1'), 'last_hit_at' => now()]);
            DB::table('redirect_hits')->upsert([['redirect_id' => $redirect->id, 'day' => $today, 'hits' => 1]], ['redirect_id', 'day'], ['hits' => DB::raw('hits + 1')]);
        });

        return ApiResponse::success(null, 'Counted.');
    }

    public function notFound(Request $request, NotFoundMonitor $monitor): JsonResponse
    {
        $this->assertSite($request);
        $validated = $request->validate(['path' => ['required', 'string', 'max:500'], 'referrer' => ['nullable', 'string', 'max:2000']]);
        $monitor->record($validated['path'], $validated['referrer'] ?? null);

        return ApiResponse::success(null, 'Recorded.');
    }

    private function assertSite(Request $request): void
    {
        $token = config('services.frontend.site_token');
        $configured = is_string($token) && $token !== '';

        if (($configured || app()->isProduction()) && ! SiteClient::trusted($request)) {
            throw new AccessDeniedHttpException('Only the website may report this.');
        }
    }
}
