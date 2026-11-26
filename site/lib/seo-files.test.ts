import { afterEach, describe, expect, it, vi } from "vitest";
import { BLOCK_ALL_ROBOTS, buildSitemap, composeRobots, indexingAllowed, siteOrigin } from "./seo-files";

const origin = "https://spravato.example";

describe("composeRobots", () => {
  it("serves the text edited in the admin even before launch", () => {
    expect(composeRobots({ allowIndexing: false, text: "User-agent: *\nAllow: /", origin })).toBe(
      "User-agent: *\nAllow: /\n\nSitemap: https://spravato.example/sitemap.xml\n",
    );
  });

  it("blocks everything before launch when the CMS text is missing", () => {
    for (const text of [null, "", "  \n "]) {
      expect(composeRobots({ allowIndexing: false, text, origin })).toBe(`${BLOCK_ALL_ROBOTS}\nSitemap: https://spravato.example/sitemap.xml\n`);
    }
    expect(BLOCK_ALL_ROBOTS).toBe("User-agent: *\nDisallow: /\n");
  });

  it("serves the CMS text and appends the sitemap line when indexing is on", () => {
    expect(composeRobots({ allowIndexing: true, text: "User-agent: *\r\nDisallow: /admin\r\n", origin })).toBe(
      "User-agent: *\nDisallow: /admin\n\nSitemap: https://spravato.example/sitemap.xml\n",
    );
  });

  it("keeps a sitemap line the CMS already has", () => {
    const text = "User-agent: *\nDisallow: /admin\nsitemap: https://cdn.example/sitemap.xml";

    expect(composeRobots({ allowIndexing: true, text, origin })).toBe(`${text}\n`);
  });

  it("falls back to crawlable rules after launch when the CMS text is empty or missing", () => {
    for (const text of [null, "", "  \n "]) {
      const robots = composeRobots({ allowIndexing: true, text, origin });

      expect(robots).toContain("User-agent: *\nDisallow: /admin\nDisallow: /api/");
      expect(robots).not.toMatch(/^Disallow: \/$/m);
      expect(robots.endsWith("Sitemap: https://spravato.example/sitemap.xml\n")).toBe(true);
    }
  });
});

describe("buildSitemap", () => {
  it("builds absolute URLs with lastmod, priority and images", () => {
    expect(
      buildSitemap(
        [
          {
            path: "/",
            type: "page",
            lastmod: "2026-11-20T10:00:00+00:00",
            priority: 1,
            images: [
              { url: "/storage/media/hero.jpg", alt: "Hero" },
              { url: "https://spravato.example/storage/media/a&b.jpg", alt: "" },
            ],
          },
        ],
        origin,
      ),
    ).toEqual([
      {
        url: "https://spravato.example/",
        lastModified: "2026-11-20T10:00:00+00:00",
        priority: 1,
        images: ["https://spravato.example/storage/media/hero.jpg", "https://spravato.example/storage/media/a&amp;b.jpg"],
      },
    ]);
  });

  it("leaves out a missing or invalid lastmod and empty images, and clamps priority", () => {
    expect(buildSitemap([{ path: "/about?x=1&y=2", type: "page", lastmod: null, priority: 3, images: [] }], origin)).toEqual([
      { url: "https://spravato.example/about?x=1&amp;y=2", priority: 1 },
    ]);
    expect(buildSitemap([{ path: "/a", type: "page", lastmod: "not a date", priority: -1, images: [] }], origin)).toEqual([
      { url: "https://spravato.example/a", priority: 0 },
    ]);
  });

  it("drops entries that are not paths on the site", () => {
    const entry = { type: "page", lastmod: null, priority: 0.5, images: [] };

    expect(buildSitemap([{ ...entry, path: "https://evil.example/" }, { ...entry, path: "//evil.example/x" }, { ...entry, path: "relative" }], origin)).toEqual([]);
  });

  it("drops images that are not http(s)", () => {
    expect(buildSitemap([{ path: "/", type: "page", lastmod: null, priority: 1, images: [{ url: "javascript:alert(1)", alt: "" }] }], origin)).toEqual([
      { url: "https://spravato.example/", priority: 1 },
    ]);
  });
});

describe("environment", () => {
  afterEach(() => vi.unstubAllEnvs());

  it("reads the origin without a trailing slash", () => {
    vi.stubEnv("NEXT_PUBLIC_SITE_URL", "https://spravato.example/");
    expect(siteOrigin()).toBe("https://spravato.example");
  });

  it("allows indexing only for exactly \"true\"", () => {
    vi.stubEnv("ALLOW_INDEXING", "true");
    expect(indexingAllowed()).toBe(true);
    vi.stubEnv("ALLOW_INDEXING", "TRUE");
    expect(indexingAllowed()).toBe(false);
    vi.stubEnv("ALLOW_INDEXING", "");
    expect(indexingAllowed()).toBe(false);
  });
});
