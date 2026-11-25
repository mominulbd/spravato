import { ButtonLink, TextLink } from "@/components/ui/Button";
import { Headline } from "@/components/ui/Headline";
import { resolveLink } from "@/lib/text";
import type { Block, Link, SiteContext } from "@/lib/types";

interface Data {
  tone?: "royal" | "sky";
  eyebrow?: string;
  headline?: string;
  body?: string;
  primary?: Link;
  secondary?: Link;
  link?: Link;
}

/** Figma "Final CTA": a royal panel with a headline and two buttons (sky is the lighter variant). */
export function CtaBand({ block, site }: { block: Block<Data>; site: SiteContext }) {
  const { data } = block;
  const royal = (data.tone ?? "royal") === "royal";

  return (
    <section id={block.anchor ?? undefined} className="mx-auto w-full max-w-[1440px] scroll-mt-6 px-3 pb-6 md:px-6">
      <div className={`flex flex-col items-start gap-6 rounded-panel px-6 py-14 md:p-12 xl:px-24 xl:pt-[104px] xl:pb-24 ${royal ? "bg-royal text-white" : "bg-sky text-ink"}`}>
        {data.eyebrow && <p className={`text-body-m font-bold ${royal ? "text-sky" : "text-royal"}`}>{data.eyebrow}</p>}
        <Headline text={data.headline} accent={royal ? "sky" : "royal"} className="max-w-[1000px] text-[40px] leading-[1.08] tracking-[-0.02em] md:text-display-l" />
        {data.body && <p className="max-w-[640px] text-body-l">{data.body}</p>}
        <div className="flex flex-wrap gap-3 pt-3">
          <ButtonLink link={resolveLink(data.primary, site.settings)} variant={royal ? "light" : "primary"} />
          <ButtonLink link={resolveLink(data.secondary, site.settings)} variant={royal ? "outline-light" : "secondary"} />
          <TextLink link={resolveLink(data.link, site.settings)} tone={royal ? "white" : "royal"} className="self-center" />
        </div>
      </div>
    </section>
  );
}
