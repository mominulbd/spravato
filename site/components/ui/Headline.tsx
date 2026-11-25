import type { ElementType } from "react";
import { accentSegments } from "@/lib/text";

/**
 * A serif headline with its *accented* words in royal (or sky on dark
 * panels). The Spravato design uses colour, never italics, for the accent.
 */
export function Headline({
  text,
  as: Tag = "h2",
  accent = "royal",
  className = "",
}: {
  text: string | null | undefined;
  as?: ElementType;
  accent?: "royal" | "sky";
  className?: string;
}) {
  if (!text) return null;

  return (
    <Tag className={`font-serif font-normal text-balance ${className}`}>
      {accentSegments(text).map((segment, i) =>
        segment.accent ? (
          <span key={i} className={accent === "royal" ? "text-royal" : "text-sky"}>
            {segment.text}
          </span>
        ) : (
          segment.text
        ),
      )}
    </Tag>
  );
}
