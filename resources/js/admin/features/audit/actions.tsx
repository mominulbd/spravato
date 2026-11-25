import type { LucideIcon } from 'lucide-react';
import { Download, Eye, FilePen, Inbox, KeyRound, LockKeyhole, LogIn, LogOut, Mail, ShieldCheck, ShieldOff, Trash2, TriangleAlert, UserMinus, UserPlus, UserRound } from 'lucide-react';

/** Human labels and icons for audit actions (Figma 27). Unknown actions fall back to their key. */
export interface ActionMeta {
    label: string;
    icon: LucideIcon;
    tone: 'info' | 'danger';
}

const SIGNED_IN: ActionMeta = { label: 'Signed in', icon: LogIn, tone: 'info' };
const PERMISSION_CHANGED: ActionMeta = { label: 'Changed role', icon: KeyRound, tone: 'info' };

const ACTIONS: Record<string, ActionMeta> = {
    'auth.login': SIGNED_IN,
    'auth.login_failed': { label: 'Failed sign-in', icon: TriangleAlert, tone: 'danger' },
    'auth.login_blocked': { label: 'Sign-in blocked (deactivated)', icon: TriangleAlert, tone: 'danger' },
    'auth.two_factor_failed': { label: 'Wrong two-factor code', icon: TriangleAlert, tone: 'danger' },
    'auth.logout': { label: 'Signed out', icon: LogOut, tone: 'info' },
    'auth.two_factor_enabled': { label: 'Turned on two-factor', icon: ShieldCheck, tone: 'info' },
    'auth.two_factor_disabled': { label: 'Turned off two-factor', icon: ShieldOff, tone: 'danger' },
    'auth.recovery_codes_regenerated': { label: 'New recovery codes', icon: KeyRound, tone: 'info' },
    'auth.password_changed': { label: 'Changed password', icon: LockKeyhole, tone: 'info' },
    'auth.password_reset': { label: 'Reset password', icon: LockKeyhole, tone: 'info' },
    'auth.password_reset_requested': { label: 'Requested password reset', icon: Mail, tone: 'info' },
    'user.invited': { label: 'Invited user', icon: Mail, tone: 'info' },
    'user.invitation_accepted': { label: 'Accepted invitation', icon: UserPlus, tone: 'info' },
    'user.created': { label: 'Created user', icon: UserPlus, tone: 'info' },
    'user.updated': { label: 'Updated user', icon: UserRound, tone: 'info' },
    'user.deleted': { label: 'Removed user', icon: UserMinus, tone: 'danger' },
    'user.two_factor_reset': { label: 'Reset two-factor', icon: ShieldOff, tone: 'danger' },
    'permission.changed': PERMISSION_CHANGED,
    'role.created': { label: 'Created role', icon: KeyRound, tone: 'info' },
    'role.updated': { label: 'Updated role', icon: KeyRound, tone: 'info' },
    'role.deleted': { label: 'Deleted role', icon: KeyRound, tone: 'danger' },
    'audit.exported': { label: 'Exported audit log', icon: Download, tone: 'info' },
    'consultation.submitted': { label: 'Request received', icon: Inbox, tone: 'info' },
    'consultation.alert_sent': { label: 'Staff alert emailed', icon: Mail, tone: 'info' },
    'consultation.confirmation_sent': { label: 'Confirmation emailed', icon: Mail, tone: 'info' },
    'consultation.viewed': { label: 'Opened request', icon: Eye, tone: 'info' },
    'consultation.status_changed': { label: 'Changed request status', icon: FilePen, tone: 'info' },
    'consultation.assigned': { label: 'Assigned request', icon: UserRound, tone: 'info' },
    'consultation.follow_up_changed': { label: 'Changed follow-up time', icon: FilePen, tone: 'info' },
    'consultation.note_added': { label: 'Added note', icon: FilePen, tone: 'info' },
    'consultation.exported': { label: 'Exported requests', icon: Download, tone: 'danger' },
    'consultation.deleted': { label: 'Deleted request', icon: Trash2, tone: 'danger' },
    'consultation.purged': { label: 'Request removed (retention)', icon: Trash2, tone: 'info' },
    'consultation.retention_run': { label: 'Retention clean-up', icon: Trash2, tone: 'info' },
};

export function actionMeta(action: string, newValues?: Record<string, unknown> | null): ActionMeta {
    if (action === 'auth.login' && newValues?.method === 'totp') return { ...SIGNED_IN, label: 'Signed in with 2FA' };
    if (action === 'auth.login' && newValues?.method === 'recovery_code') return { ...SIGNED_IN, label: 'Signed in with a recovery code' };
    if (action === 'permission.changed' && newValues && 'permissions' in newValues) return { ...PERMISSION_CHANGED, label: 'Changed role permissions' };

    return ACTIONS[action] ?? { label: action.replace(/[._]/g, ' ').replace(/^\w/, (c) => c.toUpperCase()), icon: action.includes('view') ? Eye : FilePen, tone: 'info' };
}

export const CATEGORY_LABELS: Record<string, string> = {
    sign_in: 'Sign-ins',
    users: 'Users & roles',
    consultations: 'Consultation requests',
    content: 'Content',
    seo: 'SEO',
    settings: 'Settings',
    audit: 'Audit log',
};
