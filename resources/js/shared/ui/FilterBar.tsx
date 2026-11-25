import type { ReactNode, SelectHTMLAttributes } from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from './cn';

/** The row of search + filter pills above a table (Figma 27). */
export function FilterBar({ children }: { children: ReactNode }) {
    return <div className="flex flex-wrap items-center gap-3">{children}</div>;
}

interface FilterSelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
    label: string;
}

export function FilterSelect({ label, className, children, ...props }: FilterSelectProps) {
    return (
        <span className={cn('relative inline-flex min-w-[170px]', className)}>
            <select aria-label={label} className="h-11 w-full appearance-none rounded-control border border-line bg-white pr-10 pl-3.5 text-admin-body text-ink hover:border-ink-soft/50 focus:border-royal" {...props}>
                {children}
            </select>
            <ChevronDown className="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-ink-soft" aria-hidden="true" />
        </span>
    );
}
