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
        page_path: item.type === 'link' ? null : item.href?.split('#')[0] ?? null,
        page_title: item.page_title,
        anchor: item.anchor ?? '',
        url: item.url ?? '',
        is_visible: item.is_visible,
        children: (item.children ?? []).map(map),
    });

    return items.map(map);
}

export function toPayload(items: EditItem[]): Record<string, unknown>[] {
    const map = (item: EditItem, withChildren: boolean): Record<string, unknown> => ({
        label: item.label.trim(),
        type: item.type,
        ...(item.type !== 'link' ? { page_id: item.page_id } : {}),
        ...(item.type === 'anchor' ? { anchor: item.anchor.trim() } : {}),
        ...(item.type === 'link' ? { url: item.url.trim() } : {}),
        is_visible: item.is_visible,
        ...(withChildren ? { children: item.children.map((child) => map(child, false)) } : {}),
    });

    return items.map((item) => map(item, true));
}

/** A page or anchor item whose page has since been deleted: the website skips it and saving fails until it is re-pointed or removed. */
export function pageMissing(item: EditItem): boolean {
    return item.type !== 'link' && item.page_id !== null && item.page_path === null;
}

/** What the website will link to, for display. */
export function hrefOf(item: EditItem): string {
    if (item.type === 'link') return item.url;
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
        is_visible: true,
        children: [],
    };
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
