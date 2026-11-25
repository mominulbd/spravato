import type { FormEvent } from 'react';
import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { ShieldCheck, TriangleAlert } from 'lucide-react';
import { ApiError, api, getData } from '@shared/api/client';
import { firstErrors, hasErrors, newPasswordErrors, type Errors } from '@shared/forms/validate';
import { Badge, Button, ButtonLink, Card, CardHeader, Dialog, TextInput, useToast } from '@shared/ui';
import { useCurrentUser } from '@admin/app/AuthProvider';
import { PageHeader } from '@admin/components/PageHeader';
import { FormAlert } from '@admin/features/auth/FormAlert';
import { RecoveryCodes } from '@admin/features/auth/RecoveryCodes';

interface TwoFactorStatus {
    available: boolean;
    enabled: boolean;
    required: boolean;
    confirmed_at: string | null;
    recovery_codes_left: number;
}

/** The signed-in person's own security: password and two-factor. */
export function AccountPage() {
    const user = useCurrentUser();

    return (
        <>
            <PageHeader trail={['Account']} title="My" accent="account." description={`${user.name} · ${user.email} · ${user.role?.name ?? 'No role'}`} />
            <div className="grid items-start gap-6 xl:grid-cols-2">
                <ChangePasswordCard />
                <TwoFactorCard />
            </div>
        </>
    );
}

function ChangePasswordCard() {
    const toast = useToast();
    const [current, setCurrent] = useState('');
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [errors, setErrors] = useState<Errors>({});
    const change = useMutation({ mutationFn: () => api.put('auth/password', { current_password: current, password, password_confirmation: confirmation }) });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const local = { current_password: current === '' ? 'Enter your current password.' : undefined, ...newPasswordErrors(password, confirmation) };
        setErrors(local);
        if (hasErrors(local)) return;

        change.mutate(undefined, {
            onSuccess: () => {
                toast.success('Password changed', 'Your other devices were signed out.');
                setCurrent('');
                setPassword('');
                setConfirmation('');
            },
            onError: (error) => error instanceof ApiError && setErrors(firstErrors(error.fieldErrors)),
        });
    };

    return (
        <Card>
            <CardHeader title="Password" description="At least 12 characters, with letters and numbers. Changing it signs out your other devices." />
            <form onSubmit={submit} noValidate className="flex flex-col gap-4 p-6">
                <TextInput label="Current password" type="password" value={current} onChange={(event) => setCurrent(event.target.value)} error={errors.current_password} autoComplete="current-password" />
                <TextInput label="New password" type="password" value={password} onChange={(event) => setPassword(event.target.value)} error={errors.password} autoComplete="new-password" />
                <TextInput label="Repeat new password" type="password" value={confirmation} onChange={(event) => setConfirmation(event.target.value)} error={errors.password_confirmation} autoComplete="new-password" />
                <Button type="submit" loading={change.isPending} className="self-start">
                    Change password
                </Button>
            </form>
        </Card>
    );
}

