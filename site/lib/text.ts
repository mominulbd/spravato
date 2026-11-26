import type { Link, Settings } from "./types";

/**
 * Placeholders editors may use in labels and links, so the phone number,
 * hours and email live in Clinic settings only: {phone}, {hours}, {email},
 * tel:clinic and mailto:clinic.
 */
export function fillTokens(text: string, settings: Settings): string {
  return text
    .replaceAll("{phone}", settings.clinic.phone.display)
    .replaceAll("{hours}", settings.hours.long)
    .replaceAll("{email}", settings.clinic.email);
}

export function fillHref(href: string, settings: Settings): string {
  if (href === "tel:clinic") return settings.clinic.phone.href;
  if (href === "mailto:clinic") return `mailto:${settings.clinic.email}`;

  return href;
}

/** A link worth rendering: both parts filled in, tokens resolved. */
export function resolveLink(link: Link | null | undefined, settings: Settings): Link | null {
  if (!link || link.label.trim() === "" || link.href.trim() === "") {
    return null;
  }

  return { label: fillTokens(link.label, settings), href: fillHref(link.href, settings) };
}

export function isExternal(href: string): boolean {
  return /^https?:\/\//.test(href);
}

/** `/storage/media/…/x-medication-guide.pdf`, with or without `?…`/`#…`. */
export function isPdf(href: string): boolean {
  return /\.pdf(?:[?#].*)?$/i.test(href);
}

/**
 * Anchor attributes for a CMS link. Official documents are PDFs served by
 * Laravel from /storage (inline, application/pdf): they open in a new tab.
 * Other external links also get a new tab; site links stay in place.
 */
export function linkProps(href: string): { type?: string; target?: string; rel?: string } {
  if (isPdf(href)) {
    return { type: "application/pdf", target: "_blank", rel: "noopener" };
  }

  return isExternal(href) ? { target: "_blank", rel: "noopener noreferrer" } : {};
}

export interface Segment {
  text: string;
  accent: boolean;
}

/** "Is Spravato *right for you?*" → plain and accented runs (the admin's headline syntax). */
export function accentSegments(text: string): Segment[] {
  return text
    .split(/(\*[^*]+\*)/)
    .filter((part) => part !== "")
    .map((part) => (part.startsWith("*") && part.endsWith("*") && part.length > 2 ? { text: part.slice(1, -1), accent: true } : { text: part, accent: false }));
}

export type Inline = { kind: "text"; text: string } | { kind: "strong"; text: string } | { kind: "link"; text: string; href: string };

/** The admin's small Markdown dialect, inline part: **bold** and [text](href). No HTML is ever passed through. */
export function inlineMarkdown(text: string): Inline[] {
  const out: Inline[] = [];
  const pattern = /\*\*([^*]+)\*\*|\[([^\]]+)\]\(([^)\s]+)\)/g;
  let last = 0;

  for (const match of text.matchAll(pattern)) {
    if (match.index > last) out.push({ kind: "text", text: text.slice(last, match.index) });
    if (match[1] !== undefined) out.push({ kind: "strong", text: match[1] });
    else out.push({ kind: "link", text: match[2] ?? "", href: match[3] ?? "" });
    last = match.index + match[0].length;
  }

  if (last < text.length) out.push({ kind: "text", text: text.slice(last) });

  return out;
}

/** Blank-line separated paragraphs. */
export function paragraphs(text: string | null | undefined): string[] {
  return (text ?? "")
    .split(/\n\s*\n/)
    .map((paragraph) => paragraph.trim())
    .filter((paragraph) => paragraph !== "");
}
