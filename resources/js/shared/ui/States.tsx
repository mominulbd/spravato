import type { ReactNode } from 'react';
import { Lock, RefreshCw, TriangleAlert } from 'lucide-react';
import { Button } from './Button';
import { cn } from './cn';

/** Empty, error and no-access states (Figma 28). Errors say what happened and what to do next. */
function StateIcon({ tone, children }: { tone: 'info' | 'danger'; children: ReactNode }) {
    return (
        <span className={cn('inline-flex size-12 items-center justify-center rounded-full', tone === 'info' ? 'bg-sky-soft text-royal' : 'bg-danger-soft text-danger')}>
            {children}
        </span>
    );
}

export function EmptyState({ icon, title, description, action, className }: { icon: ReactNode; title: string; description?: ReactNode; action?: ReactNode; className?: string }) {
    return (
        <div className={cn('flex flex-col items-center gap-3 px-6 py-12 text-center', className)}>
            <StateIcon tone="info">{icon}</StateIcon>
            <h3 className="text-admin-card-title font-semibold text-ink">{title}</h3>
            {description && <div className="max-w-[420px] text-admin-body text-ink-soft">{description}</div>}
            {action && <div className="mt-1">{action}</div>}
        </div>
    );
}

export function ErrorState({ title, description, onRetry, requestId, className }: { title: string; description: string; onRetry?: () => void; requestId?: string | null; className?: string }) {
    return (
        <div role="alert" className={cn('flex flex-col items-center gap-3 px-6 py-12 text-center', className)}>
            <StateIcon tone="danger">
                <TriangleAlert className="size-5" aria-hidden="true" />
            </StateIcon>
            <h3 className="text-admin-card-title font-semibold text-ink">{title}</h3>
            <p className="max-w-[460px] text-admin-body text-ink-soft">{description}</p>
            {onRetry && (
                <Button icon={<RefreshCw className="size-4" aria-hidden="true" />} onClick={onRetry} className="mt-1">
                    Try again
                </Button>
            )}
            {requestId && <p className="font-mono text-admin-small text-ink-soft">Error ID {requestId}</p>}
        </div>
    );
}

export function NoAccessState({ title, description, action }: { title: string; description: ReactNode; action?: ReactNode }) {
    return (
        <div className="flex flex-col items-center gap-3 px-6 py-16 text-center">
            <StateIcon tone="info">
                <Lock className="size-5" aria-hidden="true" />
            </StateIcon>
            <h2 className="text-admin-card-title font-semibold text-ink">{title}</h2>
            <div className="max-w-[460px] text-admin-body text-ink-soft">{description}</div>
            {action && <div className="mt-1">{action}</div>}
        </div>
    );
}

export function Skeleton({ className }: { className?: string }) {
    return <span aria-hidden="true" className={cn('block animate-pulse rounded-full bg-stone/70', className)} />;
}
