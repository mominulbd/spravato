import { useState } from 'react';
import { FileText, Plus } from 'lucide-react';
import { Button, Checkbox, SearchInput, Skeleton, TextInput, cn } from '@shared/ui';
import { MediaPicker } from '@admin/features/media/MediaPicker';
import type { MediaItem } from '@admin/features/media/types';
import { documentItem, linkItem, pageItem } from './menuTree';
import type { EditItem, PageOption } from './types';

type Tab = 'pages' | 'document' | 'link';

/** Right column of Figma 17: add pages (checklist), an official document (PDF) or a custom link. */
export function AddItemsPanel({ pages, loading, disabled, onAdd }: { pages: PageOption[]; loading: boolean; disabled: boolean; onAdd: (items: EditItem[]) => void }) {
    const [tab, setTab] = useState<Tab>('pages');
    const [search, setSearch] = useState('');
    const [checked, setChecked] = useState<number[]>([]);
    const [label, setLabel] = useState('');
    const [url, setUrl] = useState('');
    const [document, setDocument] = useState<MediaItem | null>(null);
    const [picking, setPicking] = useState(false);
    const term = search.trim().toLowerCase();
    const shown = term === '' ? pages : pages.filter((page) => `${page.title} ${page.path}`.toLowerCase().includes(term));
    const urlValid = /^(\/(?!\/)|#|https:\/\/|tel:|mailto:)/.test(url.trim());

    return (
        <section className="flex flex-col gap-4 rounded-sheet border border-line bg-white p-6">
            <h2 className="text-admin-card-title font-semibold text-ink">Add items</h2>
            <div role="tablist" aria-label="Add items from" className="grid grid-cols-3 gap-1 rounded-full bg-paper p-1">
                {(
                    [
                        ['pages', 'Pages'],
                        ['document', 'Document'],
                        ['link', 'Link'],
                    ] as const
                ).map(([value, text]) => (
                    <button
                        key={value}
                        type="button"
                        role="tab"
                        aria-selected={tab === value}
                        onClick={() => setTab(value)}
                        className={cn(
                            'rounded-full py-1.5 text-admin-body font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-40',
                            tab === value ? 'border border-line bg-white text-ink shadow-sm' : 'text-ink-soft hover:text-ink'
                        )}
                    >
                        {text}
                    </button>
                ))}
            </div>

            {tab === 'pages' ? (
                <>
                    <SearchInput label="Search pages" placeholder="Search pages" value={search} onChange={(event) => setSearch(event.target.value)} />
                    <div className="flex max-h-72 flex-col gap-3 overflow-y-auto pr-1">
                        {loading
                            ? Array.from({ length: 4 }, (_, index) => <Skeleton key={index} className="h-5 w-40" />)
                            : shown.map((page) => (
                                  <Checkbox
                                      key={page.id}
                                      label={page.title}
                                      description={`${page.path}${page.status !== 'published' ? ' · not live' : ''}`}
                                      checked={checked.includes(page.id)}
                                      disabled={disabled}
                                      onChange={(event) => setChecked(event.target.checked ? [...checked, page.id] : checked.filter((id) => id !== page.id))}
                                  />
                              ))}
                        {!loading && shown.length === 0 && <p className="text-admin-small text-ink-soft">No pages match.</p>}
                    </div>
                    <Button
                        variant="navy"
                        icon={<Plus className="size-4" />}
                        disabled={disabled || checked.length === 0}
                        onClick={() => {
                            onAdd(pages.filter((page) => checked.includes(page.id)).map(pageItem));
                            setChecked([]);
                        }}
                    >
                        {checked.length === 0 ? 'Add to menu' : `Add ${checked.length} to menu`}
                    </Button>
                </>
            ) : tab === 'document' ? (
                <form
                    className="flex flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (!document) return;
                        onAdd([documentItem(label.trim(), document)]);
                        setLabel('');
                        setDocument(null);
                    }}
                >
                    <TextInput label="Label" maxLength={80} value={label} disabled={disabled} onChange={(event) => setLabel(event.target.value)} />
                    <div className="flex items-center gap-3 rounded-card border border-line bg-white p-3">
                        <FileText className="size-5 shrink-0 text-royal" aria-hidden="true" />
                        <p className="min-w-0 flex-1 truncate text-admin-body text-ink">{document?.filename ?? 'No document chosen.'}</p>
                        <Button size="sm" variant="secondary" disabled={disabled} onClick={() => setPicking(true)}>
                            {document ? 'Change' : 'Choose'}
                        </Button>
                    </div>
                    <p className="text-admin-small text-ink-soft">Links to the file in the media library, so replacing the PDF keeps the link working.</p>
                    <Button type="submit" variant="navy" icon={<Plus className="size-4" />} disabled={disabled || label.trim() === '' || document === null}>
                        Add document to menu
                    </Button>
                    {picking && (
                        <MediaPicker
                            open
                            kind="document"
                            title="Choose a document"
                            onClose={() => setPicking(false)}
                            onSelect={(picked) => {
                                setDocument(picked);
                                if (label.trim() === '') setLabel(picked.filename.replace(/\.pdf$/i, ''));
                            }}
                        />
                    )}
                </form>
            ) : (
                <form
                    className="flex flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        onAdd([linkItem(label.trim(), url.trim())]);
                        setLabel('');
                        setUrl('');
                    }}
                >
                    <TextInput label="Label" maxLength={80} value={label} disabled={disabled} onChange={(event) => setLabel(event.target.value)} />
                    <TextInput
                        label="Address"
                        maxLength={190}
                        placeholder="https:// or /path"
                        value={url}
                        disabled={disabled}
                        onChange={(event) => setUrl(event.target.value)}
                        error={url !== '' && !urlValid ? 'Start with /, #, https://, tel: or mailto:' : undefined}
                    />
                    <Button type="submit" variant="navy" icon={<Plus className="size-4" />} disabled={disabled || label.trim() === '' || !urlValid}>
                        Add link to menu
                    </Button>
                </form>
            )}
        </section>
    );
}
