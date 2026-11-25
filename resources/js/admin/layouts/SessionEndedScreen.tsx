import { Clock } from 'lucide-react';
import { useLocation, useNavigate } from 'react-router';
import { Button } from '@shared/ui';
import { useAuth, type SessionEnd } from '@admin/app/AuthProvider';

const COPY: Record<SessionEnd, { title: string; body: (minutes: number) => string }> = {
    idle: {
        title: 'Signed out for privacy',
        body: (minutes) => `You were inactive for ${minutes} minutes, so we signed you out to protect patient information.`,
    },
    expired: {
        title: 'Your session ended',
        body: () => 'You were signed out, possibly from another tab or after a password change. Sign in again to continue.',
    },
    inactive: {
        title: 'Account deactivated',
        body: () => 'This account has been deactivated. Ask a Super Admin if you think this is a mistake.',
    },
};

/** Figma 28, "Session · timed out". */
export function SessionEndedScreen({ reason, minutes }: { reason: SessionEnd; minutes: number }) {
    const { clearSessionEnded } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const copy = COPY[reason];

    return (
        <div className="flex min-h-dvh items-center justify-center bg-navy p-4">
            <div role="alertdialog" aria-labelledby="session-ended-title" aria-describedby="session-ended-body" className="flex w-full max-w-[420px] flex-col gap-4 rounded-[20px] bg-white p-7">
                <div className="flex items-center gap-3">
                    <span className="inline-flex size-9 items-center justify-center rounded-full bg-sky-soft text-royal">
                        <Clock className="size-[18px]" aria-hidden="true" />
                    </span>
                    <h1 id="session-ended-title" className="text-admin-card-title font-semibold text-ink">
                        {copy.title}
                    </h1>
                </div>
                <p id="session-ended-body" className="text-admin-body text-ink-soft">
                    {copy.body(minutes)}
                </p>
                <Button
                    data-autofocus
                    autoFocus
                    onClick={() => {
                        clearSessionEnded();
                        const next = location.pathname + location.search;
                        navigate(next === '/' ? '/login' : `/login?next=${encodeURIComponent(next)}`, { replace: true });
                    }}
                    className="w-full"
                >
                    Sign in again
                </Button>
            </div>
        </div>
    );
}
