import type { FormEvent } from 'react';
import { useState } from 'react';
import { Mail } from 'lucide-react';
import { ApiError } from '@shared/api/client';
import { emailError, firstErrors, requiredError, type Errors } from '@shared/forms/validate';
import { Button, Dialog, TextInput, useToast } from '@shared/ui';
import { FormAlert } from '@admin/features/auth/FormAlert';
import { useInviteUser } from './api';
import { RoleSelect } from './RoleSelect';

/** Invite user: name, email, role. The person gets an email link to choose a password. */
export function InviteUserDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
    const toast = useToast();
    const invite = useInviteUser();
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [roleId, setRoleId] = useState<number | ''>('');
    const [errors, setErrors] = useState<Errors>({});
    const [alert, setAlert] = useState<string | null>(null);

    const close = () => {
        setName('');
        setEmail('');
        setRoleId('');
        setErrors({});
        setAlert(null);
        onClose();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const local = { name: requiredError(name, 'a name'), email: emailError(email), role_id: roleId === '' ? 'Choose a role.' : undefined };
        setErrors(local);
        setAlert(null);
        if (local.name || local.email || local.role_id || roleId === '') return;

        invite.mutate(
            { name: name.trim(), email: email.trim(), role_id: roleId },
            {
                onSuccess: () => {
                    toast.success('Invitation sent', `${email.trim()} has 7 days to accept.`);
                    close();
                },
                onError: (error) => {
                    if (!(error instanceof ApiError)) return;
                    setErrors(firstErrors(error.fieldErrors));
                    if (error.code === 'VALIDATION_FAILED') setAlert(null);
                    else if (error.code === 'FORBIDDEN') setAlert(error.message);
                },
            },
        );
    };

    return (
        <Dialog
            open={open}
            onClose={close}
            dismissible={!invite.isPending}
            size="md"
            title="Invite a team member"
            description="They’ll get an email link to choose a password, then set up two-factor authentication."
            icon={
                <span className="inline-flex size-10 items-center justify-center rounded-full bg-sky-soft text-royal">
                    <Mail className="size-[18px]" aria-hidden="true" />
                </span>
            }
        >
            <form id="invite-user" onSubmit={submit} noValidate className="flex flex-col gap-4">
                {alert && <FormAlert>{alert}</FormAlert>}
                <TextInput label="Full name" value={name} onChange={(event) => setName(event.target.value)} error={errors.name} autoComplete="off" data-autofocus />
                <TextInput label="Email address" type="email" value={email} onChange={(event) => setEmail(event.target.value)} error={errors.email} autoComplete="off" />
                <RoleSelect value={roleId} onChange={setRoleId} error={errors.role_id} />
                <div className="flex flex-wrap justify-end gap-3 pt-1">
                    <Button variant="ghost" onClick={close} disabled={invite.isPending}>
                        Cancel
                    </Button>
                    <Button type="submit" loading={invite.isPending}>
                        Send invitation
                    </Button>
                </div>
            </form>
        </Dialog>
    );
}
