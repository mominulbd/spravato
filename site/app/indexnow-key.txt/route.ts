import { connection } from "next/server";
import { getRobotsData } from "@/lib/seo-files";

/**
 * /indexnow-key.txt: the IndexNow key file. Laravel's IndexNow service
 * (Uptms\Seo\Services\IndexNow) submits URLs with
 * keyLocation = <site>/indexnow-key.txt, and the search engine checks that
 * this file holds the key. The key is public by design. 404 unless Laravel
 * reports IndexNow on (SEO_LIVE and the setting).
 */
export async function GET() {
  await connection();

  let key: string | null = null;

  try {
    key = (await getRobotsData()).indexnow_key;
  } catch {
    key = null;
  }

  if (!key) {
    return new Response("Not found.\n", { status: 404, headers: { "Content-Type": "text/plain; charset=utf-8" } });
  }

  return new Response(key, {
    headers: { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "public, max-age=300" },
  });
}
