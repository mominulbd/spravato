import type { DragEvent, ReactNode } from 'react';
import { useMemo, useReducer, useRef, useState } from 'react';
import { useSearchParams } from 'react-router';
import { Check, ChevronDown, FileText, Folder, FolderInput, FolderPlus, ImageOff, Info, LayoutGrid, Link2, List, Plus, Trash2, TriangleAlert, Upload, X } from 'lucide-react';
import { ApiError } from '@shared/api/client';
import { can } from '@shared/auth/can';
import { relativeTime } from '@shared/format/time';
import { useDebouncedValue } from '@shared/hooks/useDebouncedValue';
import { Button, ConfirmDialog, Dropdown, EmptyState, ErrorState, FilterSelect, SearchInput, Skeleton, cn, useToast } from '@shared/ui';
import { useCurrentUser } from '@admin/app/AuthProvider';
import { PageHeader } from '@admin/components/PageHeader';
import { useBulkMedia, useInfiniteMedia, useMediaFolders, useMediaList, useUploadMedia } from './api';
import { CleanUpPanel } from './CleanUpPanel';
import { fileFacts, usageLine } from './describe';
import { FileDetailsPanel } from './FileDetailsPanel';
import { MediaCard } from './MediaCard';
import { NewFolderDialog } from './NewFolderDialog';
import { TrashDialog } from './TrashDialog';
import { UploadTray } from './UploadTray';
import { formatBytes, type MediaItem } from './types';
import { uploadReducer } from './uploadQueue';

type Show = 'all' | 'in_use' | 'unused' | 'no_alt';

const SORTS = { newest: 'Newest first', oldest: 'Oldest first', name: 'Name A–Z', largest: 'Largest first' } as const;
const PER_PAGE = 24;

