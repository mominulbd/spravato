"use client";

import Image from "next/image";
import { useCallback, useEffect, useId, useMemo, useRef, useState, useTransition, type ReactNode } from "react";
import { submitConsultation } from "@/app/actions/consultation";
import {
  type ConsultationForm,
  type ConsultationValues,
  ctaLabel,
  DEFAULT_TOPIC,
  emptyValues,
  type FieldErrors,
  parseConsultationHash,
  type SubmitResult,
  utmFrom,
  validate,
} from "@/lib/consultation";
import { Turnstile } from "./Turnstile";

/**
 * Figma "Consultation dialog". Every link to `#consultation` on the page
 * opens it, with the topic of `#consultation?topic=…` chosen and the
 * button's label sent along as the request's source. A native modal
 * <dialog>: the page behind is inert, Esc closes it, and focus returns to
 * the button that opened it.
 */
export function ConsultationDialog({ form, phone }: { form: ConsultationForm | null; phone: { display: string; href: string } }) {
  const dialogRef = useRef<HTMLDialogElement>(null);
  const formRef = useRef<HTMLFormElement>(null);
  const opener = useRef<HTMLElement | null>(null);
  const cta = useRef<string | null>(null);
  const [opened, setOpened] = useState(false);
  const [values, setValues] = useState<ConsultationValues>(() => emptyValues());
  const [errors, setErrors] = useState<FieldErrors>({});
  const [outcome, setOutcome] = useState<SubmitResult | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [turnstileKey, setTurnstileKey] = useState(0);
  const [pending, startTransition] = useTransition();
  const honeypot = useRef<HTMLInputElement>(null);
  const titleId = useId();
  const topics = useMemo(() => form?.topics ?? [], [form]);
  const finished = useRef(false);
  const accepting = form?.accepting === true;
  const siteKey = process.env.NEXT_PUBLIC_TURNSTILE_SITE_KEY ?? "";

  const open = useCallback(
    (topic: string | null, label: string | null, from: HTMLElement | null) => {
      const dialog = dialogRef.current;
      if (!dialog) return;

      opener.current = from;
      cta.current = label;

      // A finished request starts over; one in progress keeps what was typed.
      if (finished.current) {
        finished.current = false;
        setValues(emptyValues(topic ?? DEFAULT_TOPIC));
        setOutcome(null);
        setErrors({});
      } else if (topic !== null) {
        setValues((current) => ({ ...current, topic }));
      }

      setOpened(true);
      if (!dialog.open) {
        // The page behind stays put while the dialog scrolls.
        document.documentElement.style.overflow = "hidden";
        dialog.showModal();
        dialog.scrollTop = 0;
      }
    },
    [],
  );

  // Any link to #consultation opens the dialog instead of jumping.
  useEffect(() => {
    const onClick = (event: MouseEvent) => {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

      const link = (event.target as Element | null)?.closest?.("a[href^='#consultation']");
      if (!(link instanceof HTMLAnchorElement)) return;

      const target = parseConsultationHash(link.getAttribute("href") ?? "", topics);
      if (!target) return;

      event.preventDefault();
      open(target.topic, ctaLabel(link.textContent), link);
    };

    document.addEventListener("click", onClick);

    return () => document.removeEventListener("click", onClick);
  }, [open, topics]);

  // A shared address such as /#consultation?topic=insurance opens it too.
  useEffect(() => {
    const fromHash = () => {
      const target = parseConsultationHash(window.location.hash, topics);
      if (!target) return;

      history.replaceState(null, "", window.location.pathname + window.location.search);
      open(target.topic, null, null);
    };

    fromHash();
    window.addEventListener("hashchange", fromHash);

    return () => window.removeEventListener("hashchange", fromHash);
  }, [open, topics]);

  const close = () => dialogRef.current?.close();

  const onClose = () => {
    document.documentElement.style.overflow = "";
    const back = opener.current;
    opener.current = null;
    if (back?.isConnected) back.focus();
  };

  const set = <K extends keyof ConsultationValues>(key: K, value: ConsultationValues[K]) => {
    setValues((current) => ({ ...current, [key]: value }));
    if (errors[key]) {
      setErrors((current) => {
        const next = { ...current };
        delete next[key];
        return next;
      });
    }
  };

  const submit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (pending) return;

    const problems = validate(values);
    setErrors(problems);
    setOutcome(null);

    if (Object.keys(problems).length > 0) {
      // After the render that marks the fields invalid.
      requestAnimationFrame(() => focusFirstError(formRef.current));
      return;
    }

    startTransition(async () => {
      const result = await submitConsultation(values, {
        cta: cta.current,
        source_page: window.location.pathname,
        referrer: document.referrer || null,
        utm: utmFrom(window.location.search),
        turnstile_token: token,
        website: honeypot.current?.value ?? "",
      });

      setOutcome(result);

      if (result.status === "invalid") {
        setErrors(result.errors);
        // A Turnstile token is single-use: ask for a fresh one.
        setToken(null);
        setTurnstileKey((key) => key + 1);
        requestAnimationFrame(() => focusFirstError(formRef.current));
      } else if (result.status === "success") {
        finished.current = true;
        requestAnimationFrame(() => dialogRef.current?.querySelector<HTMLElement>("[data-autofocus]")?.focus());
      } else {
        setToken(null);
        setTurnstileKey((key) => key + 1);
      }
    });
  };

  const success = outcome?.status === "success" ? outcome : null;

  return (
    <dialog
      ref={dialogRef}
      id="consultation"
      aria-labelledby={titleId}
      onClose={onClose}
      onClick={(event) => {
        // A click on the scrim (outside the panel) closes it.
        if (event.target === dialogRef.current) close();
      }}
      className="fixed inset-0 m-0 h-dvh max-h-none w-full max-w-none overflow-hidden bg-transparent p-0 backdrop:bg-navy/95 open:flex open:items-center open:justify-center md:open:p-6"
    >
      {/* Fixed height: the header stays put and only the body scrolls. */}
      <div className="flex h-dvh w-full max-w-[760px] flex-col overflow-hidden bg-white shadow-[0_24px_32px_rgba(13,31,51,0.28)] md:h-[min(860px,calc(100dvh-48px))] md:rounded-panel">
        <div className="flex shrink-0 items-start gap-6 border-b border-line px-5 pt-8 pb-6 md:px-12 md:pt-12 md:pb-7">
          <div className="flex min-w-0 flex-1 flex-col gap-2.5">
            <p className="font-display text-eyebrow font-bold tracking-[1.3px] text-royal uppercase">Spravato® consultation</p>
            <h2 id={titleId} className="font-serif text-[30px] leading-[1.15] tracking-[-0.54px] text-ink md:text-heading-m md:tracking-[-0.54px]">
              {success ? "Thank you. Your request is in." : "Request a SPRAVATO® consultation"}
            </h2>
            {!success && accepting && <p className="text-body-m text-ink-soft">Tell us how to reach you and what you would like to discuss.</p>}
          </div>
          <button
            type="button"
            onClick={close}
            aria-label="Close"
            className="flex size-11 shrink-0 items-center justify-center rounded-full border border-line bg-paper transition-colors hover:bg-sky-soft"
          >
            <Image src="/icons/close.svg" alt="" width={18} height={18} />
          </button>
        </div>

        <div className="min-h-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain px-5 pt-6 pb-8 md:px-12 md:pt-7 md:pb-10">
        {success ? (
          <Confirmation reference={success.reference} phone={phone} onClose={close} />
        ) : accepting && form ? (
          <form ref={formRef} noValidate onSubmit={submit} className="flex flex-col gap-7" aria-busy={pending}>
            <div className="flex flex-col gap-5">
              <Row>
                <TextField id="first_name" label="First name" required autoComplete="given-name" value={values.first_name} error={errors.first_name} onChange={(value) => set("first_name", value)} maxLength={60} />
                <TextField id="last_name" label="Last name" required autoComplete="family-name" value={values.last_name} error={errors.last_name} onChange={(value) => set("last_name", value)} maxLength={60} />
              </Row>
              <Row>
                <TextField id="phone" label="Phone" required type="tel" inputMode="tel" autoComplete="tel" placeholder="(253) 000-0000" value={values.phone} error={errors.phone} onChange={(value) => set("phone", value)} maxLength={30} />
                <TextField id="email" label="Email" required type="email" autoComplete="email" placeholder="name@example.com" value={values.email} error={errors.email} onChange={(value) => set("email", value)} maxLength={190} />
              </Row>
              <Row>
                <SelectField id="contact_method" label="Preferred follow-up" placeholder="Phone, email or either" options={form.contact_methods} value={values.contact_method} error={errors.contact_method} onChange={(value) => set("contact_method", value)} />
                <SelectField id="patient_status" label="Patient status" placeholder="New, existing or not sure" options={form.patient_statuses} value={values.patient_status} error={errors.patient_status} onChange={(value) => set("patient_status", value)} />
              </Row>
              <Row>
                <SelectField id="topic" label="What would you like to discuss?" options={form.topics} value={values.topic} error={errors.topic} onChange={(value) => set("topic", value)} />
                <SelectField id="best_time" label="Best time to reach you" options={form.best_times} value={values.best_time} error={errors.best_time} onChange={(value) => set("best_time", value)} />
              </Row>
              <TextField id="insurance" label="Insurance provider" optional placeholder="For benefits questions only" value={values.insurance} error={errors.insurance} onChange={(value) => set("insurance", value)} maxLength={120} />
              <Field id="message" label="Anything else you want the clinic to know?" optional error={errors.message}>
                {(props) => (
                  <textarea
                    {...props}
                    value={values.message}
                    onChange={(event) => set("message", event.target.value)}
                    maxLength={1000}
                    placeholder="Please do not include diagnoses, medications, crisis details, or other sensitive health information."
                    className={`${boxClass(Boolean(errors.message))} h-[120px] resize-none pt-3.5`}
                  />
                )}
              </Field>
              {/* Honeypot: hidden from people and assistive technology. */}
              <div aria-hidden="true" className="absolute -left-[9999px] h-px w-px overflow-hidden">
                <label>
                  Website
                  <input ref={honeypot} type="text" name="website" tabIndex={-1} autoComplete="off" defaultValue="" />
                </label>
              </div>
            </div>

            <div className="flex flex-col gap-2">
              <label className="flex cursor-pointer items-center gap-3.5">
                <span className="relative flex size-6 shrink-0">
                  <input
                    type="checkbox"
                    id="emergency_acknowledged"
                    checked={values.emergency_acknowledged}
                    onChange={(event) => set("emergency_acknowledged", event.target.checked)}
                    aria-invalid={errors.emergency_acknowledged ? true : undefined}
                    aria-describedby={errors.emergency_acknowledged ? "emergency_acknowledged-error" : undefined}
                    className={`peer size-6 cursor-pointer appearance-none rounded-[6px] border-[1.5px] bg-white checked:border-royal checked:bg-royal ${errors.emergency_acknowledged ? "border-danger" : "border-ink-soft"}`}
                  />
                  <svg aria-hidden="true" viewBox="0 0 24 24" className="pointer-events-none absolute inset-0 hidden size-6 peer-checked:block">
                    <path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke="#fff" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                </span>
                <span className="text-body-m text-ink">I understand this form is not an emergency service.</span>
              </label>
              {errors.emergency_acknowledged && <ErrorText id="emergency_acknowledged-error">{errors.emergency_acknowledged}</ErrorText>}
            </div>

            {siteKey !== "" && opened && (
              <div className="flex flex-col gap-2">
                <Turnstile key={turnstileKey} siteKey={siteKey} onToken={setToken} />
                {errors.turnstile_token && <ErrorText id="turnstile-error">{errors.turnstile_token}</ErrorText>}
              </div>
            )}

            {outcome && outcome.status !== "invalid" && <Problem outcome={outcome} phone={phone} />}
            {outcome?.status === "invalid" && errors.form && <Problem outcome={{ status: "error" }} phone={phone} />}

            <button
              type="submit"
              disabled={pending}
              className="w-full rounded-full bg-royal px-7 py-[17px] text-center font-display text-base leading-[1.2] font-bold text-white transition-colors hover:bg-royal-deep disabled:cursor-wait disabled:opacity-70"
            >
              {pending ? "Sending your request…" : "Request consultation"}
            </button>

            <Notes phone={phone} />
          </form>
        ) : (
          <div className="flex flex-col gap-7">
            <p className="text-body-m text-ink-soft">Online requests are paused right now. Please call the clinic and the team will help you directly.</p>
            <a href={phone.href} className="w-full rounded-full bg-royal px-7 py-[17px] text-center font-display text-base leading-[1.2] font-bold text-white transition-colors hover:bg-royal-deep">
              Call {phone.display}
            </a>
            <Notes phone={null} />
          </div>
        )}
        </div>
      </div>
    </dialog>
  );
}

