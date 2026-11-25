import { ButtonLink, TextLink } from "@/components/ui/Button";
import { Headline } from "@/components/ui/Headline";
import { Paragraphs } from "@/components/ui/RichText";
import { SectionRail } from "@/components/ui/SectionRail";
import { resolveLink } from "@/lib/text";
import type { Block, Faq as FaqItem, Link, SiteContext } from "@/lib/types";
import { DISPLAY_L, Section } from "./shared";

interface Data {
  rail?: string;
  headline?: string;
  intro?: string;
  primary?: Link;
  aside_title?: string;
  aside_body?: string;
  aside_link?: Link;
  aside_links?: Link[];
}

/**
 * Figma "08 · FAQ": intro, a call button and the documents card beside an
 * accordion of Collections · FAQs. Native <details>, so it works without
 * JavaScript; the first answer starts open, as designed.
 */
export function Faq({ block, site }: { block: Block<Data, { faqs?: FaqItem[] }>; site: SiteContext }) {
  const { data } = block;
  const links = [data.aside_link, ...(data.aside_links ?? [])].map((link) => resolveLink(link, site.settings)).filter((link): link is Link => link !== null);

  return (
    <Section id={block.anchor} className="flex flex-col gap-12 lg:flex-row lg:items-start lg:gap-20">
      <div className="flex flex-col items-start gap-[22px] lg:w-[400px] lg:shrink-0">
        <SectionRail label={data.rail} />
        <Headline text={data.headline} className={`text-ink ${DISPLAY_L}`} />
        {data.intro && <p className="text-body-m text-ink-soft">{data.intro}</p>}
        <ButtonLink link={resolveLink(data.primary, site.settings)} />
        {data.aside_title && (
          <div id="documents" className="flex w-full scroll-mt-6 flex-col gap-2 rounded-[20px] bg-sky-soft p-6">
            <p className="text-title font-bold text-ink">{data.aside_title}</p>
            {data.aside_body && <p className="text-body-s text-ink-soft">{data.aside_body}</p>}
            {links.map((link) => (
              <TextLink key={link.href} link={link} />
            ))}
          </div>
        )}
      </div>
      <div className="min-w-0 flex-1">
        {(block.sources.faqs ?? []).map((faq, i) => (
          <details key={faq.question} open={i === 0} className="group border-b border-line py-[22px]">
            <summary className="flex cursor-pointer list-none items-center gap-6 [&::-webkit-details-marker]:hidden">
              <span className="min-w-0 flex-1 text-title font-bold text-ink">{faq.question}</span>
              <span aria-hidden="true" className="flex size-9 shrink-0 items-center justify-center rounded-full bg-sky-soft text-title font-bold text-royal group-open:bg-royal group-open:text-white">
                <span className="group-open:hidden">+</span>
                <span className="hidden group-open:inline">−</span>
              </span>
            </summary>
            <div className="mt-3 flex flex-col gap-3">
              <Paragraphs text={faq.answer} className="text-body-m text-ink-soft" />
            </div>
          </details>
        ))}
      </div>
    </Section>
  );
}
