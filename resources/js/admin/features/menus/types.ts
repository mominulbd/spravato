/** Uptms\Menus\Services\MenuTree payloads. */
/** `document`: a PDF from the media library, linked by id so a replaced file keeps working. */
export type MenuItemType = 'page' | 'anchor' | 'link' | 'document';

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
    media_id?: number | null;
    /** Null when the document is trashed or gone (the website skips the item). */
    document_filename?: string | null;
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
    media_id: number | null;
    /** File name of the linked document, for display (null when it is gone). */
    document_name: string | null;
    /** Current address of the document, for display. */
    document_href: string | null;
    is_visible: boolean;
    children: EditItem[];
}

export interface PageOption {
    id: number;
    title: string;
    path: string;
    status: string;
}
