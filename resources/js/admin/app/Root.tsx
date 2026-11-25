import { useEffect } from 'react';
import { Outlet, isRouteErrorResponse, useRouteError } from 'react-router';
import { ButtonLink, ErrorState, useToast } from '@shared/ui';
import { OfflineBanner } from '@admin/layouts/OfflineBanner';
import { SessionEndedScreen } from '@admin/layouts/SessionEndedScreen';
import { useAuth } from './AuthProvider';
import type { Notifier } from './handleApiError';

/** Lets the query client (created outside React) raise toasts. */
export const notifier: Notifier = { error: () => undefined };

/** Top of the route tree: the session-ended screen, offline banner, toast bridge. */
export function Root() {
    const { sessionEnded, user } = useAuth();
    const toast = useToast();

    useEffect(() => {
        notifier.error = (title, description) => toast.error(title, description);
    }, [toast]);

    return (
        <>
            {sessionEnded && user === null ? <SessionEndedScreen reason={sessionEnded} minutes={30} /> : <Outlet />}
            <OfflineBanner />
        </>
    );
}

export function RouteError() {
    const error = useRouteError();
    const notFound = isRouteErrorResponse(error) && error.status === 404;

    return (
        <div className="flex min-h-dvh items-center justify-center bg-canvas p-4">
            <div className="w-full max-w-[520px] rounded-card border border-line bg-white">
                <ErrorState
                    title={notFound ? 'Page not found' : 'This screen hit a problem'}
                    description={notFound ? 'The address may be mistyped or the page moved.' : 'Nothing was lost. Reload the page; if it keeps happening, tell the web team what you were doing.'}
                />
                <div className="flex justify-center pb-10">
                    <ButtonLink to="/" variant="secondary" size="sm">
                        Back to dashboard
                    </ButtonLink>
                </div>
            </div>
        </div>
    );
}

export function NotFoundPage() {
    return (
        <div className="rounded-card border border-line bg-white">
            <ErrorState title="Page not found" description="The address may be mistyped or the page moved." />
            <div className="flex justify-center pb-10">
                <ButtonLink to="/" variant="secondary" size="sm">
                    Back to dashboard
                </ButtonLink>
            </div>
        </div>
    );
}
