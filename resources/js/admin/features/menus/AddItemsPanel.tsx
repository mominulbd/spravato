import { useState } from 'react';
import { Plus } from 'lucide-react';
import { Button, Checkbox, SearchInput, Skeleton, TextInput, cn } from '@shared/ui';
import { linkItem, pageItem } from './menuTree';
import type { EditItem, PageOption } from './types';

type Tab = 'pages' | 'posts' | 'link';

/** Right column of Figma 17: add pages (checklist) or a custom link. */
export function AddItemsPanel({ pages, loading, disabled, onAdd }: { pages: PageOption[]; loading: boolean; disabled: boolean; onAdd: (items: EditItem[]) => void }) {
    const [tab, setTab] = useState<Tab>('pages');
    const [search, setSearch] = useState('');
    const [checked, setChecked] = useState<number[]>([]);
    const [label, setLabel] = useState('');
    const [url, setUrl] = useState('');
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
                        ['posts', 'Posts'],
                        ['link', 'Link'],
                    ] as const
                ).map(([value, text]) => (
                    <button
                        key={value}
                        type="button"
                        role="tab"
                        aria-selected={tab === value}
                        disabled={value === 'posts'}
                        title={value === 'posts' ? 'Posts arrive in Phase 4' : undefined}
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