/** Figma 18 (library) and 18b (Show: Unused, clean up). State lives in the URL. */
export function MediaLibraryPage() {
    const user = useCurrentUser();
    const toast = useToast();
    const canManage = can(user, 'media.manage');
    const [params, setParams] = useSearchParams();
    const folder = params.get('folder') ?? '';
    const show = (params.get('show') ?? 'all') as Show;
    const kind = params.get('kind') ?? '';
    const sort = (params.get('sort') ?? 'newest') as keyof typeof SORTS;
    const view = params.get('view') === 'list' ? 'list' : 'grid';
    const unusedDays = params.get('unused_days') ?? '';
    const fileId = params.get('file') ? Number(params.get('file')) : null;
    const [search, setSearch] = useState(params.get('search') ?? '');
    const debounced = useDebouncedValue(search.trim(), 300);

    const update = (changes: Record<string, string | null>) =>
        setParams(
            (current) => {
                const next = new URLSearchParams(current);
                for (const [key, value] of Object.entries(changes)) {
                    if (value === null || value === '') next.delete(key);
                    else next.set(key, value);
                }
                return next;
            },
            { replace: true },
        );

    const query = useMemo(
        () => ({
            search: debounced,
            'filter[folder]': folder,
            'filter[show]': show === 'all' ? '' : show,
            'filter[kind]': kind,
            'filter[unused_days]': show === 'unused' ? unusedDays : '',
            sort,
            per_page: PER_PAGE,
        }),
        [debounced, folder, show, kind, unusedDays, sort],
    );
    const queryKey = JSON.stringify(query);

    const list = useInfiniteMedia(query);
    const overall = useMediaList({ per_page: 1 });
    const folders = useMediaFolders();
    const bulk = useBulkMedia();
    const upload = useUploadMedia();

    const items = useMemo(() => list.data?.pages.flatMap((page) => page.items) ?? [], [list.data]);
    const meta = list.data?.pages[list.data.pages.length - 1]?.meta;
    const totals = overall.data?.meta.counts;

    // Selection belongs to the current list; changing a filter starts afresh.
    const [selection, setSelection] = useState<{ key: string; ids: number[] }>({ key: queryKey, ids: [] });
    const selected = selection.key === queryKey ? selection.ids : [];
    const setSelected = (ids: number[]) => setSelection({ key: queryKey, ids });
    const toggle = (id: number) => setSelected(selected.includes(id) ? selected.filter((item) => item !== id) : [...selected, id]);

    const [folderDialog, setFolderDialog] = useState(false);
    const [trashOpen, setTrashOpen] = useState(false);
    const [confirmBulk, setConfirmBulk] = useState(false);
    const [dragging, setDragging] = useState(false);

    // Uploads go one at a time (Figma 18 tray).
    const [queue, dispatch] = useReducer(uploadReducer, []);
    const pending = useRef<{ key: string; file: File }[]>([]);
    const running = useRef(false);
    const fileInput = useRef<HTMLInputElement>(null);
    const uploadFolder = /^\d+$/.test(folder) ? Number(folder) : null;
    const uploadFolderName = folders.data?.find((item) => item.id === uploadFolder)?.name ?? null;

    const pump = async () => {
        if (running.current) return;
        running.current = true;

        for (let next = pending.current.shift(); next; next = pending.current.shift()) {
            dispatch({ type: 'start', key: next.key });
            try {
                const response = await upload.mutateAsync({ file: next.file, folderId: uploadFolder });
                dispatch({ type: 'done', key: next.key, mediaId: response.data.id, needsAlt: response.data.needs_alt_text });
            } catch (error) {
                dispatch({ type: 'failed', key: next.key, error: error instanceof ApiError ? (error.fieldErrors.file?.[0] ?? error.humanMessage) : 'Upload failed' });
            }
        }

        running.current = false;
    };

    const addFiles = (files: FileList | File[] | null) => {
        const list = Array.from(files ?? []);
        if (list.length === 0 || !canManage) return;

        const entries = list.map((file, index) => ({ key: `${Date.now()}-${index}-${file.name}`, file }));
        pending.current.push(...entries);
        dispatch({ type: 'add', files: entries.map(({ key, file }) => ({ key, name: file.name })) });
        void pump();
    };

    const cancelUploads = () => {
        pending.current = [];
        dispatch({ type: 'cancel' });
    };

    const onDrop = (event: DragEvent) => {
        event.preventDefault();
        setDragging(false);
        addFiles(event.dataTransfer.files);
    };

    const runBulk = (action: 'trash' | 'move', folderId?: number | null) =>
        bulk.mutate(
            { action, ids: selected, folder_id: folderId },
            {
                onSuccess: (response) => {
                    setConfirmBulk(false);
                    setSelected([]);
                    toast.success(response.message);
                },
                onError: (error) => {
                    setConfirmBulk(false);
                    toast.error(action === 'trash' ? 'Nothing was moved to Trash' : 'Couldn’t move the files', error instanceof ApiError ? error.humanMessage : undefined);
                },
            },
        );

    const panel: 'details' | 'cleanup' | null = fileId !== null ? 'details' : show === 'unused' ? 'cleanup' : null;
    const missingAlt = totals?.no_alt ?? 0;
    const showOptions: { value: Show; label: string; count: number | undefined; icon: ReactNode }[] = [
        { value: 'all', label: 'All', count: meta?.counts.all, icon: null },
        { value: 'in_use', label: 'In use', count: meta?.counts.in_use, icon: <Link2 className="size-3.5" aria-hidden="true" /> },
        { value: 'unused', label: 'Unused', count: meta?.counts.unused, icon: <Info className="size-3.5" aria-hidden="true" /> },
        { value: 'no_alt', label: 'No alt text', count: meta?.counts.no_alt, icon: <TriangleAlert className="size-3.5" aria-hidden="true" /> },
    ];

    return (
        <>
            <PageHeader
                trail={['Content', 'Media library']}
                title="Media"
                accent="library."
                description="Upload photos, logos and documents once, then use them on any page. Images are shrunk automatically so the website stays fast."
                actions={
                    canManage && (
                        <>
                            <Button variant="secondary" icon={<FolderPlus className="size-4" />} onClick={() => setFolderDialog(true)}>
                                New folder
                            </Button>
                            <input ref={fileInput} type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" className="sr-only" onChange={(event) => {
                                addFiles(event.target.files);
                                event.target.value = '';
                            }} />
                            <Button icon={<Upload className="size-4" />} onClick={() => fileInput.current?.click()}>
                                Upload files
                            </Button>
                        </>
                    )
                }
            />

            {show === 'unused' ? (
                <div role="note" className="flex flex-wrap items-center justify-between gap-4 rounded-card bg-sky-soft px-6 py-4">
                    <p className="flex items-start gap-3">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-white text-royal">
                            <Info className="size-4" aria-hidden="true" />
                        </span>
                        <span className="flex flex-col gap-0.5">
                            <span className="text-admin-card-title font-semibold text-navy">
                                {meta ? `${meta.unused.files} ${meta.unused.files === 1 ? 'file isn’t' : 'files aren’t'} used on any page · ${formatBytes(meta.unused.bytes)}` : 'Checking which files are unused…'}
                            </span>
                            <span className="text-admin-small text-ink-soft">Checked against every page, collection and condition. Deleted files wait in Trash for 30 days, so nothing on the live site breaks.</span>
                        </span>
                    </p>
                    <button type="button" onClick={() => update({ show: null, unused_days: null })} className="inline-flex items-center gap-1.5 text-admin-body font-semibold text-ink hover:text-royal">
                        <X className="size-4" aria-hidden="true" /> Clear filter
                    </button>
                </div>
            ) : (
                missingAlt > 0 && (
                    <div role="note" className="flex flex-wrap items-center justify-between gap-4 rounded-card bg-warning-soft px-6 py-4">
                        <p className="flex items-start gap-3">
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-white text-warning">
                                <TriangleAlert className="size-4" aria-hidden="true" />
                            </span>
                            <span className="flex flex-col gap-0.5">
                                <span className="text-admin-card-title font-semibold text-ink">
                                    {missingAlt} {missingAlt === 1 ? 'image still needs' : 'images still need'} alt text
                                </span>
                                <span className="text-admin-small text-ink-soft">Screen readers and Google use it to understand images. Images without it can’t be added to pages.</span>
                            </span>
                        </p>
                        <Button variant="secondary" size="sm" onClick={() => update({ show: 'no_alt', folder: null })}>
                            Review {missingAlt} {missingAlt === 1 ? 'image' : 'images'} →
                        </Button>
                    </div>
                )
            )}

            <nav aria-label="Folders" className="flex flex-wrap items-center gap-2.5">
                <FolderPill active={folder === ''} label="All files" count={totals?.all} onClick={() => update({ folder: null })} />
                {folders.data?.map((item) => (
                    <FolderPill key={item.id} active={folder === String(item.id)} label={item.name} count={item.media_count} onClick={() => update({ folder: String(item.id) })} />
                ))}
                {canManage && (
                    <button type="button" onClick={() => setFolderDialog(true)} className="inline-flex h-10 items-center gap-1.5 rounded-full border border-dashed border-royal/50 px-4 text-admin-body font-semibold text-royal hover:bg-sky-soft">
                        <Plus className="size-4" aria-hidden="true" /> Folder
                    </button>
                )}
            </nav>

            <div className={cn('grid items-start gap-6', panel && 'xl:grid-cols-[minmax(0,1fr)_360px]')}>
                <section
                    aria-label="Files"
                    className="relative flex min-w-0 flex-col gap-4"
                    onDragOver={(event) => {
                        if (!canManage || !event.dataTransfer.types.includes('Files')) return;
                        event.preventDefault();
                        setDragging(true);
                    }}
                    onDragLeave={(event) => {
                        if (!event.currentTarget.contains(event.relatedTarget as Node | null)) setDragging(false);
                    }}
                    onDrop={onDrop}
                >
                    <div className="flex flex-wrap items-center gap-3">
                        <SearchInput label="Search files" className="min-w-[220px] flex-1" placeholder="Search by name or alt text" value={search} onChange={(event) => {
                            setSearch(event.target.value);
                            update({ search: event.target.value.trim() || null });
                        }} />
                        <FilterSelect label="Sort" value={sort} onChange={(event) => update({ sort: event.target.value === 'newest' ? null : event.target.value })} className="min-w-[160px]">
                            {Object.entries(SORTS).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </FilterSelect>
                        <div role="group" aria-label="View" className="flex h-11 items-center gap-1 rounded-full border border-line bg-white p-1">
                            <ViewButton active={view === 'grid'} label="Grid view" onClick={() => update({ view: null })}>
                                <LayoutGrid className="size-4" aria-hidden="true" />
                            </ViewButton>
                            <ViewButton active={view === 'list'} label="List view" onClick={() => update({ view: 'list' })}>
                                <List className="size-4" aria-hidden="true" />
                            </ViewButton>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex min-w-0 items-center gap-3">
                            <span className="text-admin-body text-ink-soft">Show</span>
                            <div role="radiogroup" aria-label="Show" className="flex flex-wrap items-center gap-1 rounded-full border border-line bg-white p-1">
                                {showOptions.map((option) => (
                                    <button
                                        key={option.value}
                                        type="button"
                                        role="radio"
                                        aria-checked={show === option.value}
                                        onClick={() => update({ show: option.value === 'all' ? null : option.value, unused_days: null, file: null })}
                                        className={cn(
                                            'inline-flex h-8 items-center gap-1.5 rounded-full px-3 text-admin-body font-semibold whitespace-nowrap transition-colors',
                                            show === option.value ? 'bg-navy text-white' : 'text-ink hover:bg-paper',
                                        )}
                                    >
                                        {option.icon}
                                        {option.label}
                                        {option.count !== undefined && <span className={cn('font-normal', show === option.value ? 'text-white/80' : 'text-ink-soft')}>{option.count}</span>}
                                    </button>
                                ))}
                            </div>
                        </div>
                        {show === 'unused' ? (
                            <FilterSelect label="Unused for" value={unusedDays} onChange={(event) => update({ unused_days: event.target.value || null })} className="min-w-[190px]">
                                <option value="">Any time</option>
                                <option value="30">Not used in 30+ days</option>
                                <option value="90">Not used in 90+ days</option>
                                <option value="365">Not used in a year</option>
                            </FilterSelect>
                        ) : (
                            <FilterSelect label="Type" value={kind} onChange={(event) => update({ kind: event.target.value || null })} className="min-w-[150px]">
                                <option value="">All types</option>
                                <option value="image">Images</option>
                                <option value="document">Documents</option>
                            </FilterSelect>
                        )}
                    </div>

                    <UploadTray queue={queue} folderName={uploadFolderName} onCancel={cancelUploads} onDismiss={() => dispatch({ type: 'clear' })} onAddAlt={(id) => update({ file: String(id) })} />

                    {canManage && selected.length > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-card bg-navy px-5 py-3 text-white" role="region" aria-label="Selection">
                            <p className="flex items-center gap-3 text-admin-body">
                                <span className="flex size-5 items-center justify-center rounded-[5px] bg-royal">
                                    <Check className="size-3.5" strokeWidth={3} aria-hidden="true" />
                                </span>
                                <span className="font-semibold">{selected.length} selected</span>
                                {selected.length < items.length && (
                                    <button type="button" onClick={() => setSelected(items.map((item) => item.id))} className="text-sky hover:underline">
                                        Select all {items.length}
                                    </button>
                                )}
                                <button type="button" onClick={() => setSelected([])} className="text-white/70 hover:text-white">
                                    Clear
                                </button>
                            </p>
                            <div className="flex flex-wrap items-center gap-2">
                                <Dropdown
                                    trigger={(props) => (
                                        <button type="button" {...props} className="inline-flex h-8 items-center gap-1.5 rounded-full bg-white/10 px-3.5 text-[13px] font-bold hover:bg-white/20">
                                            <FolderInput className="size-3.5" aria-hidden="true" /> Move to folder <ChevronDown className="size-3.5" aria-hidden="true" />
                                        </button>
                                    )}
                                    items={[
                                        { label: 'No folder', onSelect: () => runBulk('move', null) },
                                        ...(folders.data ?? []).map((item) => ({ label: item.name, onSelect: () => runBulk('move', item.id) })),
                                    ]}
                                />
                                <Button size="sm" variant="danger" icon={<Trash2 className="size-3.5" />} onClick={() => setConfirmBulk(true)}>
                                    Move {selected.length} to Trash
                                </Button>
                            </div>
                        </div>
                    )}

                    {list.isPending ? (
                        <div className="grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-4">
                            {Array.from({ length: 8 }, (_, index) => (
                                <Skeleton key={index} className="aspect-[4/5] w-full rounded-[18px]" />
                            ))}
                        </div>
                    ) : list.isError ? (
                        <ErrorState title="The library didn’t load" description={list.error instanceof ApiError ? list.error.humanMessage : 'Try again in a moment.'} onRetry={() => void list.refetch()} />
                    ) : items.length === 0 ? (
                        <EmptyState
                            icon={<ImageOff className="size-5" aria-hidden="true" />}
                            title={show === 'unused' ? 'Every file is in use' : show === 'no_alt' ? 'Every image has alt text' : 'No files match'}
                            description={canManage && show === 'all' && !debounced ? 'Drop files here or use Upload files.' : undefined}
                        />
                    ) : view === 'grid' ? (
                        <ul className="grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-4">
                            {items.map((item) => (
                                <li key={item.id} className="min-w-0">
                                    <MediaCard
                                        item={item}
                                        active={fileId === item.id}
                                        checked={selected.includes(item.id)}
                                        selectable={canManage}
                                        unusedView={show === 'unused'}
                                        onOpen={() => update({ file: fileId === item.id ? null : String(item.id) })}
                                        onToggle={() => toggle(item.id)}
                                    />
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <MediaTable items={items} activeId={fileId} selected={selected} selectable={canManage} onOpen={(id) => update({ file: fileId === id ? null : String(id) })} onToggle={toggle} />
                    )}

                    {meta && items.length > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-admin-small text-ink-soft">
                                Showing {items.length} of {meta.total} {show === 'unused' ? 'unused files' : 'files'}
                            </p>
                            {list.hasNextPage && (
                                <Button variant="secondary" icon={<ChevronDown className="size-4" />} loading={list.isFetchingNextPage} onClick={() => void list.fetchNextPage()}>
                                    Load {Math.min(PER_PAGE, meta.total - items.length)} more
                                </Button>
                            )}
                        </div>
                    )}

                    {dragging && (
                        <div aria-hidden="true" className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-sheet border-2 border-dashed border-royal bg-sky-soft/85">
                            <p className="flex items-center gap-2 text-admin-card-title font-semibold text-royal">
                                <Upload className="size-5" /> Drop to upload{uploadFolderName ? ` to “${uploadFolderName}”` : ''}
                            </p>
                        </div>
                    )}
                </section>

                {panel === 'details' && fileId !== null && <FileDetailsPanel id={fileId} folders={folders.data ?? []} canManage={canManage} onClose={() => update({ file: null })} />}
                {panel === 'cleanup' && meta && (
                    <CleanUpPanel
                        summary={meta.unused}
                        selected={selected.length}
                        canManage={canManage}
                        trashCount={meta.counts.trash}
                        busy={bulk.isPending}
                        onTrashSelected={() => setConfirmBulk(true)}
                        onOpenTrash={() => setTrashOpen(true)}
                    />
                )}
            </div>

            {show !== 'unused' && (totals?.trash ?? 0) > 0 && (
                <button type="button" onClick={() => setTrashOpen(true)} className="self-start text-admin-body font-semibold text-ink-soft hover:text-royal">
                    <Trash2 className="mr-1.5 inline size-4 align-[-3px]" aria-hidden="true" />
                    Trash ({totals?.trash})
                </button>
            )}

            <NewFolderDialog open={folderDialog} onClose={() => setFolderDialog(false)} onCreated={(id) => update({ folder: String(id) })} />
            <TrashDialog open={trashOpen} onClose={() => setTrashOpen(false)} canManage={canManage} />
            <ConfirmDialog
                open={confirmBulk}
                title={`Move ${selected.length} ${selected.length === 1 ? 'file' : 'files'} to Trash?`}
                description="They stay in Trash for 30 days, so you can restore them until then. Files still used on the website are refused."
                confirmLabel={`Move ${selected.length} to Trash`}
                busy={bulk.isPending}
                onClose={() => setConfirmBulk(false)}
                onConfirm={() => runBulk('trash')}
            />
        </>
    );
}

