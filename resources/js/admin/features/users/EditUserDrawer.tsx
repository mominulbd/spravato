import type { FormEvent } from 'react';
import { useState } from 'react';
import { ApiError } from '@shared/api/client';
import { emailError, firstErrors, requiredError, type Errors } from '@shared/forms/validate';
import { Button, Drawer, TextInput, Toggle, useToast } from '@shared/ui';
import { useCurrentUser } from '@admin/app/AuthProvider';
import { FormAlert } from '@admin/features/auth/FormAlert';
import { useUpdateUser } from './api';
import { RoleSelect } from './RoleSelect';
import type { StaffUser } from './types';

/** Edit name, email, role and access. Your own role and access are locked. */
export function EditUserDrawer({ user, onClose }: { user: StaffUser; onClose: () => void }) {
    const me = useCurrentUser();
    const toast = useToast();
    const update = useUpdateUser(user.id);
    const isSelf = me.id === user.id;
    const [name, setName] = useState(user.name);
    const [email, setEmail] = useState(user.email);
    const [roleId, setRoleId] = useState<number | ''>(user.role?.id ?? '');
    const [active, setActive] = useState(user.status !== 'deactivated');
    const [errors, setErrors] = useState<Errors>({});
    const [alert, setAlert] = useState<string | null>(null);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const local = { name: requiredError(name, 'a name'), email: emailError(email), role_id: roleId === '' ? 'Choose a role.' : undefined };
        setErrors(local);
        setAlert(null);
        if (local.name || local.email || roleId === '') return;

        update.mutate(
            { name: name.trim(), email: email.trim(), role_id: roleId, is_active: active },
            {
                onSuccess: () => {
                    toast.success('Saved', `${name.trim()} was updated.`);
                    onClose();
                },
                onError: (error) => {
                    if (!(error instanceof ApiError)) return;
                    setErrors(firstErrors(error.fieldErrors));
                    if (error.code !== 'VALIDATION_FAILED') setAlert(error.message);
                },
            },
        );
    };

    return (
        <Drawer
            open
            onClose={onClose}
            title={`Edit ${user.name}`}
            description={isSelf ? 'This is your account. Another administrator changes your role or access.' : 'Changes apply at their next request.'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose} disabled={update.isPending}>
                        Cancel
                    </Button>
                    <Button type="submit" form="edit-user" loading={update.isPending}>
                        Save changes
                    </Button>
                </>
            }
        >
            <form id="edit-user" onSubmit={submit} noValidate className="flex flex-col gap-5">
                {alert && <FormAlert>{alert}</FormAlert>}
                <TextInput label="Full name" value={name} onChange={(event) => setName(event.target.value)} error={errors.name} data-autofocus />
                <TextInput label="Email address" type="email" value={email} onChange={(event) => setEmail(event.target.value)} error={errors.email} />
                <RoleSelect value={roleId} onChange={setRoleId} error={errors.role_id} disabled={isSelf} />
                <Toggle
                    label="Can sign in"
                    description={active ? 'Turn off to block sign-in and end their sessions now. History is kept.' : 'Deactivated: they cannot sign in.'}
                    checked={active}
                    disabled={isSelf}
                    onChange={(event) => setActive(event.target.checked)}
                />
            </form>
        </Drawer>
    );
}
