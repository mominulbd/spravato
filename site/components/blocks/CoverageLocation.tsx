import Image from "next/image";
import { ButtonLink } from "@/components/ui/Button";
import { Headline } from "@/components/ui/Headline";
import { SectionRail } from "@/components/ui/SectionRail";
import { resolveLink } from "@/lib/text";
import type { Block, Link, SiteContext } from "@/lib/types";
import { DISPLAY_L, Section } from "./shared";

interface Data {
  rail?: string;
  headline?: string;
  coverage_eyebrow?: string;
  coverage_title?: string;
  coverage_body?: string;
  coverage_items?: string[];
  coverage_cta?: Link;
  location_eyebrow?: string;
  location_title?: string;
  location_body?: string;
}

/** Figma "07 · Coverage & location": an insurance card beside the clinic card (details from Clinic settings). */
export function CoverageLocation({ block, site }: { block: Block<Data>; site: SiteContext }) {
  const { data } = block;
  const { clinic } = site.settings;
  const details = [
    {
      label: "Visit",
      value: (
        <>
          {clinic.address.street}
          <br />
          {clinic.address.locality}, {clinic.address.region} {clinic.address.postal_code}
        </>
      ),
    },
    { label: "Call", value: <a href={clinic.phone.href} className="hover:underline">{clinic.phone.display}</a> },
    { label: "Email", value: <a href={`mailto:${clinic.email}`} className="break-all hover:underline">{clinic.email}</a> },
  ];

  return (
    <Section id={block.anchor} className="flex flex-col gap-14">
      <div className="flex flex-col gap-5">
        <SectionRail label={data.rail} />
        <Headline text={data.headline} className={`text-ink ${DISPLAY_L}`} />
      </div>
      <div className="grid gap-6 lg:grid-cols-2">
        <div className="flex flex-col items-start gap-4 rounded-sheet bg-paper p-8 md:p-10">
          {data.coverage_eyebrow && <p className="font-display text-eyebrow font-bold text-royal uppercase">{data.coverage_eyebrow}</p>}
          {data.coverage_title && <h3 className="font-serif text-heading-m text-ink">{data.coverage_title}</h3>}
          {data.coverage_body && <p className="text-body-m text-ink-soft">{data.coverage_body}</p>}
          {data.coverage_items && data.coverage_items.length > 0 && (
            <ul className="flex flex-col gap-4">
              {data.coverage_items.map((item) => (
                <li key={item} className="flex items-center gap-3 text-body-m text-ink">
                  <Image src="/icons/bullet.svg" alt="" width={8} height={8} className="shrink-0" />
                  {item}
                </li>
              ))}
            </ul>
          )}
          <ButtonLink link={resolveLink(data.coverage_cta, site.settings)} variant="secondary" className="mt-2" />
        </div>
        <div className="flex flex-col gap-4 rounded-sheet bg-navy p-8 text-white md:p-10">
          {data.location_eyebrow && <p className="font-display text-eyebrow font-bold text-sky uppercase">{data.location_eyebrow}</p>}
          {data.location_title && <h3 className="font-serif text-heading-m">{data.location_title}</h3>}
          {data.location_body && <p className="text-body-m">{data.location_body}</p>}
          <dl className="flex flex-col">
            {details.map((detail) => (
              <div key={detail.label} className="flex gap-6 border-t border-white py-4">
                <dt className="w-20 shrink-0 pt-[3px] font-display text-eyebrow font-bold text-sky uppercase">{detail.label}</dt>
                <dd className="min-w-0 flex-1 text-title font-bold">{detail.value}</dd>
              </div>
            ))}
          </dl>
        </div>
      </div>
    </Section>
  );
}
