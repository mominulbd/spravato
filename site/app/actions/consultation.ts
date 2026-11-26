"use server";

import { headers } from "next/headers";
import { apiPost } from "@/lib/api";
import { fieldErrors, type ConsultationContext, type ConsultationValues, type SubmitResult, toPayload } from "@/lib/consultation";

/**
 * The consultation dialog posts here; this forwards to Laravel
 * (`POST /api/v1/public/consultation-requests`), which validates, stores the
 * request encrypted and alerts staff. With the shared site token the
 * visitor's IP and user agent travel along for the rate limit and Turnstile
 * only; Laravel stores neither. Nothing the person typed is logged here.
 */
export async function submitConsultation(values: ConsultationValues, context: ConsultationContext): Promise<SubmitResult> {
  const incoming = await headers();
  const forwarded: Record<string, string> = {};
  const token = process.env.SITE_TOKEN;

  if (token) {
    forwarded["X-Uptms-Site-Token"] = token;

    // The entry our own proxy added is the last one; anything before it is
    // whatever the visitor sent, so trusting the first lets them dodge the limit.
    const ip = incoming.get("x-forwarded-for")?.split(",").at(-1)?.trim() || incoming.get("x-real-ip")?.trim();
    if (ip) forwarded["X-Uptms-Client-Ip"] = ip;

    const agent = incoming.get("user-agent");
    if (agent) forwarded["X-Uptms-Client-Agent"] = agent.slice(0, 512);
  }

  const result = await apiPost<{ reference: string | null }>("public/consultation-requests", toPayload(values, context), forwarded);

  if (result.success) {
    return { status: "success", reference: result.data?.reference ?? null };
  }

  if (result.status === 422 && result.errorCode === "VALIDATION_FAILED") {
    return { status: "invalid", errors: fieldErrors(result.errors) };
  }

  if (result.status === 429 || result.errorCode === "RATE_LIMITED") {
    return { status: "rate_limited" };
  }

  return { status: "error" };
}
