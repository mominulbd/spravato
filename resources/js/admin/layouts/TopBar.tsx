import { ChevronDown, ExternalLink, LogOut, Menu, UserRound } from 'lucide-react';
import { useNavigate } from 'react-router';
import type { CurrentUser } from '@shared/auth/types';
import { Avatar, Dropdown, IconButton, buttonClasses } from '@shared/ui';

/**
 * Admin/Top bar (Figma 54:263). Global search, notifications and help arrive
 * with the features they serve (Phases 2–4); until then the bar carries the
 * site link and the account menu.
 */
export function TopBar({ user, siteUrl, onSignOut, onOpenMenu }: { user: CurrentUser; siteUrl: string | null; onSignOut: () => void; onOpenMenu: () => void }) {
    const navigate = useNavigate();

    return (
        <div className="flex h-[76px] items-center justify-between gap-4 px-5 sm:px-10">
            <IconButton label="Open navigation" onClick={onOpenMenu} className="lg:hidden">
                <Menu className="size-[18px]" aria-hidden="true" />
            </IconButton>
            <div className="hidden lg:block" />
            <div className="flex items-center gap-3">
                {siteUrl && (
                    <a href={siteUrl} target="_blank" rel="noreferrer" className={buttonClasses('secondary', 'sm')}>
                        <ExternalLink className="size-4" aria-hidden="true" />
                        View site
                        <span className="sr-only">(opens in a new tab)</span>
                    </a>
                )}
                <span aria-hidden="true" className="hidden h-7 w-px bg-line sm:block" />
                <Dropdown
                    items={[
                        { label: 'My account', icon: <UserRound className="size-4" aria-hidden="true" />, onSelect: () => navigate('/account') },
                        { label: 'Sign out', icon: <LogOut className="size-4" aria-hidden="true" />, onSelect: onSignOut, tone: 'danger' },
                    ]}
                    trigger={({ ref, ...props }) => (
                        <button ref={ref} type="button" {...props} aria-label={`Account: ${user.name}`} className="flex items-center gap-2.5 rounded-full py-1 pr-1 pl-1 hover:bg-white">
                            <Avatar name={user.name} />
                            <span className="hidden flex-col text-left sm:flex">
                                <span className="text-admin-body font-semibold text-ink">{user.name}</span>
                                <span className="text-admin-small text-ink-soft">{user.role?.name ?? 'No role'}</span>
                            </span>
                            <ChevronDown className="size-4 text-ink-soft" aria-hidden="true" />
                        </button>
                    )}
                />
            </div>
        </div>
    );
}
