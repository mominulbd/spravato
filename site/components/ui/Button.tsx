import type { Link } from "@/lib/types";
import { isExternal } from "@/lib/text";

/** The Figma "Button" component: Primary, Secondary, Light and OutlineLight pills. */
export type ButtonVariant = "primary" | "secondary" | "light" | "outline-light";

const VARIANTS: Record<ButtonVariant, string> = {
  primary: "bg-royal text-white hover:bg-royal-deep",
  secondary: "border-[1.5px] border-ink text-ink hover:bg-ink hover:text-white",
  light: "bg-white text-ink hover:bg-sky",
  "outline-light": "border-[1.5px] border-white text-white hover:bg-white hover:text-ink",
};

export function ButtonLink({ link, variant = "primary", className = "" }: { link: Link | null; variant?: ButtonVariant; className?: string }) {
  if (!link) return null;

  const external = isExternal(link.href);

  return (
    <a
      href={link.href}
      className={`inline-flex items-center justify-center rounded-full px-7 py-[17px] text-center font-display text-base leading-[1.2] font-bold transition-colors ${VARIANTS[variant]} ${className}`}
      {...(external ? { target: "_blank", rel: "noopener noreferrer" } : {})}
    >
      {link.label}
    </a>
  );
}

/** "Read the Medication Guide →": the Figma "Link" text style. */
export function TextLink({ link, tone = "royal", className = "" }: { link: Link | null; tone?: "royal" | "white"; className?: string }) {
  if (!link) return null;

  const external = isExternal(link.href);

  return (
    <a
      href={link.href}
      className={`font-display text-base leading-[1.3] font-bold underline-offset-4 hover:underline ${tone === "royal" ? "text-royal" : "text-white"} ${className}`}
      {...(external ? { target: "_blank", rel: "noopener noreferrer" } : {})}
    >
      {link.label}
    </a>
  );
}
