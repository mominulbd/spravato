// @ts-check
import { fileURLToPath } from "node:url";

/*
 * The public site and Laravel share one domain (docs/DEVELOPMENT-PLAN.md §1):
 * Laravel answers the paths below, Next.js everything else. In production a
 * reverse proxy splits them; in development these rewrites do, so
 * http://localhost:3000 serves the site and http://localhost:3000/admin/login
 * the admin, on one origin, with one session cookie.
 */
const LARAVEL_PATHS = ["/admin", "/admin/:path*", "/api/v1/:path*", "/sanctum/:path*", "/build/:path*", "/storage/:path*", "/up", "/login"];

const laravelUrl = (process.env.LARAVEL_URL ?? "http://127.0.0.1:8000").replace(/\/+$/, "");

/** @type {import("next").NextConfig} */
const nextConfig = {
  // The end-to-end run (playwright.config.ts) builds into its own directory
  // so it never disturbs a running `next dev`.
  distDir: process.env.NEXT_DIST_DIR ?? ".next",
  output: "standalone",
  cacheComponents: true,
  // The Laravel project is the workspace root: tokens.css lives in resources/css.
  turbopack: { root: fileURLToPath(new URL("..", import.meta.url)) },
  outputFileTracingRoot: fileURLToPath(new URL("..", import.meta.url)),
  env: { API_URL: process.env.API_URL ?? `${laravelUrl}/api/v1` },
  async rewrites() {
    if (process.env.NODE_ENV === "production" && process.env.PROXY_LARAVEL !== "true") {
      return [];
    }

    return {
      beforeFiles: LARAVEL_PATHS.map((source) => ({ source, destination: `${laravelUrl}${source}` })),
      afterFiles: [],
      fallback: [],
    };
  },
};

export default nextConfig;
