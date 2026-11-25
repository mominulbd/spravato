<?php

declare(strict_types=1);

namespace Uptms\Media\Http\Controllers\Admin;

use App\Support\Http\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Media\Actions\ReplaceMediaFileAction;
use Uptms\Media\Actions\TrashMediaAction;
use Uptms\Media\Actions\UploadMediaAction;
use Uptms\Media\Http\Requests\ReplaceMediaRequest;
use Uptms\Media\Http\Requests\UpdateMediaRequest;
use Uptms\Media\Http\Requests\UploadMediaRequest;
use Uptms\Media\Http\Resources\MediaResource;
use Uptms\Media\Models\Media;
use Uptms\Publishing\Events\ContentPublished;

/** Figma 18 / 18b. */
class MediaController
{
    private const array SORTS = ['newest' => ['created_at', 'desc'], 'oldest' => ['created_at', 'asc'], 'name' => ['filename', 'asc'], 'largest' => ['size', 'desc']];

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Media::class);

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'filter.folder' => ['sometimes', 'nullable', 'string', 'max:20'],
            'filter.show' => ['sometimes', Rule::in(['all', 'in_use', 'unused', 'no_alt'])],
            'filter.kind' => ['sometimes', Rule::in([Media::IMAGE, Media::DOCUMENT])],
            'filter.unused_days' => ['sometimes', 'integer', 'min:1', 'max:3650'],
            'sort' => ['sometimes', Rule::in(array_keys(self::SORTS))],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $filter = $validated['filter'] ?? [];
        $base = $this->scoped($validated['search'] ?? null, $filter['folder'] ?? null, $filter['kind'] ?? null);
        [$column, $direction] = self::SORTS[$validated['sort'] ?? 'newest'];

        $media = (clone $base)
            ->with('variants')
            ->withCount('usages')
            ->when(($filter['show'] ?? 'all') === 'in_use', fn (Builder $q) => $q->has('usages'))
            ->when(($filter['show'] ?? 'all') === 'unused', fn (Builder $q) => $q->unused())
            ->when(($filter['show'] ?? 'all') === 'no_alt', fn (Builder $q) => $q->missingAlt())
            ->when(isset($filter['unused_days']), fn (Builder $q) => $q->unused()->where(fn (Builder $w) => $w
                ->where(fn (Builder $n) => $n->whereNull('first_used_at')->where('created_at', '<', now()->subDays((int) $filter['unused_days'])))
                ->orWhere('last_used_at', '<', now()->subDays((int) $filter['unused_days']))))
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate((int) ($validated['per_page'] ?? 24))
            ->withQueryString();

        $unused = (clone $base)->unused();

        return ApiResponse::paginated($media, MediaResource::class, 'Media retrieved.', [
            'counts' => [
                'all' => (clone $base)->count(),
                'in_use' => (clone $base)->has('usages')->count(),
                'unused' => (clone $unused)->count(),
                'no_alt' => (clone $base)->missingAlt()->count(),
                'trash' => Media::onlyTrashed()->count(),
            ],
            'unused' => [
                'files' => (clone $unused)->count(),
                'bytes' => (int) (clone $unused)->sum('size'),
                'never_used' => (clone $unused)->whereNull('first_used_at')->count(),
                'removed' => (clone $unused)->whereNotNull('first_used_at')->count(),
            ],
        ]);
    }

    public function show(Media $media): JsonResponse
    {
        Gate::authorize('viewAny', Media::class);

        return ApiResponse::success(new MediaResource($media->load(['variants', 'usages'])), 'File retrieved.');
    }

    public function store(UploadMediaRequest $request, UploadMediaAction $action): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $folder = $request->validated('folder_id');
        $media = $action->execute($file, is_numeric($folder) ? (int) $folder : null, (int) $request->user()?->getAuthIdentifier());

        return ApiResponse::created(new MediaResource($media->load(['variants', 'usages'])), 'File uploaded.');
    }

    public function update(UpdateMediaRequest $request, Media $media): JsonResponse
    {
        $media->fill($request->validated());

        if ($media->is_decorative) {
            $media->alt = null;
        }

        $changesSite = $media->isDirty(['alt', 'is_decorative', 'focal_x', 'focal_y']) && $media->usages()->exists();
        $media->save();

        if ($changesSite) {
            ContentPublished::dispatch(['content']);
        }

        return ApiResponse::success(new MediaResource($media->load(['variants', 'usages'])), 'File updated.');
    }

    public function replace(ReplaceMediaRequest $request, Media $media, ReplaceMediaFileAction $action): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $media = $action->execute($media, $file);

        return ApiResponse::success(new MediaResource($media->load(['variants', 'usages'])), 'File replaced.');
    }

    public function destroy(Media $media, TrashMediaAction $action): JsonResponse
    {
        Gate::authorize('delete', $media);
        $action->execute([$media->id]);

        return ApiResponse::success(null, 'Moved to Trash. It is deleted for good after 30 days.');
    }

    /** Figma 18b selection bar: move to folder or trash. */
    public function bulk(Request $request, TrashMediaAction $trash, AuditRecorder $audit): JsonResponse
    {
        Gate::authorize('create', Media::class);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['trash', 'move'])],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'distinct'],
            'folder_id' => ['required_if:action,move', 'nullable', 'integer', Rule::exists('media_folders', 'id')],
        ]);

        /** @var list<int> $ids */
        $ids = array_map('intval', $validated['ids']);

        if ($validated['action'] === 'trash') {
            $count = $trash->execute($ids);

            return ApiResponse::success(['count' => $count], "{$count} file(s) moved to Trash.");
        }

        $count = DB::transaction(function () use ($ids, $validated, $audit): int {
            $count = Media::query()->whereIn('id', $ids)->update(['folder_id' => $validated['folder_id']]);
            $audit->record('media.moved', null, null, ['ids' => $ids, 'folder_id' => $validated['folder_id']]);

            return $count;
        });

        return ApiResponse::success(['count' => $count], "{$count} file(s) moved.");
    }

    public function trash(Request $request): JsonResponse
    {
        Gate::authorize('create', Media::class);

        $media = Media::onlyTrashed()->with('variants')->withCount('usages')
            ->orderByDesc('deleted_at')
            ->paginate(min(100, max(1, $request->integer('per_page', 24))));

        return ApiResponse::paginated($media, MediaResource::class, 'Trash retrieved.', [
            'purge_after_days' => (int) config('media.trash_days'),
        ]);
    }

    public function restore(int $id, AuditRecorder $audit): JsonResponse
    {
        $media = Media::onlyTrashed()->findOrFail($id);
        Gate::authorize('delete', $media);

        $media->restore();
        $audit->record('media.restored', $media);

        return ApiResponse::success(new MediaResource($media->load(['variants', 'usages'])), 'File restored.');
    }

    public function download(Media $media): StreamedResponse
    {
        Gate::authorize('viewAny', Media::class);

        return Storage::disk($media->disk)->download($media->path, $media->filename);
    }

    /**
     * @return Builder<Media>
     */
    private function scoped(?string $search, ?string $folder, ?string $kind): Builder
    {
        return Media::query()
            ->when($search !== null && $search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('filename', 'like', '%'.$search.'%')
                ->orWhere('alt', 'like', '%'.$search.'%')))
            ->when($folder === 'none', fn (Builder $q) => $q->whereNull('folder_id'))
            ->when($folder !== null && ctype_digit($folder), fn (Builder $q) => $q->where('folder_id', (int) $folder))
            ->when($kind !== null, fn (Builder $q) => $q->where('kind', $kind));
    }
}
