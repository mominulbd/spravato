/**
 * "Last active" style times (Figma 25): Now, 12 min ago, 3 h ago, Yesterday,
 * Oct 1, Oct 1, 2025. Shown in the browser's time zone.
 */
export function relativeTime(iso: string | null, now: Date = new Date()): string {
    if (iso === null) return '—';

    const then = new Date(iso);
    const minutes = Math.floor((now.getTime() - then.getTime()) / 60_000);

    if (minutes < 2) return 'Now';
    if (minutes < 60) return `${minutes} min ago`;

    const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const startOfYesterday = new Date(startOfToday.getTime() - 86_400_000);

    if (then >= startOfToday) return `${Math.floor(minutes / 60)} h ago`;
    if (then >= startOfYesterday) return 'Yesterday';

    return then.toLocaleDateString('en-US', { month: 'short', day: 'numeric', ...(then.getFullYear() !== now.getFullYear() ? { year: 'numeric' } : {}) });
}

export function clockTime(iso: string): string {
    return new Date(iso).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

/** Day headings for grouped lists: Today, Yesterday, Monday, Oct 1 2025. */
export function dayLabel(iso: string, now: Date = new Date()): string {
    const then = new Date(iso);
    const day = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
    const diff = Math.round((day(now) - day(then)) / 86_400_000);

    if (diff === 0) return 'Today';
    if (diff === 1) return 'Yesterday';
    if (diff < 7) return then.toLocaleDateString('en-US', { weekday: 'long' });

    return then.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
