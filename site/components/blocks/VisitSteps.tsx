import { ButtonLink } from "@/components/ui/Button";
import { Headline } from "@/components/ui/Headline";
import { Picture } from "@/components/ui/Picture";
import { SectionRail } from "@/components/ui/SectionRail";
import { resolveLink } from "@/lib/text";
import type { Block, Link, PublicImage, SiteContext } from "@/lib/types";
import { DISPLAY_L, Section, SplitHead } from "./shared";

interface Data {
  rail?: string;
  headline?: string;
  intro?: string;
  steps?: { title: string; body: string }[];
  note_eyebrow?: string;
  note_body?: string;
  note_caption?: string;
  note_image?: PublicImage | null;
  note_cta?: Link;
}

/** Figma "03 · Your visit": numbered step cards (the first in royal), then a navy note with a photo. */
export function VisitSteps({ block, site }: { block: Block<Data>; site: SiteContext }) {
  const { data } = block;
  const steps = data.steps ?? [];
  const hasNote = data.note_body || data.note_image;

  return (
    <Section id={block.anchor} className="flex flex-col gap-14">
      <SplitHead
        title={
          <>
            <SectionRail label={data.rail} />
            <Headline text={data.headline} className={`text-ink ${DISPLAY_L}`} />
          </>
        }
        intro={data.intro}
      />
      {steps.length > 0 && (
        <ol className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {steps.map((step, i) => {
            const first = i === 0;

            return (
              <li key={step.title} className={`flex flex-col gap-3.5 rounded-card px-7 pt-7 pb-8 ${first ? "bg-royal text-white" : "bg-paper"}`}>
                <span className={`font-serif text-heading-m ${first ? "text-white" : "text-royal"}`}>{String(i + 1).padStart(2, "0")}</span>
                <span aria-hidden="true" className="h-10" />
                <h3 className={`font-serif text-heading-s ${first ? "text-white" : "text-ink"}`}>{step.title}</h3>
                <p className={`text-body-m ${first ? "text-white" : "text-ink-soft"}`}>{step.body}</p>
              </li>
            );
          })}
        </ol>
      )}
      {hasNote && (
        <div className="flex flex-col overflow-hidden rounded-sheet bg-navy lg:flex-row lg:items-center lg:gap-10 lg:pr-12">
          {data.note_image && (
            <div className="relative min-h-[220px] lg:min-h-[265px] lg:w-[460px] lg:shrink-0 lg:self-stretch">
              <Picture image={data.note_image} sizes="(min-width: 1024px) 460px, 100vw" className="absolute inset-0 size-full object-cover" />
            </div>
          )}
          <div className="flex min-w-0 flex-1 flex-col gap-2 px-6 py-8 lg:px-0">
            {data.note_eyebrow && <p className="font-display text-eyebrow font-bold text-sky uppercase">{data.note_eyebrow}</p>}
            {data.note_body && <p className="font-serif text-heading-s text-white">{data.note_body}</p>}
            {data.note_caption && <p className="text-caption text-sky">{data.note_caption}</p>}
          </div>
          <ButtonLink link={resolveLink(data.note_cta, site.settings)} variant="light" className="mx-6 mb-8 lg:mx-0 lg:mb-0 lg:shrink-0" />
        </div>
      )}
    </Section>
  );
}
