/*
 * The Spravato landing page. Phase 0 shell: Phase 1 replaces this with the
 * published blocks read from Laravel (docs/DEVELOPMENT-PLAN.md).
 */
export default function HomePage() {
  return (
    <main id="main" className="mx-auto flex min-h-dvh max-w-[1200px] flex-col justify-center gap-6 px-6 py-24">
      <p className="font-display text-sm font-bold tracking-[0.12em] text-royal uppercase">Spravato® treatment · University Place &amp; Tacoma</p>
      <h1 className="font-serif text-5xl leading-tight text-navy md:text-6xl">
        Could Spravato® be a <span className="text-royal">next step</span> for you?
      </h1>
      <p className="max-w-2xl text-lg text-ink-soft">
        The page is being built from the CMS. Content arrives in Phase 1.
      </p>
    </main>
  );
}
