import { NextResponse } from "next/server";
import { securityHeaders } from "@/lib/csp";

/*
 * Security headers for the site's pages (lib/csp.ts). The policy is static,
 * so prerendered and cached pages stay cached: nothing here reads or changes
 * the request.
 */
export function proxy() {
  const response = NextResponse.next();

  const headers = securityHeaders({
    dev: process.env.NODE_ENV === "development",
    siteUrl: process.env.NEXT_PUBLIC_SITE_URL,
    mediaOrigins: process.env.MEDIA_ORIGINS,
  });

  for (const [name, value] of Object.entries(headers)) response.headers.set(name, value);

  return response;
}

export const config = {
  matcher: [
    /*
     * Everything but Laravel's paths (in development next.config.mjs rewrites
     * them after Proxy runs; Laravel sends its own headers) and the
     * fingerprinted build output. Server actions post to the page's own
     * path, so they stay covered.
     */
    "/((?!admin(?:/|$)|api/|sanctum/|build/|storage/|up$|login$|_next/static/|_next/image|favicon\\.ico$).*)",
  ],
};
