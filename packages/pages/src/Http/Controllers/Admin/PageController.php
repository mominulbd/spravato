<?php

declare(strict_types=1);

namespace Uptms\Pages\Http\Controllers\Admin;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ApiResponse;
use App\Support\Http\ErrorCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Media\Services\MediaUsageRecorder;
use Uptms\Pages\Actions\CreatePageAction;
use Uptms\Pages\Actions\PublishPageAction;
use Uptms\Pages\Actions\RestoreRevisionAction;
use Uptms\Pages\Actions\SavePageContentAction;
use Uptms\Pages\Actions\SchedulePageAction;
use Uptms\Pages\Actions\UnpublishPageAction;
use Uptms\Pages\Actions\UpdatePageSettingsAction;
use Uptms\Pages\Blocks\BlockType;
use Uptms\Pages\Blocks\BlockTypes;
use Uptms\Pages\Blocks\Field;
use Uptms\Pages\Domain\Enums\PageTemplate;
use Uptms\Pages\Http\Requests\PageSettingsRequest;
use Uptms\Pages\Models\Page;
use Uptms\Pages\Seo\PageSeoSource;
use Uptms\Pages\Services\PageResource;
use Uptms\Pages\Services\PreviewTokens;
use Uptms\Publishing\Domain\Enums\PublishStatus;
use Uptms\Publishing\Events\ContentPublished;
use Uptms\Publishing\Models\Revision;
use Uptms\Publishing\Services\BlockSources;
use Uptms\Seo\Services\SeoWorkspace;

/** Figma 06 (list) and 07 (editor). */
class PageController
{
    private const array SORTS = ['title', 'path', 'updated_at', 'status'];

    public function __construct(private readonly PageResource $resource) {}