function Confirmation({ reference, phone, onClose }: { reference: string | null; phone: { display: string; href: string }; onClose: () => void }) {
  return (
    <div className="flex flex-col gap-7" role="status">
      <p className="text-body-m text-ink-soft">
        The team will reach out within one business day to talk through your questions. If you gave an email address, a receipt is on its way.
      </p>
      {reference && (
        <div className="flex flex-col gap-1 rounded-2xl bg-sky-soft px-[18px] py-3.5">
          <p className="font-display text-eyebrow font-bold tracking-[1.3px] text-royal uppercase">Your reference</p>
          <p className="font-serif text-heading-s text-ink">{reference}</p>
        </div>
      )}
      <button
        type="button"
        data-autofocus
        onClick={onClose}
        className="w-full rounded-full bg-royal px-7 py-[17px] text-center font-display text-base leading-[1.2] font-bold text-white transition-colors hover:bg-royal-deep"
      >
        Done
      </button>
      <Notes phone={phone} />
    </div>
  );
}

function Problem({ outcome, phone }: { outcome: SubmitResult; phone: { display: string; href: string } }) {
  const message =
    outcome.status === "rate_limited"
      ? "Several requests were sent from here in the last minute. Please wait a minute and try again, or call "
      : "Your request could not be sent. Nothing was lost: please try again in a moment, or call ";

  return (
    <p role="alert" className="rounded-2xl bg-danger-soft px-[18px] py-3.5 text-body-s text-danger">
      {message}
      <a href={phone.href} className="font-display font-bold underline">
        {phone.display}
      </a>
      .
    </p>
  );
}

