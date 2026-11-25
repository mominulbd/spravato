/**
 * Human messages per stable error code (docs/API.md §4). Mirrors
 * App\Support\Http\ErrorCode; add a line here whenever a code is added there.
 */
const MESSAGES: Record<string, string> = {
    VALIDATION_FAILED: 'Some fields need attention.',
    UNAUTHENTICATED: 'Please sign in.',
    INVALID_CREDENTIALS: 'That email and password don’t match an account.',
    FORBIDDEN: 'You don’t have permission to do this.',
    NOT_FOUND: 'That could not be found.',
    RATE_LIMITED: 'Too many attempts. Wait a moment and try again.',
    CONFLICT: 'This conflicts with a change that already happened. Refresh and try again.',
    SERVER_ERROR: 'Something went wrong on our side. Nothing was lost — try again.',
    SERVICE_UNAVAILABLE: 'The server is temporarily unavailable. Try again in a minute.',
    NETWORK_ERROR: 'You’re offline or the server can’t be reached. Check the connection.',
    INVALID_STATE_TRANSITION: 'That isn’t possible in the current state.',
    ACCOUNT_INACTIVE: 'This account has been deactivated. Ask a Super Admin.',
    TWO_FACTOR_SETUP_REQUIRED: 'Set up two-factor authentication to continue.',
    INVALID_TWO_FACTOR_CODE: 'That code didn’t work. Try the newest code from your app.',
    INVITATION_INVALID: 'This invitation link has expired or was already used. Ask an administrator for a new one.',
};

export function messageFor(code: string, fallback?: string): string {
    return MESSAGES[code] ?? fallback ?? 'Something went wrong on our side.';
}
