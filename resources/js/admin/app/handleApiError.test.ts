import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '@shared/api/client';
import { authEvents } from './authEvents';
import { handleApiError } from './handleApiError';

const unsubscribe: (() => void)[] = [];
afterEach(() => unsubscribe.splice(0).forEach((fn) => fn()));

function listen() {
    const events: string[] = [];
    unsubscribe.push(authEvents.subscribe((event) => events.push(event)));

    return events;
}

describe('central API error handling', () => {
    it('routes auth failures to the auth context', () => {
        const events = listen();
        const notify = { error: vi.fn() };

        handleApiError(new ApiError(401, 'UNAUTHENTICATED', 'x'), 'query', notify);
        handleApiError(new ApiError(403, 'TWO_FACTOR_SETUP_REQUIRED', 'x'), 'mutation', notify);
        handleApiError(new ApiError(403, 'ACCOUNT_INACTIVE', 'x'), 'query', notify);

        expect(events).toEqual(['unauthenticated', 'two-factor-required', 'inactive']);
        expect(notify.error).not.toHaveBeenCalled();
    });

    it('toasts failed mutations but leaves 422 to the form and queries to their ErrorState', () => {
        const notify = { error: vi.fn() };

        handleApiError(new ApiError(422, 'VALIDATION_FAILED', 'x'), 'mutation', notify);
        handleApiError(new ApiError(500, 'SERVER_ERROR', 'x'), 'query', notify);
        expect(notify.error).not.toHaveBeenCalled();

        handleApiError(new ApiError(500, 'SERVER_ERROR', 'boom', {}, 'req-9'), 'mutation', notify);
        handleApiError(new ApiError(0, 'NETWORK_ERROR', 'x'), 'mutation', notify);
        handleApiError(new ApiError(429, 'RATE_LIMITED', 'x'), 'mutation', notify);

        expect(notify.error).toHaveBeenCalledTimes(3);
        expect(notify.error.mock.calls[0]?.[1]).toContain('req-9');
    });
});
