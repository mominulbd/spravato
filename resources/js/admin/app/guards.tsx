import type { ReactNode } from 'react';
import { Navigate, Outlet, useLocation } from 'react-router';
import { can } from '@shared/auth/can';
import { ButtonLink, NoAccessState, Spinner } from '@shared/ui';
import { useAuth } from './AuthProvider';

export function FullPageSpinner() {
    return (
        <div className="flex min-h-dvh items-center justify-center bg-canvas text-royal" role="status" aria-label="Loading">
            <Spinner className="size-7" />
        </div>
    );
}

/**
 * Signed in, and 2FA set up when the role requires it. Anything else goes
 * to sign-in (remembering where the person was going) or to 2FA set-up.
 */
export function RequireAuth() {
    const { user, loading } = useAuth();
    const location = useLocation();

    if (loading) {
        return <FullPageSpinner />;
    }

    if (user === null) {
        const next = location.pathname + location.search;

        return <Navigate to={next === '/' ? '/login' : `/login?next=${encodeURIComponent(next)}`} replace />;
    }

    if (user.two_factor.required && !user.two_factor.enabled) {
        return <Navigate to="/two-factor/setup" replace />;
    }

    return <Outlet />;
}

/** Sign-in pages bounce a signed-in person to the dashboard. */
export function GuestOnly() {
    const { user, loading } = useAuth();

    if (loading) {
        return <FullPageSpinner />;
    }

    return user === null ? <Outlet /> : <Navigate to="/" replace />;
}

/** The 403 view of Figma 28 ("No access"), shown in place of the page. */
export function RequirePermission({ permission, area, children }: { permission: string | string[]; area: string; children: ReactNode }) {
    const { user } = useAuth();

    if (!can(user, permission)) {
        return <NoAccess area={area} />;
    }

    return children;
}

export function NoAccess({ area }: { area: string }) {
    const { user } = useAuth();

    return (
        <NoAccessState
            title={`You don’t have access to ${area}`}
            description={`Your role is ${user?.role?.name ?? 'not set'}. Ask a Super Admin if you need it for your work.`}
            action={
                <ButtonLink to="/" variant="secondary" size="sm">
                    Back to dashboard
                </ButtonLink>
            }
        />
    );
}
