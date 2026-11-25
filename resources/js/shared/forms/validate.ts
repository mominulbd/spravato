/**
 * Client-side checks that mirror the server's Form Requests, so people see
 * problems before sending. The server validates again and wins.
 */
export type Errors = Record<string, string | undefined>;

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function emailError(value: string): string | undefined {
    if (value.trim() === '') return 'Enter an email address.';
    if (!EMAIL.test(value.trim())) return 'Enter a valid email address, like name@uptms.com.';

    return undefined;
}

export function requiredError(value: string, label: string): string | undefined {
    return value.trim() === '' ? `Enter ${label}.` : undefined;
}

/** Laravel: Password::min(12)->letters()->numbers(), confirmed. */
export function newPasswordErrors(password: string, confirmation: string): Errors {
    let error: string | undefined;

    if (password.length < 12) error = 'Use at least 12 characters.';
    else if (!/[a-z]/i.test(password)) error = 'Include at least one letter.';
    else if (!/\d/.test(password)) error = 'Include at least one number.';

    return {
        password: error,
        password_confirmation: error === undefined && confirmation !== password ? 'The passwords don’t match.' : undefined,
    };
}

export function totpError(code: string): string | undefined {
    return /^\d{6}$/.test(code.replace(/\s+/g, '')) ? undefined : 'Enter the 6-digit code from your app.';
}

export function hasErrors(errors: Errors): boolean {
    return Object.values(errors).some((value) => value !== undefined);
}

/** Server field errors (ApiError.fieldErrors) → one message per field. */
export function firstErrors(fieldErrors: Record<string, string[]>): Errors {
    return Object.fromEntries(Object.entries(fieldErrors).map(([key, messages]) => [key, messages[0]]));
}

/** Only same-app paths are followed after sign-in (no open redirects). */
export function safeNext(next: string | null): string {
    return next !== null && next.startsWith('/') && !next.startsWith('//') && !next.startsWith('/\\') ? next : '/';
}
