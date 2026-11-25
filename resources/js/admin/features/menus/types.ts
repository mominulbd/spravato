/** Uptms\Menus\Services\MenuTree payloads. */
export type MenuItemType = 'page' | 'anchor' | 'link';

export interface MenuSummary {
    key: string;
    name: string;
    description: string | null;
    items_count: number;
}

export interface MenuItemData {
    id: number;
    label: string;
    type: MenuItemType;
    page_id: number | null;
    page_title: string | null;
    page_published: boolean | null;
    anchor: string | null;
    url: string | null;
    href: string | null;
    is_visible: boolean;
    /** Only top-level items carry children (one level deep). */
    children?: MenuItemData[];
}

export interface MenuDetail {
    key: string;
    name: string;
    description: string | null;
    items: MenuItemData[];
    preview: {
        label: string;
        href: string;
        children: { label: string; href: string }[];
    }[];
}

/** One row being edited; `uid` is local so new rows have a stable key. */
export interface EditItem {
    uid: string;
    label: string;
    type: MenuItemType;
    page_id: number | null;
    /** Address of the linked page, for display (null for links). */
    page_path: string | null;
    page_title: string | null;
    anchor: string;
    url: string;
    is_visible: boolean;
    children: EditItem[];
}

export interface PageOption {
    id: number;
    title: string;
    path: string;
    status: string;
}
