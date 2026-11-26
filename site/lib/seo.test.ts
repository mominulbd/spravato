import { afterEach, describe, expect, it, vi } from "vitest";
import { buildMetadata, jsonLdDocument, robotsFor, serializeJsonLd } from "./seo";
import type { PageSeo, SiteSeo } from "./types";

function pageSeo(overrides: Partial<PageSeo> = {}): PageSeo {
  return {
    title: "Spravato® Treatment near Tacoma | UPTMS",
    description: "Supervised Spravato® visits in University Place.",
    canonical: "https://example.test/",
    robots: { index: true, follow: true, max_image_preview: "large", max_snippet: null },
    open_graph: {
      type: "website",
      title: "Spravato® at UPTMS",
      description: "Share description",
      url: "https://example.test/",
      site_name: "UPTMS",
      image: { url: "https://example.test/storage/hero.jpg", width: 1200, height: 630, alt: "Clinic room" },
      published_time: null,
      modified_time: null,
    },
    twitter: { card: "summary_large_image", site: "@uptms" },
    json_ld: [{ "@type": "MedicalClinic", "@id": "https://example.test/#clinic", name: "UPTMS" }],
    ...overrides,
  };
}

const site: SiteSeo = {
  site_name: "UPTMS",
  separator: "|",
  x_handle: "@uptms",
  verification: { google: "g-code", bing: "b-code" },
  tag_manager: { id: "GTM-ABC123", exclude_paths: ["/"] },
};

afterEach(() => {
  vi.unstubAllEnvs();
});

describe("buildMetadata", () => {
  it("maps the API payload to Next.js metadata", () => {
    const metadata = buildMetadata(pageSeo(), site, true);

    expect(metadata).toEqual({
      title: { absolute: "Spravato® Treatment near Tacoma | UPTMS" },
      description: "Supervised Spravato® visits in University Place.",
      alternates: { canonical: "https://example.test/" },
      robots: { index: true, follow: true, "max-image-preview": "large" },
      openGraph: {
        type: "website",
        title: "Spravato® at UPTMS",
        description: "Share description",
        url: "https://example.test/",
        siteName: "UPTMS",
        locale: "en_US",
        images: [{ url: "https://example.test/storage/hero.jpg", width: 1200, height: 630, alt: "Clinic room" }],
      },
      twitter: {
        card: "summary_large_image",
        site: "@uptms",
        title: "Spravato® at UPTMS",
        description: "Share description",
        images: [{ url: "https://example.test/storage/hero.jpg", alt: "Clinic room" }],
      },
      verification: { google: "g-code", other: { "msvalidate.01": "b-code" } },
    });
  });

  it("never renders a Tag Manager entry", () => {
    expect(JSON.stringify(buildMetadata(pageSeo(), site, true))).not.toContain("GTM-");
  });

  it("maps article dates", () => {
    const seo = pageSeo();
    seo.open_graph = { ...seo.open_graph, type: "article", published_time: "2026-11-01T00:00:00+00:00", modified_time: "2026-11-20T00:00:00+00:00" };

    expect(buildMetadata(seo, site, true).openGraph).toMatchObject({
      type: "article",
      publishedTime: "2026-11-01T00:00:00+00:00",
      modifiedTime: "2026-11-20T00:00:00+00:00",
    });
  });

  it("leaves images out when there is no share image", () => {
    const seo = pageSeo({ twitter: { card: null, site: null } });
    seo.open_graph = { ...seo.open_graph, image: null };

    const metadata = buildMetadata(seo, { ...site, x_handle: "clinic" }, true);

    expect(metadata.openGraph).not.toHaveProperty("images");
    expect(metadata.twitter).toEqual({ card: "summary", site: "@clinic", title: "Spravato® at UPTMS", description: "Share description" });
  });

  it("leaves out missing image dimensions and empty verification", () => {
    const seo = pageSeo();
    seo.open_graph = { ...seo.open_graph, image: { url: "https://example.test/a.png", width: null, height: null, alt: "" } };

    const metadata = buildMetadata(seo, { ...site, verification: [] }, true);

    expect(metadata.openGraph).toMatchObject({ images: [{ url: "https://example.test/a.png" }] });
    expect((metadata.openGraph as { images: object[] }).images[0]).toEqual({ url: "https://example.test/a.png" });
    expect(metadata).not.toHaveProperty("verification");
  });

  it("keeps only the guard when the page has no SEO", () => {
    expect(buildMetadata(null, null, true)).toEqual({ robots: { index: false, follow: false } });
  });
});

describe("indexing guard", () => {
  it("is noindex, nofollow unless ALLOW_INDEXING is exactly true", () => {
    for (const value of ["", "false", "1", "TRUE"]) {
      vi.stubEnv("ALLOW_INDEXING", value);
      expect(buildMetadata(pageSeo(), site).robots).toEqual({ index: false, follow: false });
    }
  });

  it("uses the API values once indexing is allowed", () => {
    vi.stubEnv("ALLOW_INDEXING", "true");
    const seo = pageSeo({ robots: { index: true, follow: false, max_image_preview: "standard", max_snippet: 160 } });

    expect(buildMetadata(seo, site).robots).toEqual({ index: true, follow: false, "max-image-preview": "standard", "max-snippet": 160 });
  });

  it("keeps a page's noindex even when indexing is allowed", () => {
    const seo = pageSeo({ robots: { index: false, follow: true, max_image_preview: null, max_snippet: null } });

    expect(robotsFor(seo, true)).toEqual({ index: false, follow: true });
  });
});

describe("JSON-LD", () => {
  it("wraps the graph in a schema.org document", () => {
    expect(jsonLdDocument(pageSeo())).toEqual({
      "@context": "https://schema.org",
      "@graph": [{ "@type": "MedicalClinic", "@id": "https://example.test/#clinic", name: "UPTMS" }],
    });
  });

  it("is null for an empty or missing graph", () => {
    expect(jsonLdDocument(pageSeo({ json_ld: [] }))).toBeNull();
    expect(jsonLdDocument(null)).toBeNull();
  });

  it("escapes characters that could break out of the script", () => {
    const doc = jsonLdDocument(pageSeo({ json_ld: [{ "@type": "FAQPage", name: "</script><script>alert(1)</script> & <!-- \u2028" }] }));
    const json = serializeJsonLd(doc);

    expect(json).not.toMatch(/[<>&\u2028\u2029]/);
    expect(json).toContain("\\u003c/script\\u003e");
    expect(JSON.parse(json)).toEqual(doc);
  });
});
