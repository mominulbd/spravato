import { useRef, useState } from 'react';
import { Link } from 'react-router';
import { CircleCheck, Download, ExternalLink, FileText, Lock, RefreshCw, Trash2, X } from 'lucide-react';
import { ApiError } from '@shared/api/client';
import { Badge, Button, ConfirmDialog, ErrorState, Select, Skeleton, TextInput, Textarea, Toggle, buttonClasses, useToast } from '@shared/ui';
import { useMediaItem, useReplaceMedia, useTrashMedia, useUpdateMedia } from './api';
import { isLogo, savings } from './describe';
import { FocalPointEditor } from './FocalPointEditor';
import type { MediaFolder, MediaItem } from './types';
import { formatBytes } from './types';

/** The right-hand "FILE DETAILS" panel of Figma 18. */
interface FileDetailsPanelProps {
    id: number;
    folders: MediaFolder[];
    canManage: boolean;
    onClose: () => void;
}

export function FileDetailsPanel({ id, folders, canManage, onClose }: FileDetailsPanelProps) {
    const media = useMediaItem(id);

    return (
        <aside aria-label="File details" className="flex flex-col gap-5 rounded-sheet border border-line bg-white p-6">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="font-display text-admin-overline font-bold tracking-[1.1px] text-ink-soft uppercase">File details</p>
                    <p className="mt-1 text-admin-card-title font-semibold text-ink">1 selected</p>
                </div>
                <button type="button" aria-label="Close file details" onClick={onClose} className="rounded-full border border-line p-2 text-ink-soft hover:border-ink-soft hover:text-ink">
                    <X className="size-4" aria-hidden="true" />
                </button>
            </div>
            {media.isPending ? (
                <div className="flex flex-col gap-4">
                    <Skeleton className="aspect-[4/3] w-full rounded-[16px]" />
                    <Skeleton className="h-10 w-full rounded-control" />
                    <Skeleton className="h-24 w-full rounded-control" />
                </div>
            ) : media.isError ? (
                <ErrorState title="This file didn’t load" description="It may have been moved to Trash." onRetry={() => void media.refetch()} />
            ) : (
                <DetailsForm key={`${media.data.id}:${media.data.updated_at}`} item={media.data} folders={folders} canManage={canManage} onClose={onClose} />
            )}
        </aside>
    );
}

