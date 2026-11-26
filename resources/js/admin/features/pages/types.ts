import type { PublishStatus } from '@admin/lib/content';

/** Payloads of packages/pages (docs/API.md §7). */
export interface PagePerson {
    id: number;
    name: string | null;
}

export interface PageRow {
    id: number;
    uuid: string;
    title: string;
    slug: string;
    path: string;
    parent_id: number | null;
    depth: number;
    template: string;
    template_label: string;
    status: PublishStatus;
    is_home: boolean;
    has_unpublished_changes: boolean;
    published_at: string | null;
    scheduled_at: string | null;
    updated_at: string | null;
    updated_by: PagePerson | null;
    created_by: PagePerson | null;
    deleted_at: string | null;
    /** List rows only. */
    seo_score?: number | null;
}

export type BlockData = Record<string, unknown>;

export interface PageBlock {
    id: number | null;
    type: string;
    data: BlockData;
    is_visible: boolean;
}

export interface PageDetail extends PageRow {
    lock_version: number;
    revisions_count: number;
    blocks: PageBlock[];
}

export type FieldType = 'text' | 'headline' | 'textarea' | 'markdown' | 'link' | 'image' | 'document' | 'list' | 'repeater' | 'select' | 'toggle' | 'source';

export interface BlockField {
    name: string;
    type: FieldType;
    label: string;
    required?: boolean;
    max?: number;
    help?: string;
    options?: { value: string; label: string }[];
    fields?: BlockField[];
    max_items?: number;
    source?: string;
    default?: unknown;
    group?: string;
}

export interface BlockTypeDef {
    key: string;
    label: string;
    icon: string;
    description: string;
    fields: BlockField[];
    defaults: BlockData;
}

export interface PageCounts {
    all: number;
    published: number;
    draft: number;
    scheduled: number;
    trash: number;
}

export interface Revision {
    id: number;
    kind: 'draft' | 'published' | 'scheduled' | 'restored';
    note: string | null;
    is_live: boolean;
    blocks_count: number;
    user: PagePerson | null;
    created_at: string;
}

/** A block in the editor: `key` is stable for React even before the block has an id. */
export interface EditorBlock extends PageBlock {
    key: string;
}
