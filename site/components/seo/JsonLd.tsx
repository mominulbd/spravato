import { jsonLdDocument, serializeJsonLd } from "@/lib/seo";
import type { PageSeo } from "@/lib/types";

/** The page's schema.org graph as one JSON-LD script; nothing when the graph is empty. */
export function JsonLd({ seo }: { seo: PageSeo | null }) {
  const document = jsonLdDocument(seo);

  if (!document) return null;

  // serializeJsonLd escapes <, > and &, so CMS text cannot break out of the script.
  return <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: serializeJsonLd(document) }} />;
}
