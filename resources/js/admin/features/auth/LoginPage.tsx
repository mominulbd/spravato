import type { FormEvent } from 'react';
import { useState } from 'react';
import { Lock } from 'lucide-react';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { ApiError, api } from '@shared/api/client';
import type { CurrentUser } from '@shared/auth/types';
import { emailError, firstErrors, safeNext, totpError, type Errors } from '@shared/forms/validate';
import { Button, TextInput } from '@shared/ui';
import { useAuth } from '@admin/app/AuthProvider';
import { AuthCard, AuthLayout } from '@admin/layouts/AuthLayout';
import { FormAlert } from './FormAlert';

type LoginResponse = { two_factor: true } | { two_factor: false; user: CurrentUser };

/**
 * Figma 01. Step one: email + password. If the account has 2FA on, step two
 * asks for the authenticator code (or a recovery code) before any session
 * exists. "Keep me signed in" is left out on purpose: staff sessions end
 * after 30 idle minutes because the admin shows patient contact details.
 */
export function LoginPage() {
    const { setUser } = useAuth();
    const navigate = useNavigate();
    const [params] = useSearchParams();
    const [step, setStep] = useState<'credentials' | 'code'>('credentials');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [code, setCode] = useState('');
    const [useRecovery, setUseRecovery] = useState(false);
    const [errors, setErrors] = useState<Errors>({});
    const [alert, setAlert] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const finish = (user: CurrentUser) => {
        setUser(user);
        navigate(safeNext(params.get('next')), { replace: true });
    };

    const fail = (error: unknown) => {
        if (!(error instanceof ApiError)) throw error;
        setErrors(firstErrors(error.fieldErrors));

        if (error.code === 'RATE_LIMITED') {
            setAlert('Too many attempts. Wait a minute, then try again.');
        } else if (error.code === 'UNAUTHENTICATED' && step === 'code') {
            setStep('credentials');
            setAlert(error.humanMessage === 'Please sign in.' ? 'Your sign-in timed out. Enter your password again.' : error.message);
        } else if (error.code !== 'VALIDATION_FAILED') {
            setAlert(error.code === 'INVALID_CREDENTIALS' ? 'That email and password don’t match an account.' : error.humanMessage);
        }
    };

    const submitCredentials = async (event: FormEvent) => {
        event.preventDefault();
        const local = { email: emailError(email), password: password === '' ? 'Enter your password.' : undefined };
        setErrors(local);
        setAlert(null);
        if (local.email || local.password) return;

        setBusy(true);
        try {
            const { data } = await api.post<LoginResponse>('auth/login', { email: email.trim(), password });
            if (data.two_factor) {
                setPassword('');
                setStep('code');
            } else {
                finish(data.user);
            }
        } catch (error) {
            fail(error);
        } finally {
            setBusy(false);
        }
    };

    const submitCode = async (event: FormEvent) => {
        event.preventDefault();
        const field = useRecovery ? 'recovery_code' : 'code';
        const local = { [field]: useRecovery ? (code.trim() === '' ? 'Enter one of your recovery codes.' : undefined) : totpError(code) };
        setErrors(local);
        setAlert(null);
        if (local[field]) return;

        setBusy(true);
        try {
            const { data } = await api.post<{ two_factor: false; user: CurrentUser }>('auth/two-factor-challenge', { [field]: code.trim() });
            finish(data.user);
        } catch (error) {
            fail(error);
        } finally {
            setBusy(false);
        }
    };

    if (step === 'code') {
        return (
            <AuthLayout variant="split">
                <AuthCard
                    title="Two-step check"
                    description={useRecovery ? 'Enter one of the recovery codes you saved when you set up two-factor authentication.' : 'Open your authenticator app and enter the 6-digit code for UPTMS.'}
                >
                    <form onSubmit={submitCode} noValidate className="flex flex-col gap-5">
                        {alert && <FormAlert>{alert}</FormAlert>}
                        <TextInput
                            key={useRecovery ? 'recovery' : 'totp'}
                            label={useRecovery ? 'Recovery code' : 'Authentication code'}
                            value={code}
                            onChange={(event) => setCode(event.target.value)}
                            error={errors.code ?? errors.recovery_code}
                            inputMode={useRecovery ? 'text' : 'numeric'}
                            autoComplete="one-time-code"
                            placeholder={useRecovery ? 'xxxxx-xxxxx' : '123456'}
                            autoFocus
                        />
                        <Button type="submit" loading={busy} className="w-full">
                            Verify and sign in
                        </Button>
                        <div className="flex flex-wrap justify-between gap-3 text-admin-body">
                            <button
                                type="button"
                                className="font-semibold text-royal hover:underline"
                                onClick={() => {
                                    setUseRecovery((value) => !value);
                                    setCode('');
                                    setErrors({});
                                }}
                            >
                                {useRecovery ? 'Use the app code instead' : 'Use a recovery code'}
                            </button>
                            <button type="button" className="font-semibold text-ink-soft hover:text-ink" onClick={() => setStep('credentials')}>
                                Start over
                            </button>
                        </div>
                    </form>
                </AuthCard>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout variant="split">
            <AuthCard title="Sign in" description="Use your clinic staff account.">
                <form onSubmit={submitCredentials} noValidate className="flex flex-col gap-5">
                    {alert && <FormAlert>{alert}</FormAlert>}
                    <TextInput label="Email address" type="email" value={email} onChange={(event) => setEmail(event.target.value)} error={errors.email} autoComplete="username" autoFocus />
                    <TextInput label="Password" type="password" value={password} onChange={(event) => setPassword(event.target.value)} error={errors.password} autoComplete="current-password" />
                    <div className="flex justify-end">
                        <Link to="/forgot-password" className="text-admin-body font-semibold text-royal hover:underline">
                            Forgot password?
                        </Link>
                    </div>
                    <Button type="submit" loading={busy} className="w-full">
                        Sign in
                    </Button>
                    <p className="flex items-center gap-2 rounded-control bg-sky-soft px-3 py-2.5 text-admin-small text-navy">
                        <Lock className="size-4 shrink-0" aria-hidden="true" />
                        Protected area. Sign-ins and changes are logged.
                    </p>
                </form>
            </AuthCard>
        </AuthLayout>
    );
}
