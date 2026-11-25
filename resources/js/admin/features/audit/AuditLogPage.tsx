import { useEffect, useMemo, useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { ArrowRight, Download, History } from 'lucide-react';
import { ApiError, api } from '@shared/api/client';
import { can } from '@shared/auth/can';
import { clockTime, dayLabel } from '@shared/format/time';
import { useDebouncedValue } from '@shared/hooks/useDebouncedValue';
import { useTableParams } from '@shared/hooks/useTableParams';
import { Avatar, Button, DataTable, Drawer, EmptyState, ErrorState, FilterBar, FilterSelect, Pagination, SearchInput, cn, type Column, type PageMeta, type RowGroup } from '@shared/ui';
import { useCurrentUser } from '@admin/app/AuthProvider';
import { PageHeader } from '@admin/components/PageHeader';
import { useUsers } from '@admin/features/users/api';
import { CATEGORY_LABELS, actionMeta } from './actions';

export interface AuditEntry {
    id: number;
    action: string;
    user: { id: number; name: string | null } | null;
    auditable_type: string | null;
    auditable_id: string | null;
    auditable_label: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    request_id: string | null;
    created_at: string;
}

const RANGES: Record<string, { label: string; days: number | null }> = {
    '1': { label: 'Last 24 hours', days: 1 },
    '7': { label: 'Last 7 days', days: 7 },
    '30': { label: 'Last 30 days', days: 30 },
    '90': { label: 'Last 90 days', days: 90 },
    all: { label: 'All time', days: null },
};

function withRange(query: Record<string, string | number>, range: string): Record<string, string | number> {
    const days = RANGES[range]?.days ?? null;

    return days === null ? query : { ...query, 'filter[from]': new Date(Date.now() - days * 86_400_000).toISOString().slice(0, 19).replace('T', ' ') };
}

/** "Joy Kiongo" → "Joy K." as on Figma 27. */
export function shortName(name: string | null | undefined): string {
    if (!name) return 'Someone';
    const [first, ...rest] = name.trim().split(/\s+/);
    const last = rest[rest.length - 1];

    return last ? `${first} ${last[0]}.` : (first ?? name);
}

/** One-line summary of what changed, for the Record column. */
export function summarise(entry: AuditEntry): string {
    const before = entry.old_values ?? {};
    const after = entry.new_values ?? {};

    if (entry.action === 'permission.changed' && 'role' in after) return `${entry.auditable_label ?? ''} · ${String(before.role ?? 'none')} → ${String(after.role)}`;
    if (entry.action === 'auth.login_failed') return `Wrong password${typeof after.email === 'string' ? ` · ${after.email}` : ''}`;

    const changed = Object.keys(after).filter((key) => key !== 'updated_at');
    const label = entry.auditable_label ?? '—';

    return entry.action.endsWith('.updated') && changed.length > 0 ? `${label} · ${changed.join(', ')} changed` : label;
}

export function AuditLogPage() {
    const me = useCurrentUser();
    const table = useTableParams({ perPage: 25 });
    const [search, setSearch] = useState(table.search);
    const debounced = useDebouncedValue(search);
    const [selected, setSelected] = useState<AuditEntry | null>(null);
    const range = table.filters.range ?? '7';
    const actors = useUsers({ per_page: 100, sort: 'name' }, can(me, 'users.manage'));

    useEffect(() => {
        if (debounced !== table.search) table.setSearch(debounced);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only react to the debounced text
    }, [debounced]);

    // `range` is a UI filter; the API gets `filter[from]`, computed when the request is sent.
    const query = useMemo(() => {
        const { 'filter[range]': _range, ...rest } = table.query;
        void _range;

        return rest;
    }, [table.query]);

    const logs = useQuery({
        queryKey: ['audit-logs', query, range],
        queryFn: async ({ signal }) => {
            const response = await api.get<AuditEntry[]>('admin/audit-logs', { query: withRange(query, range), signal });

            return { entries: response.data, meta: response.meta as unknown as PageMeta & { categories: string[] } };
        },
        placeholderData: keepPreviousData,
    });

    const groups: RowGroup<AuditEntry>[] = useMemo(() => {
        const byDay: RowGroup<AuditEntry>[] = [];
        for (const entry of logs.data?.entries ?? []) {
            const label = dayLabel(entry.created_at);
            const current = byDay[byDay.length - 1];
            if (current?.label === label) current.rows.push(entry);
            else byDay.push({ label, rows: [entry] });
        }

        return byDay;
    }, [logs.data]);

    const exportCsv = () => {
        const params = Object.entries(withRange(query, range)).filter(([key]) => key !== 'page' && key !== 'per_page');
        window.location.assign(`/api/v1/admin/audit-logs/export?${new URLSearchParams(params.map(([key, value]) => [key, String(value)])).toString()}`);
    };

    const columns: Column<AuditEntry>[] = [
        { key: 'when', header: 'When', className: 'w-[110px] whitespace-nowrap', cell: (entry) => <time dateTime={entry.created_at}>{clockTime(entry.created_at)}</time> },
        {
            key: 'who',
            header: 'Who',
            className: 'w-[170px]',
            cell: (entry) => (
                <span className="flex items-center gap-2.5">
                    <Avatar name={entry.user?.name ?? '?'} size="sm" />
                    <span className="truncate">{entry.user ? shortName(entry.user.name) : 'System'}</span>
                </span>
            ),
        },
        {
            key: 'action',
            header: 'Action',
            className: 'w-[260px]',
            cell: (entry) => {
                const meta = actionMeta(entry.action, entry.new_values);

                return (
                    <button type="button" onClick={() => setSelected(entry)} className="flex items-center gap-2.5 text-left font-semibold text-ink hover:text-royal">
                        <span className={cn('inline-flex size-7 shrink-0 items-center justify-center rounded-full', meta.tone === 'danger' ? 'bg-danger-soft text-danger' : 'bg-sky-soft text-royal')}>
                            <meta.icon className="size-3.5" aria-hidden="true" />
                        </span>
                        {meta.label}
                    </button>
                );
            },
        },
        { key: 'record', header: 'Record', cell: (entry) => <span className="line-clamp-1 break-all">{summarise(entry)}</span> },
        { key: 'ip', header: 'IP address', className: 'w-[150px]', cell: (entry) => <span className="font-mono text-admin-small">{entry.ip_address ?? '—'}</span> },
    ];

    return (
        <>
            <PageHeader
                trail={['System', 'Audit log']}
                title="Audit"
                accent="log."
                description="Every sign-in, change and view of a consultation request. Entries can’t be edited or deleted and are kept for 6 years."
                actions={
                    <Button variant="secondary" icon={<Download className="size-[18px]" aria-hidden="true" />} onClick={exportCsv}>
                        Export CSV
                    </Button>
                }
            />
            <FilterBar>
                <SearchInput label="Search actions or records" placeholder="Search actions or records" value={search} onChange={(event) => setSearch(event.target.value)} className="w-full sm:w-[320px]" />
                {actors.data && (
                    <FilterSelect label="Person" value={table.filters.user_id ?? ''} onChange={(event) => table.setFilter('user_id', event.target.value || null)}>
                        <option value="">Everyone</option>
                        {actors.data.users.map((user) => (
                            <option key={user.id} value={user.id}>
                                {user.name}
                            </option>
                        ))}
                    </FilterSelect>
                )}
                <FilterSelect label="Action" value={table.filters.category ?? ''} onChange={(event) => table.setFilter('category', event.target.value || null)}>
                    <option value="">All actions</option>
                    {Object.entries(CATEGORY_LABELS).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </FilterSelect>
                <FilterSelect label="Date range" value={range} onChange={(event) => table.setFilter('range', event.target.value === '7' ? null : event.target.value)}>
                    {Object.entries(RANGES).map(([value, option]) => (
                        <option key={value} value={value}>
                            {option.label}
                        </option>
                    ))}
                </FilterSelect>
            </FilterBar>
            <DataTable
                caption="Audit log entries, newest first"
                columns={columns}
                rows={groups}
                rowKey={(entry) => entry.id}
                loading={logs.isPending}
                error={logs.isError ? <ErrorState title="The audit log didn’t load" description={logs.error instanceof ApiError ? logs.error.humanMessage : 'Try again in a moment.'} requestId={logs.error instanceof ApiError ? logs.error.requestId : null} onRetry={() => void logs.refetch()} /> : undefined}
                empty={<EmptyState icon={<History className="size-5" aria-hidden="true" />} title="Nothing logged for these filters" description="Try a longer date range or clear the filters." />}
            />
            {logs.data && <Pagination meta={logs.data.meta} onPage={table.setPage} onPerPage={table.setPerPage} />}
            {selected && <AuditEntryDrawer entry={selected} onClose={() => setSelected(null)} />}
        </>
    );
}

function Values({ title, values }: { title: string; values: Record<string, unknown> | null }) {
    if (!values || Object.keys(values).length === 0) return null;

    return (
        <div className="flex flex-col gap-2">
            <h3 className="font-display text-[10px] font-bold tracking-[1.1px] text-ink-soft uppercase">{title}</h3>
            <dl className="grid grid-cols-[minmax(0,140px)_1fr] gap-x-4 gap-y-1.5 rounded-control border border-line bg-white p-4 text-admin-small">
                {Object.entries(values).map(([key, value]) => (
                    <div key={key} className="contents">
                        <dt className="font-semibold break-all text-ink-soft">{key}</dt>
                        <dd className="break-all text-ink">{typeof value === 'object' ? JSON.stringify(value) : String(value)}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

function AuditEntryDrawer({ entry, onClose }: { entry: AuditEntry; onClose: () => void }) {
    const meta = actionMeta(entry.action, entry.new_values);

    return (
        <Drawer open onClose={onClose} title={meta.label} description={`${new Date(entry.created_at).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'medium' })} · ${entry.user?.name ?? 'System'}`}>
            <div className="flex flex-col gap-5">
                <dl className="grid grid-cols-[minmax(0,120px)_1fr] gap-x-4 gap-y-2 text-admin-body">
                    <dt className="text-ink-soft">Action</dt>
                    <dd className="font-mono text-admin-small">{entry.action}</dd>
                    <dt className="text-ink-soft">Record</dt>
                    <dd className="break-all">{entry.auditable_type ? `${entry.auditable_type} #${entry.auditable_id} · ${entry.auditable_label ?? ''}` : '—'}</dd>
                    <dt className="text-ink-soft">IP address</dt>
                    <dd className="font-mono text-admin-small">{entry.ip_address ?? '—'}</dd>
                    <dt className="text-ink-soft">Browser</dt>
                    <dd className="text-admin-small break-words">{entry.user_agent ?? '—'}</dd>
                    <dt className="text-ink-soft">Request ID</dt>
                    <dd className="font-mono text-admin-small break-all">{entry.request_id ?? '—'}</dd>
                </dl>
                {entry.old_values && entry.new_values ? (
                    <div className="flex flex-col gap-3">
                        <Values title="Before" values={entry.old_values} />
                        <ArrowRight className="size-4 self-center text-ink-soft" aria-hidden="true" />
                        <Values title="After" values={entry.new_values} />
                    </div>
                ) : (
                    <>
                        <Values title="Before" values={entry.old_values} />
                        <Values title="Details" values={entry.new_values} />
                    </>
                )}
            </div>
        </Drawer>
    );
}
