import { Select } from '@shared/ui';
import { useCurrentUser } from '@admin/app/AuthProvider';
import { useRoles } from './api';

/**
 * Role picker. Roles the actor may not hand out (Super Admin for
 * non-Super-Admins, roles with permissions they lack) are disabled; the
 * API refuses them anyway.
 */
export function RoleSelect({ value, onChange, error, disabled }: { value: number | ''; onChange: (id: number) => void; error?: string | undefined; disabled?: boolean }) {
    const user = useCurrentUser();
    const roles = useRoles();

    return (
        <Select label="Role" value={value} onChange={(event) => onChange(Number(event.target.value))} error={error} disabled={disabled || roles.isPending}>
            <option value="" disabled>
                {roles.isPending ? 'Loading roles…' : 'Choose a role'}
            </option>
            {roles.data?.map((role) => {
                const allowed = user.is_super_admin || (!role.is_super_admin && role.permissions.every((key) => user.permissions.includes(key)));

                return (
                    <option key={role.id} value={role.id} disabled={!allowed}>
                        {role.name}
                        {allowed ? '' : ' — needs a Super Admin'}
                    </option>
                );
            })}
        </Select>
    );
}
