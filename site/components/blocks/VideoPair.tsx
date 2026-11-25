import { Headline } from "@/components/ui/Headline";
import { Picture } from "@/components/ui/Picture";
import { SectionRail } from "@/components/ui/SectionRail";
import type { Block, PublicImage } from "@/lib/types";
import { DISPLAY_L, Section, SplitHead } from "./shared";
import { VideoCard } from "./VideoCard";

interface Data {
  rail?: string;
  headline?: string;
  intro?: string;
  videos?: { title: string; body?: string; poster?: PublicImage | null; url?: string }[];
}

/** Figma "05 · Videos": two video cards with a navy scrim and a play button. */
export function VideoPair({ block }: { block: Block<Data> }) {
  const { data } = block;

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
      <div className="grid gap-6 md:grid-cols-2">
        {(data.videos ?? []).map((video) => (
          <article key={video.title} className="flex flex-col overflow-hidden rounded-sheet bg-paper">
            <div className="relative aspect-[588/330] w-full overflow-hidden">
              <VideoCard title={video.title} url={(video.url ?? "").trim()} cover={<Picture image={video.poster} sizes="(min-width: 768px) 588px, 100vw" className="absolute inset-0 size-full object-cover" />} />
            </div>
            <div className="flex flex-col gap-1.5 px-7 pt-6 pb-7">
              <h3 className="font-serif text-heading-s text-ink">{video.title}</h3>
              {video.body && <p className="text-body-m text-ink-soft">{video.body}</p>}
            </div>
          </article>
        ))}
      </div>
    </Section>
  );
}
