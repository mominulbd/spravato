import type { MediaItem } from '@admin/features/media/types';
import type { EditItem, MenuItemData, PageOption } from './types';

/** Pure edits of a menu tree (one level of children, Figma 17). */
let counter = 0;
export const newUid = () => `n${++counter}`;

export function fromServer(items: MenuItemData[]): EditItem[] {
    const map = (item: MenuItemData): EditItem => ({
        uid: `s${item.id}`,
        label: item.label,
        type: item.type,
        page_id: item.page_id,
        page_path: onPage(item.type) ? (item.href?.split('#')[0] ?? null) : null,
        page_title: item.page_title,
        anchor: item.anchor ?? '',
        url: item.url ?? '',
        media_id: item.media_id ?? null,
        document_name: item.document_filename ?? null,
        document_href: item.type === 'document' ? item.href : null,
        is_visible: item.is_visible,
        children: (item.children ?? []).map(map),
    });

    return items.map(map);
}

export function toPayload(items: EditItem[]): Record<string, unknown>[] {
    const map = (item: EditItem, withChildren: boolean): Record<string, unknown> => ({
        label: item.label.trim(),
        type: item.type,
        ...(onPage(item.type) ? { page_id: item.page_id } : {}),
        ...(item.type === 'anchor' ? { anchor: item.anchor.trim() } : {}),
        ...(item.type === 'link' ? { url: item.url.trim() } : {}),
        ...(item.type === 'document' ? { media_id: item.media_id } : {}),
        is_visible: item.is_visible,
        ...(withChildren ? { children: item.children.map((child) => map(child, false)) } : {}),
    });

    return items.map((item) => map(item, true));
}

/** A page or anchor item whose page has since been deleted: the website skips it and saving fails until it is re-pointed or removed. */
export function pageMissing(item: EditItem): boolean {
    return onPage(item.type) && item.page_id !== null && item.page_path === null;
}

/** A document item whose file was trashed or purged: the website skips it until another document is chosen. */
export function documentMissing(item: EditItem): boolean {
    return item.type === 'document' && (item.media_id === null || item.document_name === null);
}

function onPage(type: EditItem['type']): boolean {
    return type === 'page' || type === 'anchor';
}

/** What the website will link to, for display. */
export function hrefOf(item: EditItem): string {
    if (item.type === 'link') return item.url;
    if (item.type === 'document') return item.document_href ?? item.document_name ?? '';
    const path = item.page_path ?? '?';

    return item.type === 'anchor' ? `${path}#${item.anchor}` : path;
}

export function pageItem(page: PageOption): EditItem {
    return {
        uid: newUid(),
        label: page.title,
        type: 'page',
        page_id: page.id,
        page_path: page.path,
        page_title: page.title,
        anchor: '',
        url: '',
        media_id: null,
        document_name: null,
        document_href: null,
        is_visible: true,
        children: [],
    };
}

/** An official document (PDF) from the media library. */
export function documentItem(label: string, media: Pick<MediaItem, 'id' | 'filename' | 'url'>): EditItem {
    return {
        uid: newUid(),
        label,
        type: 'document',
        page_id: null,
        page_path: null,
        page_title: null,
        anchor: '',
        url: '',
        ...documentFields(media),
        is_visible: true,
        children: [],
    };
}

/** The document fields of an item, from a library file. */
export function documentFields(media: Pick<MediaItem, 'id' | 'filename' | 'url'>): Pick<EditItem, 'media_id' | 'document_name' | 'document_href'> {
    let href = media.url;

    try {
        href = new URL(media.url, 'http://localhost').pathname;
    } catch {
        // keep the address as given
    }

    return { media_id: media.id, document_name: media.filename, document_href: href };
}

export function linkItem(label: string, url: string): EditItem {
    return {
        uid: newUid(),
        label,
        type: 'link',
        page_id: null,
        page_path: null,
        page_title: null,
        anchor: '',
        url,
        media_id: null,
        document_name: null,
        document_href: null,
        is_visible: true,
        children: [],
    };
}

/** Replace one item anywhere in the tree. */
export function updateItem(items: EditItem[], uid: string, patch: Partial<EditItem>): EditItem[] {
    return items.map((item) => (item.uid === uid ? { ...item, ...patch } : { ...item, children: updateItem(item.children, uid, patch) }));
}

export function removeItem(items: EditItem[], uid: string): EditItem[] {
    return items.filter((item) => item.uid !== uid).map((item) => ({ ...item, children: removeItem(item.children, uid) }));
}

/** Make a top-level item a child of the one above it (its own children move up with it). */
export function indent(items: EditItem[], uid: string): EditItem[] {
    const index = items.findIndex((item) => item.uid === uid);
    const target = items[index];
    const parent = items[index - 1];

    if (index < 1 || !target || !parent) return items;

    const moved = { ...target, children: [] };
    const next = items.filter((item) => item.uid !== uid);

    return next.map((item) =>
        item.uid === parent.uid
            ? {
                  ...item,
                  children: [...item.children, moved, ...target.children],
              }
            : item
    );
}

/** Move a child out to the top level, right after its parent. */
export function outdent(items: EditItem[], uid: string): EditItem[] {
    const parentIndex = items.findIndex((item) => item.children.some((child) => child.uid === uid));
    const parent = items[parentIndex];
    const child = parent?.children.find((item) => item.uid === uid);

    if (!parent || !child) return items;

    const next = items.map((item, index) => (index === parentIndex ? { ...item, children: item.children.filter((c) => c.uid !== uid) } : item));
    next.splice(parentIndex + 1, 0, child);

    return next;
}

export function moveChild(items: EditItem[], parentUid: string, from: number, to: number): EditItem[] {
    return items.map((item) => {
        if (item.uid !== parentUid) return item;
        const children = [...item.children];
        const [moved] = children.splice(from, 1);
        if (moved) children.splice(to, 0, moved);

        return { ...item, children };
    });
}

export function countItems(items: EditItem[]): number {
    return items.reduce((total, item) => total + 1 + item.children.length, 0);
}
