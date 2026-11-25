import type { ReactNode } from 'react';
import { cn } from './cn';

/** Admin/Badge (Figma 53:77): status pills, optional dot. */
export type BadgeTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger' | 'navy';

const TONES: Record<BadgeTone, string> = {
    neutral: 'bg-stone text-ink-soft',
    info: 'bg-sky-soft text-royal',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    navy: 'bg-navy text-white',
};

export function Badge({ tone = 'neutral', dot = false, children, className }: { tone?: BadgeTone; dot?: boolean; children: ReactNode; className?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-[3px] text-admin-small font-semibold whitespace-nowrap', TONES[tone], className)}>
            {dot && <span aria-hidden="true" className="size-1.5 rounded-full bg-current" />}
            {children}
        </span>
    );
}

/** Small count pill used in tabs and the sidebar. */
export function Count({ children, active = false, className }: { children: ReactNode; active?: boolean; className?: string }) {
    return (
        <span className={cn('inline-flex rounded-full px-[7px] py-px text-[11px] leading-[1.4] font-semibold', active ? 'bg-sky-soft text-royal' : 'bg-stone text-ink-soft', className)}>
            {children}
        </span>
    );
}
