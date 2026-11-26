import { describe, expect, it } from 'vitest';
import { documentItem, documentMissing, fromServer, hrefOf, indent, linkItem, outdent, pageItem, pageMissing, removeItem, toPayload } from './menuTree';
import type { EditItem, MenuItemData } from './types';

const server = (id: number, label: string, extra: Partial<MenuItemData> = {}): MenuItemData => ({
    id,
    label,
    type: 'page',
    page_id: id,
    page_title: label,
    page_published: true,
    anchor: null,
    url: null,
    href: `/${label.toLowerCase()}`,
    is_visible: true,
    children: [],
    ...extra,
});

function at(items: EditItem[], index: number): EditItem {
    const item = items[index];
    if (!item) throw new Error(`No item at ${index}`);

    return item;
}

describe('menu tree edits', () => {
    it('round-trips server items into the save payload', () => {
        const items = fromServer([
            server(1, 'TMS', {
                children: [
                    server(2, 'Basics', {
                        type: 'anchor',
                        anchor: 'basics',
                        href: '/tms#basics',
                    }),
                ],
            }),
        ]);

        expect(hrefOf(at(at(items, 0).children, 0))).toBe('/tms#basics');
        expect(toPayload(items)).toEqual([
            {
                label: 'TMS',
                type: 'page',
                page_id: 1,
                is_visible: true,
                children: [
                    {
                        label: 'Basics',
                        type: 'anchor',
                        page_id: 2,
                        anchor: 'basics',
                        is_visible: true,
                    },
                ],
            },
        ]);
    });

    it('indents under the item above and outdents back after the parent', () => {
        const items = [pageItem({ id: 1, title: 'Home', path: '/', status: 'published' }), linkItem('Call', 'tel:clinic'), linkItem('Map', 'https://maps.example')];
        const indented = indent(items, at(items, 1).uid);

        expect(indented).toHaveLength(2);
        expect(at(indented, 0).children.map((child) => child.label)).toEqual(['Call']);
        expect(indent(items, at(items, 0).uid)).toBe(items);
        expect(outdent(indented, at(items, 1).uid).map((item) => item.label)).toEqual(['Home', 'Call', 'Map']);
    });

    it('removes items at any depth', () => {
        const items = [linkItem('A', '/a'), linkItem('B', '/b')];
        const tree = indent(items, at(items, 1).uid);

        expect(at(removeItem(tree, at(at(tree, 0).children, 0).uid), 0).children).toEqual([]);
    });

    it('flags a page item whose page was deleted (no href from the server), not links or live pages', () => {
        const items = fromServer([
            { id: 1, label: 'Privacy policy', type: 'page', page_id: 9, page_title: null, page_published: null, anchor: null, url: null, href: null, is_visible: true },
            { id: 2, label: 'Home', type: 'page', page_id: 1, page_title: 'Home', page_published: true, anchor: null, url: null, href: '/', is_visible: true },
            { id: 3, label: 'Main site', type: 'link', page_id: null, page_title: null, page_published: null, anchor: null, url: 'https://uptms.com', href: 'https://uptms.com', is_visible: true },
        ]);

        expect(items.map(pageMissing)).toEqual([true, false, false]);
    });

    it('keeps documents by media id and flags one that left the library', () => {
        const added = documentItem('Medication Guide', { id: 7, filename: 'guide.pdf', url: 'http://localhost:8000/storage/media/u/1-guide.pdf' });

        expect(hrefOf(added)).toBe('/storage/media/u/1-guide.pdf');
        expect(toPayload([added])).toEqual([{ label: 'Medication Guide', type: 'document', media_id: 7, is_visible: true, children: [] }]);

        const items = fromServer([
            { id: 1, label: 'Guide', type: 'document', page_id: null, page_title: null, page_published: null, anchor: null, url: null, media_id: 7, document_filename: 'guide.pdf', href: '/storage/media/u/2-guide.pdf', is_visible: true },
            { id: 2, label: 'Gone', type: 'document', page_id: null, page_title: null, page_published: null, anchor: null, url: null, media_id: 8, document_filename: null, href: null, is_visible: true },
        ]);

        expect(items.map(documentMissing)).toEqual([false, true]);
        expect(items.map(pageMissing)).toEqual([false, false]);
        expect(hrefOf(at(items, 0))).toBe('/storage/media/u/2-guide.pdf');
    });
});
