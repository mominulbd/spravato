/*
 * Response headers for every page of the public site (docs/ARCHITECTURE.md §9),
 * set by proxy.ts. Laravel sends its own for /admin and /api (SecurityHeaders);
 * the reverse proxy must not add a second CSP on top of either.
 *
 * Why no nonce: Next.js only stamps a nonce on its scripts while rendering a
 * request, so a nonce makes every page dynamic and is incompatible with the
 * prerendered shells of Cache Components (PPR). The page is cached and
 * revalidated on publish, so the policy is static: scripts only from this
 * origin and Turnstile, plus 'unsafe-inline' for the RSC payload Next.js
 * inlines (`self.__next_f.push(…)`, different per page, so hashes cannot list
 * it). The API never returns HTML and rich text is rendered as React elements,
 * so there is no injection point for that allowance to widen. The JSON-LD
 * block is a data block (type="application/ld+json"); CSP does not apply to it.
 */

export interface CspOptions {
  /** `next dev`: React needs eval for its error overlay, HMR uses a websocket. */
  dev: boolean;
  /** The public origin, e.g. https://uptms.com (NEXT_PUBLIC_SITE_URL). */
  siteUrl?: string;
  /** Extra origins media may come from when Laravel's APP_URL / public disk is not the site's origin (MEDIA_ORIGINS, comma separated). */
  mediaOrigins?: string;
}

export const TURNSTILE_ORIGIN = "https://challenges.cloudflare.com";

/** Hosts the video facade embeds (components/blocks/VideoCard.tsx). */
export const VIDEO_FRAME_ORIGINS = ["https://www.youtube-nocookie.com", "https://player.vimeo.com"];

export function contentSecurityPolicy({ dev, siteUrl, mediaOrigins }: CspOptions): string {
  const media = origins(mediaOrigins);
  const https = isHttps(siteUrl);

  const directives: Record<string, string[]> = {
    "default-src": ["'self'"],
    "script-src": ["'self'", "'unsafe-inline'", TURNSTILE_ORIGIN, ...(dev ? ["'unsafe-eval'"] : [])],
    "style-src": ["'self'", "'unsafe-inline'"],
    "img-src": ["'self'", "data:", "blob:", ...media],
    "font-src": ["'self'", "data:"],
    // Videos may be Laravel's /storage files or any https file the CMS names.
    "media-src": ["'self'", "https:", ...media],
    "connect-src": ["'self'", ...(dev ? ["ws:", "wss:"] : [])],
    "frame-src": [TURNSTILE_ORIGIN, ...VIDEO_FRAME_ORIGINS],
    "worker-src": ["'self'", "blob:"],
    "object-src": ["'none'"],
    "base-uri": ["'self'"],
    // Server actions post to the page's own URL.
    "form-action": ["'self'"],
    "frame-ancestors": ["'none'"],
  };

  const policy = Object.entries(directives).map(([name, values]) => `${name} ${values.join(" ")}`);

  // Only where the site is served over https: on http://localhost it would
  // rewrite same-origin assets to an https port that does not answer.
  if (https && !dev) policy.push("upgrade-insecure-requests");

  return policy.join("; ");
}

export function securityHeaders(options: CspOptions): Record<string, string> {
  const headers: Record<string, string> = {
    "Content-Security-Policy": contentSecurityPolicy(options),
    "X-Content-Type-Options": "nosniff",
    "X-Frame-Options": "DENY",
    "Referrer-Policy": "strict-origin-when-cross-origin",
    "Permissions-Policy": "camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()",
    "Cross-Origin-Opener-Policy": "same-origin",
  };

  // Browsers ignore HSTS over http; sending it from dev would also pin
  // localhost to https.
  if (!options.dev && isHttps(options.siteUrl)) {
    headers["Strict-Transport-Security"] = "max-age=31536000; includeSubDomains";
  }

  return headers;
}

function isHttps(url: string | undefined): boolean {
  return typeof url === "string" && /^https:\/\//i.test(url.trim());
}

/** Valid http(s) origins from a comma-separated list; anything else is dropped. */
function origins(list: string | undefined): string[] {
  if (!list) return [];

  return list
    .split(",")
    .map((value) => value.trim())
    .flatMap((value) => {
      try {
        const url = new URL(value);
        return url.protocol === "https:" || url.protocol === "http:" ? [url.origin] : [];
      } catch {
        return [];
      }
    });
}
