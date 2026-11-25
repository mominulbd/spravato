import { useCallback, useRef, useState } from 'react';
import { Outlet } from 'react-router';
import { useFocusTrap } from '@shared/hooks/useFocusTrap';
import { useAuth, useCurrentUser } from '@admin/app/AuthProvider';
import { useIdleTimeout } from '@admin/app/useIdleTimeout';
import { Sidebar } from './Sidebar';
import { TopBar } from './TopBar';

/**
 * The signed-in frame (Figma "Version C"): navy shell, sidebar, and one
 * rounded paper sheet holding the top bar and the page. Below 1024px the
 * sidebar becomes a drawer (tablet rules are finalised in Phase 5).
 */
export function AppLayout() {
    const user = useCurrentUser();
    const { signOut } = useAuth();
    const [menuOpen, setMenuOpen] = useState(false);
    const drawer = useRef<HTMLDivElement>(null);
    const closeMenu = useCallback(() => setMenuOpen(false), []);
    useFocusTrap(drawer, menuOpen, closeMenu);
    useIdleTimeout(user.session.idle_timeout_minutes, () => void signOut('idle'));

    const siteUrl = document.querySelector<HTMLMetaElement>('meta[name="site-url"]')?.content || null;

    return (
        <div className="flex min-h-dvh bg-navy">
            <a href="#main" className="sr-only z-50 rounded-control bg-white px-4 py-2 text-royal focus:not-sr-only focus:fixed focus:top-3 focus:left-3">
                Skip to content
            </a>
            <aside className="sticky top-0 hidden h-dvh shrink-0 lg:block">
                <Sidebar user={user} onSignOut={() => void signOut()} />
            </aside>
            {menuOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div aria-hidden="true" className="absolute inset-0 bg-navy/60" onClick={closeMenu} />
                    <div ref={drawer} role="dialog" aria-modal="true" aria-label="Navigation" className="relative h-full w-[264px] shadow-2xl">
                        <Sidebar user={user} onSignOut={() => void signOut()} onNavigate={closeMenu} />
                    </div>
                </div>
            )}
            <div className="flex min-w-0 flex-1 flex-col py-3 pr-3 pl-3 lg:pl-0">
                <div className="flex min-h-full flex-1 flex-col overflow-clip rounded-sheet bg-paper">
                    <TopBar user={user} siteUrl={siteUrl} onSignOut={() => void signOut()} onOpenMenu={() => setMenuOpen(true)} />
                    <main id="main" tabIndex={-1} className="flex w-full flex-1 flex-col gap-6 px-5 pt-2 pb-12 outline-none sm:px-10">
                        <Outlet />
                    </main>
                </div>
            </div>
        </div>
    );
}
