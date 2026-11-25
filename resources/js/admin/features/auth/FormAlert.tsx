import type { ReactNode } from 'react';
import { CircleCheck, TriangleAlert } from 'lucide-react';
import { cn } from '@shared/ui';

/** Form-level message (Figma 02 success note, Figma 28 validation banner). */
export function FormAlert({ tone = 'danger', children }: { tone?: 'danger' | 'success'; children: ReactNode }) {
    return (
        <div role={tone === 'danger' ? 'alert' : 'status'} className={cn('flex items-start gap-2.5 rounded-control px-3.5 py-2.5 text-admin-small', tone === 'danger' ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success')}>
            {tone === 'danger' ? <TriangleAlert className="mt-px size-4 shrink-0" aria-hidden="true" /> : <CircleCheck className="mt-px size-4 shrink-0" aria-hidden="true" />}
            <div>{children}</div>
        </div>
    );
}
