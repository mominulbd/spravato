/** GET /api/v1/auth/me (Uptms\Auth\Http\Resources\CurrentUserResource). */
export interface CurrentUser {
    id: number;
    uuid: string;
    name: string;
    email: string;
    role: { slug: string; name: string } | null;
    is_super_admin: boolean;
    permissions: string[];
    /** `available`: the site-wide switch (Settings → Security); while off, sign-in never asks for a code. */
    two_factor: { available: boolean; enabled: boolean; required: boolean };
    session: { idle_timeout_minutes: number };
}
