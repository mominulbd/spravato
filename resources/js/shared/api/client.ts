import { messageFor } from './errors';

/**
 * The one fetch wrapper the admin uses (docs/ARCHITECTURE.md §4):
 * same-origin session cookie, CSRF header, envelope unwrap, typed errors.
 */
export interface Envelope<T> {
    success: true;
    message: string;
    data: T;
    meta: Record<string, unknown>;
}

export type FieldErrors = Record<string, string[]>;

export class ApiError extends Error {
    constructor(
        public readonly status: number,
        public readonly code: string,
        message: string,
        public readonly errors: Record<string, unknown> = {},
        public readonly requestId: string | null = null,
    ) {
        super(message);
        this.name = 'ApiError';
    }

    /** Human text for the UI: the dictionary first, the server's message as fallback. */
    get humanMessage(): string {
        return this.code === 'VALIDATION_FAILED' || this.code === 'FORBIDDEN' ? this.message : messageFor(this.code, this.message);
    }

    /** 422 field errors as { field: [messages] }. */
    get fieldErrors(): FieldErrors {
        const result: FieldErrors = {};

        for (const [key, value] of Object.entries(this.errors)) {
            if (Array.isArray(value)) {
                result[key] = value.map(String);
            }
        }

        return result;
    }
}

export interface RequestOptions {
    body?: unknown;
    query?: Record<string, string | number | boolean | null | undefined>;
    signal?: AbortSignal;
}

let csrfReady: Promise<void> | null = null;

export function ensureCsrf(force = false): Promise<void> {
    if (csrfReady === null || force) {
        csrfReady = fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' }).then(() => undefined);
    }

    return csrfReady;
}

function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match?.[1] ? decodeURIComponent(match[1]) : null;
}

function buildUrl(path: string, query?: RequestOptions['query']): string {
    const url = new URL(path.startsWith('/') ? path : `/api/v1/${path}`, window.location.origin);

    for (const [key, value] of Object.entries(query ?? {})) {
        if (value !== null && value !== undefined && value !== '') {
            url.searchParams.set(key, String(value));
        }
    }

    return url.pathname + url.search;
}

async function send<T>(method: string, path: string, options: RequestOptions, retried: boolean): Promise<Envelope<T>> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (method !== 'GET') {
        await ensureCsrf();
        const token = xsrfToken();
        if (token !== null) {
            headers['X-XSRF-TOKEN'] = token;
        }
    }

    // A FormData body (media uploads) sets its own multipart boundary.
    const multipart = options.body instanceof FormData;

    if (options.body !== undefined && !multipart) {
        headers['Content-Type'] = 'application/json';
    }

    let response: Response;

    try {
        response = await fetch(buildUrl(path, options.query), {
            method,
            headers,
            credentials: 'same-origin',
            body: options.body === undefined ? undefined : multipart ? (options.body as FormData) : JSON.stringify(options.body),
            signal: options.signal,
        });
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            throw error;
        }

        throw new ApiError(0, 'NETWORK_ERROR', messageFor('NETWORK_ERROR'));
    }

    // CSRF token expired: refresh once and retry.
    if (response.status === 419 && !retried) {
        await ensureCsrf(true);

        return send<T>(method, path, options, true);
    }

    const payload: unknown = response.status === 204 ? null : await response.json().catch(() => null);

    if (!response.ok || (payload as { success?: boolean } | null)?.success === false) {
        const body = (payload ?? {}) as { error_code?: string; message?: string; errors?: Record<string, unknown>; meta?: { request_id?: string } };

        throw new ApiError(
            response.status,
            body.error_code ?? 'SERVER_ERROR',
            body.message ?? messageFor('SERVER_ERROR'),
            body.errors ?? {},
            body.meta?.request_id ?? response.headers.get('X-Request-Id'),
        );
    }

    return (payload ?? { success: true, message: '', data: null, meta: {} }) as Envelope<T>;
}

export const api = {
    get: <T>(path: string, options: Omit<RequestOptions, 'body'> = {}) => send<T>('GET', path, options, false),
    post: <T>(path: string, body?: unknown, options: Omit<RequestOptions, 'body'> = {}) => send<T>('POST', path, { ...options, body }, false),
    put: <T>(path: string, body?: unknown, options: Omit<RequestOptions, 'body'> = {}) => send<T>('PUT', path, { ...options, body }, false),
    patch: <T>(path: string, body?: unknown, options: Omit<RequestOptions, 'body'> = {}) => send<T>('PATCH', path, { ...options, body }, false),
    // DELETE may carry a body, e.g. a password confirmation.
    delete: <T>(path: string, options: RequestOptions = {}) => send<T>('DELETE', path, options, false),
};

/** Shorthand when only `data` matters. */
export async function getData<T>(path: string, options: Omit<RequestOptions, 'body'> = {}): Promise<T> {
    return (await api.get<T>(path, options)).data;
}
