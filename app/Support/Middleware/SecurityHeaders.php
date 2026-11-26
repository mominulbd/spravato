<?php

declare(strict_types=1);

namespace App\Support\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response headers for everything Laravel answers (docs/ARCHITECTURE.md §9):
 * /admin, /api, /sanctum and /up. The public site's pages get theirs from
 * site/proxy.ts. The reverse proxy must not add a second copy of any of
 * these (two CSP headers intersect; two HSTS values conflict).
 *
 * - API responses never render HTML, so their CSP forbids everything.
 * - The admin shell gets a per-request nonce, which `@vite` and
 *   `@viteReactRefresh` stamp on their tags. Scripts run only from /build
 *   (or the Vite dev server while `public/hot` exists) and with that nonce.
 */
final class SecurityHeaders
{
    public const string API_POLICY = "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'";

    public function __construct(private readonly Vite $vite) {}

    public function handle(Request $request, Closure $next): Response
    {
        $api = $request->is('api/*', 'sanctum/*', 'up');
        $nonce = $api ? null : $this->vite->useCspNonce();

        /** @var Response $response */
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Content-Security-Policy', $nonce === null ? self::API_POLICY : $this->adminPolicy($nonce));

        // Behind the proxy, isSecure() follows X-Forwarded-Proto (TrustProxies).
        if ($request->isSecure() && ! app()->environment('local')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function adminPolicy(string $nonce): string
    {
        $dev = $this->devServer();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...$dev],
            // React style attributes (focal points, widths) are inline.
            'style-src' => ["'self'", "'unsafe-inline'", ...$dev],
            'img-src' => ["'self'", 'data:', 'blob:', ...$dev],
            'font-src' => ["'self'", 'data:', ...$dev],
            'media-src' => ["'self'", 'blob:'],
            'connect-src' => ["'self'", ...$dev, ...array_map(fn (string $origin): string => (string) preg_replace('/^http/', 'ws', $origin), $dev)],
            'frame-src' => ["'none'"],
            'worker-src' => ["'self'", 'blob:'],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return implode('; ', array_map(
            fn (string $name, array $values): string => $name.' '.implode(' ', $values),
            array_keys($directives),
            $directives,
        ));
    }

    /**
     * The Vite dev server's origin while `npm run dev` runs (public/hot), so
     * its scripts, styles and HMR socket are allowed. Never in production.
     *
     * @return list<string>
     */
    private function devServer(): array
    {
        if (app()->isProduction() || ! $this->vite->isRunningHot()) {
            return [];
        }

        $url = trim((string) file_get_contents($this->vite->hotFile()));
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return [];
        }

        $host = str_contains($parts['host'], ':') && ! str_starts_with($parts['host'], '[') ? '['.$parts['host'].']' : $parts['host'];

        return [$parts['scheme'].'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '')];
    }
}
