import { connection } from "next/server";
import { composeRobots, getRobotsData, indexingAllowed, siteOrigin } from "@/lib/seo-files";

/**
 * /robots.txt: the raw text staff edit in the admin (SEO › Sitemap &
 * robots), which `app/robots.ts` cannot express, so this is a plain route
 * handler. ALLOW_INDEXING (read per request) only picks the fallback used
 * when the CMS cannot be reached; see composeRobots.
 */
export async function GET() {
  await connection();

  const allowIndexing = indexingAllowed();
  let text: string | null;

  try {
    text = (await getRobotsData()).text;
  } catch {
    text = null;
  }

  return new Response(composeRobots({ allowIndexing, text, origin: siteOrigin() }), {
    headers: { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "public, max-age=300" },
  });
}
