import type { ReactNode } from 'react';
import { cn } from '@shared/ui';

/** White settings card with a title row (Figma 24). */
export function SettingsCard({ title, description, aside, children, className }: { title: string; description?: string; aside?: ReactNode; children: ReactNode; className?: string }) {
    return (
        <section className={cn('flex flex-col gap-5 rounded-sheet border border-line bg-white p-6 sm:p-7', className)}>
            <header className="flex items-start justify-between gap-4">
                <div className="min-w-0">
                    <h2 className="text-[17px] leading-[1.35] font-semibold text-ink">{title}</h2>
                    {description && <p className="mt-0.5 text-admin-small text-ink-soft">{description}</p>}
                </div>
                {aside}
            </header>
            {children}
        </section>
    );
}
