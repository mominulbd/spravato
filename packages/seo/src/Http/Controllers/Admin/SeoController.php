<?php

declare(strict_types=1);

namespace Uptms\Seo\Http\Controllers\Admin;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Uptms\Seo\Http\Requests\SaveSeoMetaRequest;
use Uptms\Seo\Services\SeoWorkspace;

/** Figma 08–10 for any page, post or condition: `{type}` is its morph alias. */
class SeoController
{
    public function __construct(private readonly SeoWorkspace $workspace) {}

    public function show(string $type, int $id): JsonResponse
    {
        return ApiResponse::success($this->workspace->payload($type, $id), 'SEO retrieved.');
    }

    /** The live checklist while someone types; nothing is saved. */
    public function analyze(SaveSeoMetaRequest $request, string $type, int $id): JsonResponse
    {
        /** @var array<string, mixed> $values */
        $values = $request->validated();

        return ApiResponse::success($this->workspace->preview($type, $id, $values), 'Checked.');
    }

    public function update(SaveSeoMetaRequest $request, string $type, int $id): JsonResponse
    {
        /** @var array<string, mixed> $values */
        $values = $request->validated();
        $userId = $request->user()?->getAuthIdentifier();

        return ApiResponse::success($this->workspace->save($type, $id, $values, is_int($userId) ? $userId : null), 'SEO saved. The website updates within seconds.');
    }

    public function scores(Request $request, string $type): JsonResponse
    {
        $validated = $request->validate(['ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer']]);

        return ApiResponse::success((object) $this->workspace->scores($type, array_values(array_map('intval', $validated['ids']))));
    }
}
