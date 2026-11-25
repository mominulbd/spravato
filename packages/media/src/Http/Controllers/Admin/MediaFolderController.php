<?php

declare(strict_types=1);

namespace Uptms\Media\Http\Controllers\Admin;

use App\Support\Http\ApiResponse;
use App\Support\Http\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Uptms\Media\Models\Media;
use Uptms\Media\Models\MediaFolder;

/** Folder pills of Figma 18. Deleting a folder keeps its files (they move to "no folder"). */
class MediaFolderController
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Media::class);

        $folders = MediaFolder::query()->withCount('media')->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (MediaFolder $folder): array => $this->present($folder));

        return ApiResponse::success($folders->values()->all(), 'Folders retrieved.', [
            'total' => Media::query()->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Media::class);
        $validated = $request->validate(['name' => ['required', 'string', 'max:80']]);

        $slug = Str::slug($validated['name']);

        if ($slug === '' || MediaFolder::query()->where('slug', $slug)->exists()) {
            return ApiResponse::error(ErrorCode::ValidationFailed, 'A folder with this name already exists.', ['name' => ['A folder with this name already exists.']]);
        }

        $folder = MediaFolder::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'sort_order' => (int) MediaFolder::query()->max('sort_order') + 1,
        ]);

        return ApiResponse::created($this->present($folder->loadCount('media')), 'Folder created.');
    }

    public function update(Request $request, MediaFolder $folder): JsonResponse
    {
        Gate::authorize('create', Media::class);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('media_folders', 'name')->ignore($folder->id)],
        ]);

        $folder->update(['name' => $validated['name']]);

        return ApiResponse::success($this->present($folder->loadCount('media')), 'Folder renamed.');
    }

    public function destroy(MediaFolder $folder): JsonResponse
    {
        Gate::authorize('create', Media::class);
        $folder->delete();

        return ApiResponse::success(null, 'Folder removed. Its files are kept.');
    }

    /** @return array{id: int, name: string, slug: string, media_count: int} */
    private function present(MediaFolder $folder): array
    {
        return ['id' => $folder->id, 'name' => $folder->name, 'slug' => $folder->slug, 'media_count' => (int) $folder->media_count];
    }
}
