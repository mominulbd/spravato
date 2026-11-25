import { ButtonLink } from "@/components/ui/Button";
import { Headline } from "@/components/ui/Headline";
import { Picture } from "@/components/ui/Picture";
import { resolveLink } from "@/lib/text";
import type { Block, Link, PublicImage, SiteContext } from "@/lib/types";

interface Data {
  eyebrow?: string;
  headline?: string;
  lead?: string;
  body?: string;
  primary?: Link;
  secondary?: Link;
  image?: PublicImage | null;
}

/** Figma "Hero": navy panel, copy beside the nasal-spray photo. */
export function LandingHero({ block, site }: { block: Block<Data>; site: SiteContext }) {
  const { data } = block;

  return (
    <section id="top" className="mx-auto w-full max-w-[1440px] px-3 md:px-6">
      <div className="flex flex-col items-center gap-10 overflow-hidden rounded-panel bg-navy px-6 pt-10 pb-6 text-white md:px-12 md:pt-14 lg:flex-row lg:gap-16 xl:px-[72px] xl:pt-[72px] xl:pb-10">
        <div className="flex min-w-0 flex-1 flex-col gap-7">
          {data.eyebrow && <p className="font-display text-eyebrow font-bold text-sky uppercase">{data.eyebrow}</p>}
          <Headline as="h1" text={data.headline} accent="sky" className="text-[46px] leading-[1.04] tracking-[-0.025em] md:text-[60px] xl:text-display-xl" />
          {data.lead && <p className="text-body-l">{data.lead}</p>}
          {data.body && <p className="text-body-m">{data.body}</p>}
          <div className="flex flex-wrap gap-3">
            <ButtonLink link={resolveLink(data.primary, site.settings)} variant="light" />
            <ButtonLink link={resolveLink(data.secondary, site.settings)} variant="outline-light" />
          </div>
        </div>
        <div className="w-full max-w-[500px] lg:mb-8 lg:ml-[60px] lg:w-[500px] lg:shrink-0">
          <Picture image={data.image} sizes="(min-width: 1024px) 500px, 100vw" priority className="aspect-[500/560] w-full rounded-sheet object-cover" />
        </div>
      </div>
    </section>
  );
}
