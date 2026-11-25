import Image from "next/image";
import { ButtonLink } from "@/components/ui/Button";
import { Headline } from "@/components/ui/Headline";
import { SectionRail } from "@/components/ui/SectionRail";
import { resolveLink } from "@/lib/text";
import type { Block, Link, SiteContext } from "@/lib/types";
import { DISPLAY_L, PanelSection } from "./shared";

interface Data {
  rail?: string;
  headline?: string;
  intro?: string;
  primary?: Link;
  items?: { title: string; body: string }[];
  note?: string;
}

/** Figma "02 · Eligibility": intro and button beside checklist cards. */
export function Criteria({ block, site }: { block: Block<Data>; site: SiteContext }) {
  const { data } = block;

  return (
    <PanelSection id={block.anchor} tone="sky-soft" className="flex flex-col gap-12 lg:flex-row lg:items-start lg:gap-20">
      <div className="flex flex-col items-start gap-[22px] lg:w-[440px] lg:shrink-0">
        <SectionRail label={data.rail} />
        <Headline text={data.headline} className={`text-ink ${DISPLAY_L}`} />
        {data.intro && <p className="text-body-l text-ink-soft">{data.intro}</p>}
        <ButtonLink link={resolveLink(data.primary, site.settings)} />
      </div>
      <div className="flex min-w-0 flex-1 flex-col gap-4">
        <ul className="flex flex-col gap-4">
          {(data.items ?? []).map((item) => (
            <li key={item.title} className="flex gap-5 rounded-card bg-white py-7 pr-8 pl-7">
              <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-royal">
                <Image src="/icons/check.svg" alt="" width={20} height={20} />
              </span>
              <div className="flex min-w-0 flex-col gap-1.5">
                <h3 className="font-serif text-heading-s text-ink">{item.title}</h3>
                <p className="text-body-m text-ink-soft">{item.body}</p>
              </div>
            </li>
          ))}
        </ul>
        {data.note && <p className="text-caption text-ink-soft">{data.note}</p>}
      </div>
    </PanelSection>
  );
}
