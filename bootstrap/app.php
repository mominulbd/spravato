<?php

declare(strict_types=1);

use App\Support\Exceptions\DomainException;
use App\Support\Http\ExceptionRenderer;
use App\Support\Middleware\AssignRequestId;
use App\Support\Middleware\EnsureActiveAccount;
use App\Support\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/*
 * Domain routes are registered by each package's service provider
 * (App\Support\Packages\ModuleServiceProvider). routes/api.php holds only
 * what belongs to no module.
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The admin SPA is served from this origin and authenticates with
        // Sanctum's session cookie (docs/ARCHITECTURE.md §5).
        $middleware->statefulApi();

        $middleware->api(prepend: [AssignRequestId::class]);
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'active' => EnsureActiveAccount::class,
        ]);

        // `public` is read anonymously by the Next.js site (site/).
        // `admin` gains `permission:admin.access` when the permissions module
        // lands in Phase 1; the route's `permission:`, the Form Request's
        // policy check and the action complete the gates on every write.
        $middleware->group('public', ['throttle:public']);
        $middleware->group('admin', ['auth:sanctum', 'active', 'throttle:admin']);

        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('api/*') ? null : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (Throwable $e, Request $request) => app(ExceptionRenderer::class)->render($e, $request));

        // Business-rule failures are expected control flow, not incidents.
        $exceptions->dontReport([DomainException::class]);
    })->create();
