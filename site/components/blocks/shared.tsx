import type { ReactNode } from "react";

/** Full-bleed section with the page's horizontal padding (Figma: 120px at 1440). */
export function Section({ id, className = "", children }: { id?: string | null; className?: string; children: ReactNode }) {
  return (
    <section id={id ?? undefined} className={`mx-auto w-full max-w-[1440px] scroll-mt-6 px-5 py-20 md:px-10 xl:px-[120px] xl:py-28 ${className}`}>
      {children}
    </section>
  );
}

/** A rounded colour panel inset 24px from the page edge (design Version C). */
export function PanelSection({ id, tone, className = "", children }: { id?: string | null; tone: "navy" | "sky-soft" | "royal"; className?: string; children: ReactNode }) {
  const bg = { navy: "bg-navy text-white", "sky-soft": "bg-sky-soft", royal: "bg-royal text-white" }[tone];

  return (
    <section id={id ?? undefined} className="mx-auto w-full max-w-[1440px] scroll-mt-6 px-3 md:px-6">
      <div className={`rounded-panel p-6 py-14 md:p-12 xl:p-24 ${bg} ${className}`}>{children}</div>
    </section>
  );
}

/** Title on the left, intro on the right, bottom-aligned (sections 03, 05, 06). */
export function SplitHead({ title, intro }: { title: ReactNode; intro?: string | null }) {
  return (
    <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between lg:gap-20">
      <div className="flex min-w-0 flex-1 flex-col gap-5 lg:max-w-[700px]">{title}</div>
      {intro && <p className="text-body-m text-ink-soft lg:w-[420px] lg:shrink-0">{intro}</p>}
    </div>
  );
}

export const DISPLAY_L = "text-[40px] leading-[1.08] tracking-[-0.02em] md:text-display-l";
