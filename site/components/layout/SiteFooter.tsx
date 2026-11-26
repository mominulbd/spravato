import Image from "next/image";
import type { MenuItem, Settings } from "@/lib/types";
import { isPdf, linkProps } from "@/lib/text";

/**
 * Figma "Landing footer": brand, visit, contact and the official SPRAVATO®
 * documents (the `documents` menu, empty until the PDFs are uploaded), then
 * the copyright and trademark line.
 */
export function SiteFooter({ settings, documents }: { settings: Settings; documents: MenuItem[] }) {
  const { clinic } = settings;

  return (
    <footer className="bg-navy text-white">
      <div className="mx-auto flex max-w-[1440px] flex-col gap-12 px-5 pt-[72px] pb-10 md:px-10 xl:px-[120px]">
        <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-16">
          <div className="flex flex-col gap-4">
            <div className="self-start rounded-[12px] bg-white px-3 py-2">
              <Image src="/brand/uptms-logo-light.png" alt={clinic.name} width={186} height={53} className="h-[53px] w-[186px] object-contain" />
            </div>
            {clinic.tagline && <p className="text-body-s opacity-86">{clinic.tagline}</p>}
          </div>

          <div className="flex flex-col gap-3">
            <h2 className="text-title font-bold">Visit</h2>
            <address className="text-body-s not-italic opacity-86">
              {clinic.address.street}
              <br />
              {clinic.address.locality}, {clinic.address.region} {clinic.address.postal_code}
            </address>
          </div>

          <div className="flex flex-col gap-3">
            <h2 className="text-title font-bold">Contact</h2>
            <p className="flex flex-col text-body-s opacity-86">
              <a href={clinic.phone.href} className="inline-flex min-h-6 items-center self-start hover:underline">
                {clinic.phone.display}
              </a>
              <a href={`mailto:${clinic.email}`} className="inline-flex min-h-6 items-center self-start hover:underline">
                {clinic.email}
              </a>
            </p>
          </div>

          {documents.length > 0 && (
            <div className="flex flex-col gap-2.5">
              <h2 className="text-title font-bold">Official documents</h2>
              <ul className="flex flex-col gap-2.5 text-body-s opacity-86">
                {documents.map((item) => (
                  <li key={item.href}>
                    <a href={item.href} className="inline-flex min-h-6 items-center hover:underline" {...linkProps(item.href)}>
                      {item.label}
                      {isPdf(item.href) && <span className="sr-only"> (PDF, opens in a new tab)</span>}
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>

        <div className="flex flex-col gap-2 border-t border-white pt-6 text-caption lg:flex-row lg:justify-between lg:gap-8">
          <p className="opacity-72">{settings.legal.copyright}</p>
          <p className="opacity-72 lg:text-right">SPRAVATO® is a registered trademark of Johnson &amp; Johnson and its affiliated companies. This page is not a crisis service.</p>
        </div>
      </div>
    </footer>
  );
}
