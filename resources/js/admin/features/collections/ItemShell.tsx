import type { ReactNode } from 'react';
import { Lock } from 'lucide-react';
import { Badge, Toggle } from '@shared/ui';

/** Published switch with the reason it's locked, shared by every editor. */
export function PublishedToggle({
    checked,
    onChange,
    mayPublish,
    label = 'Published',
}: {
    checked: boolean;
    onChange: (value: boolean) => void;
    mayPublish: boolean;
    label?: string;
}) {
    return (
        <Toggle
            label={label}
            description={mayPublish ? (checked ? 'Shown on the website.' : 'Hidden from the website.') : 'Publishing needs the content.publish permission.'}
            checked={checked}
            disabled={!mayPublish}
            onChange={(event) => onChange(event.target.checked)}
        />
    );
}

export function VisibilityBadge({ published }: { published: boolean }) {
    return published ? (
        <Badge tone="success" dot>
            Published
        </Badge>
    ) : (
        <Badge tone="neutral" dot>
            Hidden
        </Badge>
    );
}

export function ReadOnlyNote({ children }: { children: ReactNode }) {
    return (
        <p className="flex items-start gap-2 rounded-control bg-paper px-3.5 py-2.5 text-admin-small text-ink-soft">
            <Lock className="mt-px size-3.5 shrink-0" aria-hidden="true" />
            <span>{children}</span>
        </p>
    );
}
