import type { ComponentType } from "react";
import type { Block, SiteContext } from "@/lib/types";
import { CareTeam } from "./CareTeam";
import { CoverageLocation } from "./CoverageLocation";
import { Criteria } from "./Criteria";
import { CtaBand } from "./CtaBand";
import { Faq } from "./Faq";
import { LandingHero } from "./LandingHero";
import { MediaText } from "./MediaText";
import { Schedule } from "./Schedule";
import { VideoPair } from "./VideoPair";
import { VisitSteps } from "./VisitSteps";

/**
 * Block type (packages/pages BlockTypes) → component. A type the site
 * doesn't render (the UPTMS-only blocks) is skipped rather than breaking
 * the page.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const COMPONENTS: Record<string, ComponentType<{ block: Block<any, any>; site: SiteContext }>> = {
  landing_hero: LandingHero,
  media_text: MediaText,
  criteria: Criteria,
  visit_steps: VisitSteps,
  schedule: Schedule,
  video_pair: VideoPair,
  care_team: CareTeam,
  coverage_location: CoverageLocation,
  faq: Faq,
  cta_band: CtaBand,
};

export function BlockRenderer({ blocks, site }: { blocks: Block[]; site: SiteContext }) {
  return (
    <>
      {blocks.map((block, i) => {
        const Component = COMPONENTS[block.type];

        return Component ? <Component key={block.id ?? i} block={block} site={site} /> : null;
      })}
    </>
  );
}
