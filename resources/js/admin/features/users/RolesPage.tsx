import { useMemo, useState } from 'react';
import { Check, Pencil } from 'lucide-react';
import { useSearchParams } from 'react-router';
import { ApiError } from '@shared/api/client';
import { can } from '@shared/auth/can';
import { Badge, ErrorState, Skeleton } from '@shared/ui';
import { useCurrentUser } from '@admin/app/AuthProvider';
import { usePermissionRegistry, useRoles } from './api';
import { RoleEditorDrawer } from './RoleEditorDrawer';
import type { Role } from './types';

/**
 * Figma 26: permissions down, roles across. "Assigned only" marks a role
 * that sees requests but not every request (no consultations.view_all);
 * "Not Super Admins" marks users.manage, which never reaches Super Admins.
 */
export function cellFor(role: Role, key: string): 'yes' | 'no' | 'assigned-only' | 'not-super-admins' {
    const holds = role.is_super_admin || role.permissions.includes(key);

    if (!holds) return 'no';
    if (role.is_super_admin) return 'yes';
    if ((key === 'consultations.view' || key === 'consultations.update') && !role.permissions.includes('consultations.view_all')) return 'assigned-only';
    if (key === 'users.manage') return 'not-super-admins';

    return 'yes';
}

function Cell({ value }: { value: ReturnType<typeof cellFor> }) {
    switch (value) {
        case 'yes':
            return (
                <span className="inline-flex size-6 items-center justify-center rounded-full bg-success-soft text-success">
                    <Check className="size-3.5" strokeWidth={2.5} aria-hidden="true" />
                    <span className="sr-only">Allowed</span>
                </span>
            );
        case 'assigned-only':
            return <Badge tone="warning">Assigned only</Badge>;
        case 'not-super-admins':
            return <Badge tone="warning">Not Super Admins</Badge>;
        default:
            return (
                <span className="text-ink-soft">
                    <span aria-hidden="true">—</span>
                    <span className="sr-only">Not allowed</span>
                </span>
            );
    }
}

export function RolesPage() {
    const me = useCurrentUser();
    const roles = useRoles();
    const registry = usePermissionRegistry();
    const [params, setParams] = useSearchParams();
    const [editing, setEditing] = useState<Role | null>(null);
    const creating = params.get('new') === '1';
    const editable = can(me, 'roles.manage');

    const groups = useMemo(() => registry.data?.groups ?? [], [registry.data]);

    if (roles.isError || registry.isError) {
        const error = roles.error ?? registry.error;

        return (
            <div className="rounded-card border border-line bg-white">
                <ErrorState title="Roles didn’t load" description={error instanceof ApiError ? error.humanMessage : 'Try again in a moment.'} onRetry={() => void Promise.all([roles.refetch(), registry.refetch()])} />
            </div>
        );
    }

    return (
        <>
            <div className="overflow-hidden rounded-card border border-line bg-white">
                <div className="relative overflow-x-auto">
                    <table className="w-full min-w-[820px] border-collapse text-admin-body">
                        <caption className="sr-only">Permissions held by each role</caption>
                        <thead>
                            <tr className="h-[52px] border-b border-line">
                                <th scope="col" className="pl-6 text-left align-middle font-display text-[10px] font-bold tracking-[1.1px] text-ink-soft uppercase">
                                    Permission
                                </th>
                                {roles.isPending
                                    ? Array.from({ length: 4 }, (_, i) => (
                                          <th key={i} className="w-[130px]">
                                              <Skeleton className="mx-auto h-3 w-16" />
                                          </th>
                                      ))
                                    : roles.data.map((role) => (
                                          <th key={role.id} scope="col" className="w-[130px] px-2 py-2 text-center align-top last:pr-6">
                                              <span className="flex flex-col items-center">
                                                  <span className="font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-ink uppercase">{role.name}</span>
                                                  <span className="text-admin-small font-normal text-ink-soft">
                                                      {role.users_count ?? 0} user{role.users_count === 1 ? '' : 's'}
                                                  </span>
                                                  {editable && role.is_super_admin && <span className="mt-1 px-2 py-0.5 text-admin-small text-ink-soft">Fixed</span>}
                                                  {editable && !role.is_super_admin && (
                                                      <button type="button" onClick={() => setEditing(role)} className="mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-admin-small font-semibold text-royal hover:bg-sky-soft" aria-label={`Edit ${role.name}`}>
                                                          <Pencil className="size-3" aria-hidden="true" /> Edit
                                                      </button>
                                                  )}
                                              </span>
                                          </th>
                                      ))}
                            </tr>
                        </thead>
                        <tbody>
                            {(roles.isPending || registry.isPending) &&
                                Array.from({ length: 6 }, (_, i) => (
                                    <tr key={i} className="h-[60px] border-b border-line">
                                        <td className="pl-6">
                                            <Skeleton className="h-3 w-48" />
                                        </td>
                                        <td colSpan={4} />
                                    </tr>
                                ))}
                            {roles.data &&
                                groups.map((group) => (
                                    <GroupRows key={group.key} label={group.label} permissions={group.permissions} roles={roles.data} />
                                ))}
                        </tbody>
                    </table>
                </div>
            </div>
            {roles.data && (
                <p className="text-admin-small text-ink-soft">
                    {roles.data.filter((role) => role.requires_two_factor).map((role) => role.name).join(', ') || 'No role'} require{roles.data.filter((role) => role.requires_two_factor).length === 1 ? 's' : ''} two-factor authentication.
                    {editable ? ' Super Admins always hold every permission.' : ' Only a Super Admin can change role permissions.'}
                </p>
            )}
            {(editing || creating) && registry.data && (
                <RoleEditorDrawer
                    role={editing}
                    groups={registry.data.groups}
                    onClose={() => {
                        setEditing(null);
                        if (creating) setParams({}, { replace: true });
                    }}
                />
            )}
        </>
    );
}

function GroupRows({ label, permissions, roles }: { label: string; permissions: { key: string; label: string; description: string }[]; roles: Role[] }) {
    return (
        <>
            <tr className="bg-paper">
                <th colSpan={roles.length + 1} scope="colgroup" className="px-6 py-2.5 text-left font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-ink-soft uppercase">
                    {label}
                </th>
            </tr>
            {permissions.map((permission) => (
                <tr key={permission.key} className="h-[60px] border-b border-line">
                    <th scope="row" className="py-2.5 pr-4 pl-6 text-left font-normal">
                        <span className="block font-semibold text-ink">{permission.label}</span>
                        <span className="block text-admin-small text-ink-soft">{permission.description}</span>
                    </th>
                    {roles.map((role) => (
                        <td key={role.id} className="px-2 text-center last:pr-6">
                            <Cell value={cellFor(role, permission.key)} />
                        </td>
                    ))}
                </tr>
            ))}
        </>
    );
}
