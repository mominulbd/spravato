<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Context;
use JsonSerializable;

/**
 * The one response envelope every endpoint speaks (docs/API.md §1).
 *
 * Success: { success: true, message, data, meta }
 * Error:   { success: false, message, error_code, errors, meta: { request_id } }
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, string $message = 'OK', array $meta = [], int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'data' => self::normalise($data),
            'meta' => (object) $meta,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function created(mixed $data = null, string $message = 'Created.', array $meta = []): JsonResponse
    {
        return self::success($data, $message, $meta, 201);
    }

    public static function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }

    /**
     * Paginated collections carry pagination in `meta` and page URLs in
     * `links`, while `data` stays a plain list.
     *
     * @param  LengthAwarePaginator<int, mixed>|CursorPaginator<int, mixed>  $paginator
     * @param  class-string<JsonResource>|null  $resource
     * @param  array<string, mixed>  $meta
     */
    public static function paginated(
        LengthAwarePaginator|CursorPaginator $paginator,
        ?string $resource = null,
        string $message = 'OK',
        array $meta = [],
    ): JsonResponse {
        // Each resource is built from the item alone. `::collection()` would pass the item's
        // array key as a second constructor argument, which a resource taking an option
        // (`bool $withBody`, `bool $admin`) silently reads as `true` for every row but the first.
        $items = $resource !== null
            ? array_map(fn (mixed $item): mixed => (new $resource($item))->resolve(), $paginator->items())
            : array_map(fn (mixed $item) => self::normalise($item), $paginator->items());

        if ($paginator instanceof LengthAwarePaginator) {
            $pagination = [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ];
            $links = [
                'first' => $paginator->url(1),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
                'last' => $paginator->url($paginator->lastPage()),
            ];
        } else {
            $pagination = [
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
            ];
            $links = [
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'data' => array_values($items),
            'meta' => array_merge($pagination, $meta),
            'links' => $links,
        ]);
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $meta
     */
    public static function error(ErrorCode $code, string $message, array $errors = [], array $meta = [], ?int $status = null): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
            'error_code' => $code->value,
            'errors' => (object) $errors,
            'meta' => (object) array_merge(['request_id' => Context::get('request_id')], $meta),
        ], $status ?? $code->status());
    }

    private static function normalise(mixed $data): mixed
    {
        return match (true) {
            $data instanceof JsonResource => $data->resolve(),
            $data instanceof JsonSerializable => $data->jsonSerialize(),
            $data instanceof Arrayable => $data->toArray(),
            default => $data,
        };
    }
}
