import { ApiError } from '@shared/api/client';
import { authEvents } from './authEvents';

export interface Notifier {
    error: (title: string, description?: string) => void;
}

/**
 * Central error handling (docs/ROADMAP.md Phase 1): 401 → sign-in,
 * 2FA set-up required → set-up screen, deactivated → sign-in. Mutations get
 * a toast for 403 / 429 / 5xx / offline; 422 belongs to the form that sent
 * it, and failed queries render their own ErrorState.
 */
export function handleApiError(error: unknown, source: 'query' | 'mutation', notify: Notifier): void {
    if (!(error instanceof ApiError)) {
        return;
    }

    if (error.code === 'UNAUTHENTICATED' && error.status === 401) {
        authEvents.emit('unauthenticated');

        return;
    }

    if (error.code === 'TWO_FACTOR_SETUP_REQUIRED') {
        authEvents.emit('two-factor-required');

        return;
    }

    if (error.code === 'ACCOUNT_INACTIVE') {
        authEvents.emit('inactive');

        return;
    }

    if (source !== 'mutation') {
        return;
    }

    if (error.status === 0 || error.status === 429 || error.status >= 500 || error.status === 403) {
        notify.error(
            error.status === 403 ? 'Not allowed' : error.status === 429 ? 'Slow down' : 'That didn’t save',
            error.requestId && error.status >= 500 ? `${error.humanMessage} (Error ID ${error.requestId})` : error.humanMessage,
        );
    }
}