function FolderPill({ active, label, count, onClick }: { active: boolean; label: string; count: number | undefined; onClick: () => void }) {
    return (
        <button
            type="button"
            aria-pressed={active}
            onClick={onClick}
            className={cn(
                'inline-flex h-10 items-center gap-2 rounded-full border px-4 text-admin-body font-semibold transition-colors',
                active ? 'border-navy bg-navy text-white' : 'border-line bg-white text-ink hover:border-ink-soft/50',
            )}
        >
            <Folder className="size-4" aria-hidden="true" />
            {label}
            {count !== undefined && <span className={cn('font-normal', active ? 'text-white/80' : 'text-ink-soft')}>{count}</span>}
        </button>
    );
}

function ViewButton({ active, label, onClick, children }: { active: boolean; label: string; onClick: () => void; children: ReactNode }) {
    return (
        <button type="button" aria-label={label} aria-pressed={active} onClick={onClick} className={cn('flex size-9 items-center justify-center rounded-full transition-colors', active ? 'bg-navy text-white' : 'text-ink-soft hover:bg-paper hover:text-ink')}>
            {children}
        </button>
    );
}

function MediaTable({ items, activeId, selected, selectable, onOpen, onToggle }: { items: MediaItem[]; activeId: number | null; selected: number[]; selectable: boolean; onOpen: (id: number) => void; onToggle: (id: number) => void }) {
    return (
        <div className="overflow-x-auto rounded-card border border-line bg-white">
            <table className="w-full min-w-[640px] text-left">
                <caption className="sr-only">Files</caption>
                <thead>
                    <tr className="border-b border-line font-display text-admin-overline font-bold tracking-[1.1px] text-ink-soft uppercase">
                        {selectable && <th scope="col" className="w-12 py-3 pl-5"><span className="sr-only">Select</span></th>}
                        <th scope="col" className="px-4 py-3">File</th>
                        <th scope="col" className="px-4 py-3">Size</th>
                        <th scope="col" className="px-4 py-3">Usage</th>
                        <th scope="col" className="px-4 py-3">Uploaded</th>
                    </tr>
                </thead>
                <tbody>
                    {items.map((item) => (
                        <tr key={item.id} className={cn('border-b border-line last:border-0', activeId === item.id && 'bg-sky-soft/60')}>
                            {selectable && (
                                <td className="py-3 pl-5">
                                    <input type="checkbox" aria-label={`Select ${item.filename}`} checked={selected.includes(item.id)} onChange={() => onToggle(item.id)} className="size-4 accent-royal" />
                                </td>
                            )}
                            <td className="px-4 py-2.5">
                                <button type="button" onClick={() => onOpen(item.id)} className="flex min-w-0 items-center gap-3 text-left">
                                    <span className="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-[10px] bg-sky-soft text-royal">
                                        {item.thumbnail_url ? <img src={item.thumbnail_url} alt="" className="size-full object-cover" /> : <FileText className="size-5" aria-hidden="true" />}
                                    </span>
                                    <span className="flex min-w-0 flex-col">
                                        <span className="truncate text-admin-body font-semibold text-ink">{item.filename}</span>
                                        {item.needs_alt_text && <span className="text-admin-small text-warning">No alt text</span>}
                                    </span>
                                </button>
                            </td>
                            <td className="px-4 py-2.5 text-admin-body text-ink-soft">{fileFacts(item)}</td>
                            <td className="px-4 py-2.5 text-admin-body text-ink-soft">{usageLine(item)}</td>
                            <td className="px-4 py-2.5 text-admin-body text-ink-soft">{relativeTime(item.created_at)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
