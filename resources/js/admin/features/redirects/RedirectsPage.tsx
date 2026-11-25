import { useEffect, useRef, useState } from 'react';
import { ArrowRight, Check, ChevronRight, CircleAlert, Download, Ellipsis, Plus, Trash2, Upload } from 'lucide-react';
import { ApiError } from '@shared/api/client';
import { relativeTime } from '@shared/format/time';
import { useDebouncedValue } from '@shared/hooks/useDebouncedValue';
import { useTableParams } from '@shared/hooks/useTableParams';
import { Badge, Button, Card, ConfirmDialog, DataTable, Dropdown, EmptyState, ErrorState, FilterBar, FilterSelect, Pagination, SearchInput, Select, TextInput, Toggle, cn, useToast, type BadgeTone, type Column } from '@shared/ui';
import { PageHeader } from '@admin/components/PageHeader';
import { checkRedirect, useRedirectMutations, useRedirects, type RedirectCheck, type RedirectRow } from './api';

const SOURCES: Record<RedirectRow['source'], { label: string; tone: BadgeTone }> = {
    slug_change: { label: 'Slug change', tone: 'info' },
    manual: { label: 'Manual', tone: 'neutral' },
    imported: { label: 'Imported', tone: 'neutral' },
    not_found: { label: '404 fix', tone: 'neutral' },
};

