import { useState } from 'react';
import { Ellipsis, Mail, Pencil, Power, RefreshCw, ShieldCheck, ShieldOff, Trash2, TriangleAlert, UserRound } from 'lucide-react';
import { ApiError } from '@shared/api/client';
import { can } from '@shared/auth/can';
import { relativeTime } from '@shared/format/time';
import { useTableParams } from '@shared/hooks/useTableParams';
import { Avatar, Badge, Button, ConfirmDialog, DataTable, Dropdown, EmptyState, ErrorState, Pagination, type BadgeTone, type Column, type DropdownItem } from '@shared/ui';
import { useToast } from '@shared/ui';
import { useCurrentUser } from '@admin/app/AuthProvider';
import { useRequireTwoFactor, useUserAction, useUsers } from './api';
import { EditUserDrawer } from './EditUserDrawer';
import type { StaffUser } from './types';

const STATUS: Record<StaffUser['status'], { label: string; tone: BadgeTone }> = {
    active: { label: 'Active', tone: 'success' },
    invited: { label: 'Invited', tone: 'warning' },
    deactivated: { label: 'Deactivated', tone: 'neutral' },
};

export function roleTone(slug: string | undefined): BadgeTone {
    if (slug === 'super_admin') return 'navy';
    if (slug === 'staff') return 'neutral';

    return 'info';
}

type Action = 'remove' | 'resend' | 'reset-2fa' | 'deactivate' | 'reactivate';

const CONFIRM: Record<Exclude<Action, 'resend' | 'reactivate'>, { title: (u: StaffUser) => string; body: (u: StaffUser) => string; label: string; tone: 'danger' | 'primary' }> = {
    remove: {
        title: (u) => `Remove ${u.name}?`,
        body: (u) => `${u.name} loses access at once and every session ends. Their name stays on audit entries and past work.`,
        label: 'Remove user',
        tone: 'danger',
    },
    'reset-2fa': {
        title: (u) => `Reset two-factor for ${u.name}?`,
        body: () => 'Use this when someone loses their phone. They are signed out and set up two-factor again at their next sign-in.',
        label: 'Reset two-factor',
        tone: 'primary',
    },
    deactivate: {
        title: (u) => `Deactivate ${u.name}?`,
        body: () => 'They can’t sign in until reactivated, and their open sessions end now.',
        label: 'Deactivate',
        tone: 'danger',
    },
};

