import { useEffect, useState } from 'react';
import { CornerDownRight, Ellipsis, ExternalLink, FileText, Pencil, Plus, RotateCcw, Trash2 } from 'lucide-react';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { ApiError } from '@shared/api/client';
import { relativeTime } from '@shared/format/time';
import { useTableParams } from '@shared/hooks/useTableParams';
import { useDebouncedValue } from '@shared/hooks/useDebouncedValue';
import { Button, ConfirmDialog, Count, DataTable, Dropdown, EmptyState, ErrorState, FilterBar, FilterSelect, Pagination, SearchInput, cn, useToast, type Column, type DropdownItem } from '@shared/ui';
import { PageHeader } from '@admin/components/PageHeader';
import { siteUrl } from '@admin/lib/content';
import { SeoScore } from '@admin/features/seo/parts';
import { usePages, useRestorePage, useTrashPage } from './api';
import { NewPageDialog } from './NewPageDialog';
import { StatusBadge, shortDateTime, shortName } from './statusBadge';
import type { PageCounts, PageRow } from './types';

const TABS: { value: string; label: string; count: keyof PageCounts }[] = [
    { value: '', label: 'All', count: 'all' },
    { value: 'published', label: 'Published', count: 'published' },
    { value: 'draft', label: 'Drafts', count: 'draft' },
    { value: 'scheduled', label: 'Scheduled', count: 'scheduled' },
    { value: 'trash', label: 'Trash', count: 'trash' },
];