function Notes({ phone }: { phone: { display: string; href: string } | null }) {
  return (
    <div className="flex flex-col gap-3 pt-1">
      <p className="flex items-center gap-3 rounded-2xl bg-sky-soft px-[18px] py-3.5 text-body-s text-ink">
        <Image src="/icons/info.svg" alt="" width={20} height={20} className="shrink-0" />
        If you may be in immediate danger, call 911 or call/text 988 for crisis support in the United States.
      </p>
      {phone && (
        <p className="text-center text-body-m text-ink-soft">
          Prefer to talk?{" "}
          <a href={phone.href} className="font-display font-bold text-royal underline">
            {phone.display}
          </a>
        </p>
      )}
    </div>
  );
}

function Row({ children }: { children: ReactNode }) {
  return <div className="grid gap-5 md:grid-cols-2">{children}</div>;
}

function boxClass(invalid: boolean): string {
  return `w-full rounded-[14px] border-[1.5px] bg-white px-[18px] text-body-m text-ink placeholder:text-ink-soft/80 ${invalid ? "border-danger" : "border-ink-soft"}`;
}

interface ControlProps {
  id: string;
  name: string;
  "aria-invalid"?: true;
  "aria-describedby"?: string;
  "aria-required"?: true;
}

function Field({ id, label, required, optional, error, children }: { id: string; label: string; required?: boolean; optional?: boolean; error?: string; children: (props: ControlProps) => ReactNode }) {
  const errorId = `${id}-error`;

  return (
    <div className="flex min-w-0 flex-col gap-2">
      <label htmlFor={id} className="font-display text-[14px] leading-[1.3] font-bold text-ink">
        {label}
        {required && (
          <span aria-hidden="true" className="text-royal">
            {" "}
            *
          </span>
        )}
        {optional && <span className="font-sans font-normal text-ink-soft"> (optional)</span>}
      </label>
      {children({ id, name: id, ...(error ? { "aria-invalid": true, "aria-describedby": errorId } : {}), ...(required ? { "aria-required": true } : {}) })}
      {error && <ErrorText id={errorId}>{error}</ErrorText>}
    </div>
  );
}

