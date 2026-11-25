<?php

declare(strict_types=1);

namespace Uptms\Collections\Http\Controllers\Public;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Uptms\Collections\Services\CollectionReader;
use Uptms\Collections\Services\CollectionTypes;
use Uptms\Publishing\Services\PublicCache;

/** Published items only; `?group=` (FAQs) and `?page=` (team) narrow the list. */
class CollectionController
{
    public function index(Request $request, string $type, CollectionTypes $types, CollectionReader $reader, PublicCache $cache): JsonResponse
    {
        $collection = $types->get($type);
        $params = $request->validate([
            'group' => ['sometimes', 'string', 'max:80'],
            'page' => ['sometimes', 'string', 'max:20'],
        ]);

        ksort($params);
        $key = 'collections:'.$type.':'.http_build_query($params);

        return ApiResponse::success($cache->remember($key, fn (): array => $reader->published($collection, $params)), 'Items retrieved.');
    }
}
