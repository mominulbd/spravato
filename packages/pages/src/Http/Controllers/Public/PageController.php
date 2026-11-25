<?php

declare(strict_types=1);

namespace Uptms\Pages\Http\Controllers\Public;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ApiResponse;
use App\Support\Http\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Uptms\Pages\Models\Page;
use Uptms\Pages\Services\PageContent;
use Uptms\Pages\Services\PagePresenter;
use Uptms\Pages\Services\PreviewTokens;
use Uptms\Publishing\Domain\Enums\PublishStatus;
use Uptms\Publishing\Models\Revision;
use Uptms\Publishing\Services\PublicCache;

/**
 * `GET /public/page?path=/tms` — the published version only. A valid
 * preview token for the same page returns the working copy instead,
 * uncached and marked `preview: true`.
 */
class PageController
{
    public function __construct(
        private readonly PagePresenter $presenter,
        private readonly PublicCache $cache,
    ) {}

    public function show(Request $request, PreviewTokens $tokens, PageContent $content): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:190', 'regex:/^\/[a-z0-9\-\/]*$/'],
            'preview' => ['sometimes', 'string', 'max:300'],
        ]);
        $path = $validated['path'] === '/' ? '/' : rtrim($validated['path'], '/');

        if (isset($validated['preview'])) {
            $page = Page::query()->where('path', $path)->first();

            if (! $page instanceof Page || $tokens->verify($validated['preview']) !== $page->id) {
                throw new GenericDomainException(ErrorCode::NotFound, 'Preview link expired or invalid.');
            }

            return ApiResponse::success($this->presenter->present($page, $content->snapshot($page), preview: true), 'Preview retrieved.');
        }

        $data = $this->cache->remember('page:'.$path, function () use ($path): ?array {
            $page = Page::query()->where('path', $path)->where('status', PublishStatus::Published)->with('publishedRevision')->first();
            $revision = $page?->publishedRevision;

            if (! $page instanceof Page || ! $revision instanceof Revision) {
                return null;
            }

            /** @var array{title: string, blocks: list<array{id: int|null, type: string, data: array<string, mixed>, is_visible: bool}>} $payload */
            $payload = $revision->payload;

            return $this->presenter->present($page, $payload);
        });

        if ($data === null) {
            throw new GenericDomainException(ErrorCode::NotFound, 'Page not found.');
        }

        return ApiResponse::success($data, 'Page retrieved.');
    }

    /** Published addresses, for the website's static params and sitemap. */
    public function index(): JsonResponse
    {
        $pages = $this->cache->remember('pages:index', fn (): array => Page::query()
            ->where('status', PublishStatus::Published)
            ->orderBy('path')
            ->get(['id', 'path', 'title', 'template', 'content_updated_at', 'published_at'])
            ->map(fn (Page $page): array => [
                'path' => $page->path,
                'title' => $page->title,
                'template' => $page->template->value,
                'updated_at' => ($page->content_updated_at ?? $page->published_at)?->toIso8601String(),
            ])->all());

        return ApiResponse::success($pages, 'Pages retrieved.');
    }
}