function TextField({
  id,
  label,
  value,
  onChange,
  error,
  required,
  optional,
  type = "text",
  ...rest
}: {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  required?: boolean;
  optional?: boolean;
  type?: "text" | "tel" | "email";
  placeholder?: string;
  autoComplete?: string;
  inputMode?: "tel" | "email" | "text";
  maxLength?: number;
}) {
  return (
    <Field id={id} label={label} required={required} optional={optional} error={error}>
      {(props) => <input {...props} {...rest} type={type} value={value} onChange={(event) => onChange(event.target.value)} className={`${boxClass(Boolean(error))} h-[54px]`} />}
    </Field>
  );
}

function SelectField({ id, label, options, value, onChange, error, placeholder }: { id: string; label: string; options: { value: string; label: string }[]; value: string; onChange: (value: string) => void; error?: string; placeholder?: string }) {
  return (
    <Field id={id} label={label} error={error}>
      {(props) => (
        <span className="relative block">
          <select
            {...props}
            value={value}
            onChange={(event) => onChange(event.target.value)}
            className={`${boxClass(Boolean(error))} h-[54px] appearance-none pr-12 ${value === "" ? "text-ink-soft/80" : ""}`}
          >
            {placeholder !== undefined && <option value="">{placeholder}</option>}
            {options.map((option) => (
              <option key={option.value} value={option.value} className="text-ink">
                {option.label}
              </option>
            ))}
          </select>
          <Image src="/icons/chevron.svg" alt="" width={14} height={9} className="pointer-events-none absolute top-1/2 right-[18px] -translate-y-1/2" />
        </span>
      )}
    </Field>
  );
}

function ErrorText({ id, children }: { id: string; children: ReactNode }) {
  return (
    <p id={id} className="text-[14px] leading-[1.4] text-danger">
      {children}
    </p>
  );
}

function focusFirstError(form: HTMLFormElement | null) {
  form?.querySelector<HTMLElement>("[aria-invalid='true']")?.focus();
}
