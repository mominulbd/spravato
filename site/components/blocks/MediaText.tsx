import { ButtonLink, TextLink } from "@/components/ui/Button";
import { Headline } from "@/components/ui/Headline";
import { Picture } from "@/components/ui/Picture";
import { Paragraphs } from "@/components/ui/RichText";
import { SectionRail } from "@/components/ui/SectionRail";
import { resolveLink } from "@/lib/text";
import type { Block, Link, PublicImage, SiteContext } from "@/lib/types";
import { DISPLAY_L, Section } from "./shared";

interface Data {
  rail?: string;
  headline?: string;
  body?: string;
  facts?: { value: string; label: string }[];
  disclaimer?: string;
  primary?: Link;
  link?: Link | null;
  image?: PublicImage | null;
  image_side?: "left" | "right";
  caption_eyebrow?: string;
  caption?: string;
}

/** Figma "01 · What is Spravato": product photo on a sky backdrop with a caption chip, beside copy and fact tiles. */
export function MediaText({ block, site }: { block: Block<Data>; site: SiteContext }) {
  const { data } = block;
  const figureFirst = (data.image_side ?? "left") === "left";

  return (
    <Section id={block.anchor}>
      <div className={`flex flex-col items-center gap-12 lg:gap-20 ${figureFirst ? "lg:flex-row" : "lg:flex-row-reverse"}`}>
        {data.image && (
          <figure className="relative w-full max-w-[560px] shrink-0 pb-10 lg:w-[560px] lg:pb-10">
            <div aria-hidden="true" className="absolute inset-x-0 top-0 bottom-10 rounded-sheet bg-sky-soft" />
            <div className="relative px-[14.3%] pt-[9%]">
              <Picture image={data.image} sizes="(min-width: 1024px) 400px, 72vw" className="aspect-square w-full rounded-card object-cover" />
            </div>
            {(data.caption_eyebrow || data.caption) && (
              <figcaption className="relative mx-4 -mt-10 flex flex-col gap-1 rounded-[20px] bg-navy px-[22px] py-[18px] lg:absolute lg:bottom-0 lg:left-[150px] lg:mx-0 lg:mt-0 lg:w-max lg:max-w-[474px]">
                {data.caption_eyebrow && <span className="font-display text-eyebrow font-bold text-sky uppercase">{data.caption_eyebrow}</span>}
                {data.caption && <span className="text-body-s text-white">{data.caption}</span>}
              </figcaption>
            )}
          </figure>
        )}
        <div className="flex min-w-0 flex-1 flex-col gap-[22px]">
          <SectionRail label={data.rail} />
          <Headline text={data.headline} className={`text-ink ${DISPLAY_L}`} />
          <Paragraphs text={data.body} className="text-body-m text-ink-soft" />
          {data.facts && data.facts.length > 0 && (
            <dl className="grid gap-4 sm:grid-cols-2">
              {data.facts.map((fact) => (
                <div key={fact.value} className="flex flex-col gap-1 rounded-[20px] bg-sky-soft px-[22px] py-5">
                  <dt className="font-serif text-heading-s text-royal">{fact.value}</dt>
                  <dd className="text-body-s text-ink-soft">{fact.label}</dd>
                </div>
              ))}
            </dl>
          )}
          {data.disclaimer && <p className="text-caption text-ink-soft">{data.disclaimer}</p>}
          <div className="flex flex-wrap items-center gap-5">
            <ButtonLink link={resolveLink(data.primary, site.settings)} />
            <TextLink link={resolveLink(data.link, site.settings)} />
          </div>
        </div>
      </div>
    </Section>
  );
}
