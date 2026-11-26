/**
 * The consultation dialog's form logic, shared by the client island and the
 * server action. Laravel is the one validator
 * (Uptms\Consultations\Http\Requests\SubmitConsultationRequestRequest); these
 * rules mirror it so people see a mistake before anything is sent, in the
 * same words.
 */

export interface Option {
  value: string;
  label: string;
}

/** `GET /public/consultation-form`. */
export interface ConsultationForm {
  accepting: boolean;
  topics: Option[];
  best_times: Option[];
  contact_methods: Option[];
  patient_statuses: Option[];
}

export interface ConsultationValues {
  first_name: string;
  last_name: string;
  phone: string;
  email: string;
  contact_method: string;
  patient_status: string;
  topic: string;
  best_time: string;
  insurance: string;
  message: string;
  emergency_acknowledged: boolean;
}

/** What the browser adds: where the request came from. Never anything typed. */
export interface ConsultationContext {
  cta: string | null;
  source_page: string | null;
  referrer: string | null;
  utm: Partial<Record<"source" | "medium" | "campaign", string>>;
  turnstile_token: string | null;
  /** Honeypot. */
  website: string;
}

export type FieldErrors = Partial<Record<keyof ConsultationValues | "turnstile_token" | "form", string>>;

export type SubmitResult =
  | { status: "success"; reference: string | null }
  | { status: "invalid"; errors: FieldErrors }
  | { status: "rate_limited" }
  | { status: "error" };

export const DEFAULT_TOPIC = "spravato";
export const DEFAULT_BEST_TIME = "no_preference";

export function emptyValues(topic: string = DEFAULT_TOPIC): ConsultationValues {
  return {
    first_name: "",
    last_name: "",
    phone: "",
    email: "",
    contact_method: "",
    patient_status: "",
    topic,
    best_time: DEFAULT_BEST_TIME,
    insurance: "",
    message: "",
    emergency_acknowledged: false,
  };
}

export const MESSAGES = {
  first_name: "Enter your first name so the team knows who to ask for.",
  first_name_max: "Keep your first name under 60 characters.",
  last_name: "Enter your last name.",
  last_name_max: "Keep your last name under 60 characters.",
  phone_required: "Enter a phone number the clinic can call.",
  phone: "Enter a 10-digit U.S. phone number, e.g. (253) 555-0142.",
  email_required: "Enter an email address the clinic can reply to.",
  email: "Enter a valid email address, e.g. name@example.com.",
  insurance_max: "Keep the insurance provider under 120 characters.",
  message_max: "Keep your note under 1,000 characters.",
  emergency: "Please confirm you understand this form is not an emergency service.",
} as const;

export function isUsPhone(value: string): boolean {
  if (!/^[0-9 ()+.-]+$/.test(value)) return false;

  const digits = value.replace(/\D/g, "");

  return digits.length === 10 || (digits.length === 11 && digits.startsWith("1"));
}

/** The first problem with each field, or nothing when the form can be sent. */
export function validate(values: ConsultationValues): FieldErrors {
  const errors: FieldErrors = {};
  const first = values.first_name.trim();
  const last = values.last_name.trim();
  const phone = values.phone.trim();
  const email = values.email.trim();

  if (first === "") errors.first_name = MESSAGES.first_name;
  else if (first.length > 60) errors.first_name = MESSAGES.first_name_max;

  if (last === "") errors.last_name = MESSAGES.last_name;
  else if (last.length > 60) errors.last_name = MESSAGES.last_name_max;

  if (phone === "") errors.phone = MESSAGES.phone_required;
  else if (phone.length > 30 || !isUsPhone(phone)) errors.phone = MESSAGES.phone;

  if (email === "") errors.email = MESSAGES.email_required;
  else if (email.length > 190 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.email = MESSAGES.email;

  if (values.insurance.trim().length > 120) errors.insurance = MESSAGES.insurance_max;
  if (values.message.trim().length > 1000) errors.message = MESSAGES.message_max;
  if (!values.emergency_acknowledged) errors.emergency_acknowledged = MESSAGES.emergency;

  return errors;
}

/**
 * Consultation buttons link to `#consultation`, optionally with a topic:
 * `#consultation?topic=insurance`. Returns null for any other hash, and the
 * topic only when the form offers it.
 */
export function parseConsultationHash(hash: string, topics: readonly Option[]): { topic: string | null } | null {
  const match = /^#consultation(?:\?(.*))?$/.exec(hash);

  if (!match) return null;

  const topic = new URLSearchParams(match[1] ?? "").get("topic");

  return { topic: topic !== null && topics.some((option) => option.value === topic) ? topic : null };
}

/** The button's own words, as Laravel accepts them (letters and punctuation, ≤ 80). */
export function ctaLabel(text: string | null | undefined): string | null {
  const label = (text ?? "").replace(/\s+/g, " ").trim();

  if (label === "" || label.length > 80 || !/^[\p{L}\p{N}\s&·,.'’?!()\-–—→:®]+$/u.test(label)) {
    return null;
  }

  return label;
}

/** utm_source / utm_medium / utm_campaign from the page address. */
export function utmFrom(search: string): ConsultationContext["utm"] {
  const params = new URLSearchParams(search);
  const utm: ConsultationContext["utm"] = {};

  for (const key of ["source", "medium", "campaign"] as const) {
    const value = params.get(`utm_${key}`)?.trim().slice(0, 100);
    if (value) utm[key] = value;
  }

  return utm;
}

/** The body Laravel expects: trimmed, blanks dropped. */
export function toPayload(values: ConsultationValues, context: ConsultationContext): Record<string, unknown> {
  const text = (value: string) => {
    const trimmed = value.trim();
    return trimmed === "" ? null : trimmed;
  };

  return {
    first_name: values.first_name.trim(),
    last_name: values.last_name.trim(),
    phone: values.phone.trim(),
    email: values.email.trim(),
    contact_method: text(values.contact_method),
    patient_status: text(values.patient_status),
    topic: text(values.topic),
    best_time: text(values.best_time),
    insurance: text(values.insurance),
    message: text(values.message),
    emergency_acknowledged: values.emergency_acknowledged,
    cta: context.cta,
    source_page: context.source_page,
    referrer: context.referrer,
    utm: Object.keys(context.utm).length > 0 ? context.utm : null,
    turnstile_token: context.turnstile_token,
    website: context.website,
  };
}

const FIELDS = new Set<string>([
  "first_name",
  "last_name",
  "phone",
  "email",
  "contact_method",
  "patient_status",
  "topic",
  "best_time",
  "insurance",
  "message",
  "emergency_acknowledged",
  "turnstile_token",
]);

/** Laravel's `errors` (field → messages) as one message per field; anything else is a form-level error. */
export function fieldErrors(errors: unknown): FieldErrors {
  const out: FieldErrors = {};

  if (errors === null || typeof errors !== "object") return out;

  for (const [field, messages] of Object.entries(errors as Record<string, unknown>)) {
    const message = Array.isArray(messages) && typeof messages[0] === "string" ? messages[0] : null;
    if (message === null) continue;

    const key = (FIELDS.has(field) ? field : "form") as keyof FieldErrors;
    out[key] ??= message;
  }

  return out;
}
