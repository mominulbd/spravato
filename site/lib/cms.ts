import "server-only";
import { cacheLife, cacheTag } from "next/cache";
import { ApiError, getData } from "./api";
import type { Menus, PublicPage, Settings } from "./types";

/*
 * Every read is cached and tagged `content`: Laravel sends that tag with
 * every publish (Uptms\Publishing\Events\ContentPublished) to
 * /api/revalidate, which expires it at once.
 */

export async function getPage(path: string): Promise<PublicPage | null> {
  "use cache";
  cacheLife("hours");
  cacheTag("content", `page:${path}`);

  try {
    return await getData<PublicPage>(`public/page?path=${encodeURIComponent(path)}`);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      return null;
    }

    throw error;
  }
}

export async function getSettings(): Promise<Settings> {
  "use cache";
  cacheLife("hours");
  cacheTag("content", "settings");

  return getData<Settings>("public/settings");
}

export async function getMenus(): Promise<Menus> {
  "use cache";
  cacheLife("hours");
  cacheTag("content", "menus");

  return getData<Menus>("public/menus");
}