/** Figma 20: send old addresses to the right page. Changing a slug adds one automatically. */
export function RedirectsPage() {
    const toast = useToast();
    const table = useTableParams({ perPage: 10 });
    const list = useRedirects(table.query);
    const { create, update, remove, importCsv } = useRedirectMutations();
    const [search, setSearch] = useState(table.search);
    const debounced = useDebouncedValue(search, 300);
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [code, setCode] = useState('301');
    const [check, setCheck] = useState<RedirectCheck | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [deleting, setDeleting] = useState<RedirectRow | null>(null);
    const file = useRef<HTMLInputElement>(null);
    const gone = code === '410';
    const draft = useDebouncedValue(`${from}|${to}|${code}`, 350);

    useEffect(() => {
        if (debounced !== table.search) table.setSearch(debounced);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only react to the debounced text
    }, [debounced]);

    // Live loop/chain check under the form (Figma 20).
    useEffect(() => {
        const [f, t, c] = draft.split('|');
        if (!f || !f.startsWith('/') || (c !== '410' && !t)) return;
        const controller = new AbortController();
        checkRedirect({ from_path: f, to_path: c === '410' ? null : (t ?? null) }, controller.signal)
            .then(setCheck)
            .catch(() => undefined);

        return () => controller.abort();
    }, [draft]);

    const ready = from.startsWith('/') && (gone || to !== '');
    const shownCheck = ready ? check : null;

    const add = () =>
        create.mutate(
            { from_path: from, to_path: gone ? null : to, status_code: Number(code) },
            {
                onSuccess: () => {
                    toast.success('Redirect added', 'The website applies it within a minute.');
                    setFrom('');
                    setTo('');
                    setErrors({});
                },
                onError: (error) => {
                    if (error instanceof ApiError) setErrors(Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [key, messages[0] ?? ''])));
                    if (error instanceof ApiError && Object.keys(error.fieldErrors).length === 0) toast.error('Not added', error.humanMessage);
                },
            },
        );

    const columns: Column<RedirectRow>[] = [
        { key: 'from', header: 'From', cell: (row) => <span className="font-mono text-[13px] break-all text-ink">{row.from_path}</span> },
        { key: 'arrow', header: <span className="sr-only">to</span>, className: 'w-[32px] !px-0', cell: () => <ChevronRight className="size-4 text-ink-soft" aria-hidden="true" /> },
        { key: 'to', header: 'To', cell: (row) => <span className="font-mono text-[13px] break-all text-ink">{row.to_path ?? 'Gone'}</span> },
        { key: 'type', header: 'Type', className: 'w-[110px]', cell: (row) => <span className="whitespace-nowrap">{row.status_code}{row.match_type === 'wildcard' ? ' · wildcard' : ''}</span> },
        { key: 'hits', header: 'Hits · 30 d', align: 'end', className: 'w-[100px]', cell: (row) => row.hits_30d.toLocaleString('en-US') },
        { key: 'last', header: 'Last hit', className: 'w-[110px]', cell: (row) => <span className="whitespace-nowrap text-ink-soft">{row.last_hit_at ? relativeTime(row.last_hit_at) : '—'}</span> },
        { key: 'source', header: 'Source', className: 'w-[120px]', cell: (row) => <Badge tone={SOURCES[row.source].tone}>{SOURCES[row.source].label}</Badge> },
        {
            key: 'enabled',
            header: <span className="sr-only">Enabled</span>,
            className: 'w-[64px]',
            cell: (row) => (
                <Toggle
                    label={<span className="sr-only">Enabled</span>}
                    checked={row.is_enabled}
                    onChange={(event) => update.mutate({ id: row.id, from_path: row.from_path, to_path: row.to_path, status_code: row.status_code, is_enabled: event.target.checked }, { onError: (error) => toast.error('Not saved', error instanceof ApiError ? error.humanMessage : undefined) })}
                />
            ),
        },
        {
            key: 'actions',
            header: <span className="sr-only">Actions</span>,
            className: 'w-[56px]',
            align: 'end',
            cell: (row) => (
                <Dropdown
                    items={[{ label: 'Delete', icon: <Trash2 className="size-4" aria-hidden="true" />, tone: 'danger', onSelect: () => setDeleting(row) }]}
                    trigger={({ ref, ...props }) => (
                        <button ref={ref} type="button" {...props} aria-label={`Actions for ${row.from_path}`} className="rounded-full p-2 text-ink hover:bg-paper">
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
                trail={['SEO', 'Redirects']}
                title=""
                accent="Redirects."
                description="Send old addresses to the right page so visitors and search engines don’t hit dead ends. Changing a slug adds one automatically."
                actions={
                    <>
                        <input
                            ref={file}
                            type="file"
                            accept=".csv,text/csv"
                            className="hidden"
                            onChange={(event) => {
                                const chosen = event.target.files?.[0];
                                if (!chosen) return;
                                importCsv.mutate(chosen, {
                                    onSuccess: (response) => toast.success(`${response.data.imported} imported`, response.data.errors.length > 0 ? `${response.data.errors.length} rows skipped: ${response.data.errors[0]}` : undefined),
                                    onError: (error) => toast.error('Import failed', error instanceof ApiError ? error.humanMessage : undefined),
                                });
                                event.target.value = '';
                            }}
                        />
                        <Button variant="secondary" icon={<Upload className="size-[18px]" aria-hidden="true" />} loading={importCsv.isPending} onClick={() => file.current?.click()}>
                            Import CSV
                        </Button>
                        <Button variant="secondary" icon={<Download className="size-[18px]" aria-hidden="true" />} onClick={() => window.location.assign('/api/v1/admin/redirects/export')}>
                            Export
                        </Button>
                    </>
                }
            />

            <Card className="rounded-sheet p-6">
                <form
                    className="flex flex-col gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        add();
                    }}
                >
                    <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_24px_minmax(0,1fr)_200px_auto]">
                        <TextInput label="From" value={from} placeholder="/ocd-treatment" onChange={(event) => setFrom(event.target.value)} error={errors.from_path} />
                        <ArrowRight className="mt-10 hidden size-4 text-ink-soft lg:block" aria-hidden="true" />
                        <TextInput label="To" value={gone ? '' : to} disabled={gone} placeholder={gone ? 'Gone (410)' : '/tms'} onChange={(event) => setTo(event.target.value)} error={errors.to_path} />
                        <Select label="Type" value={code} onChange={(event) => setCode(event.target.value)}>
                            <option value="301">301 · Permanent</option>
                            <option value="302">302 · Temporary</option>
                            <option value="307">307 · Temporary (keep method)</option>
                            <option value="410">410 · Gone</option>
                        </Select>
                        <Button type="submit" className="lg:mt-[26px]" icon={<Plus className="size-[18px]" aria-hidden="true" />} loading={create.isPending} disabled={!ready}>
                            Add redirect
                        </Button>
                    </div>
                    {shownCheck && <CheckLine check={shownCheck} />}
                    <p className="text-admin-small text-ink-soft">End “From” with /* to match everything under it; $1 in “To” is replaced by the rest of the address.</p>
                </form>
            </Card>

            <FilterBar>
                <SearchInput label="Search paths" placeholder="Search paths" value={search} onChange={(event) => setSearch(event.target.value)} className="w-full sm:w-[300px]" />
                <FilterSelect label="Type" value={table.filters.type ?? ''} onChange={(event) => table.setFilter('type', event.target.value || null)}>
                    <option value="">All types</option>
                    <option value="301">301</option>
                    <option value="302">302</option>
                    <option value="307">307</option>
                    <option value="410">410</option>
                </FilterSelect>
                <FilterSelect label="Source" value={table.filters.source ?? ''} onChange={(event) => table.setFilter('source', event.target.value || null)}>
                    <option value="">Any source</option>
                    <option value="slug_change">Slug change</option>
                    <option value="manual">Manual</option>
                    <option value="imported">Imported</option>
                    <option value="not_found">404 fix</option>
                </FilterSelect>
            </FilterBar>

            <DataTable
                caption="Redirects"
                columns={columns}
                rows={list.data?.rows}
                rowKey={(row) => row.id}
                loading={list.isPending}
                error={list.isError ? <ErrorState title="Redirects didn’t load" description={list.error instanceof ApiError ? list.error.humanMessage : 'Try again in a moment.'} onRetry={() => void list.refetch()} /> : undefined}
                empty={<EmptyState icon={<ArrowRight className="size-5" aria-hidden="true" />} title="No redirects yet" description="Add one above, or move a live page and one is added for you." />}
            />
            {list.data && list.data.meta.total > 0 && <Pagination meta={list.data.meta} onPage={table.setPage} onPerPage={table.setPerPage} noun="redirects" />}

            <ConfirmDialog
                open={deleting !== null}
                title={`Delete the redirect from ${deleting?.from_path ?? ''}?`}
                description="Visitors to the old address will see a not-found page."
                confirmLabel="Delete redirect"
                busy={remove.isPending}
                onClose={() => setDeleting(null)}
                onConfirm={() => deleting && remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })}
            />
        </>
    );
}

function CheckLine({ check }: { check: RedirectCheck }) {
    const problem = check.loop || check.shadows_content || check.target.state === 'missing';
    const text = check.loop
        ? 'This would send visitors in a circle.'
        : check.shadows_content
          ? 'A published page lives at the “From” address.'
          : check.target.state === 'missing'
            ? `${check.target.path} doesn’t exist on the site yet.`
            : check.target.state === 'gone'
              ? 'Visitors and search engines will be told the page is gone for good.'
              : check.target.state === 'external'
                ? `Sends visitors to another website: ${check.target.path}.`
                : `${check.chain.length > 0 ? `Chain shortened to go straight to ${check.target.path}.` : 'No loop or chain detected.'} ${check.target.path} returns 200.`;

    return (
        <p className={cn('flex items-center gap-2 text-admin-small', problem ? 'text-danger' : 'text-success')} role="status">
            {problem ? <CircleAlert className="size-4" aria-hidden="true" /> : <Check className="size-4" aria-hidden="true" />}
            {text}
        </p>
    );
}
