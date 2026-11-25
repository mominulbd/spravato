import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from './cn';

export function Card({ className, children, ...props }: HTMLAttributes<HTMLDivElement>) {
    return (
        <div className={cn('rounded-card border border-line bg-white', className)} {...props}>
            {children}
        </div>
    );
}

export function CardHeader({ title, description, actions }: { title: ReactNode; description?: ReactNode; actions?: ReactNode }) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-4 border-b border-line px-6 py-5">
            <div className="min-w-0">
                <h2 className="text-admin-card-title font-semibold text-ink">{title}</h2>
                {description && <p className="mt-0.5 text-admin-body text-ink-soft">{description}</p>}
            </div>
            {actions}
        </div>
    );
}
