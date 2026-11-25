/** Uptms\Users\Http\Resources\UserResource */
export type UserStatus = 'invited' | 'active' | 'deactivated';

export interface StaffUser {
    id: number;
    uuid: string;
    name: string;
    email: string;
    role: { id: number; slug: string; name: string } | null;
    status: UserStatus;
    two_factor_enabled: boolean;
    requires_two_factor: boolean;
    last_seen_at: string | null;
    last_login_at: string | null;
    created_at: string | null;
    invitation?: { sent_at: string | null; expires_at: string; expired: boolean } | null;
}

export interface UserCounts {
    all: number;
    invited: number;
    without_two_factor: number;
}

/** Uptms\Permissions\Http\Resources\RoleResource */
export interface Role {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    is_system: boolean;
    is_super_admin: boolean;
    requires_two_factor: boolean;
    permissions: string[];
    users_count?: number;
}

export interface PermissionGroup {
    key: string;
    label: string;
    permissions: { key: string; label: string; description: string }[];
}
