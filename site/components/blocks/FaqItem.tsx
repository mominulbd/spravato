"use client";

import { type ReactNode, useId, useState } from "react";

/**
 * One FAQ disclosure (WAI-ARIA disclosure pattern): the question is a
 * button in a heading with `aria-expanded`, and the answer a region named
 * by it. The answer stays in the HTML while closed, for search engines.
 */
export function FaqItem({ question, defaultOpen = false, children }: { question: string; defaultOpen?: boolean; children: ReactNode }) {
  const [open, setOpen] = useState(defaultOpen);
  const id = useId();
  const buttonId = `${id}-question`;
  const panelId = `${id}-answer`;

  return (
    <div className="border-b border-line py-[22px]">
      <h3 className="m-0">
        <button
          type="button"
          id={buttonId}
          aria-expanded={open}
          aria-controls={panelId}
          onClick={() => setOpen((value) => !value)}
          className="flex w-full items-center gap-6 rounded-sm text-left"
        >
          <span className="min-w-0 flex-1 text-title font-bold text-ink">{question}</span>
          <span aria-hidden="true" className={`flex size-9 shrink-0 items-center justify-center rounded-full text-title font-bold ${open ? "bg-royal text-white" : "bg-sky-soft text-royal"}`}>
            {open ? "−" : "+"}
          </span>
        </button>
      </h3>
      <div id={panelId} role="region" aria-labelledby={buttonId} hidden={!open} className="mt-3 flex flex-col gap-3">
        {children}
      </div>
    </div>
  );
}
