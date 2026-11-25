import type { FormEvent } from 'react';
import { useState } from 'react';
import { ApiError } from '@shared/api/client';
import { firstErrors, requiredError, type Errors } from '@shared/forms/validate';
import { Button, Checkbox, ConfirmDialog, Drawer, TextInput, Textarea, Toggle, useToast } from '@shared/ui';
import { FormAlert } from '@admin/features/auth/FormAlert';
import { useDeleteRole, useSaveRole } from './api';
import type { PermissionGroup, Role } from './types';

function slugify(name: string): string {
    return name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 60);
}

/** Create or edit a role's grants (Super Admin only; the Super Admin role itself is fixed). */
export function RoleEditorDrawer({ role, groups, onClose }: { role: Role | null; groups: PermissionGroup[]; onClose: () => void }) {
    const toast = useToast();
    const save = useSaveRole(role?.id ?? null);
    const remove = useDeleteRole();
    const [name, setName] = useState(role?.name ?? '');
    const [description, setDescription] = useState(role?.description ?? '');
    const [requiresTwoFactor, setRequiresTwoFactor] = useState(role?.requires_two_factor ?? true);
    const [permissions, setPermissions] = useState<Set<string>>(new Set(role?.permissions ?? ['admin.access']));
    const [errors, setErrors] = useState<Errors>({});
    const [alert, setAlert] = useState<string | null>(null);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleteError, setDeleteError] = useState<string | null>(null);

    const toggle = (key: string, on: boolean) =>
        setPermissions((current) => {
            const next = new Set(current);
            if (on) next.add(key);
            else next.delete(key);

            return next;
        });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const local = { name: requiredError(name, 'a role name') };
        setErrors(local);
        setAlert(null);
        if (local.name) return;

        save.mutate(
            {
                name: name.trim(),
                ...(role === null ? { slug: slugify(name) } : {}),
                description: description.trim() || null,
                requires_two_factor: requiresTwoFactor,
                permissions: [...permissions].sort(),
            },
            {
                onSuccess: () => {
                    toast.success(role ? 'Role updated' : 'Role created', 'Holders get the new permissions on their next request.');
                    onClose();
                },
                onError: (error) => {
                    if (!(error instanceof ApiError)) return;
                    const fields = firstErrors(error.fieldErrors);
                    setErrors({ ...fields, name: fields.name ?? fields.slug });
                    if (error.code !== 'VALIDATION_FAILED') setAlert(error.message);
                },
            },
        );
    };

    const seesRequests = permissions.has('consultations.view');

    return (
        <>
            <Drawer
                open={!confirmDelete}
                onClose={onClose}
                title={role ? `Edit ${role.name}` : 'New role'}
                description="Permissions are enforced by the server on every request."
                footer={
                    <>
                        {role && !role.is_system && (
                            <Button variant="ghost" className="mr-auto text-danger" onClick={() => setConfirmDelete(true)}>
                                Delete role
                            </Button>
                        )}
                        <Button variant="ghost" onClick={onClose} disabled={save.isPending}>
                            Cancel
                        </Button>
                        <Button type="submit" form="role-editor" loading={save.isPending}>
                            {role ? 'Save role' : 'Create role'}
                        </Button>
                    </>
                }
            >
                <form id="role-editor" onSubmit={submit} noValidate className="flex flex-col gap-6">
                    {alert && <FormAlert>{alert}</FormAlert>}
                    <TextInput label="Role name" value={name} onChange={(event) => setName(event.target.value)} error={errors.name} help={role === null && name ? `Key: ${slugify(name)}` : undefined} data-autofocus />
                    <Textarea label="Description" value={description} onChange={(event) => setDescription(event.target.value)} maxLength={500} rows={2} />
                    <Toggle
                        label="Require two-factor authentication"
                        description={seesRequests && !requiresTwoFactor ? 'Strongly advised: this role can see patient contact details.' : 'Holders set up an authenticator app before using the admin.'}
                        checked={requiresTwoFactor}
                        onChange={(event) => setRequiresTwoFactor(event.target.checked)}
                    />
                    {groups.map((group) => (
                        <fieldset key={group.key} className="flex flex-col gap-3 rounded-card border border-line bg-white p-5">
                            <legend className="px-1 font-display text-[10px] font-bold tracking-[1.1px] text-ink-soft uppercase">{group.label}</legend>
                            {group.permissions.map((permission) => (
                                <Checkbox
                                    key={permission.key}
                                    label={permission.label}
                                    description={permission.description}
                                    checked={permissions.has(permission.key)}
                                    onChange={(event) => toggle(permission.key, event.target.checked)}
                                />
                            ))}
                        </fieldset>
                    ))}
                    {errors.permissions && <p className="text-admin-small text-danger">{errors.permissions}</p>}
                </form>
            </Drawer>
            {role && (
                <ConfirmDialog
                    open={confirmDelete}
                    title={`Delete the ${role.name} role?`}
                    description="Only possible when nobody holds it. This can’t be undone."
                    confirmLabel="Delete role"
                    busy={remove.isPending}
                    error={deleteError}
                    onClose={() => {
                        setConfirmDelete(false);
                        setDeleteError(null);
                    }}
                    onConfirm={() =>
                        remove.mutate(role.id, {
                            onSuccess: () => {
                                toast.success('Role deleted');
                                onClose();
                            },
                            onError: (error) => setDeleteError(error instanceof ApiError ? error.message : 'That didn’t work.'),
                        })
                    }
                />
            )}
        </>
    );
}
