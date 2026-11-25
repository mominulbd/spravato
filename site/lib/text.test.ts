import { describe, expect, it } from "vitest";
import { accentSegments, fillHref, inlineMarkdown, paragraphs, resolveLink } from "./text";
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
