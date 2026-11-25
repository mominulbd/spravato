import { Headline } from "@/components/ui/Headline";
import { SectionRail } from "@/components/ui/SectionRail";
import type { Block } from "@/lib/types";
import { DISPLAY_L, PanelSection } from "./shared";

interface Data {
  rail?: string;
  headline?: string;
  lead?: string;
  body?: string;
  rows?: { period: string; title: string; body?: string }[];
  note?: string;
}

/** Figma "04 · Schedule": navy panel, intro beside a white card of schedule rows. */
export function Schedule({ block }: { block: Block<Data> }) {
  const { data } = block;

  return (
    <PanelSection id={block.anchor} tone="navy" className="flex flex-col gap-12 lg:flex-row lg:items-center lg:gap-20">
      <div className="flex min-w-0 flex-1 flex-col gap-[22px]">
        <SectionRail label={data.rail} tone="dark" />
        <Headline text={data.headline} accent="sky" className={`text-white ${DISPLAY_L}`} />
        {data.lead && <p className="text-body-l">{data.lead}</p>}
        {data.body && <p className="text-body-m opacity-80">{data.body}</p>}
      </div>
      <div className="flex w-full flex-col rounded-sheet bg-white px-6 pt-3 pb-7 md:px-9 lg:w-[560px] lg:shrink-0">
        <dl>
          {(data.rows ?? []).map((row) => (
            <div key={row.period} className="flex flex-col gap-2 border-b border-line py-6 sm:flex-row sm:items-center sm:gap-6">
              <dt className="font-display text-eyebrow font-bold text-royal uppercase sm:w-[130px] sm:shrink-0">{row.period}</dt>
              <dd className="flex min-w-0 flex-1 flex-col gap-0.5">
                <span className="font-serif text-heading-s text-ink">{row.title}</span>
                {row.body && <span className="text-body-s text-ink-soft">{row.body}</span>}
              </dd>
            </div>
          ))}
        </dl>
        {data.note && <p className="mt-4 text-caption text-ink-soft">{data.note}</p>}
      </div>
    </PanelSection>
  );
}