    public function index(Request $request, SeoWorkspace $seo): JsonResponse
    {
        Gate::authorize('viewAny', Page::class);

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'filter.status' => ['sometimes', Rule::in(['draft', 'published', 'scheduled', 'trash'])],
            'filter.template' => ['sometimes', Rule::enum(PageTemplate::class)],
            'filter.author' => ['sometimes', 'integer'],
            'sort' => ['sometimes', Rule::in([...self::SORTS, ...array_map(fn (string $s): string => '-'.$s, self::SORTS)])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $filter = $validated['filter'] ?? [];
        $search = $validated['search'] ?? null;
        $sort = $validated['sort'] ?? 'path';
        $column = ltrim($sort, '-') === 'updated_at' ? 'content_updated_at' : ltrim($sort, '-');

        $base = fn (): Builder => Page::query()
            ->when($search !== null && $search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('title', 'like', '%'.$search.'%')->orWhere('path', 'like', '%'.$search.'%')))
            ->when(isset($filter['template']), fn (Builder $q) => $q->where('template', $filter['template']))
            ->when(isset($filter['author']), fn (Builder $q) => $q->where('updated_by', (int) $filter['author']));

        $status = $filter['status'] ?? null;
        $pages = $base()
            ->when($status === 'trash', fn (Builder $q) => $q->onlyTrashed())
            ->when($status === 'draft', fn (Builder $q) => $q->where('status', PublishStatus::Draft))
            ->when($status === 'published', fn (Builder $q) => $q->where('status', PublishStatus::Published))
            ->when($status === 'scheduled', fn (Builder $q) => $q->whereNotNull('scheduled_at'))
            ->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 25))
            ->withQueryString();

        $authors = $this->resource->names(array_values(Page::query()->distinct()->pluck('updated_by')->filter()->all()));

        $ids = array_values($pages->getCollection()->map(fn (Page $page): int => $page->id)->all());

        return ApiResponse::success($this->resource->rows($pages->getCollection(), $status === 'trash' ? [] : $seo->scores('page', $ids)), 'Pages retrieved.', [
            'current_page' => $pages->currentPage(),
            'per_page' => $pages->perPage(),
            'total' => $pages->total(),
            'last_page' => $pages->lastPage(),
            'from' => $pages->firstItem(),
            'to' => $pages->lastItem(),
            'counts' => [
                'all' => $base()->count(),
                'published' => $base()->where('status', PublishStatus::Published)->count(),
                'draft' => $base()->where('status', PublishStatus::Draft)->count(),
                'scheduled' => $base()->whereNotNull('scheduled_at')->count(),
                'trash' => $base()->onlyTrashed()->count(),
            ],
            'templates' => array_map(fn (PageTemplate $template): array => ['value' => $template->value, 'label' => $template->label()], PageTemplate::cases()),
            'authors' => array_map(fn (int $id, string $name): array => ['id' => $id, 'name' => $name], array_keys($authors), $authors),
        ]);
    }

    /** The block library and every source's choices, for the editor's forms. */
    public function blockTypes(BlockTypes $types, BlockSources $sources): JsonResponse
    {
        Gate::authorize('viewAny', Page::class);

        $list = array_values(array_map(fn (BlockType $type): array => [
            'key' => $type->key,
            'label' => $type->label,
            'icon' => $type->icon,
            'description' => $type->description,
            'fields' => array_map(fn (Field $field): array => $field->toArray($field->source !== null ? $sources->options($field->source) : []), $type->fields),
            'defaults' => $type->defaults(),
        ], $types->all()));

        return ApiResponse::success($list, 'Block types retrieved.');
    }

    public function show(Page $page): JsonResponse
    {
        Gate::authorize('viewAny', Page::class);

        return ApiResponse::success($this->resource->detail($page), 'Page retrieved.');
    }

    public function store(PageSettingsRequest $request, CreatePageAction $action): JsonResponse
    {
        $page = $action->execute(
            (string) $request->validated('title'),
            (string) $request->validated('slug'),
            PageTemplate::from((string) $request->validated('template')),
            $this->parent($request->validated('parent_id')),
            $this->actor($request),
        );

        return ApiResponse::created($this->resource->detail($page), 'Draft page created.');
    }

    public function updateSettings(PageSettingsRequest $request, Page $page, UpdatePageSettingsAction $action): JsonResponse
    {
        $action->execute(
            $page,
            (string) $request->validated('title'),
            (string) $request->validated('slug'),
            PageTemplate::from((string) $request->validated('template')),
            $this->parent($request->validated('parent_id')),
            $this->actor($request),
        );

        return ApiResponse::success($this->resource->detail($page->refresh()), 'Page settings saved.');
    }

    /** Autosave (`checkpoint` false) and "Save draft" (`checkpoint` true). */
    public function updateContent(Request $request, Page $page, SavePageContentAction $action, SeoWorkspace $seo, PageSeoSource $source): JsonResponse
    {
        Gate::authorize('update', $page);

        $validated = $request->validate([
            'lock_version' => ['required', 'integer'],
            'blocks' => ['present', 'array', 'max:40'],
            'blocks.*.id' => ['nullable', 'integer'],
            'blocks.*.type' => ['required', 'string', 'max:40'],
            'blocks.*.data' => ['present', 'array'],
            'blocks.*.is_visible' => ['sometimes', 'boolean'],
            'checkpoint' => ['sometimes', 'boolean'],
            'note' => ['sometimes', 'nullable', 'string', 'max:190'],
        ]);

        /** @var list<array<string, mixed>> $blocks */
        $blocks = $request->input('blocks', []);
        $action->execute($page, $blocks, (int) $validated['lock_version'], $this->actor($request), (bool) ($validated['checkpoint'] ?? false), $validated['note'] ?? null);

        // Keep the list's SEO score in step with the words on "Save draft".
        if ($validated['checkpoint'] ?? false) {
            $subject = $source->subject($page->id);
            if ($subject !== null) {
                $seo->rescore($subject);
            }
        }

        return ApiResponse::success($this->resource->detail($page->refresh()), ($validated['checkpoint'] ?? false) ? 'Draft saved.' : 'Autosaved.');
    }

    /** Publishes what is saved; the editor saves first so nothing unsaved is skipped. */
    public function publish(Request $request, Page $page, PublishPageAction $action): JsonResponse
    {
        Gate::authorize('publish', $page);
        $this->assertFresh($request, $page);
        $action->execute($page);

        return ApiResponse::success($this->resource->detail($page->refresh()), 'Published. The website updates within seconds.');
    }

    public function schedule(Request $request, Page $page, SchedulePageAction $action): JsonResponse
    {
        Gate::authorize('publish', $page);
        $validated = $request->validate(['at' => ['required', 'date', 'after:now', 'before:+1 year']]);
        $this->assertFresh($request, $page);
        $action->execute($page, Carbon::parse($validated['at']));

        return ApiResponse::success($this->resource->detail($page->refresh()), 'Scheduled.');
    }

    public function unschedule(Page $page, SchedulePageAction $action): JsonResponse
    {
        Gate::authorize('publish', $page);
        $action->cancel($page);

        return ApiResponse::success($this->resource->detail($page->refresh()), 'Schedule cancelled.');
    }

    public function unpublish(Page $page, UnpublishPageAction $action): JsonResponse
    {
        Gate::authorize('publish', $page);
        $action->execute($page);

        return ApiResponse::success($this->resource->detail($page->refresh()), 'Unpublished. The page is no longer on the website.');
    }

    public function revisions(Page $page): JsonResponse
    {
        Gate::authorize('viewAny', Page::class);

        $revisions = $page->revisions()->limit(100)->get();
        $names = $this->resource->names(array_values($revisions->pluck('user_id')->filter()->unique()->all()));

        return ApiResponse::success($revisions->map(fn (Revision $revision): array => [
            'id' => $revision->id,
            'kind' => $revision->kind,
            'note' => $revision->note,
            'is_live' => $revision->id === $page->published_revision_id,
            'blocks_count' => count($revision->payload['blocks'] ?? []),
            'user' => $revision->user_id !== null ? ['id' => $revision->user_id, 'name' => $names[$revision->user_id] ?? null] : null,
            'created_at' => $revision->created_at->toIso8601String(),
        ])->values()->all(), 'Revisions retrieved.');
    }

    public function restoreRevision(Request $request, Page $page, int $revision, RestoreRevisionAction $action): JsonResponse
    {
        Gate::authorize('update', $page);
        $validated = $request->validate(['lock_version' => ['required', 'integer']]);
        $model = $page->revisions()->findOrFail($revision);
        $action->execute($page, $model, (int) $validated['lock_version'], $this->actor($request));

        return ApiResponse::success($this->resource->detail($page->refresh()), 'Revision restored to your draft. Publish to make it live.');
    }

    public function preview(Page $page, PreviewTokens $tokens): JsonResponse
    {
        Gate::authorize('update', $page);
        $token = $tokens->issue($page->id);
        $site = rtrim((string) config('services.frontend.url'), '/');

        return ApiResponse::success([
            'token' => $token,
            'url' => $site.'/preview?'.http_build_query(['path' => $page->path, 'token' => $token]),
            'expires_in' => 1800,
        ], 'Preview link ready.');
    }

    public function destroy(Page $page, AuditRecorder $audit): JsonResponse
    {
        Gate::authorize('delete', $page);

        if ($page->children()->exists()) {
            throw new GenericDomainException(ErrorCode::Conflict, 'Move or delete the pages under this one first.');
        }

        $wasLive = $page->status === PublishStatus::Published;
        $page->delete();

        if ($wasLive) {
            ContentPublished::dispatch(['content', 'page:'.$page->cacheKey()]);
        }

        return ApiResponse::success(null, 'Moved to Trash.');
    }

    public function restore(int $id, MediaUsageRecorder $usages, SavePageContentAction $writer): JsonResponse
    {
        $page = Page::onlyTrashed()->findOrFail($id);
        Gate::authorize('delete', $page);

        DB::transaction(function () use ($page, $writer): void {
            $page->restore();
            $writer->syncMedia($page);
        });

        if ($page->status === PublishStatus::Published) {
            ContentPublished::dispatch(['content', 'page:'.$page->cacheKey()]);
        }

        return ApiResponse::success($this->resource->detail($page), 'Page restored.');
    }

    /** Publishing an older lock_version than the server's means unsaved edits elsewhere. */
    private function assertFresh(Request $request, Page $page): void
    {
        $lock = $request->integer('lock_version', $page->lock_version);

        if ($lock !== $page->lock_version) {
            throw new GenericDomainException(ErrorCode::Conflict, 'This page changed since you opened it. Reload, check the changes, then publish.');
        }
    }

    private function parent(mixed $id): ?Page
    {
        return is_numeric($id) ? Page::query()->findOrFail((int) $id) : null;
    }

    private function actor(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) ? $id : null;
    }
}
