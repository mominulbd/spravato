import type { CurrentUser } from './types';

/** Hides controls the user cannot use. Never a security boundary: Laravel checks again. */
export function can(user: Pick<CurrentUser, 'permissions'> | null | undefined, permission: string | string[]): boolean {
    if (!user) {
        return false;
    }

    const wanted = Array.isArray(permission) ? permission : [permission];

    return wanted.some((key) => user.permissions.includes(key));
}

export function initials(name: string): string {
    const parts = name.trim().split(/\s+/);

    return ((parts[0]?.[0] ?? '') + (parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : '')).toUpperCase();
}
