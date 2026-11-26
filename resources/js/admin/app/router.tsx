import type { ComponentType, ReactNode } from 'react';
import { Suspense, lazy } from 'react';
import type { RouteObject } from 'react-router';
import { Navigate, createBrowserRouter } from 'react-router';
import { can } from '@shared/auth/can';
import { Skeleton } from '@shared/ui';
import { AccountPage } from '@admin/features/account/AccountPage';
import { AuditLogPage } from '@admin/features/audit/AuditLogPage';
import { AcceptInvitationPage } from '@admin/features/auth/AcceptInvitationPage';
import { ForgotPasswordPage } from '@admin/features/auth/ForgotPasswordPage';
import { LoginPage } from '@admin/features/auth/LoginPage';
import { ResetPasswordPage } from '@admin/features/auth/ResetPasswordPage';
import { TwoFactorSetupPage } from '@admin/features/auth/TwoFactorSetupPage';
import { DashboardPage } from '@admin/features/dashboard/DashboardPage';
import { ComingSoonPage } from '@admin/features/placeholder/ComingSoonPage';
import { RolesPage } from '@admin/features/users/RolesPage';
import { UsersLayout } from '@admin/features/users/UsersLayout';
import { UsersPage } from '@admin/features/users/UsersPage';
import { AppLayout } from '@admin/layouts/AppLayout';
import { useAuth } from './AuthProvider';
import { GuestOnly, RequireAuth, RequirePermission } from './guards';
import { NAVIGATION } from './navigation';
import { NotFoundPage, Root, RouteError } from './Root';

/** Someone with roles.manage but not users.manage lands on the matrix. */
function UsersIndex() {
    const { user } = useAuth();

    return can(user, 'users.manage') ? <UsersPage /> : <Navigate to="/users/roles" replace />;
}

/** Feature screens load on demand, so the sign-in bundle stays small. */
function page(load: () => Promise<ComponentType>) {
    const Component = lazy(async () => ({ default: await load() }));

    return (
        <Suspense
            fallback={
                <div className="flex flex-col gap-4" aria-busy="true" aria-label="Loading">
                    <Skeleton className="h-4 w-40" />
                    <Skeleton className="h-10 w-72" />
                    <Skeleton className="h-64 w-full rounded-card" />
                </div>
            }
        >
            <Component />
        </Suspense>
    );
}

function guarded(permission: string | string[], area: string, element: ReactNode): ReactNode {
    return (
        <RequirePermission permission={permission} area={area}>
            {element}
        </RequirePermission>
    );
}

/** Phase 3 consultation requests (Figma 04–05). */
const consultations: RouteObject[] = [
    { path: 'consultations', element: guarded('consultations.view', 'consultation requests', page(() => import('@admin/features/consultations/ConsultationsPage').then((m) => m.ConsultationsPage))) },
    { path: 'consultations/:id', element: guarded('consultations.view', 'consultation requests', page(() => import('@admin/features/consultations/ConsultationDetailPage').then((m) => m.ConsultationDetailPage))) },
];

/** Phase 4 SEO screens (Figma 19–23). */
const seo: RouteObject[] = [
    { path: 'seo', element: guarded(['seo.edit', 'seo.manage'], 'the SEO overview', page(() => import('@admin/features/seo/SeoOverviewPage').then((m) => m.SeoOverviewPage))) },
    { path: 'seo/redirects', element: guarded('seo.manage', 'redirects', page(() => import('@admin/features/redirects/RedirectsPage').then((m) => m.RedirectsPage))) },
    { path: 'seo/404', element: guarded('seo.manage', 'the 404 monitor', page(() => import('@admin/features/redirects/NotFoundPage').then((m) => m.NotFoundPage))) },
    { path: 'seo/sitemap', element: guarded('seo.manage', 'the sitemap and robots.txt', page(() => import('@admin/features/seo/SitemapPage').then((m) => m.SitemapPage))) },
    { path: 'seo/settings', element: guarded('seo.manage', 'SEO settings', page(() => import('@admin/features/seo/SeoSettingsPage').then((m) => m.SeoSettingsPage))) },
];

