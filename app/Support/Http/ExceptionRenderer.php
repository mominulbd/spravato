<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Support\Exceptions\DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Maps every exception that escapes a controller onto the API error envelope
 * (docs/API.md §1). Registered once in bootstrap/app.php.
 */
final class ExceptionRenderer
{
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => ApiResponse::error(
                ErrorCode::ValidationFailed,
                'The given data was invalid.',
                $e->errors(),
            ),
            $e instanceof DomainException => ApiResponse::error(
                $e->errorCode(),
                $e->getMessage(),
                $e->context(),
            ),
            $e instanceof AuthenticationException => ApiResponse::error(
                ErrorCode::Unauthenticated,
                'Authentication is required.',
            ),
            $e instanceof TokenMismatchException => ApiResponse::error(
                ErrorCode::Unauthenticated,
                'Your session expired. Sign in again.',
                [],
                [],
                419,
            ),
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => ApiResponse::error(
                ErrorCode::Forbidden,
                $e->getMessage() !== '' && $e->getMessage() !== 'This action is unauthorized.'
                    ? $e->getMessage()
                    : 'You do not have permission to do this.',
            ),
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiResponse::error(
                ErrorCode::NotFound,
                'The requested resource was not found.',
            ),
            // Retry-After and X-RateLimit-* stay on the response for clients and proxies.
            $e instanceof ThrottleRequestsException => ApiResponse::error(
                ErrorCode::RateLimited,
                'Too many requests. Please slow down.',
                [],
                ['retry_after' => $e->getHeaders()['Retry-After'] ?? null],
            )->withHeaders($e->getHeaders()),
            $e instanceof HttpExceptionInterface => ApiResponse::error(
                self::codeForStatus($e->getStatusCode()),
                $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.',
                [],
                [],
                $e->getStatusCode(),
            ),
            default => ApiResponse::error(
                ErrorCode::ServerError,
                config('app.debug') ? $e->getMessage() : 'Something went wrong on our side. Quote the request id to support.',
                config('app.debug') ? ['exception' => $e::class, 'file' => $e->getFile().':'.$e->getLine()] : [],
            ),
        };
    }

    private static function codeForStatus(int $status): ErrorCode
    {
        return match ($status) {
            401, 419 => ErrorCode::Unauthenticated,
            403 => ErrorCode::Forbidden,
            404 => ErrorCode::NotFound,
            409 => ErrorCode::Conflict,
            422 => ErrorCode::ValidationFailed,
            429 => ErrorCode::RateLimited,
            default => ErrorCode::ServerError,
        };
    }
}
