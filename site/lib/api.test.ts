import { afterEach, describe, expect, it, vi } from "vitest";
import { ApiError, apiGet, apiUrl } from "./api";

describe("api client", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.unstubAllEnvs();
  });

  it("joins the base and the path with one slash", () => {
    vi.stubEnv("API_URL", "http://127.0.0.1:8000/api/v1/");
    expect(apiUrl("/health")).toBe("http://127.0.0.1:8000/api/v1/health");
  });

  it("unwraps a success envelope", async () => {
    vi.stubEnv("API_URL", "http://api.test/api/v1");
    vi.stubGlobal("fetch", vi.fn(async () => Response.json({ success: true, message: "OK", data: { status: "ok" }, meta: {} })));
    await expect(apiGet("health")).resolves.toMatchObject({ data: { status: "ok" } });
  });

  it("throws the error code of a failure envelope", async () => {
    vi.stubEnv("API_URL", "http://api.test/api/v1");
    vi.stubGlobal("fetch", vi.fn(async () => Response.json({ success: false, message: "Nope", error_code: "NOT_FOUND", meta: { request_id: "r1" } }, { status: 404 })));
    await expect(apiGet("missing")).rejects.toEqual(new ApiError(404, "NOT_FOUND", "Nope", "r1"));
  });
});
