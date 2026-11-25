import { relativeTime } from '@shared/format/time';
import type { MediaItem } from './types';
import { formatBytes } from './types';

/** "1600 × 1200 · 184 KB" or "PDF · 220 KB". */
export function fileFacts(item: Pick<MediaItem, 'kind' | 'width' | 'height' | 'size' | 'delivered_size'>): string {
    if (item.kind === 'document') return `PDF · ${formatBytes(item.size)}`;

    return `${item.width ?? '?'} × ${item.height ?? '?'} · ${formatBytes(item.delivered_size)}`;
}

/** "Used on 3 pages", "Not used yet", or the Figma 18b reason line. */
export function usageLine(item: MediaItem): string {
    const count = item.usages_count ?? 0;

    if (count > 0) return `Used on ${count} ${count === 1 ? 'page' : 'pages'}`;
    if (item.usage_state === 'removed') return `Removed from pages · ${shortDate(item.last_used_at)}`;

    return item.created_at ? `Never used · uploaded ${shortDate(item.created_at)}` : 'Not used yet';
}

function shortDate(iso: string | null): string {
    return relativeTime(iso);
}

/** "92% smaller than the 2.4 MB original". */
export function savings(item: Pick<MediaItem, 'delivered_size' | 'original_size'>): number {
    if (item.original_size <= 0 || item.delivered_size >= item.original_size) return 0;

    return Math.round((1 - item.delivered_size / item.original_size) * 100);
}

/** Small, wide images (logos) are shown whole on white rather than cropped. */
export function isLogo(item: Pick<MediaItem, 'width' | 'height'>): boolean {
    return item.width !== null && item.height !== null && item.height > 0 && item.width <= 800 && item.width / item.height >= 1.95;
}
