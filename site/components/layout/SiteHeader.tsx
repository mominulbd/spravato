import Image from "next/image";
import { ButtonLink } from "@/components/ui/Button";
import type { Settings } from "@/lib/types";

/**
 * Figma "Landing header": a navy top bar (service area, phone) and the logo
 * beside one consultation button. Spravato is a standalone page, so there is
 * no site navigation.
 */
export function SiteHeader({ settings }: { settings: Settings }) {
  const { clinic } = settings;

  return (
    <header className="bg-white">
      <div className="bg-navy">
        <div className="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-5 py-2.5 md:px-10 xl:px-[120px]">
          <p className="text-body-s text-white">{clinic.service_area_label}</p>
          <a href={clinic.phone.href} className="font-display text-[14px] leading-[1.3] font-bold whitespace-nowrap text-sky hover:underline">
            Call {clinic.phone.display}
          </a>
        </div>
      </div>
      <div className="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-5 py-4 md:px-10 xl:px-[120px]">
        <a href="#top" aria-label={`${clinic.name} — back to top`} className="shrink-0">
          <Image src="/brand/uptms-logo.jpg" alt={clinic.name} width={196} height={56} priority className="h-11 w-auto md:h-14" />
        </a>
        <ButtonLink link={{ label: "Request a consultation", href: "#consultation" }} className="px-5 py-3.5 text-[15px] md:px-7 md:py-[17px] md:text-base" />
      </div>
    </header>
  );
}
