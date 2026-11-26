import "server-only";
import type { MetadataRoute } from "next";
import { cacheLife, cacheTag } from "next/cache";
import { getData } from "./api";

/*
 * What /sitemap.xml, /robots.txt and /indexnow-key.txt serve. Laravel owns
 * the content (Uptms\Seo\Http\Controllers\Public\SitemapController); the
 * site turns it into files on its own origin. Reads are cached and tagged
 * `content` + `seo`, which Laravel expires on every publish.
 */

/** One entry of GET /api/v1/public/sitemap. */
export interface SitemapEntry {
  path: string;
  type: string;
  lastmod: string | null;
  priority: number;
  images: { url: string; alt: string }[];
}

/** GET /api/v1/public/robots. `indexnow_key` is null unless IndexNow is on and the site is live. */
export interface RobotsData {
  text: string;
  indexnow_key: string | null;
}

/** robots.txt before launch when the CMS cannot be reached. */
export const BLOCK_ALL_ROBOTS = "User-agent: *\nDisallow: /\n";

/** robots.txt rules when indexing is on but the CMS cannot be reached. Mirrors Laravel's default. */
export const FALLBACK_ROBOTS = "User-agent: *\nDisallow: /admin\nDisallow: /api/\n";

/** The public origin, without a trailing slash (NEXT_PUBLIC_SITE_URL). */
export function siteOrigin(): string {
  return (process.env.NEXT_PUBLIC_SITE_URL || "http://localhost:3000").replace(/\/+$/, "");
}

/** Search engines may index the site only when ALLOW_INDEXING is exactly "true". */
export function indexingAllowed(): boolean {
  return process.env.ALLOW_INDEXING === "true";
}

/**
 * The robots.txt body: the text staff edit in the admin (SEO › Sitemap &
 * robots), plus a `Sitemap:` line when it has none. Before launch the pages
 * still carry `noindex` (lib/seo.ts), so the CMS text decides crawling only.
 * When the CMS text is missing (API down) the fallback depends on indexing:
 * block everything before launch, the default rules after it.
 */
export function composeRobots(options: { allowIndexing: boolean; text: string | null; origin: string }): string {
  const fallback = options.allowIndexing ? FALLBACK_ROBOTS : BLOCK_ALL_ROBOTS;
  const text = (options.text ?? "").replace(/\r\n?/g, "\n").trim() || fallback.trim();
  const hasSitemap = /^\s*sitemap\s*:/im.test(text);

  return hasSitemap ? `${text}\n` : `${text}\n\nSitemap: ${options.origin}/sitemap.xml\n`;
}

/** Next.js writes sitemap values into the XML as they are; escape them here. */
function escapeXml(value: string): string {
  return value.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&apos;");
}

/** An absolute URL on `origin` for a site path or a media URL; null for anything off-site or malformed. */
function absolute(url: string, origin: string, sameOriginOnly: boolean): string | null {
  try {
    const resolved = new URL(url, `${origin}/`);

    if (resolved.protocol !== "http:" && resolved.protocol !== "https:") {
      return null;
    }

    if (sameOriginOnly && resolved.origin !== new URL(origin).origin) {
      return null;
    }

    return resolved.href;
  } catch {
    return null;
  }
}

/**
 * The API's entries as a Next.js sitemap: absolute, XML-escaped URLs on the
 * site's origin, priority clamped to 0–1, lastmod only when it parses.
 * Entries whose path is not a site path are dropped.
 */
export function buildSitemap(entries: SitemapEntry[], origin: string): MetadataRoute.Sitemap {
  const sitemap: MetadataRoute.Sitemap = [];

  for (const entry of entries) {
    if (typeof entry.path !== "string" || !entry.path.startsWith("/") || entry.path.startsWith("//")) {
      continue;
    }

    const url = absolute(entry.path, origin, true);

    if (url === null) {
      continue;
    }

    const images = (entry.images ?? []).map((image) => absolute(image.url, origin, false)).filter((image): image is string => image !== null);
    const lastModified = entry.lastmod && !Number.isNaN(Date.parse(entry.lastmod)) ? entry.lastmod : undefined;
    const priority = Number.isFinite(entry.priority) ? Math.min(1, Math.max(0, entry.priority)) : undefined;

    sitemap.push({
      url: escapeXml(url),
      ...(lastModified ? { lastModified } : {}),
      ...(priority !== undefined ? { priority } : {}),
      ...(images.length > 0 ? { images: images.map(escapeXml) } : {}),
    });
  }

  return sitemap;
}

/** The sitemap's entries (cached until the next publish). */
export async function getSitemapEntries(): Promise<SitemapEntry[]> {
  "use cache";
  cacheLife("hours");
  cacheTag("content", "seo", "sitemap");

  return getData<SitemapEntry[]>("public/sitemap");
}

/** The CMS's robots text and IndexNow key (cached until the next publish). */
export async function getRobotsData(): Promise<RobotsData> {
  "use cache";
  cacheLife("hours");
  cacheTag("content", "seo", "robots");

  return getData<RobotsData>("public/robots");
}
