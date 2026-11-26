import { describe, expect, it } from "vitest";
import { accentSegments, fillHref, inlineMarkdown, isPdf, linkProps, paragraphs, resolveLink } from "./text";
import type { Settings } from "./types";

const settings = {
  clinic: { phone: { display: "253-234-4199", href: "tel:+12532344199" }, email: "contact@uptms.com" },
  hours: { long: "Mon–Fri 8–5" },
} as Settings;

describe("text helpers", () => {
  it("splits accented words out of a headline", () => {
    expect(accentSegments("Is Spravato *right for you?*")).toEqual([
      { text: "Is Spravato ", accent: false },
      { text: "right for you?", accent: true },
    ]);
  });

  it("fills clinic tokens in labels and links", () => {
    expect(resolveLink({ label: "Call {phone}", href: "tel:clinic" }, settings)).toEqual({ label: "Call 253-234-4199", href: "tel:+12532344199" });
    expect(fillHref("mailto:clinic", settings)).toBe("mailto:contact@uptms.com");
    expect(resolveLink({ label: "Read", href: "" }, settings)).toBeNull();
  });

  it("parses bold and links without passing HTML through", () => {
    expect(inlineMarkdown("See **this** and [that](/x) <b>")).toEqual([
      { kind: "text", text: "See " },
      { kind: "strong", text: "this" },
      { kind: "text", text: " and " },
      { kind: "link", text: "that", href: "/x" },
      { kind: "text", text: " <b>" },
    ]);
  });

  it("splits paragraphs on blank lines", () => {
    expect(paragraphs("One.\n\nTwo.\n")).toEqual(["One.", "Two."]);
  });
});

describe("linkProps", () => {
  it("opens official PDFs in a new tab as application/pdf", () => {
    for (const href of ["/storage/media/a/1-spravato-medication-guide.pdf", "/storage/x.PDF?v=2", "https://cdn.example/x.pdf#page=2"]) {
      expect(isPdf(href)).toBe(true);
      expect(linkProps(href)).toEqual({ type: "application/pdf", target: "_blank", rel: "noopener" });
    }
  });

  it("keeps other links as before", () => {
    expect(isPdf("/#consultation")).toBe(false);
    expect(linkProps("https://example.com/pdf-guide")).toEqual({ target: "_blank", rel: "noopener noreferrer" });
    expect(linkProps("/#faq")).toEqual({});
  });
});
