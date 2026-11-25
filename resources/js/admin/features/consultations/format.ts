import type { BadgeTone } from '@shared/ui';
import type { ConsultationRow, ConsultationStatus } from './types';

/** "42 min", "1 h 54 min", "2 d 1 h" (Figma 04 "Waiting"). */
export function waitingLabel(minutes: number): string {
    if (minutes < 60) return `${Math.max(0, minutes)} min`;

    const days = Math.floor(minutes / 1440);
    const hours = Math.floor((minutes % 1440) / 60);
    const mins = minutes % 60;

    if (days > 0) return hours > 0 ? `${days} d ${hours} h` : `${days} d`;

    return mins > 0 ? `${hours} h ${mins} min` : `${hours} h`;
}

/** Red once the follow-up target has passed, amber after a day, else plain. */
export function waitingTone(row: Pick<ConsultationRow, 'is_overdue' | 'waiting_minutes'>): 'danger' | 'warning' | null {
    if (row.is_overdue) return 'danger';
    if ((row.waiting_minutes ?? 0) >= 1440) return 'warning';

    return null;
}

export const STATUS_LABELS: Record<ConsultationStatus, string> = {
    new: 'New',
    contacted: 'Contacted',
    scheduled: 'Scheduled',
    closed: 'Closed',
    spam: 'Spam',
};

const STATUS_TONES: Record<ConsultationStatus, BadgeTone> = {
    new: 'info',
    contacted: 'warning',
    scheduled: 'success',
    closed: 'neutral',
    spam: 'neutral',
};

/** "Overdue" wins over "New": it is what staff must act on. */
export function statusBadge(row: Pick<ConsultationRow, 'status' | 'is_overdue'>): { label: string; tone: BadgeTone } {
    if (row.is_overdue) return { label: 'Overdue', tone: 'danger' };

    return { label: STATUS_LABELS[row.status], tone: STATUS_TONES[row.status] };
}

/** "Joy Kiongo" → "Joy K." */
export function shortStaffName(name: string): string {
    const parts = name.trim().split(/\s+/);

    return parts.length > 1 ? `${parts[0]} ${parts[parts.length - 1]?.charAt(0) ?? ''}.` : name;
}

/** "Today, 9:14 AM", "Yesterday, 3:20 PM", "Oct 7, 12:30 PM" in the browser's time zone. */
export function receivedLabel(iso: string, now: Date = new Date()): string {
    const then = new Date(iso);
    const time = then.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    const day = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
    const diff = Math.round((day(now) - day(then)) / 86_400_000);

    if (diff === 0) return `Today, ${time}`;
    if (diff === 1) return `Yesterday, ${time}`;

    return `${then.toLocaleDateString('en-US', { month: 'short', day: 'numeric', ...(then.getFullYear() !== now.getFullYear() ? { year: 'numeric' } : {}) })}, ${time}`;
}

/** The detail title: "Provider referral." with the last word in royal italic. */
export function splitTitle(label: string): { title: string; accent: string } {
    const words = label.split(' ');
    const accent = `${words.pop() ?? label}.`;

    return { title: words.join(' '), accent };
}
