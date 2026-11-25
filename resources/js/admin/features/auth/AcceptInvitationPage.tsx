import type { FormEvent } from 'react';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate, useParams } from 'react-router';
import { ApiError, api, getData } from '@shared/api/client';
import { firstErrors, hasErrors, newPasswordErrors, type Errors } from '@shared/forms/validate';
import { Button, ButtonLink, Spinner, TextInput } from '@shared/ui';
import { useAuth } from '@admin/app/AuthProvider';
import { AuthCard, AuthLayout } from '@admin/layouts/AuthLayout';
import { FormAlert } from './FormAlert';

interface Invitation {
    name: string;
    email: string;
    role: string | null;
    expires_at: string;
}

/**
 * The invitation link (/admin/invitation/{token}): choose a password, then
 * RequireAuth sends the person on to 2FA set-up when their role needs it.
 */
export function AcceptInvitationPage() {
    const { token = '' } = useParams();
    const { refresh } = useAuth();
    const navigate = useNavigate();
    const invitation = useQuery({ queryKey: ['invitation', token], queryFn: () => getData<Invitation>(`invitations/${token}`), retry: false });
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [errors, setErrors] = useState<Errors>({});
    const [alert, setAlert] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        const local = newPasswordErrors(password, confirmation);
        setErrors(local);
        setAlert(null);
        if (hasErrors(local)) return;

        setBusy(true);
        try {
            await api.post(`invitations/${token}/accept`, { password, password_confirmation: confirmation });
            await refresh();
            navigate('/', { replace: true });
        } catch (caught) {
            if (!(caught instanceof ApiError)) throw caught;
            setErrors(firstErrors(caught.fieldErrors));
            if (caught.code !== 'VALIDATION_FAILED') setAlert(caught.humanMessage);
        } finally {
            setBusy(false);
        }
    };

    if (invitation.isPending) {
        return (
            <AuthLayout>
                <div className="flex justify-center py-10 text-royal" role="status" aria-label="Checking the invitation">
                    <Spinner className="size-7" />
                </div>
            </AuthLayout>
        );
    }

    if (invitation.isError) {
        const error = invitation.error;

        return (
            <AuthLayout>
                <AuthCard title="This link no longer works" description={error instanceof ApiError ? error.humanMessage : 'The invitation could not be checked.'}>
                    <ButtonLink to="/login" variant="secondary" className="w-full">
                        Go to sign in
                    </ButtonLink>
                </AuthCard>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout>
            <AuthCard
                title={`Welcome, ${invitation.data.name.split(' ')[0] ?? ''}`}
                description={
                    <>
                        You’re joining the UPTMS admin{invitation.data.role ? ` as ${invitation.data.role}` : ''}. Choose a password for <strong className="font-semibold text-ink">{invitation.data.email}</strong>; next you’ll set up two-factor authentication.
                    </>
                }
            >
                <form onSubmit={submit} noValidate className="flex flex-col gap-5">
                    {alert && <FormAlert>{alert}</FormAlert>}
                    <input type="email" value={invitation.data.email} autoComplete="username" readOnly hidden />
                    <TextInput label="Password" type="password" value={password} onChange={(event) => setPassword(event.target.value)} error={errors.password} help="At least 12 characters, with letters and numbers." autoComplete="new-password" autoFocus />
                    <TextInput label="Repeat password" type="password" value={confirmation} onChange={(event) => setConfirmation(event.target.value)} error={errors.password_confirmation} autoComplete="new-password" />
                    <Button type="submit" loading={busy} className="w-full">
                        Set password and continue
                    </Button>
                </form>
            </AuthCard>
        </AuthLayout>
    );
}
