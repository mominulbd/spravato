import { useEffect, useState } from 'react';
import { Check, Download, Ellipsis, Inbox, TriangleAlert, Users } from 'lucide-react';
import { Link, useNavigate } from 'react-router';
import { ApiError } from '@shared/api/client';
import { useDebouncedValue } from '@shared/hooks/useDebouncedValue';
import { useTableParams } from '@shared/hooks/useTableParams';
import { Avatar, Badge, Button, Count, DataTable, Dropdown, EmptyState, ErrorState, FilterBar, FilterSelect, Pagination, SearchInput, cn, useToast, type Column, type DropdownItem } from '@shared/ui';
import { PageHeader } from '@admin/components/PageHeader';
import { exportUrl, useBulkConsultations, useConsultations, type BulkAction } from './api';
import { receivedLabel, shortStaffName, statusBadge, waitingLabel, waitingTone } from './format';
import type { ConsultationRow, StatusCounts } from './types';

const TABS: { value: string; label: string; count: keyof StatusCounts }[] = [
    { value: '', label: 'All', count: 'all' },
    { value: 'new', label: 'New', count: 'new' },
    { value: 'contacted', label: 'Contacted', count: 'contacted' },
    { value: 'scheduled', label: 'Scheduled', count: 'scheduled' },
    { value: 'closed', label: 'Closed', count: 'closed' },
    { value: 'spam', label: 'Spam', count: 'spam' },
];

const TONE_TEXT = { danger: 'font-bold text-danger', warning: 'font-bold text-warning' } as const;

/**
 * Figma 04: the consultation inbox. Contact details are masked here; the
 * full details are on the request itself, where every opening is logged.
 */
