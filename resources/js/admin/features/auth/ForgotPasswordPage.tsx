import type { FormEvent } from 'react';
import { useState } from 'react';
import { ChevronLeft } from 'lucide-react';
import { Link } from 'react-router';
import { ApiError, api } from '@shared/api/client';
import { emailError } from '@shared/forms/validate';
import { Button, TextInput } from '@shared/ui';
import { AuthCard, AuthLayout } from '@admin/layouts/AuthLayout';
import { FormAlert } from './FormAlert';

/** Figma 02. The answer is the same whether or not the account exists. */
export function ForgotPasswordPage() {
    const [email, setEmail] = useState('');
    const [error, setError] = useState<string | undefined>();
    const [alert, setAlert] = useState<string | null>(null);
    const [sent, setSent] = useState(false);
    const [busy, setBusy] = useState(false);

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        const local = emailError(email);
        setError(local);
        setAlert(null);
        if (local) return;

        setBusy(true);
        try {
            await api.post('auth/forgot-password', { email: email.trim() });
            setSent(true);
        } catch (caught) {
            if (!(caught instanceof ApiError)) throw caught;
            setError(caught.fieldErrors.email?.[0]);
            if (caught.code !== 'VALIDATION_FAILED') setAlert(caught.humanMessage);
        } finally {
            setBusy(false);
        }
    };

    return (
        <AuthLayout>
            <AuthCard title="Reset your password" description="Enter your staff email. If an account exists, we’ll send a reset link that expires in 60 minutes.">
                <form onSubmit={submit} noValidate className="flex flex-col gap-5">
                    {alert && <FormAlert>{alert}</FormAlert>}
                    <TextInput label="Email address" type="email" value={email} onChange={(event) => setEmail(event.target.value)} error={error} autoComplete="username" autoFocus />
                    <Button type="submit" loading={busy} className="w-full">
                        Send reset link
                    </Button>
                    {sent && <FormAlert tone="success">Check your inbox. The link was sent if the address matches an account.</FormAlert>}
                    <Link to="/login" className="inline-flex items-center justify-center gap-1.5 text-admin-body font-semibold text-royal hover:underline">
                        <ChevronLeft className="size-4" aria-hidden="true" />
                        Back to sign in
                    </Link>
                </form>
            </AuthCard>
        </AuthLayout>
    );
}
