import { TextLink } from "@/components/ui/Button";
import { Headline } from "@/components/ui/Headline";
import { Picture } from "@/components/ui/Picture";
import { SectionRail } from "@/components/ui/SectionRail";
import { resolveLink } from "@/lib/text";
import type { Block, Link, SiteContext, TeamMember } from "@/lib/types";
import { DISPLAY_L, PanelSection, SplitHead } from "./shared";

interface Data {
  rail?: string;
  headline?: string;
  intro?: string;
  founder_link?: Link;
  member_link?: Link;
}

/** Figma "06 · Care team": photo cards for the people Collections · Team shows on this page, founder first. */
export function CareTeam({ block, site }: { block: Block<Data, { team?: TeamMember[] }>; site: SiteContext }) {
  const { data } = block;
  const team = [...(block.sources.team ?? [])].sort((a, b) => Number(b.is_founder) - Number(a.is_founder));

  return (
    <PanelSection id={block.anchor} tone="sky-soft" className="flex flex-col gap-12">
      <SplitHead
        title={
          <>
            <SectionRail label={data.rail} />
            <Headline text={data.headline} className={`text-ink ${DISPLAY_L}`} />
          </>
        }
        intro={data.intro}
      />
      <div className="grid items-start gap-6 lg:grid-cols-2">
        {team.map((member) => (
          <article key={member.name} className="flex flex-col gap-7 rounded-sheet bg-white p-4 sm:flex-row sm:pr-8">
            {member.photo && <Picture image={member.photo} sizes="200px" className="h-[260px] w-full rounded-[20px] object-cover sm:w-[200px] sm:shrink-0" />}
            <div className="flex min-w-0 flex-1 flex-col gap-2 px-2 pb-2 sm:px-0 sm:py-4">
              <p className="font-display text-eyebrow font-bold text-royal uppercase">{member.role_title}</p>
              <h3 className="font-serif text-heading-m text-ink">{member.name}</h3>
              {member.credentials && <p className="text-[14px] leading-[1.5] font-semibold text-ink-soft">{member.credentials}</p>}
              {member.bio && <p className="text-body-s text-ink-soft">{member.bio}</p>}
              <TextLink link={resolveLink(member.is_founder ? data.founder_link : data.member_link, site.settings)} />
            </div>
          </article>
        ))}
      </div>
    </PanelSection>
  );
}
