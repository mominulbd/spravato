import { Badge, type BadgeTone } from '@shared/ui';
import type { PublishStatus } from '@admin/lib/content';

const STATUS: Record<PublishStatus, { label: string; tone: BadgeTone }> = {
    published: { label: 'Published', tone: 'success' },
    draft: { label: 'Draft', tone: 'neutral' },
    scheduled: { label: 'Scheduled', tone: 'info' },
};

export function StatusBadge({ status }: { status: PublishStatus }) {
    return (
        <Badge tone={STATUS[status].tone} dot>
            {STATUS[status].label}
        </Badge>
    );
}

/** "Oct 12, 9:00 AM" */
export function shortDateTime(iso: string): string {
    return new Date(iso).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

export function shortDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

/** "Ayub Mwaura" → "Ayub M." (Figma 06) */
export function shortName(name: string | null | undefined): string {
    if (!name) return '—';
    const [first, ...rest] = name.trim().split(/\s+/);
    const last = rest[rest.length - 1];

    return last ? `${first} ${last[0]}.` : (first ?? '—');
}