/** Phase 2 content screens (Figma 06–07, 13–18b, 24). */
const content: RouteObject[] = [
    { path: 'pages', element: guarded('content.edit', 'pages', page(() => import('@admin/features/pages/PagesPage').then((m) => m.PagesPage))) },
    { path: 'pages/:id', element: guarded('content.edit', 'pages', page(() => import('@admin/features/pages/PageEditorPage').then((m) => m.PageEditorPage))) },
    { path: 'collections', element: <Navigate to="/collections/team" replace /> },
    { path: 'collections/:type', element: guarded('content.edit', 'collections', page(() => import('@admin/features/collections/CollectionsPage').then((m) => m.CollectionsPage))) },
    { path: 'menus', element: guarded('content.edit', 'navigation menus', page(() => import('@admin/features/menus/MenusPage').then((m) => m.MenusPage))) },
    { path: 'media', element: guarded(['media.manage', 'content.edit'], 'the media library', page(() => import('@admin/features/media/MediaLibraryPage').then((m) => m.MediaLibraryPage))) },
    { path: 'settings', element: guarded('settings.manage', 'clinic settings', page(() => import('@admin/features/settings/ClinicSettingsPage').then((m) => m.ClinicSettingsPage))) },
];

const planned: RouteObject[] = NAVIGATION.flatMap((group) =>
    group.items
        .filter((item) => item.phase !== undefined)
        .map((item) => ({
            path: item.to.slice(1),
            element: (
                <RequirePermission permission={item.permissions} area={item.label}>
                    <ComingSoonPage item={item} group={group.label} />
                </RequirePermission>
            ),
        })),
);

/**
 * Admin routes at the root of Laravel's origin (routes/web.php serves the shell). Every
 * screen's API is guarded by Laravel; the guards here only decide what to
 * show (docs/ARCHITECTURE.md §5).
 */
export const routes: RouteObject[] = [
    {
        element: <Root />,
        errorElement: <RouteError />,
        children: [
            {
                element: <GuestOnly />,
                children: [
                    { path: 'login', element: <LoginPage /> },
                    { path: 'forgot-password', element: <ForgotPasswordPage /> },
                    { path: 'reset-password/:token', element: <ResetPasswordPage /> },
                ],
            },
            { path: 'invitation/:token', element: <AcceptInvitationPage /> },
            { path: 'two-factor/setup', element: <TwoFactorSetupPage /> },
            {
                element: <RequireAuth />,
                children: [
                    {
                        element: <AppLayout />,
                        children: [
                            { index: true, element: <DashboardPage /> },
                            { path: 'account', element: <AccountPage /> },
                            {
                                path: 'users',
                                element: (
                                    <RequirePermission permission={['users.manage', 'roles.manage']} area="users and roles">
                                        <UsersLayout />
                                    </RequirePermission>
                                ),
                                children: [
                                    { index: true, element: <UsersIndex /> },
                                    { path: 'roles', element: <RolesPage /> },
                                    {
                                        path: 'invitations',
                                        element: (
                                            <RequirePermission permission="users.manage" area="invitations">
                                                <UsersPage status="invited" />
                                            </RequirePermission>
                                        ),
                                    },
                                ],
                            },
                            {
                                path: 'audit-log',
                                element: (
                                    <RequirePermission permission="audit.view" area="the audit log">
                                        <AuditLogPage />
                                    </RequirePermission>
                                ),
                            },
                            ...consultations,
                            ...seo,
                            ...content,
                            ...planned,
                            { path: '*', element: <NotFoundPage /> },
                        ],
                    },
                ],
            },
        ],
    },
];

export const router = createBrowserRouter(routes);