function TwoFactorCard() {
    const toast = useToast();
    const status = useQuery({ queryKey: ['auth', 'two-factor'], queryFn: () => getData<TwoFactorStatus>('auth/two-factor') });
    const [asking, setAsking] = useState<'codes' | 'disable' | null>(null);
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | undefined>();
    const [codes, setCodes] = useState<string[] | null>(null);
    const action = useMutation({
        mutationFn: (kind: 'codes' | 'disable') =>
            kind === 'codes'
                ? api.post<{ recovery_codes: string[] } | null>('auth/two-factor/recovery-codes', { password })
                : api.delete<{ recovery_codes: string[] } | null>('auth/two-factor', { body: { password } }),
    });

    const close = () => {
        setAsking(null);
        setPassword('');
        setError(undefined);
        setCodes(null);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!asking) return;
        if (password === '') {
            setError('Enter your password.');
            return;
        }

        action.mutate(asking, {
            onSuccess: (response) => {
                void status.refetch();
                if (asking === 'codes' && response.data) setCodes(response.data.recovery_codes);
                else {
                    toast.success('Two-factor turned off');
                    close();
                }
            },
            onError: (caught) => caught instanceof ApiError && setError(caught.fieldErrors.password?.[0] ?? caught.message),
        });
    };

    const data = status.data;

    return (
        <Card>
            <CardHeader
                title="Two-factor authentication"
                description="A code from your phone at every sign-in."
                actions={data && (data.enabled ? <Badge tone="success" dot>On</Badge> : <Badge tone="warning" dot>Off</Badge>)}
            />
            <div className="flex flex-col gap-4 p-6">
                {data && !data.available && (
                    <p className="rounded-card bg-sky-soft px-4 py-3 text-admin-small text-ink">
                        Two-factor sign-in is turned off for the whole admin (Clinic settings → Security), so sign-in asks only for your password. You can still set it up now; it’s used as soon as it’s turned back on.
                    </p>
                )}
                {data?.enabled ? (
                    <>
                        <p className="flex items-start gap-2.5 text-admin-body text-ink">
                            <ShieldCheck className="mt-0.5 size-[18px] shrink-0 text-success" aria-hidden="true" />
                            On since {data.confirmed_at ? new Date(data.confirmed_at).toLocaleDateString('en-US', { dateStyle: 'medium' }) : '—'}. {data.recovery_codes_left} recovery code{data.recovery_codes_left === 1 ? '' : 's'} left.
                        </p>
                        {data.recovery_codes_left <= 2 && (
                            <FormAlert>You’re running low on recovery codes. Create new ones and store them safely.</FormAlert>
                        )}
                        <div className="flex flex-wrap gap-3">
                            <Button variant="secondary" onClick={() => setAsking('codes')}>
                                New recovery codes
                            </Button>
                            {!data.required && (
                                <Button variant="ghost" className="text-danger" onClick={() => setAsking('disable')}>
                                    Turn off
                                </Button>
                            )}
                        </div>
                        {data.required && <p className="text-admin-small text-ink-soft">Your role requires two-factor, so it can’t be turned off. Lost your phone? Ask an administrator to reset it.</p>}
                    </>
                ) : (
                    data && (
                        <>
                            <p className="flex items-start gap-2.5 text-admin-body text-ink">
                                <TriangleAlert className="mt-0.5 size-[18px] shrink-0 text-warning" aria-hidden="true" />
                                Two-factor is off. Turn it on to protect patient information if your password ever leaks.
                            </p>
                            <ButtonLink to="/two-factor/setup" className="self-start">
                                Set up two-factor
                            </ButtonLink>
                        </>
                    )
                )}
            </div>
            <Dialog
                open={asking !== null}
                onClose={close}
                dismissible={!action.isPending}
                title={codes ? 'Your new recovery codes' : asking === 'codes' ? 'Create new recovery codes' : 'Turn off two-factor?'}
                description={codes ? 'The old codes no longer work. Save these now — they won’t be shown again.' : 'Confirm with your password.'}
            >
                {codes ? (
                    <>
                        <RecoveryCodes codes={codes} />
                        <Button onClick={close} className="self-end">
                            Done
                        </Button>
                    </>
                ) : (
                    <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                        <TextInput label="Password" type="password" value={password} onChange={(event) => setPassword(event.target.value)} error={error} autoComplete="current-password" data-autofocus />
                        <div className="flex justify-end gap-3">
                            <Button variant="ghost" onClick={close} disabled={action.isPending}>
                                Cancel
                            </Button>
                            <Button type="submit" variant={asking === 'disable' ? 'danger' : 'primary'} loading={action.isPending}>
                                {asking === 'disable' ? 'Turn off' : 'Create codes'}
                            </Button>
                        </div>
                    </form>
                )}
            </Dialog>
        </Card>
    );
}
