import { useState } from 'react';
import { Button, Dialog, Select, TextInput, Toggle } from '@shared/ui';
import { CONTENT_TOKENS_HELP } from '@admin/lib/content';
import type { EditItem, MenuItemType, PageOption } from './types';

/** Edit one menu link: label, what it points at, visibility. */
export function ItemDialog({ item, pages, onClose, onSave }: { item: EditItem; pages: PageOption[]; onClose: () => void; onSave: (item: EditItem) => void }) {
    const [value, setValue] = useState(item);
    const set = <K extends keyof EditItem>(key: K, next: EditItem[K]) => setValue((current) => ({ ...current, [key]: next }));
    const page = pages.find((option) => option.id === value.page_id);
    const valid =
        value.label.trim() !== '' && (value.type === 'link' ? value.url.trim() !== '' : value.page_id !== null) && (value.type !== 'anchor' || /^[a-z0-9-]+$/.test(value.anchor));

    return (
        <Dialog
            open
            size="md"
            onClose={onClose}
            title="Edit menu item"
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button disabled={!valid} onClick={() => onSave(value)}>
                        Apply
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4">
                <TextInput label="Label" maxLength={80} value={value.label} onChange={(event) => set('label', event.target.value)} />
                <Select label="Links to" value={value.type} onChange={(event) => set('type', event.target.value as MenuItemType)}>
                    <option value="page">A page</option>
                    <option value="anchor">A section of a page</option>
                    <option value="link">Any address</option>
                </Select>
                {value.type !== 'link' && (
                    <Select
                        label="Page"
                        value={value.page_id ?? ''}
                        help="Links follow the page if its address changes."
                        onChange={(event) => {
                            const next = pages.find((option) => option.id === Number(event.target.value));
                            setValue((current) => ({
                                ...current,
                                page_id: next?.id ?? null,
                                page_path: next?.path ?? null,
                                page_title: next?.title ?? null,
                            }));
                        }}
                    >
                        <option value="" disabled>
                            Choose a page
                        </option>
                        {pages.map((option) => (
                            <option key={option.id} value={option.id}>
                                {option.title} ({option.path}){option.status !== 'published' ? ' · not live' : ''}
                            </option>
                        ))}
                    </Select>
                )}
                {value.type === 'anchor' && (
                    <TextInput
                        label="Section anchor"
                        value={value.anchor}
                        maxLength={40}
                        onChange={(event) => set('anchor', event.target.value.toLowerCase())}
                        help={`Links to ${page?.path ?? '/page'}#${value.anchor || 'anchor'} — the anchor set on that section.`}
                    />
                )}
                {value.type === 'link' && (
                    <TextInput
                        label="Address"
                        value={value.url}
                        maxLength={190}
                        placeholder="/contact or https://"
                        onChange={(event) => set('url', event.target.value)}
                        help={`A page, #anchor, https://, tel: or mailto:. ${CONTENT_TOKENS_HELP}`}
                    />
                )}
                <Toggle
                    label="Visible"
                    description={value.is_visible ? 'Shown on the website.' : 'Kept in the menu but hidden from visitors.'}
                    checked={value.is_visible}
                    onChange={(event) => set('is_visible', event.target.checked)}
                />
                {page && page.status !== 'published' && <p className="text-admin-small text-warning">This page isn’t published, so the link stays hidden until it is.</p>}
            </div>
        </Dialog>
    );
}
