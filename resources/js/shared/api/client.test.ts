import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiError, api } from './client';

function respond(status: number, body: unknown, headers: Record<string, string> = {}) {
    return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json', ...headers } });
}

afterEach(() => vi.restoreAllMocks());

describe('api client', () => {
    it('unwraps the success envelope and prefixes /api/v1', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(respond(200, { success: true, message: 'OK', data: { status: 'ok' }, meta: {} }));

        const result = await api.get<{ status: string }>('health');

        expect(result.data.status).toBe('ok');
        expect(vi.mocked(fetch).mock.calls[0]?.[0]).toBe('/api/v1/health');
    });

    it('drops empty query values', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(respond(200, { success: true, message: 'OK', data: [], meta: {} }));

        await api.get('admin/pages', { query: { search: '', status: 'draft', page: 2, author: null } });

        expect(vi.mocked(fetch).mock.calls[0]?.[0]).toBe('/api/v1/admin/pages?status=draft&page=2');
    });

    it('throws a typed error carrying the stable code and request id', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(
            respond(409, { success: false, message: 'Already published.', error_code: 'CONFLICT', errors: {}, meta: { request_id: 'req-1' } }),
        );

        const error = await api.get('x').catch((e: unknown) => e);

        expect(error).toBeInstanceOf(ApiError);
        expect((error as ApiError).code).toBe('CONFLICT');
        expect((error as ApiError).requestId).toBe('req-1');
        expect((error as ApiError).humanMessage).toMatch(/Refresh and try again/);
    });

    it('exposes 422 field errors', async () => {
        vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) =>
            String(input).includes('csrf-cookie')
                ? new Response(null, { status: 204 })
                : respond(422, { success: false, message: 'The given data was invalid.', error_code: 'VALIDATION_FAILED', errors: { email: ['Enter a valid email.'] }, meta: {} }),
        );

        const error = (await api.post('auth/login', { email: 'x' }).catch((e: unknown) => e)) as ApiError;

        expect(error.fieldErrors).toEqual({ email: ['Enter a valid email.'] });
        expect(error.humanMessage).toBe('The given data was invalid.');
    });

    it('refreshes the CSRF cookie once on 419 and retries', async () => {
        let attempts = 0;
        const fetchMock = vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
            if (String(input).includes('csrf-cookie')) return new Response(null, { status: 204 });
            attempts += 1;
            return attempts === 1 ? respond(419, { message: 'CSRF token mismatch.' }) : respond(201, { success: true, message: 'Created', data: {}, meta: {} });
        });

        await api.post('admin/redirects', { from: '/a', to: '/b' });

        expect(attempts).toBe(2);
        expect(fetchMock.mock.calls.some(([url]) => String(url) === '/sanctum/csrf-cookie')).toBe(true);
    });

    it('sends FormData as multipart without a JSON content type', async () => {
        vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) =>
            String(input).includes('csrf-cookie') ? new Response(null, { status: 204 }) : respond(201, { success: true, message: 'OK', data: { id: 5 }, meta: {} }),
        );
        const body = new FormData();
        body.append('file', new File(['x'], 'a.png', { type: 'image/png' }));

        await api.post('admin/media', body);

        const init = vi.mocked(fetch).mock.calls.find((c) => String(c[0]) === '/api/v1/admin/media')?.[1];
        expect(init?.body).toBe(body);
        expect((init?.headers as Record<string, string>)['Content-Type']).toBeUndefined();
    });

    it('maps network failures to NETWORK_ERROR', async () => {
        vi.spyOn(globalThis, 'fetch').mockRejectedValue(new TypeError('Failed to fetch'));

        await expect(api.get('health')).rejects.toMatchObject({ code: 'NETWORK_ERROR', status: 0 });
    });
});
