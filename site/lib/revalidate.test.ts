import { createHmac } from "node:crypto";
import { describe, expect, it } from "vitest";
import { verifySignature } from "./revalidate";

const sign = (secret: string, timestamp: string, body: string) => createHmac("sha256", secret).update(`${timestamp}.${body}`).digest("hex");

describe("verifySignature", () => {
  const now = 1_800_000_000_000;
  const timestamp = String(now / 1000);
  const body = '{"tags":["content"]}';

  it("accepts a fresh, correctly signed body", () => {
    expect(verifySignature("s3cret", timestamp, sign("s3cret", timestamp, body), body, now)).toBe(true);
  });

  it("refuses a wrong secret, a changed body and an old timestamp", () => {
    expect(verifySignature("s3cret", timestamp, sign("other", timestamp, body), body, now)).toBe(false);
    expect(verifySignature("s3cret", timestamp, sign("s3cret", timestamp, body), '{"tags":[]}', now)).toBe(false);
    expect(verifySignature("s3cret", timestamp, sign("s3cret", timestamp, body), body, now + 301_000)).toBe(false);
  });
});
