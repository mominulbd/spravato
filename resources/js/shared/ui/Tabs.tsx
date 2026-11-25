import type { ReactNode } from 'react';
import { NavLink } from 'react-router';
import { Count } from './Badge';
import { cn } from './cn';

/**
 * Admin/Tab (Figma 53:220). Tabs that are pages use links so the URL is the
 * state and the back button works.
 */
export interface TabItem {
    to: string;
    label: string;
    count?: number | undefined;
    end?: boolean;
}

export function TabLinks({ items, label }: { items: TabItem[]; label: string }) {
    return (
        <nav aria-label={label} className="relative flex w-full gap-7 overflow-x-auto border-b border-line">
            {items.map((item) => (
                <NavLink
                    key={item.to}
                    to={item.to}
                    end={item.end ?? true}
                    className={({ isActive }) =>
                        cn(
                            '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-0.5 pt-2.5 pb-3 text-admin-body font-semibold transition-colors',
                            isActive ? 'border-royal text-royal' : 'border-transparent text-ink-soft hover:text-ink',
                        )
                    }
                >
                    {({ isActive }) => (
                        <>
                            {item.label}
                            {item.count !== undefined && <Count active={isActive}>{item.count}</Count>}
                        </>
                    )}
                </NavLink>
            ))}
        </nav>
    );
}

export function SegmentedTabs<T extends string>({
    value,
    options,
    onChange,
    label,
}: {
    value: T;
    options: { value: T; label: ReactNode }[];
    onChange: (value: T) => void;
    label: string;
}) {
    return (
        <div role="tablist" aria-label={label} className="flex w-full gap-7 border-b border-line">
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    role="tab"
                    aria-selected={option.value === value}
                    onClick={() => onChange(option.value)}
                    className={cn(
                        '-mb-px border-b-2 px-0.5 pt-2.5 pb-3 text-admin-body font-semibold',
                        option.value === value ? 'border-royal text-royal' : 'border-transparent text-ink-soft hover:text-ink',
                    )}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}
