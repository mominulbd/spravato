import { useState } from 'react';
import { Plus } from 'lucide-react';
import { Outlet, useLocation, useNavigate } from 'react-router';
import { can } from '@shared/auth/can';
import { Button, TabLinks } from '@shared/ui';
import { useCurrentUser } from '@admin/app/AuthProvider';
import { PageHeader } from '@admin/components/PageHeader';
import { useRoles, useUsers } from './api';
import { InviteUserDialog } from './InviteUserDialog';

/** Figma 25 / 26: one header, three tabs (Users, Roles & permissions, Pending invitations). */
export function UsersLayout() {
    const user = useCurrentUser();
    const location = useLocation();
    const navigate = useNavigate();
    const onRoles = location.pathname.endsWith('/roles');
    const [inviting, setInviting] = useState(false);
    const counts = useUsers({ per_page: 1 }, can(user, 'users.manage')).data?.meta.counts;
    const roles = useRoles().data;

    return (
        <>
            <PageHeader
                trail={['System', 'Users & roles']}
                title="Users &"
                accent="roles."
                description="Who can sign in and what they can do. Permissions are enforced on the server, not just hidden in the interface."
                actions={
                    onRoles
                        ? can(user, 'roles.manage') && (
                              <Button icon={<Plus className="size-[18px]" aria-hidden="true" />} onClick={() => navigate('/users/roles?new=1')}>
                                  New role
                              </Button>
                          )
                        : can(user, 'users.manage') && (
                              <Button icon={<Plus className="size-[18px]" aria-hidden="true" />} onClick={() => setInviting(true)}>
                                  Invite user
                              </Button>
                          )
                }
            />
            <TabLinks
                label="Users and roles"
                items={[
                    ...(can(user, 'users.manage') ? [{ to: '/users', label: 'Users', count: counts?.all }] : []),
                    { to: '/users/roles', label: 'Roles & permissions', count: roles?.length },
                    ...(can(user, 'users.manage') ? [{ to: '/users/invitations', label: 'Pending invitations', count: counts?.invited }] : []),
                ]}
            />
            <Outlet />
            <InviteUserDialog open={inviting} onClose={() => setInviting(false)} />
        </>
    );
}
