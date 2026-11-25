import { LogOut } from 'lucide-react';
import { NavLink } from 'react-router';
import type { CurrentUser } from '@shared/auth/types';
import { can } from '@shared/auth/can';
import { Avatar, cn } from '@shared/ui';
import { visibleNavigation } from '@admin/app/navigation';
import { useWaitingCount } from '@admin/features/consultations/api';
import { Logo } from '@admin/components/Logo';

/** Admin/Sidebar (Figma 54:22): navy rail, grouped nav, user card with sign-out. */
export function Sidebar({ user, onSignOut, onNavigate }: { user: CurrentUser; onSignOut: () => void; onNavigate?: () => void }) {
    const groups = visibleNavigation(user.permissions);
    // An item whose address prefixes another's (/seo vs /seo/settings) only matches exactly.
    const all = groups.flatMap((group) => group.items.map((item) => item.to));
    const exact = (to: string) => to === '/' || all.some((other) => other !== to && other.startsWith(`${to}/`));
    const waiting = useWaitingCount(can(user, 'consultations.view'));

    return (
        <div className="flex h-full w-[264px] flex-col gap-[18px] bg-navy px-4 pt-[18px] pb-4">
            <div className="flex items-center gap-2.5">
                <Logo />
                <span className="rounded-full border border-white/35 px-2 py-[3px] font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-white uppercase">CMS</span>
            </div>
            <nav aria-label="Admin" className="-mx-1 flex min-h-0 flex-1 flex-col gap-3.5 overflow-y-auto px-1 pb-4 [mask-image:linear-gradient(to_bottom,black_calc(100%-24px),transparent)] [@media(max-height:960px)]:gap-2">
                {groups.map((group) => (
                    <div key={group.label} className="flex flex-col gap-px">
                        <p className="px-3 pb-1 font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-white/50 uppercase [@media(max-height:960px)]:pb-0.5">{group.label}</p>
                        {group.items.map((item) => (
                            <NavLink
                                key={item.to}
                                to={item.to}
                                end={exact(item.to)}
                                onClick={onNavigate}
                                className={({ isActive }) =>
                                    cn(
                                        'flex h-9 shrink-0 items-center gap-3 rounded-control px-3 font-display text-[14px] leading-[1.2] transition-colors [@media(max-height:960px)]:h-8',
                                        isActive ? 'bg-white font-bold text-navy' : 'font-semibold text-white/82 hover:bg-white/8 hover:text-white',
                                    )
                                }
                            >
                                <item.icon className="size-[18px] shrink-0" aria-hidden="true" strokeWidth={1.75} />
                                <span className="min-w-0 flex-1 truncate">{item.label}</span>
                                {item.badge === 'consultations' && waiting.data !== undefined && waiting.data > 0 && (
                                    <span className="rounded-full bg-royal px-2 py-0.5 text-[11px] leading-[1.4] font-semibold text-white" aria-label={`${waiting.data} waiting`}>
                                        {waiting.data}
                                    </span>
                                )}
                                {item.phase !== undefined && (
                                    <span className="rounded-full bg-white/10 px-1.5 py-px text-[10px] font-semibold text-white/60" title={`Arrives in Phase ${item.phase}`}>
                                        Soon
                                    </span>
                                )}
                            </NavLink>
                        ))}
                    </div>
                ))}
            </nav>
            <div className="flex items-center gap-2.5 rounded-[14px] bg-sidebar-active px-3 py-2.5">
                <Avatar name={user.name} />
                <div className="flex min-w-0 flex-1 flex-col text-white">
                    <span className="truncate text-admin-body font-semibold">{user.name}</span>
                    <span className="truncate text-admin-small opacity-60">{user.role?.name ?? 'No role'}</span>
                </div>
                <button type="button" onClick={onSignOut} aria-label="Sign out" title="Sign out" className="rounded-full p-1.5 text-white/80 hover:bg-white/10 hover:text-white">
                    <LogOut className="size-[18px]" aria-hidden="true" />
                </button>
            </div>
        </div>
    );
}
