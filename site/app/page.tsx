import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { BlockRenderer } from "@/components/blocks/BlockRenderer";
import { SiteFooter } from "@/components/layout/SiteFooter";
import { SiteHeader } from "@/components/layout/SiteHeader";
import { getMenus, getPage, getSettings } from "@/lib/cms";

/** The Spravato landing page: the published blocks of the page at `/`. */
export async function generateMetadata(): Promise<Metadata> {
  const page = await getPage("/");

  return {
    title: typeof page?.seo.title === "string" ? page.seo.title : undefined,
    description: typeof page?.seo.description === "string" ? page.seo.description : undefined,
  };
}

export default async function HomePage() {
  const [page, settings, menus] = await Promise.all([getPage("/"), getSettings(), getMenus()]);

  if (!page) notFound();

  const site = { settings, menus };

  return (
    <>
      <SiteHeader settings={settings} />
      <main id="main" className="flex flex-col">
        <BlockRenderer blocks={page.blocks} site={site} />
      </main>
      <SiteFooter settings={settings} documents={menus.documents ?? []} />
    </>
  );
}
