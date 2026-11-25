/** The Figma "Section rail": a short rule and an uppercase label above a section heading. */
export function SectionRail({ label, tone = "light" }: { label: string | null | undefined; tone?: "light" | "dark" }) {
  if (!label) return null;

  return (
    <p className="flex items-center gap-3">
      <span aria-hidden="true" className={`h-[1.5px] w-8 shrink-0 ${tone === "light" ? "bg-royal" : "bg-sky"}`} />
      <span className={`font-display text-eyebrow font-bold uppercase ${tone === "light" ? "text-ink-soft" : "text-white"}`}>{label}</span>
    </p>
  );
}
