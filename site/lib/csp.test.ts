import { unstable_doesMiddlewareMatch } from "next/experimental/testing/server";
import { describe, expect, it } from "vitest";
import { config } from "../proxy";
import { contentSecurityPolicy, securityHeaders } from "./csp";

const directive = (policy: string, name: string) => policy.split("; ").find((part) => part.startsWith(`${name} `));

describe("contentSecurityPolicy", () => {
  const production = contentSecurityPolicy({ dev: false, siteUrl: "https://uptms.example" });

  it("allows scripts only from the site and Turnstile, without eval", () => {
    expect(directive(production, "script-src")).toBe("script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com");
    expect(production).not.toContain("unsafe-eval");
  });

  it("frames only Turnstile and the two video hosts, and is never framed", () => {
    expect(directive(production, "frame-src")).toBe("frame-src https://challenges.cloudflare.com https://www.youtube-nocookie.com https://player.vimeo.com");
    expect(directive(production, "frame-ancestors")).toBe("frame-ancestors 'none'");
  });

  it("keeps server actions, Laravel media and plugins in their lanes", () => {
    expect(directive(production, "form-action")).toBe("form-action 'self'");
    expect(directive(production, "connect-src")).toBe("connect-src 'self'");
    expect(directive(production, "img-src")).toBe("img-src 'self' data: blob:");
    expect(directive(production, "object-src")).toBe("object-src 'none'");
    expect(directive(production, "base-uri")).toBe("base-uri 'self'");
    expect(production).toContain("upgrade-insecure-requests");
  });

  it("adds a separate media origin when one is configured, and drops junk", () => {
    const policy = contentSecurityPolicy({ dev: false, siteUrl: "https://uptms.example", mediaOrigins: "https://cdn.uptms.example/storage, javascript:alert(1), nope" });

    expect(directive(policy, "img-src")).toBe("img-src 'self' data: blob: https://cdn.uptms.example");
  });

  it("allows eval and the HMR socket in development only, and never upgrades http", () => {
    const dev = contentSecurityPolicy({ dev: true, siteUrl: "http://localhost:3000" });

    expect(directive(dev, "script-src")).toContain("'unsafe-eval'");
    expect(directive(dev, "connect-src")).toBe("connect-src 'self' ws: wss:");
    expect(dev).not.toContain("upgrade-insecure-requests");
    expect(contentSecurityPolicy({ dev: false, siteUrl: "http://localhost:3124" })).not.toContain("upgrade-insecure-requests");
  });
});

describe("securityHeaders", () => {
  it("sends the baseline headers and HSTS only on an https production site", () => {
    const headers = securityHeaders({ dev: false, siteUrl: "https://uptms.example" });

    expect(headers).toMatchObject({
      "X-Content-Type-Options": "nosniff",
      "X-Frame-Options": "DENY",
      "Referrer-Policy": "strict-origin-when-cross-origin",
      "Strict-Transport-Security": "max-age=31536000; includeSubDomains",
    });
    expect(headers["Permissions-Policy"]).toContain("camera=()");
    expect(securityHeaders({ dev: false, siteUrl: "http://localhost:3124" })["Strict-Transport-Security"]).toBeUndefined();
    expect(securityHeaders({ dev: true, siteUrl: "https://uptms.example" })["Strict-Transport-Security"]).toBeUndefined();
  });
});

describe("proxy matcher", () => {
  const matches = (url: string) => unstable_doesMiddlewareMatch({ config, url });

  it("covers the site's pages", () => {
    for (const url of ["/", "/spravato", "/administration-guide", "/robots.txt", "/sitemap.xml"]) expect(matches(url), url).toBe(true);
  });

  it("leaves Laravel's paths and build output alone", () => {
    for (const url of ["/admin", "/admin/login", "/api/v1/public/page", "/sanctum/csrf-cookie", "/build/assets/app.js", "/storage/media/a.webp", "/up", "/login", "/_next/static/chunks/a.js", "/_next/image?url=x", "/favicon.ico"]) {
      expect(matches(url), url).toBe(false);
    }
  });
});
