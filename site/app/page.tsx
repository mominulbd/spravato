import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { BlockRenderer } from "@/components/blocks/BlockRenderer";
import { ConsultationDialog } from "@/components/consultation/ConsultationDialog";
import { SiteFooter } from "@/components/layout/SiteFooter";
import { SiteHeader } from "@/components/layout/SiteHeader";
import { JsonLd } from "@/components/seo/JsonLd";
import { getConsultationForm, getMenus, getPage, getSettings, getSiteSeo } from "@/lib/cms";
import { buildMetadata } from "@/lib/seo";

/*
 * The Spravato landing page: the published blocks of the page at `/`.
 * No Tag Manager here, ever: `/` hosts the consultation dialog, one of
 * SeoSettings::SENSITIVE_PATHS, so `tag_manager` from /public/seo is ignored.
 */
export async function generateMetadata(): Promise<Metadata> {
  const [page, site] = await Promise.all([getPage("/"), getSiteSeo()]);

  return buildMetadata(page?.seo ?? null, site);
}

export default async function HomePage() {
  const [page, settings, menus, form] = await Promise.all([getPage("/"), getSettings(), getMenus(), getConsultationForm()]);

  if (!page) notFound();

  const site = { settings, menus };

  return (
    <>
      <JsonLd seo={page.seo} />
      <SiteHeader settings={settings} />
      <main id="main" className="flex flex-col">
        <BlockRenderer blocks={page.blocks} site={site} />
      </main>
      <SiteFooter settings={settings} documents={menus.documents ?? []} />
      <ConsultationDialog form={form} phone={settings.clinic.phone} />
    </>
  );
}
