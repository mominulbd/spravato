import { describe, expect, it } from "vitest";
import { ctaLabel, emptyValues, fieldErrors, MESSAGES, parseConsultationHash, toPayload, utmFrom, validate } from "./consultation";

const topics = [
  { value: "spravato", label: "Spravato consultation" },
  { value: "insurance", label: "Insurance & cost" },
];

const filled = {
  ...emptyValues(),
  first_name: " Daniel ",
  last_name: "Reyes",
  phone: "(253) 555-0198",
  email: "d.reyes@example.com",
  emergency_acknowledged: true,
};

describe("validate", () => {
  it("asks for the required fields in the patient's words", () => {
    expect(validate(emptyValues())).toEqual({
      first_name: MESSAGES.first_name,
      last_name: MESSAGES.last_name,
      phone: MESSAGES.phone_required,
      email: MESSAGES.email_required,
      emergency_acknowledged: MESSAGES.emergency,
    });
  });

  it("accepts the required fields alone", () => {
    expect(validate(filled)).toEqual({});
  });

  it("checks the phone, email and lengths like Laravel", () => {
    expect(validate({ ...filled, phone: "555-01" }).phone).toBe(MESSAGES.phone);
    expect(validate({ ...filled, phone: "+1 253 555 0198" }).phone).toBeUndefined();
    expect(validate({ ...filled, phone: "2 253 555 0198" }).phone).toBe(MESSAGES.phone);
    expect(validate({ ...filled, phone: "253-555-019x" }).phone).toBe(MESSAGES.phone);
    expect(validate({ ...filled, email: "not-an-email" }).email).toBe(MESSAGES.email);
    expect(validate({ ...filled, insurance: "a".repeat(121) }).insurance).toBe(MESSAGES.insurance_max);
    expect(validate({ ...filled, message: "a".repeat(1001) }).message).toBe(MESSAGES.message_max);
  });
});

describe("parseConsultationHash", () => {
  it("opens on #consultation with a known topic only", () => {
    expect(parseConsultationHash("#consultation", topics)).toEqual({ topic: null });
    expect(parseConsultationHash("#consultation?topic=insurance", topics)).toEqual({ topic: "insurance" });
    expect(parseConsultationHash("#consultation?topic=surgery", topics)).toEqual({ topic: null });
    expect(parseConsultationHash("#consultation-form", topics)).toBeNull();
    expect(parseConsultationHash("#faq", topics)).toBeNull();
  });
});

describe("ctaLabel", () => {
  it("keeps the button's own words and drops anything else", () => {
    expect(ctaLabel("  Ask about coverage\n & cost ")).toBe("Ask about coverage & cost");
    expect(ctaLabel("Start a conversation →")).toBe("Start a conversation →");
    expect(ctaLabel("<img src=x>")).toBeNull();
    expect(ctaLabel("a".repeat(81))).toBeNull();
    expect(ctaLabel("")).toBeNull();
  });
});

describe("payload", () => {
  it("trims, drops blanks and adds only where the request came from", () => {
    const payload = toPayload(
      { ...filled, insurance: "  ", message: " Mornings are easiest. " },
      { cta: "Request a consultation", source_page: "/", referrer: null, utm: utmFrom("?utm_source=newsletter&utm_medium=email&x=1"), turnstile_token: null, website: "" },
    );

    expect(payload).toMatchObject({
      first_name: "Daniel",
      insurance: null,
      contact_method: null,
      topic: "spravato",
      best_time: "no_preference",
      message: "Mornings are easiest.",
      emergency_acknowledged: true,
      cta: "Request a consultation",
      source_page: "/",
      utm: { source: "newsletter", medium: "email" },
    });
  });

  it("maps Laravel's errors to one message per field", () => {
    expect(fieldErrors({ phone: ["Enter a 10-digit U.S. phone number, e.g. (253) 555-0142."], cta: ["Something went wrong."], email: "bad" })).toEqual({
      phone: "Enter a 10-digit U.S. phone number, e.g. (253) 555-0142.",
      form: "Something went wrong.",
    });
    expect(fieldErrors(null)).toEqual({});
  });
});
