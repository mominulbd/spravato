import "server-only";

/**
 * Server-side client for the Laravel API in this project. Every read happens
 * in a Server Component or route handler, so the API address never reaches
 * the browser. Laravel answers in one envelope (docs/API.md §1):
 *
 *   success: { success: true, message, data, meta }
 *   error:   { success: false, message, error_code, errors, meta: { request_id } }
 */
export interface Envelope<T> {
  success: true;
  message: string;
  data: T;
  meta: Record<string, unknown>;
}

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly requestId: string | null = null,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export function apiUrl(path: string): string {
  const base = process.env.API_URL;

  if (!base) {
    throw new Error("API_URL is not set. Copy site/.env.example to site/.env.local.");
  }

  return `${base.replace(/\/+$/, "")}/${path.replace(/^\/+/, "")}`;
}

export async function apiGet<T>(path: string): Promise<Envelope<T>> {
  let response: Response;

  try {
    response = await fetch(apiUrl(path), { headers: { Accept: "application/json" } });
  } catch {
    throw new ApiError(0, "NETWORK_ERROR", "The API cannot be reached.");
  }

  const payload = (await response.json().catch(() => null)) as
    | (Partial<Envelope<T>> & { error_code?: string; meta?: { request_id?: string } })
    | null;

  if (!response.ok || payload?.success !== true) {
    throw new ApiError(
      response.status,
      payload?.error_code ?? "SERVER_ERROR",
      payload?.message ?? "The API returned an unexpected response.",
      payload?.meta?.request_id ?? response.headers.get("X-Request-Id"),
    );
  }

  return payload as Envelope<T>;
}

/** Shorthand when only `data` matters. */
export async function getData<T>(path: string): Promise<T> {
  return (await apiGet<T>(path)).data;
}

export interface PostResult<T> {
  status: number;
  success: boolean;
  data: T | null;
  errorCode: string | null;
  errors: Record<string, string[]> | null;
}

/**
 * A server-to-server POST (the consultation dialog's server action). Never
 * throws for an API answer: the caller branches on `status` and
 * `errorCode`. A network failure is status 0.
 */
export async function apiPost<T>(path: string, body: unknown, headers: Record<string, string> = {}): Promise<PostResult<T>> {
  let response: Response;

  try {
    response = await fetch(apiUrl(path), {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json", ...headers },
      body: JSON.stringify(body),
      cache: "no-store",
      signal: AbortSignal.timeout(15000),
    });
  } catch {
    return { status: 0, success: false, data: null, errorCode: "NETWORK_ERROR", errors: null };
  }

  const payload = (await response.json().catch(() => null)) as
    | { success?: boolean; data?: T; error_code?: string; errors?: Record<string, string[]> | null }
    | null;

  return {
    status: response.status,
    success: response.ok && payload?.success === true,
    data: payload?.data ?? null,
    errorCode: payload?.error_code ?? null,
    errors: payload?.errors ?? null,
  };
}