/** Figma 25 (Users) and the Pending invitations tab (`status="invited"`). */
export function UsersPage({ status }: { status?: 'invited' }) {
    const me = useCurrentUser();
    const toast = useToast();
    const table = useTableParams({ sort: 'name', perPage: 25 });
    const query = status ? { ...table.query, 'filter[status]': status } : table.query;
    const users = useUsers(query);
    const action = useUserAction();
    const requireTwoFactor = useRequireTwoFactor();
    const [editing, setEditing] = useState<StaffUser | null>(null);
    const [confirm, setConfirm] = useState<{ user: StaffUser; action: keyof typeof CONFIRM } | null>(null);
    const [confirmError, setConfirmError] = useState<string | null>(null);

    const run = (user: StaffUser, kind: Action) => {
        action.mutate(
            { user, action: kind },
            {
                onSuccess: () => {
                    setConfirm(null);
                    toast.success(
                        {
                            remove: 'User removed',
                            resend: 'Invitation sent again',
                            'reset-2fa': 'Two-factor reset',
                            deactivate: 'User deactivated',
                            reactivate: 'User reactivated',
                        }[kind],
                        user.email,
                    );
                },
                onError: (error) => setConfirmError(error instanceof ApiError ? error.message : 'That didn’t work.'),
            },
        );
    };

    const itemsFor = (user: StaffUser): DropdownItem[] => {
        const isSelf = user.id === me.id;
        const locked = user.role?.slug === 'super_admin' && !me.is_super_admin;
        const items: DropdownItem[] = [{ label: 'Edit', icon: <Pencil className="size-4" aria-hidden="true" />, onSelect: () => setEditing(user), disabled: locked }];

        if (user.status === 'invited') items.push({ label: 'Resend invitation', icon: <Mail className="size-4" aria-hidden="true" />, onSelect: () => run(user, 'resend'), disabled: locked });
        if (user.two_factor_enabled && !isSelf) items.push({ label: 'Reset two-factor', icon: <ShieldOff className="size-4" aria-hidden="true" />, onSelect: () => setConfirm({ user, action: 'reset-2fa' }), disabled: locked });
        if (user.status === 'deactivated') items.push({ label: 'Reactivate', icon: <RefreshCw className="size-4" aria-hidden="true" />, onSelect: () => run(user, 'reactivate'), disabled: locked });
        else if (user.status === 'active' && !isSelf) items.push({ label: 'Deactivate', icon: <Power className="size-4" aria-hidden="true" />, onSelect: () => setConfirm({ user, action: 'deactivate' }), disabled: locked });
        if (!isSelf) items.push({ label: 'Remove', icon: <Trash2 className="size-4" aria-hidden="true" />, onSelect: () => setConfirm({ user, action: 'remove' }), tone: 'danger', disabled: locked });

        return items;
    };

    const columns: Column<StaffUser>[] = [
        {
            key: 'user',
            header: 'User',
            sortKey: 'name',
            cell: (user) => (
                <div className="flex min-w-0 items-center gap-3">
                    <Avatar name={user.name} />
                    <div className="flex min-w-0 flex-col">
                        <span className="truncate font-semibold text-ink">
                            {user.name}
                            {user.id === me.id && <span className="ml-1.5 text-admin-small font-normal text-ink-soft">(you)</span>}
                        </span>
                        <span className="truncate text-admin-small text-ink-soft">{user.email}</span>
                    </div>
                </div>
            ),
        },
        { key: 'role', header: 'Role', className: 'w-[150px]', cell: (user) => (user.role ? <Badge tone={roleTone(user.role.slug)}>{user.role.name}</Badge> : <span className="text-ink-soft">—</span>) },
        {
            key: 'two_factor',
            header: 'Two-factor',
            className: 'w-[140px]',
            cell: (user) =>
                user.two_factor_enabled ? (
                    <span className="inline-flex items-center gap-1.5 text-success">
                        <ShieldCheck className="size-4" aria-hidden="true" /> On
                    </span>
                ) : (
                    <span className="inline-flex items-center gap-1.5 text-warning">
                        <TriangleAlert className="size-4" aria-hidden="true" /> Not set up
                    </span>
                ),
        },
        {
            key: 'last_seen',
            header: status ? 'Invited' : 'Last active',
            sortKey: status ? 'created_at' : 'last_seen_at',
            className: 'w-[150px]',
            cell: (user) =>
                status ? (
                    <span className="flex flex-col">
                        <span>{relativeTime(user.invitation?.sent_at ?? user.created_at)}</span>
                        {user.invitation && <span className={user.invitation.expired ? 'text-admin-small text-danger' : 'text-admin-small text-ink-soft'}>{user.invitation.expired ? 'Link expired' : `Expires ${new Date(user.invitation.expires_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`}</span>}
                    </span>
                ) : (
                    relativeTime(user.last_seen_at)
                ),
        },
        { key: 'status', header: 'Status', className: 'w-[140px]', cell: (user) => <Badge tone={STATUS[user.status].tone} dot>{STATUS[user.status].label}</Badge> },
        {
            key: 'actions',
            header: <span className="sr-only">Actions</span>,
            className: 'w-[64px]',
            align: 'end',
            cell: (user) => (
                <Dropdown
                    items={itemsFor(user)}
                    trigger={({ ref, ...props }) => (
                        <button ref={ref} type="button" {...props} aria-label={`Actions for ${user.name}`} className="rounded-full p-2 text-ink hover:bg-paper">
                            <Ellipsis className="size-[18px]" aria-hidden="true" />
                        </button>
                    )}
                />
            ),
        },
    ];

    const exposed = users.data?.users.filter((user) => user.status === 'active' && !user.two_factor_enabled && !user.requires_two_factor) ?? [];

    return (
        <>
            <DataTable
                caption={status ? 'Pending invitations' : 'Staff accounts'}
                columns={columns}
                rows={users.data?.users}
                rowKey={(user) => user.id}
                loading={users.isPending}
                sort={table.sort}
                onSort={table.setSort}
                rowClassName="h-[72px]"
                error={users.isError ? <ErrorState title="Users didn’t load" description={users.error instanceof ApiError ? users.error.humanMessage : 'Try again in a moment.'} requestId={users.error instanceof ApiError ? users.error.requestId : null} onRetry={() => void users.refetch()} /> : undefined}
                empty={
                    <EmptyState
                        icon={status ? <Mail className="size-5" aria-hidden="true" /> : <UserRound className="size-5" aria-hidden="true" />}
                        title={status ? 'No pending invitations' : 'No users match'}
                        description={status ? 'Everyone you invited has accepted.' : undefined}
                    />
                }
            />
            {users.data && <Pagination meta={users.data.meta} onPage={table.setPage} onPerPage={table.setPerPage} noun={status ? 'invitations' : 'users'} />}
            {exposed.length > 0 && (
                <div role="note" className="flex flex-wrap items-center justify-between gap-4 rounded-card bg-warning-soft px-6 py-3.5 text-admin-body text-warning">
                    <p className="flex items-start gap-2.5">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        {exposed.map((user) => user.name).join(', ')} {exposed.length === 1 ? 'has' : 'have'} admin access without two-factor authentication. Require 2FA for every role that can view consultation requests.
                    </p>
                    {can(me, 'roles.manage') && (
                        <Button
                            variant="secondary"
                            size="sm"
                            loading={requireTwoFactor.isPending}
                            onClick={() => requireTwoFactor.mutate(undefined, { onSuccess: (response) => toast.success('2FA required', response.message) })}
                        >
                            Require 2FA
                        </Button>
                    )}
                </div>
            )}
            {editing && <EditUserDrawer user={editing} onClose={() => setEditing(null)} />}
            {confirm && (
                <ConfirmDialog
                    open
                    title={CONFIRM[confirm.action].title(confirm.user)}
                    description={CONFIRM[confirm.action].body(confirm.user)}
                    confirmLabel={CONFIRM[confirm.action].label}
                    tone={CONFIRM[confirm.action].tone}
                    busy={action.isPending}
                    error={confirmError}
                    onConfirm={() => run(confirm.user, confirm.action)}
                    onClose={() => {
                        setConfirm(null);
                        setConfirmError(null);
                    }}
                />
            )}
        </>
    );
}
