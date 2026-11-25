/** Media payloads (Uptms\Media\Http\Resources\MediaResource, docs/API.md §7). */
export interface MediaItem {
    id: number;
    uuid: string;
    filename: string;
    original_name: string;
    kind: 'image' | 'document';
    mime: string;
    url: string;
    thumbnail_url: string | null;
    size: number;
    original_size: number;
    delivered_size: number;
    width: number | null;
    height: number | null;
    alt: string | null;
    is_decorative: boolean;
    needs_alt_text: boolean;
    caption: string | null;
    focal: { x: number; y: number };
    folder_id: number | null;
    variants_ready: boolean;
    usages_count: number | null;
    usage_state: 'in_use' | 'never_used' | 'removed' | null;
    usages?: { label: string; context: string; admin_path: string | null }[];
    variants?: { format: string; width: number; height: number; size: number }[];
    created_at: string | null;
    updated_at: string | null;
    first_used_at: string | null;
    last_used_at: string | null;
    deleted_at: string | null;
}

export interface MediaFolder {
    id: number;
    name: string;
    slug: string;
    media_count: number;
}

export interface MediaCounts {
    all: number;
    in_use: number;
    unused: number;
    no_alt: number;
    trash: number;
}

export interface UnusedSummary {
    files: number;
    bytes: number;
    never_used: number;
    removed: number;
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
