import { Fragment } from "react";
import { inlineMarkdown, isExternal, paragraphs } from "@/lib/text";

/** Inline Markdown (bold, links) rendered as React elements: no HTML from the API is ever injected. */
export function InlineText({ text }: { text: string }) {
  return (
    <>
      {inlineMarkdown(text).map((part, i) => {
        if (part.kind === "strong") return <strong key={i}>{part.text}</strong>;
        if (part.kind === "link") {
          return (
            <a key={i} href={part.href} className="font-semibold text-royal underline underline-offset-4" {...(isExternal(part.href) ? { target: "_blank", rel: "noopener noreferrer" } : {})}>
              {part.text}
            </a>
          );
        }

        return <Fragment key={i}>{part.text}</Fragment>;
      })}
    </>
  );
}

/** Blank-line separated paragraphs. */
export function Paragraphs({ text, className = "" }: { text: string | null | undefined; className?: string }) {
  return (
    <>
      {paragraphs(text).map((paragraph, i) => (
        <p key={i} className={className}>
          <InlineText text={paragraph} />
        </p>
      ))}
    </>
  );
}
