import type { MetadataRoute } from "next";
import { connection } from "next/server";
import { buildSitemap, getSitemapEntries, siteOrigin } from "@/lib/seo-files";

/**
 * /sitemap.xml from the CMS (the published, indexable pages and their
 * images). Rendered per request so a build without Laravel never bakes a
 * stale fallback in; the API read itself is cached until the next publish.
 * If the API cannot be reached, the sitemap lists `/` alone: the landing
 * page always exists, and a sitemap that fails would be worse than a short one.
 */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  await connection();

  const origin = siteOrigin();

  try {
    return buildSitemap(await getSitemapEntries(), origin);
  } catch {
    return buildSitemap([{ path: "/", type: "page", lastmod: null, priority: 1, images: [] }], origin);
  }
}