export function ConsultationsPage() {
    const navigate = useNavigate();
    const toast = useToast();
    const table = useTableParams({ sort: '-received_at', perPage: 10 });
    const list = useConsultations(table.query);
    const bulk = useBulkConsultations();
    const [search, setSearch] = useState(table.search);
    const debounced = useDebouncedValue(search, 300);
    const [picked, setSelected] = useState<number[]>([]);
    const status = table.filters.status ?? '';
    const meta = list.data?.meta;
    const rows = list.data?.rows;
    const canUpdate = meta?.can.update ?? false;
    const filtered = Object.keys(table.filters).some((key) => key !== 'status') || table.search !== '';

    useEffect(() => {
        if (debounced !== table.search) table.setSearch(debounced);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only react to the debounced text
    }, [debounced]);

    const run = (body: BulkAction, done: string) =>
        bulk.mutate(body, {
            onSuccess: (response) => {
                const { updated, skipped } = response.data;
                toast.success(done, skipped > 0 ? `${updated} updated · ${skipped} couldn’t change from their current status` : `${updated} request${updated === 1 ? '' : 's'} updated`);
                setSelected([]);
            },
            onError: (error) => toast.error('Couldn’t update the requests', error instanceof ApiError ? error.humanMessage : undefined),
        });

    const rowItems = (row: ConsultationRow): DropdownItem[] => {
        const items: DropdownItem[] = [{ label: 'Open request', onSelect: () => navigate(`/consultations/${row.id}`) }];

        if (canUpdate && row.allowed_statuses.includes('contacted')) items.push({ label: 'Mark contacted', icon: <Check className="size-4" aria-hidden="true" />, onSelect: () => run({ action: 'contacted', ids: [row.id] }, 'Marked as contacted') });
        if (canUpdate && row.allowed_statuses.includes('spam')) items.push({ label: 'Mark as spam', icon: <TriangleAlert className="size-4" aria-hidden="true" />, tone: 'danger', onSelect: () => run({ action: 'spam', ids: [row.id] }, 'Marked as spam') });

        return items;
    };

    const allOnPage = rows?.map((row) => row.id) ?? [];
    // A selection only ever covers rows on screen: paging or filtering drops the rest.
    const selected = picked.filter((id) => allOnPage.includes(id));
    const allSelected = allOnPage.length > 0 && allOnPage.every((id) => selected.includes(id));
    const toggle = (id: number) => setSelected((current) => (current.includes(id) ? current.filter((value) => value !== id) : [...current, id]));

    const columns: Column<ConsultationRow>[] = [
        ...(canUpdate
            ? [
                  {
                      key: 'select',
                      header: <SelectBox checked={allSelected} label="Select every request on this page" onChange={() => setSelected(allSelected ? [] : allOnPage)} />,
                      className: 'w-[52px] !pr-0',
                      cell: (row: ConsultationRow) => <SelectBox checked={selected.includes(row.id)} label={`Select ${row.reference}`} onChange={() => toggle(row.id)} />,
                  },
              ]
            : []),
        {
            key: 'request',
            header: 'Request',
            cell: (row) => (
                <div className="flex min-w-0 flex-col">
                    <Link to={`/consultations/${row.id}`} className="truncate font-semibold text-ink hover:text-royal">
                        {row.topic.label}
                    </Link>
                    <span className="flex flex-wrap items-center gap-x-2 gap-y-1 text-admin-small text-ink-soft">
                        {row.reference}
                        {row.origin === 'quiz' && <Badge tone="info">Self-check</Badge>}
                    </span>
                </div>
            ),
        },
        { key: 'contact', header: 'Contact', className: 'w-[210px]', cell: (row) => <span className="whitespace-nowrap">{row.contact}</span> },
        { key: 'received', header: 'Received', sortKey: 'received_at', className: 'w-[160px]', cell: (row) => <span className="whitespace-nowrap">{receivedLabel(row.received_at)}</span> },
        {
            key: 'waiting',
            header: 'Waiting',
            className: 'w-[110px]',
            cell: (row) => {
                if (row.waiting_minutes === null) return <span className="text-ink-soft">—</span>;
                const tone = waitingTone(row);

                return <span className={cn('whitespace-nowrap', tone && TONE_TEXT[tone])}>{waitingLabel(row.waiting_minutes)}</span>;
            },
        },
        {
            key: 'assigned',
            header: 'Assigned',
            className: 'w-[140px]',
            cell: (row) =>
                row.assigned_to ? (
                    <span className="flex items-center gap-2 whitespace-nowrap">
                        <Avatar name={row.assigned_to.name} size="sm" />
                        {shortStaffName(row.assigned_to.name)}
                    </span>
                ) : (
                    <span className="text-ink-soft">Unassigned</span>
                ),
        },
        {
            key: 'status',
            header: 'Status',
            className: 'w-[120px]',
            cell: (row) => {
                const badge = statusBadge(row);

                return (
                    <Badge tone={badge.tone} dot>
                        {badge.label}
                    </Badge>
                );
            },
        },
        {
            key: 'actions',
            header: <span className="sr-only">Actions</span>,
            className: 'w-[56px]',
            align: 'end',
            cell: (row) => (
                <Dropdown
                    items={rowItems(row)}
                    trigger={({ ref, ...props }) => (
                        <button ref={ref} type="button" {...props} aria-label={`Actions for ${row.reference}`} className="rounded-full p-2 text-ink hover:bg-paper">
                            <Ellipsis className="size-[18px]" aria-hidden="true" />
                        </button>
                    )}
                />
            ),
        },
    ];

    const assignItems: DropdownItem[] = [
        { label: 'Unassigned', onSelect: () => run({ action: 'assign', ids: selected, assigned_to: null }, 'Assignment removed') },
        ...(meta?.assignees ?? []).map((person) => ({ label: person.name, onSelect: () => run({ action: 'assign', ids: selected, assigned_to: person.id }, `Assigned to ${person.name}`) })),
    ];

    const toolbar =
        selected.length > 0 ? (
            <div className="flex flex-wrap items-center justify-between gap-3 bg-navy px-6 py-3 text-white" role="region" aria-label="Bulk actions">
                <p className="text-admin-body font-semibold" aria-live="polite">
                    {selected.length} selected
                </p>
                <div className="flex flex-wrap items-center gap-2">
                    {meta?.can.assign && (
                        <Dropdown
                            items={assignItems}
                            trigger={({ ref, ...props }) => (
                                <button ref={ref} type="button" {...props} disabled={bulk.isPending} className={BAR_BUTTON}>
                                    <Users className="size-4" aria-hidden="true" />
                                    Assign
                                </button>
                            )}
                        />
                    )}
                    <button type="button" disabled={bulk.isPending} className={BAR_BUTTON} onClick={() => run({ action: 'contacted', ids: selected }, 'Marked as contacted')}>
                        <Check className="size-4" aria-hidden="true" />
                        Mark contacted
                    </button>
                    <button type="button" disabled={bulk.isPending} className={BAR_BUTTON} onClick={() => run({ action: 'spam', ids: selected }, 'Marked as spam')}>
                        <TriangleAlert className="size-4" aria-hidden="true" />
                        Mark as spam
                    </button>
                </div>
            </div>
        ) : undefined;

    return (
        <>
            <PageHeader
                trail={['Consultations']}
                title="Consultation"
                accent="requests."
                description="From the website’s contact form. Contact details are encrypted, and every time someone opens a request it’s recorded."
                actions={
                    meta?.can.export ? (
                        <Button variant="secondary" icon={<Download className="size-[18px]" aria-hidden="true" />} onClick={() => window.location.assign(exportUrl(table.filters))}>
                            Export CSV
                        </Button>
                    ) : undefined
                }
            />

            <div role="tablist" aria-label="Request status" className="flex w-full gap-7 overflow-x-auto border-b border-line">
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

            <div className="flex flex-wrap items-center justify-between gap-3">
                <FilterBar>
                    <SearchInput label="Search requests" placeholder="Search by name, phone or reference" value={search} onChange={(event) => setSearch(event.target.value)} className="w-full sm:w-[300px]" />
                    <FilterSelect label="Topic" value={table.filters.topic ?? ''} onChange={(event) => table.setFilter('topic', event.target.value || null)}>
                        <option value="">All topics</option>
                        {(meta?.topics ?? []).map((topic) => (
                            <option key={topic.value} value={topic.value}>
                                {topic.label}
                            </option>
                        ))}
                    </FilterSelect>
                    <FilterSelect label="Assigned to" value={table.filters.assigned_to ?? ''} onChange={(event) => table.setFilter('assigned_to', event.target.value || null)}>
                        <option value="">Anyone</option>
                        <option value="me">Assigned to me</option>
                        {meta?.can.assign && <option value="unassigned">Unassigned</option>}
                        {(meta?.assignees ?? []).map((person) => (
                            <option key={person.id} value={person.id}>
                                {person.name}
                            </option>
                        ))}
                    </FilterSelect>
                    <FilterSelect label="Received" value={table.filters.received ?? ''} onChange={(event) => table.setFilter('received', event.target.value || null)}>
                        <option value="">Any time</option>
                        <option value="7">Last 7 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                    </FilterSelect>
                </FilterBar>
                {filtered && (
                    <button
                        type="button"
                        className="text-admin-body font-semibold text-ink hover:text-royal"
                        onClick={() => {
                            setSearch('');
                            for (const key of ['topic', 'assigned_to', 'received']) table.setFilter(key, null);
                            table.setSearch('');
                        }}
                    >
                        Clear filters
                    </button>
                )}
            </div>

            <DataTable
                caption="Consultation requests"
                columns={columns}
                rows={rows}
                rowKey={(row) => row.id}
                loading={list.isPending}
                sort={table.sort}
                onSort={table.setSort}
                toolbar={toolbar}
                rowClassName={(row) => (selected.includes(row.id) ? 'bg-sky-soft' : undefined)}
                error={list.isError ? <ErrorState title="Requests didn’t load" description={list.error instanceof ApiError ? list.error.humanMessage : 'Try again in a moment.'} requestId={list.error instanceof ApiError ? list.error.requestId : null} onRetry={() => void list.refetch()} /> : undefined}
                empty={
                    <EmptyState
                        icon={<Inbox className="size-5" aria-hidden="true" />}
                        title={filtered ? 'No requests match' : status === '' ? 'No requests yet' : 'Nothing here'}
                        description={filtered ? 'Try another search or filter.' : 'Requests sent from the website’s Contact form appear here within seconds.'}
                    />
                }
            />
            {meta && meta.total > 0 && <Pagination meta={meta} onPage={table.setPage} onPerPage={table.setPerPage} noun="requests" />}
        </>
    );
}

const BAR_BUTTON = 'inline-flex h-8 items-center gap-1.5 rounded-full bg-white/10 px-3.5 font-display text-[13px] font-bold text-white transition-colors hover:bg-white/20 disabled:opacity-50';

/** A bare checkbox for table rows (the labelled Checkbox would repeat the row text). */
function SelectBox({ checked, label, onChange }: { checked: boolean; label: string; onChange: () => void }) {
    return (
        <span className="relative inline-flex size-[18px] align-middle">
            <input type="checkbox" checked={checked} onChange={onChange} aria-label={label} className="peer absolute inset-0 m-0 cursor-pointer opacity-0" />
            <span className="pointer-events-none inline-flex size-[18px] items-center justify-center rounded-[5px] border-[1.5px] border-ink-soft bg-white text-white transition-colors peer-checked:border-royal peer-checked:bg-royal peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-royal [&>svg]:opacity-0 peer-checked:[&>svg]:opacity-100">
                <Check className="size-3.5" strokeWidth={3} aria-hidden="true" />
            </span>
        </span>
    );
}
