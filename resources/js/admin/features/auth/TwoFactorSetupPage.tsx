import type { FormEvent } from 'react';
import { useState } from 'react';
import { ShieldCheck, Smartphone } from 'lucide-react';
import { Navigate, useNavigate } from 'react-router';
import { ApiError, api } from '@shared/api/client';
import { totpError } from '@shared/forms/validate';
import { Button, Checkbox, QrCode, TextInput } from '@shared/ui';
import { useAuth } from '@admin/app/AuthProvider';
import { FullPageSpinner } from '@admin/app/guards';
import { AuthCard, AuthLayout } from '@admin/layouts/AuthLayout';
import { FormAlert } from './FormAlert';
import { RecoveryCodes } from './RecoveryCodes';

interface Secret {
    secret: string;
    otpauth_uri: string;
}

/**
 * Two-factor set-up: scan, confirm a code, save the recovery codes. Shown
 * after an invitation is accepted and whenever a role requires 2FA that the
 * person has not set up (the API refuses admin routes until then).
 */
export function TwoFactorSetupPage() {
    const { user, loading, refresh, signOut } = useAuth();
    const navigate = useNavigate();
    const [secret, setSecret] = useState<Secret | null>(null);
    const [code, setCode] = useState('');
    const [codes, setCodes] = useState<string[] | null>(null);
    const [saved, setSaved] = useState(false);
    const [error, setError] = useState<string | undefined>();
    const [alert, setAlert] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    if (loading) return <FullPageSpinner />;
    if (user === null) return <Navigate to="/login" replace />;
    if (user.two_factor.enabled && codes === null) return <Navigate to="/" replace />;

    const begin = async () => {
        setBusy(true);
        setAlert(null);
        try {
            setSecret((await api.post<Secret>('auth/two-factor')).data);
        } catch (caught) {
            if (!(caught instanceof ApiError)) throw caught;
            setAlert(caught.humanMessage);
        } finally {
            setBusy(false);
        }
    };

    const confirm = async (event: FormEvent) => {
        event.preventDefault();
        const local = totpError(code);
        setError(local);
        setAlert(null);
        if (local) return;

        setBusy(true);
        try {
            setCodes((await api.post<{ recovery_codes: string[] }>('auth/two-factor/confirm', { code: code.replace(/\s+/g, '') })).data.recovery_codes);
        } catch (caught) {
            if (!(caught instanceof ApiError)) throw caught;
            setError(caught.fieldErrors.code?.[0] ?? (caught.code === 'INVALID_TWO_FACTOR_CODE' ? caught.humanMessage : undefined));
            if (caught.code !== 'INVALID_TWO_FACTOR_CODE' && caught.code !== 'VALIDATION_FAILED') setAlert(caught.humanMessage);
        } finally {
            setBusy(false);
        }
    };

    if (codes !== null) {
        return (
            <AuthLayout>
                <AuthCard title="Save your recovery codes" description="If you lose your phone, each of these codes signs you in once. Keep them somewhere safe, like a password manager.">
                    <RecoveryCodes codes={codes} />
                    <Checkbox label="I’ve saved these codes" checked={saved} onChange={(event) => setSaved(event.target.checked)} />
                    <Button
                        className="w-full"
                        disabled={!saved}
                        onClick={async () => {
                            await refresh();
                            navigate('/', { replace: true });
                        }}
                    >
                        Continue to the admin
                    </Button>
                </AuthCard>
            </AuthLayout>
        );
    }

    if (secret === null) {
        return (
            <AuthLayout>
                <AuthCard
                    title="Set up two-factor authentication"
                    description={
                        user.two_factor.required
                            ? `Your ${user.role?.name ?? ''} role can see patient contact details, so every sign-in needs a code from your phone as well as your password.`
                            : 'Add a code from your phone to every sign-in.'
                    }
                >
                    {alert && <FormAlert>{alert}</FormAlert>}
                    <ol className="flex flex-col gap-3 text-admin-body text-ink">
                        <li className="flex gap-3">
                            <Smartphone className="mt-0.5 size-[18px] shrink-0 text-royal" aria-hidden="true" />
                            Install an authenticator app such as Google Authenticator, Microsoft Authenticator or 1Password.
                        </li>
                        <li className="flex gap-3">
                            <ShieldCheck className="mt-0.5 size-[18px] shrink-0 text-royal" aria-hidden="true" />
                            Scan the code on the next screen and type in the 6 digits it shows.
                        </li>
                    </ol>
                    <Button className="w-full" loading={busy} onClick={begin}>
                        Start set-up
                    </Button>
                    <button type="button" onClick={() => void signOut()} className="text-admin-body font-semibold text-ink-soft hover:text-ink">
                        Sign out
                    </button>
                </AuthCard>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout>
            <AuthCard title="Scan this code" description="Open your authenticator app, add an account, and scan. Then enter the 6-digit code it shows.">
                <form onSubmit={confirm} noValidate className="flex flex-col gap-5">
                    {alert && <FormAlert>{alert}</FormAlert>}
                    <div className="flex flex-col items-center gap-3">
                        <QrCode value={secret.otpauth_uri} label="QR code for your authenticator app" />
                        <details className="w-full text-admin-small text-ink-soft">
                            <summary className="cursor-pointer text-center font-semibold text-royal">Can’t scan? Enter the key by hand</summary>
                            <p className="mt-2 rounded-control bg-paper px-3 py-2.5 text-center font-mono text-[13px] tracking-[0.06em] text-ink">{secret.secret.match(/.{1,4}/g)?.join(' ')}</p>
                        </details>
                    </div>
                    <TextInput label="6-digit code" value={code} onChange={(event) => setCode(event.target.value)} error={error} inputMode="numeric" autoComplete="one-time-code" placeholder="123456" autoFocus />
                    <Button type="submit" loading={busy} className="w-full">
                        Turn on two-factor
                    </Button>
                </form>
            </AuthCard>
        </AuthLayout>
    );
}
