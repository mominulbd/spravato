import "server-only";
import { cacheLife, cacheTag } from "next/cache";
import { ApiError, getData } from "./api";
import type { ConsultationForm } from "./consultation";
import type { Menus, PublicPage, Settings, SiteSeo } from "./types";

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

/** The consultation dialog's choices; null when the consultations module is off. */
export async function getConsultationForm(): Promise<ConsultationForm | null> {
  "use cache";
  cacheLife("hours");
  cacheTag("content", "consultation-form");

  try {
    return await getData<ConsultationForm>("public/consultation-form");
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      return null;
    }

    throw error;
  }
}

/** Site-wide SEO values (site name, X handle, verification); null when the SEO module is off. */
export async function getSiteSeo(): Promise<SiteSeo | null> {
  "use cache";
  cacheLife("hours");
  cacheTag("content", "seo");

  try {
    return await getData<SiteSeo>("public/seo");
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      return null;
    }

    throw error;
  }
}