/** Figma 06: every page on the website, its template, status and last edit. */
export function PagesPage() {
    const navigate = useNavigate();
    const toast = useToast();
    const table = useTableParams({ sort: 'path', perPage: 25 });
    const pages = usePages(table.query);
    const trash = useTrashPage();
    const restore = useRestorePage();
    const [searchParams] = useSearchParams();
    // The dashboard's "New page" opens straight into the dialog.
    const [creating, setCreating] = useState(() => searchParams.get('new') === '1');
    const [trashing, setTrashing] = useState<PageRow | null>(null);
    const [search, setSearch] = useState(table.search);
    const debounced = useDebouncedValue(search, 300);
    const status = table.filters.status ?? '';
    const meta = pages.data?.meta;
    const inTrash = status === 'trash';

    useEffect(() => {
        if (debounced !== table.search) table.setSearch(debounced);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only react to the debounced text
    }, [debounced]);

    const itemsFor = (page: PageRow): DropdownItem[] => {
        if (inTrash) {
            return [
                {
                    label: 'Restore',
                    icon: <RotateCcw className="size-4" aria-hidden="true" />,
                    onSelect: () =>
                        restore.mutate(page.id, {
                            onSuccess: () => toast.success('Page restored', page.title),
                            onError: (error) => toast.error('Couldn’t restore', error instanceof ApiError ? error.humanMessage : undefined),
                        }),
                },
            ];
        }

        const url = siteUrl(page.path);
        const items: DropdownItem[] = [{ label: 'Edit', icon: <Pencil className="size-4" aria-hidden="true" />, onSelect: () => navigate(`/pages/${page.id}`) }];

        if (url && page.status === 'published') items.push({ label: 'View on site', icon: <ExternalLink className="size-4" aria-hidden="true" />, onSelect: () => window.open(url, '_blank', 'noopener') });
        if (!page.is_home) items.push({ label: 'Move to Trash', icon: <Trash2 className="size-4" aria-hidden="true" />, tone: 'danger', onSelect: () => setTrashing(page) });

        return items;
    };

    const columns: Column<PageRow>[] = [
        {
            key: 'title',
            header: 'Title',
            sortKey: 'title',
            cell: (page) => (
                <div className="flex min-w-0 items-start gap-2" style={{ paddingLeft: `${Math.max(0, page.depth) * 20}px` }}>
                    {page.depth > 0 && <CornerDownRight className="mt-1 size-4 shrink-0 text-ink-soft" aria-hidden="true" />}
                    <div className="flex min-w-0 flex-col">
                        {inTrash ? (
                            <span className="truncate font-semibold text-ink">{page.title}</span>
                        ) : (
                            <Link to={`/pages/${page.id}`} className="truncate font-semibold text-ink hover:text-royal">
                                {page.title}
                            </Link>
                        )}
                        <span className="truncate text-admin-small text-ink-soft">{page.path}</span>
                    </div>
                </div>
            ),
        },
        { key: 'template', header: 'Template', className: 'w-[140px]', cell: (page) => page.template_label },
        { key: 'seo', header: 'SEO', className: 'w-[90px]', cell: (page) => <SeoScore score={inTrash ? null : page.seo_score} /> },
        {
            key: 'status',
            header: 'Status',
            sortKey: 'status',
            className: 'w-[170px]',
            cell: (page) => (
                <div className="flex flex-col items-start gap-1">
                    <StatusBadge status={page.status} />
                    {page.scheduled_at && <span className="text-admin-small text-ink-soft">{page.status === 'published' ? 'Update ' : ''}{shortDateTime(page.scheduled_at)}</span>}
                    {page.status === 'published' && page.has_unpublished_changes && !page.scheduled_at && <span className="text-admin-small text-warning">Unpublished changes</span>}
                </div>
            ),
        },
        {
            key: 'updated',
            header: 'Updated',
            sortKey: 'updated_at',
            className: 'w-[150px]',
            cell: (page) => (
                <div className="flex flex-col">
                    <span className="text-ink">{relativeTime(inTrash ? page.deleted_at : page.updated_at)}</span>
                    <span className="text-admin-small text-ink-soft">{shortName(page.updated_by?.name)}</span>
                </div>
            ),
        },
        {
            key: 'actions',
            header: <span className="sr-only">Actions</span>,
            className: 'w-[64px]',
            align: 'end',
            cell: (page) => (
                <Dropdown
                    items={itemsFor(page)}
                    trigger={({ ref, ...props }) => (
                        <button ref={ref} type="button" {...props} aria-label={`Actions for ${page.title}`} className="rounded-full p-2 text-ink hover:bg-paper">
                            <Ellipsis className="size-[18px]" aria-hidden="true" />
                        </button>
                    )}
                />
            ),
        },
    ];

    return (
        <>
            <PageHeader
                trail={['Content', 'Pages']}
                title=""
                accent="Pages."
                description="Every page on uptms.com. Build pages from the same sections as the website, then check SEO before you publish."
                actions={
                    <Button icon={<Plus className="size-[18px]" aria-hidden="true" />} onClick={() => setCreating(true)}>
                        New page
                    </Button>
                }
            />

            <div role="tablist" aria-label="Page status" className="flex w-full gap-7 overflow-x-auto border-b border-line">
                {TABS.map((tab) => {
                    const active = status === tab.value;

                    return (
                        <button
                            key={tab.value || 'all'}
                            type="button"
                            role="tab"
                            aria-selected={active}
                            onClick={() => table.setFilter('status', tab.value || null)}
                            className={cn(
                                '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-0.5 pt-2.5 pb-3 text-admin-body font-semibold transition-colors',
                                active ? 'border-royal text-royal' : 'border-transparent text-ink-soft hover:text-ink',
                            )}
                        >
                            {tab.label}
                            {meta && <Count active={active}>{meta.counts[tab.count]}</Count>}
                        </button>
                    );
                })}
            </div>

            <FilterBar>
                <SearchInput label="Search pages" placeholder="Search pages" value={search} onChange={(event) => setSearch(event.target.value)} className="w-full sm:w-[320px]" />
                <FilterSelect label="Template" value={table.filters.template ?? ''} onChange={(event) => table.setFilter('template', event.target.value || null)}>
                    <option value="">All templates</option>
                    {(meta?.templates ?? []).map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </FilterSelect>
                <FilterSelect label="Author" value={table.filters.author ?? ''} onChange={(event) => table.setFilter('author', event.target.value || null)}>
                    <option value="">Any author</option>
                    {(meta?.authors ?? []).map((author) => (
                        <option key={author.id} value={author.id}>
                            {author.name}
                        </option>
                    ))}
                </FilterSelect>
            </FilterBar>

            <DataTable
                caption="Pages"
                columns={columns}
                rows={pages.data?.pages}
                rowKey={(page) => page.id}
                loading={pages.isPending}
                sort={table.sort}
                onSort={table.setSort}
                rowClassName="h-[64px]"
                error={pages.isError ? <ErrorState title="Pages didn’t load" description={pages.error instanceof ApiError ? pages.error.humanMessage : 'Try again in a moment.'} requestId={pages.error instanceof ApiError ? pages.error.requestId : null} onRetry={() => void pages.refetch()} /> : undefined}
                empty={<EmptyState icon={<FileText className="size-5" aria-hidden="true" />} title={inTrash ? 'Trash is empty' : 'No pages match'} description={inTrash ? 'Pages you move to Trash can be restored from here.' : 'Try another search or filter.'} />}
            />
            {meta && <Pagination meta={meta} onPage={table.setPage} onPerPage={table.setPerPage} noun="pages" />}

            <NewPageDialog open={creating} onClose={() => setCreating(false)} templates={meta?.templates ?? []} />
            <ConfirmDialog
                open={trashing !== null}
                title={`Move “${trashing?.title ?? ''}” to Trash?`}
                description={trashing?.status === 'published' ? 'It comes off the website at once. You can restore it from Trash.' : 'You can restore it from Trash later.'}
                confirmLabel="Move to Trash"
                busy={trash.isPending}
                error={trash.error instanceof ApiError ? trash.error.humanMessage : null}
                onClose={() => {
                    setTrashing(null);
                    trash.reset();
                }}
                onConfirm={() => {
                    if (!trashing) return;
                    trash.mutate(trashing.id, {
                        onSuccess: () => {
                            toast.success('Moved to Trash', trashing.title);
                            setTrashing(null);
                        },
                    });
                }}
            />
        </>
    );
}
