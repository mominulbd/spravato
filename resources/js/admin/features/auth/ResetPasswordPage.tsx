import type { FormEvent } from 'react';
import { useState } from 'react';
import { ChevronLeft } from 'lucide-react';
import { Link, useParams, useSearchParams } from 'react-router';
import { ApiError, api } from '@shared/api/client';
import { firstErrors, hasErrors, newPasswordErrors, type Errors } from '@shared/forms/validate';
import { Button, ButtonLink, TextInput } from '@shared/ui';
import { AuthCard, AuthLayout } from '@admin/layouts/AuthLayout';
import { FormAlert } from './FormAlert';

/** The emailed reset link lands here: /admin/reset-password/{token}?email=… */
export function ResetPasswordPage() {
    const { token = '' } = useParams();
    const [params] = useSearchParams();
    const email = params.get('email') ?? '';
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [errors, setErrors] = useState<Errors>({});
    const [alert, setAlert] = useState<string | null>(null);
    const [done, setDone] = useState(false);
    const [busy, setBusy] = useState(false);

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        const local = newPasswordErrors(password, confirmation);
        setErrors(local);
        setAlert(null);
        if (hasErrors(local)) return;

        setBusy(true);
        try {
            await api.post('auth/reset-password', { token, email, password, password_confirmation: confirmation });
            setDone(true);
        } catch (caught) {
            if (!(caught instanceof ApiError)) throw caught;
            const fields = firstErrors(caught.fieldErrors);
            setErrors(fields);
            setAlert(fields.email ?? (caught.code === 'VALIDATION_FAILED' ? null : caught.humanMessage));
        } finally {
            setBusy(false);
        }
    };

    if (done) {
        return (
            <AuthLayout>
                <AuthCard title="Password changed" description="Every other session was signed out. Sign in with your new password.">
                    <ButtonLink to="/login" className="w-full">
                        Sign in
                    </ButtonLink>
                </AuthCard>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout>
            <AuthCard title="Choose a new password" description={email ? <>For {email}. At least 12 characters, with letters and numbers.</> : 'At least 12 characters, with letters and numbers.'}>
                <form onSubmit={submit} noValidate className="flex flex-col gap-5">
                    {alert && (
                        <FormAlert>
                            {alert}{' '}
                            <Link to="/forgot-password" className="font-semibold underline">
                                Request a new link
                            </Link>
                        </FormAlert>
                    )}
                    <TextInput label="New password" type="password" value={password} onChange={(event) => setPassword(event.target.value)} error={errors.password} autoComplete="new-password" autoFocus />
                    <TextInput label="Repeat new password" type="password" value={confirmation} onChange={(event) => setConfirmation(event.target.value)} error={errors.password_confirmation} autoComplete="new-password" />
                    <Button type="submit" loading={busy} className="w-full">
                        Save password
                    </Button>
                    <Link to="/login" className="inline-flex items-center justify-center gap-1.5 text-admin-body font-semibold text-royal hover:underline">
                        <ChevronLeft className="size-4" aria-hidden="true" />
                        Back to sign in
                    </Link>
                </form>
            </AuthCard>
        </AuthLayout>
    );
}
