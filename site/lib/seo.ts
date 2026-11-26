import type { Metadata } from "next";
import type { JsonLdNode, PageSeo, SiteSeo } from "./types";

/*
 * Turns the API's SEO payload (SeoPresenter::public + Public\SeoController::site)
 * into Next.js metadata and the JSON-LD document. Pure: no fetches, so it is
 * unit-tested in lib/seo.test.ts.
 */

/** Nothing is indexed until launch (docs/DEVELOPMENT-PLAN.md, Phase 3). */
export function indexingAllowed(): boolean {
  return process.env.ALLOW_INDEXING === "true";
}

export const NO_INDEX = { index: false, follow: false } as const;

export function robotsFor(seo: PageSeo | null, allowIndexing = indexingAllowed()): NonNullable<Metadata["robots"]> {
  if (!allowIndexing || !seo) return { ...NO_INDEX };

  const { index, follow, max_image_preview: image, max_snippet: snippet } = seo.robots;

  return {
    index,
    follow,
    ...(image ? { "max-image-preview": image } : {}),
    ...(typeof snippet === "number" ? { "max-snippet": snippet } : {}),
  };
}

function handle(value: string | null | undefined): string | undefined {
  const bare = (value ?? "").trim().replace(/^@+/, "");

  return bare === "" ? undefined : `@${bare}`;
}

function verificationFor(site: SiteSeo | null): Metadata["verification"] {
  const codes = site && !Array.isArray(site.verification) ? site.verification : {};
  const google = codes.google?.trim();
  const bing = codes.bing?.trim();

  if (!google && !bing) return undefined;

  return {
    ...(google ? { google } : {}),
    ...(bing ? { other: { "msvalidate.01": bing } } : {}),
  };
}

/**
 * The page's metadata. `seo` null (page or SEO module missing) still yields
 * the indexing guard, so the layout's fallback title/description apply.
 */
export function buildMetadata(seo: PageSeo | null, site: SiteSeo | null, allowIndexing = indexingAllowed()): Metadata {
  const robots = robotsFor(seo, allowIndexing);
  const verification = verificationFor(site);

  if (!seo) {
    return { robots, ...(verification ? { verification } : {}) };
  }

  const og = seo.open_graph;
  const image = og.image
    ? {
        url: og.image.url,
        ...(og.image.width ? { width: og.image.width } : {}),
        ...(og.image.height ? { height: og.image.height } : {}),
        ...(og.image.alt ? { alt: og.image.alt } : {}),
      }
    : null;
  const siteName = og.site_name || site?.site_name || undefined;
  const xSite = handle(seo.twitter.site) ?? handle(site?.x_handle);

  const openGraph: NonNullable<Metadata["openGraph"]> =
    og.type === "article"
      ? {
          type: "article",
          ...(og.published_time ? { publishedTime: og.published_time } : {}),
          ...(og.modified_time ? { modifiedTime: og.modified_time } : {}),
          title: og.title,
          description: og.description,
          url: og.url,
          siteName,
          locale: "en_US",
          ...(image ? { images: [image] } : {}),
        }
      : {
          type: "website",
          title: og.title,
          description: og.description,
          url: og.url,
          siteName,
          locale: "en_US",
          ...(image ? { images: [image] } : {}),
        };

  return {
    // absolute: never doubled by a parent title template.
    title: { absolute: seo.title },
    description: seo.description,
    alternates: { canonical: seo.canonical },
    robots,
    openGraph,
    twitter: {
      card: seo.twitter.card ?? (image ? "summary_large_image" : "summary"),
      ...(xSite ? { site: xSite } : {}),
      title: og.title,
      description: og.description,
      ...(image ? { images: [{ url: image.url, ...(image.alt ? { alt: image.alt } : {}) }] } : {}),
    },
    ...(verification ? { verification } : {}),
  };
}

export interface JsonLdDocument {
  "@context": "https://schema.org";
  "@graph": JsonLdNode[];
}

/** The page's JSON-LD document, or null when the graph is empty. */
export function jsonLdDocument(seo: PageSeo | null): JsonLdDocument | null {
  const graph = Array.isArray(seo?.json_ld) ? seo.json_ld : [];

  return graph.length > 0 ? { "@context": "https://schema.org", "@graph": graph } : null;
}

const UNSAFE: Record<string, string> = {
  "<": "\\u003c",
  ">": "\\u003e",
  "&": "\\u0026",
  "\u2028": "\\u2028",
  "\u2029": "\\u2029",
};

/**
 * JSON for a `<script type="application/ld+json">` body: `<`, `>` and `&`
 * become \u escapes (still the same JSON), so CMS text such as `</script>`
 * or `<!--` cannot close or comment out the script.
 */
export function serializeJsonLd(value: unknown): string {
  return JSON.stringify(value).replace(/[<>&\u2028\u2029]/g, (char) => UNSAFE[char] ?? char);
}
