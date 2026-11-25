import type { ReactNode } from 'react';
import { useState } from 'react';
import { ArrowRight, Bot, Download, ExternalLink, Globe, Link2, SearchX, TrendingDown, TrendingUp } from 'lucide-react';
import { ApiError } from '@shared/api/client';
import { relativeTime } from '@shared/format/time';
import { useTableParams } from '@shared/hooks/useTableParams';
import { Button, Card, Count, DataTable, Dialog, EmptyState, ErrorState, Pagination, TextInput, cn, useToast, type Column } from '@shared/ui';
import { PageHeader } from '@admin/components/PageHeader';
import { useNotFound, useNotFoundMutations, type NotFoundRow } from './api';

const TABS = [
    { value: 'open', label: 'Needs attention' },
    { value: 'ignored', label: 'Ignored' },
    { value: 'resolved', label: 'Resolved' },
] as const;

const REFERRER = { google: 'Google', search: 'Search engine', internal: 'Link on site', external: 'Another site', direct: 'Direct' } as const;

/** Figma 21: addresses visitors tried that don’t exist, and one-click fixes. IP addresses are never stored. */
export function NotFoundPage() {
    const toast = useToast();
    const table = useTableParams({ perPage: 25 });
    const status = (table.filters.status ?? 'open') as (typeof TABS)[number]['value'];
    const list = useNotFound({ ...table.query, 'filter[status]': status });
    const { resolve, ignore, restore, clearIgnored } = useNotFoundMutations();
    const [choosing, setChoosing] = useState<NotFoundRow | null>(null);
    const [target, setTarget] = useState('');
    const meta = list.data?.meta;
    const stats = meta?.stats;
    const fail = (error: unknown) => toast.error('That didn’t work', error instanceof ApiError ? (Object.values(error.fieldErrors)[0]?.[0] ?? error.humanMessage) : undefined);
    const redirect = (row: NotFoundRow, to: string | null, code: 301 | 410 = 301) =>
        resolve.mutate({ id: row.id, to_path: to, status_code: code }, { onSuccess: () => toast.success(code === 410 ? 'Marked as gone' : 'Redirect created', code === 410 ? row.path : `${row.path} → ${to}`), onError: fail });

    const columns: Column<NotFoundRow>[] = [
        { key: 'path', header: 'Address', cell: (row) => <span className="font-mono text-[13px] break-all text-ink">{row.path}</span> },
        { key: 'hits', header: 'Hits', align: 'end', className: 'w-[70px]', cell: (row) => row.hits.toLocaleString('en-US') },
        { key: 'last', header: 'Last seen', className: 'w-[120px]', cell: (row) => <span className="whitespace-nowrap">{relativeTime(row.last_seen_at)}</span> },
        {
            key: 'referrer',
            header: 'Referrer',
            className: 'w-[150px]',
            cell: (row) => (
                <span className="flex items-center gap-1.5 whitespace-nowrap text-ink">
                    {row.referrer.kind === 'internal' ? <Link2 className="size-4 text-ink-soft" aria-hidden="true" /> : row.referrer.kind === 'direct' ? <ExternalLink className="size-4 text-ink-soft" aria-hidden="true" /> : <Globe className="size-4 text-ink-soft" aria-hidden="true" />}
                    {row.referrer.kind === 'internal' && row.referrer.path ? row.referrer.path : REFERRER[row.referrer.kind]}
                </span>
            ),
        },
        {
            key: 'suggestion',
            header: 'Suggested redirect',
            className: 'w-[260px]',
            cell: (row) =>
                row.is_bot ? (
                    <span className="flex items-center gap-1.5 text-admin-small text-ink-soft">
                        <Bot className="size-4" aria-hidden="true" /> Bot traffic
                    </span>
                ) : row.suggestion ? (
                    <span className="flex min-w-0 flex-col">
                        <span className="flex items-center gap-1 font-mono text-[13px] text-royal">
                            <ArrowRight className="size-3.5 shrink-0" aria-hidden="true" />
                            <span className="truncate">{row.suggestion.path}</span>
                        </span>
                        <span className="text-admin-small text-ink-soft">{row.suggestion.score}% match</span>
                    </span>
                ) : (
                    <span className="text-admin-small text-ink-soft">No close match</span>
                ),
        },
        {
            key: 'actions',
            header: <span className="sr-only">Actions</span>,
            className: 'w-[230px]',
            align: 'end',
            cell: (row) =>
                status !== 'open' ? (
                    <Button variant="ghost" size="sm" onClick={() => restore.mutate(row.id, { onError: fail })}>
                        Move back
                    </Button>
                ) : (
                    <div className="flex items-center justify-end gap-1.5">
                        {row.suggestion && !row.is_bot ? (
                            <Button variant="soft" size="sm" loading={resolve.isPending && resolve.variables?.id === row.id} onClick={() => redirect(row, row.suggestion?.path ?? null)}>
                                Create redirect
                            </Button>
                        ) : !row.is_bot ? (
                            <Button variant="soft" size="sm" onClick={() => setChoosing(row)}>
                                Redirect…
                            </Button>
                        ) : null}
                        {!row.suggestion && !row.is_bot && (
                            <Button variant="ghost" size="sm" onClick={() => redirect(row, null, 410)}>
                                Mark 410
                            </Button>
                        )}
                        <Button variant="ghost" size="sm" onClick={() => ignore.mutate(row.id, { onError: fail })}>
                            Ignore
                        </Button>
                    </div>
                ),
        },
    ];

    return (
        <>
            <PageHeader
                trail={['SEO', '404 monitor']}
                title="Broken"
                accent="links."
                description="Addresses visitors tried that don’t exist. Fix the ones that get traffic with a redirect. IP addresses are never stored."
                actions={
                    <>
                        {(meta?.counts.ignored ?? 0) > 0 && (
                            <Button variant="ghost" loading={clearIgnored.isPending} onClick={() => clearIgnored.mutate(undefined, { onSuccess: () => toast.success('Ignored addresses cleared'), onError: fail })}>
                                Clear ignored
                            </Button>
                        )}
                        <Button variant="secondary" icon={<Download className="size-[18px]" aria-hidden="true" />} onClick={() => window.location.assign('/api/v1/admin/not-found/export')}>
                            Export
                        </Button>
                    </>
                }
            />

            <Card className="grid rounded-sheet sm:grid-cols-2 xl:grid-cols-4">
                <Stat label="404s this week" value={stats?.week} aside={stats?.change_percent !== null && stats?.change_percent !== undefined ? <Trend percent={stats.change_percent} /> : null} />
                <Stat label="Unique addresses" value={stats?.unique} />
                <Stat label="From Google" value={stats?.from_google} aside={(stats?.from_google ?? 0) > 0 ? <span className="text-admin-small font-semibold text-danger">needs a redirect</span> : null} />
                <Stat label="Fixed this month" value={stats?.fixed_this_month} />
            </Card>

            <div role="tablist" aria-label="404 status" className="flex w-full gap-7 overflow-x-auto border-b border-line">
                {TABS.map((tab) => {
                    const active = status === tab.value;

                    return (
                        <button
                            key={tab.value}
                            type="button"
                            role="tab"
                            aria-selected={active}
                            onClick={() => table.setFilter('status', tab.value === 'open' ? null : tab.value)}
                            className={cn('-mb-px flex shrink-0 items-center gap-2 border-b-2 px-0.5 pt-2.5 pb-3 text-admin-body font-semibold transition-colors', active ? 'border-royal text-royal' : 'border-transparent text-ink-soft hover:text-ink')}
                        >
                            {tab.label}
                            {meta && <Count active={active}>{meta.counts[tab.value]}</Count>}
                        </button>
                    );
                })}
            </div>

            <DataTable
                caption="Missing addresses"
                columns={columns}
                rows={list.data?.rows}
                rowKey={(row) => row.id}
                loading={list.isPending}
                error={list.isError ? <ErrorState title="The 404 log didn’t load" description={list.error instanceof ApiError ? list.error.humanMessage : 'Try again in a moment.'} onRetry={() => void list.refetch()} /> : undefined}
                empty={<EmptyState icon={<SearchX className="size-5" aria-hidden="true" />} title={status === 'open' ? 'No broken links right now' : 'Nothing here'} description={status === 'open' ? 'When a visitor reaches an address that doesn’t exist, it appears here.' : undefined} />}
            />
            {meta && meta.total > 0 && <Pagination meta={meta} onPage={table.setPage} onPerPage={table.setPerPage} noun="addresses" />}

            <Dialog
                open={choosing !== null}
                onClose={() => {
                    setChoosing(null);
                    setTarget('');
                }}
                title={`Redirect ${choosing?.path ?? ''}`}
                description="Send visitors to a page on the site (/tms) or a full https:// address."
                footer={
                    <Button
                        disabled={!/^(\/|https:\/\/)/.test(target)}
                        loading={resolve.isPending}
                        onClick={() =>
                            choosing &&
                            resolve.mutate(
                                { id: choosing.id, to_path: target, status_code: 301 },
                                {
                                    onSuccess: () => {
                                        toast.success('Redirect created');
                                        setChoosing(null);
                                        setTarget('');
                                    },
                                    onError: fail,
                                },
                            )
                        }
                    >
                        Create redirect
                    </Button>
                }
            >
                <TextInput label="To" autoFocus value={target} placeholder="/tms" onChange={(event) => setTarget(event.target.value)} />
            </Dialog>
        </>
    );
}

function Stat({ label, value, aside }: { label: string; value: number | undefined; aside?: ReactNode }) {
    return (
        <div className="flex min-w-0 flex-col gap-1.5 px-6 py-5 sm:border-l sm:border-line sm:first:border-l-0">
            <p className="text-admin-small text-ink-soft">{label}</p>
            <p className="flex items-baseline gap-2.5">
                <span className="font-serif text-[34px] leading-none tracking-[-0.02em] text-ink">{value ?? '—'}</span>
                {aside}
            </p>
        </div>
    );
}

/** Fewer 404s is good news, so a drop is green. */
function Trend({ percent }: { percent: number }) {
    const down = percent <= 0;

    return (
        <span className={cn('inline-flex items-center gap-1 text-admin-small font-semibold', down ? 'text-success' : 'text-danger')}>
            {down ? <TrendingDown className="size-3.5" aria-hidden="true" /> : <TrendingUp className="size-3.5" aria-hidden="true" />}
            {Math.abs(percent)}%
        </span>
    );
}