function DetailsForm({ item, folders, canManage, onClose }: { item: MediaItem; folders: MediaFolder[]; canManage: boolean; onClose: () => void }) {
    const toast = useToast();
    const update = useUpdateMedia();
    const replace = useReplaceMedia();
    const trash = useTrashMedia();
    const fileInput = useRef<HTMLInputElement>(null);
    const [filename, setFilename] = useState(item.filename);
    const [alt, setAlt] = useState(item.alt ?? '');
    const [decorative, setDecorative] = useState(item.is_decorative);
    const [caption, setCaption] = useState(item.caption ?? '');
    const [folderId, setFolderId] = useState(item.folder_id);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [confirmTrash, setConfirmTrash] = useState(false);
    const usages = item.usages ?? [];
    const dirty = filename !== item.filename || alt !== (item.alt ?? '') || decorative !== item.is_decorative || caption !== (item.caption ?? '') || folderId !== item.folder_id;

    const fail = (title: string) => (error: unknown) => {
        if (error instanceof ApiError && error.status === 422) {
            setErrors(Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [key, messages[0] ?? ''])));
        } else {
            toast.error(title, error instanceof ApiError ? error.humanMessage : undefined);
        }
    };

    const save = () => {
        const local: Record<string, string> = {};
        if (filename.trim() === '') local.filename = 'Give the file a name.';
        if (item.kind === 'image' && !decorative && alt.trim() === '') local.alt = 'Describe the image, or mark it decorative.';
        setErrors(local);
        if (Object.keys(local).length > 0) return;

        update.mutate(
            { id: item.id, filename: filename.trim(), alt: decorative ? null : alt.trim(), is_decorative: decorative, caption: caption.trim() || null, folder_id: folderId },
            { onSuccess: () => toast.success('File details saved', usages.length > 0 ? 'The website updates within seconds.' : undefined), onError: fail('Couldn’t save the details') },
        );
    };

    const saved = savings(item);

    return (
        <>
            {item.kind === 'image' ? (
                <div className="flex flex-col gap-2">
                    <FocalPointEditor
                        src={item.url}
                        focal={item.focal}
                        disabled={!canManage}
                        contain={isLogo(item)}
                        onCommit={(focal) => update.mutate({ id: item.id, focal_x: focal.x, focal_y: focal.y }, { onError: fail('Couldn’t save the focal point') })}
                    />
                    <p className="text-admin-small text-ink-soft">The focus stays visible when this image is cropped for cards and phones.</p>
                </div>
            ) : (
                <div className="flex aspect-[4/3] w-full flex-col items-center justify-center gap-2 rounded-[16px] bg-sky-soft text-royal">
                    <FileText className="size-10" aria-hidden="true" />
                    <span className="text-admin-small font-bold tracking-[0.08em]">PDF · {formatBytes(item.size)}</span>
                </div>
            )}

            <TextInput label="File name" value={filename} disabled={!canManage} maxLength={190} error={errors.filename} onChange={(event) => setFilename(event.target.value)} />

            {item.kind === 'image' && (
                <div className="flex flex-col gap-1 rounded-card bg-success-soft px-4 py-3.5 text-success">
                    <p className="flex items-center gap-2 text-admin-body font-semibold">
                        <CircleCheck className="size-4" aria-hidden="true" /> {formatBytes(item.delivered_size)} on the website
                    </p>
                    <p className="text-admin-small">
                        {saved > 0 ? `${saved}% smaller than the ${formatBytes(item.original_size)} original` : `Original ${formatBytes(item.original_size)}`}
                        {item.width && item.height ? ` · ${item.width} × ${item.height} px` : ''}
                        {!item.variants_ready && ' · WebP/AVIF still processing'}
                    </p>
                </div>
            )}

            {item.kind === 'image' && (
                <div className="flex flex-col gap-3">
                    {!decorative && (
                        <Textarea
                            label="Alt text (required)"
                            value={alt}
                            maxLength={125}
                            rows={2}
                            disabled={!canManage}
                            error={errors.alt}
                            help="Describe it as you would to someone on the phone."
                            onChange={(event) => setAlt(event.target.value)}
                        />
                    )}
                    <Toggle label="Decorative image — no alt text needed" checked={decorative} disabled={!canManage} onChange={(event) => setDecorative(event.target.checked)} />
                </div>
            )}

            <TextInput label="Caption (optional)" value={caption} maxLength={190} disabled={!canManage} error={errors.caption} onChange={(event) => setCaption(event.target.value)} />

            <Select label="Folder" value={folderId ?? ''} disabled={!canManage} onChange={(event) => setFolderId(event.target.value === '' ? null : Number(event.target.value))}>
                <option value="">No folder</option>
                {folders.map((folder) => (
                    <option key={folder.id} value={folder.id}>
                        {folder.name}
                    </option>
                ))}
            </Select>

            {canManage && dirty && (
                <div className="flex gap-2">
                    <Button loading={update.isPending} onClick={save}>
                        Save details
                    </Button>
                    <Button
                        variant="ghost"
                        onClick={() => {
                            setFilename(item.filename);
                            setAlt(item.alt ?? '');
                            setDecorative(item.is_decorative);
                            setCaption(item.caption ?? '');
                            setFolderId(item.folder_id);
                            setErrors({});
                        }}
                    >
                        Discard
                    </Button>
                </div>
            )}

            <section aria-labelledby="usage-heading" className="flex flex-col gap-2.5">
                <div className="flex items-center justify-between gap-3">
                    <h3 id="usage-heading" className="text-admin-label font-semibold text-ink">
                        Where it’s used
                    </h3>
                    <Badge tone={usages.length > 0 ? 'info' : 'neutral'}>
                        {usages.length} {usages.length === 1 ? 'place' : 'places'}
                    </Badge>
                </div>
                {usages.length === 0 ? (
                    <p className="rounded-card bg-paper px-4 py-3 text-admin-small text-ink-soft">Not on any page yet.</p>
                ) : (
                    <ul className="flex flex-col gap-2">
                        {usages.map((usage) => (
                            <li key={`${usage.label}:${usage.context}`}>
                                {usage.admin_path ? (
                                    <Link to={usage.admin_path} className="flex items-center justify-between gap-3 rounded-card bg-paper px-4 py-3 hover:bg-stone/60">
                                        <UsageText label={usage.label} context={usage.context} />
                                        <ExternalLink className="size-4 shrink-0 text-ink-soft" aria-hidden="true" />
                                    </Link>
                                ) : (
                                    <div className="rounded-card bg-paper px-4 py-3">
                                        <UsageText label={usage.label} context={usage.context} />
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <div className="flex flex-wrap items-center gap-2">
                {canManage && (
                    <>
                        <input
                            ref={fileInput}
                            type="file"
                            className="sr-only"
                            accept={item.kind === 'image' ? 'image/jpeg,image/png,image/webp' : 'application/pdf'}
                            onChange={(event) => {
                                const file = event.target.files?.[0];
                                event.target.value = '';
                                if (!file) return;
                                replace.mutate(
                                    { id: item.id, file },
                                    { onSuccess: () => toast.success('File replaced', 'Every page now shows the new file.'), onError: fail('Couldn’t replace the file') },
                                );
                            }}
                        />
                        <Button variant="secondary" size="sm" icon={<RefreshCw className="size-3.5" />} loading={replace.isPending} onClick={() => fileInput.current?.click()}>
                            Replace file
                        </Button>
                    </>
                )}
                <a href={`/api/v1/admin/media/${item.id}/download`} className={buttonClasses('ghost', 'sm')}>
                    <Download className="size-3.5" aria-hidden="true" /> Download
                </a>
            </div>
            {canManage && <p className="-mt-3 text-admin-small text-ink-soft">Replacing keeps every page linked to the new file.</p>}

            {canManage &&
                (usages.length > 0 ? (
                    <div className="flex items-center gap-3 rounded-card border border-line px-4 py-3.5 text-ink-soft">
                        <Lock className="size-4 shrink-0" aria-hidden="true" />
                        <p className="flex-1 text-admin-small">
                            To delete, remove it from {usages.length} {usages.length === 1 ? 'page' : 'pages'} first.
                        </p>
                        <span className="text-admin-small font-semibold text-ink-soft/70">Delete</span>
                    </div>
                ) : (
                    <Button variant="secondary" icon={<Trash2 className="size-4" />} className="text-danger" onClick={() => setConfirmTrash(true)}>
                        Move to Trash
                    </Button>
                ))}

            <ConfirmDialog
                open={confirmTrash}
                title={`Move ${item.filename} to Trash?`}
                description="It stays in Trash for 30 days, so you can restore it until then."
                confirmLabel="Move to Trash"
                busy={trash.isPending}
                onClose={() => setConfirmTrash(false)}
                onConfirm={() =>
                    trash.mutate(item.id, {
                        onSuccess: () => {
                            setConfirmTrash(false);
                            toast.success('Moved to Trash', item.filename);
                            onClose();
                        },
                        onError: (error) => {
                            setConfirmTrash(false);
                            fail('Couldn’t move it to Trash')(error);
                        },
                    })
                }
            />
        </>
    );
}

function UsageText({ label, context }: { label: string; context: string }) {
    return (
        <span className="flex min-w-0 flex-col">
            <span className="truncate text-admin-body font-semibold text-ink">{label}</span>
            <span className="truncate text-admin-small text-ink-soft">{context}</span>
        </span>
    );
}
