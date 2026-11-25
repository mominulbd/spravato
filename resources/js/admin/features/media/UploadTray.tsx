import { Check, CircleAlert, ImageIcon, Upload } from 'lucide-react';
import { cn } from '@shared/ui';
import { uploadProgress, type UploadEntry } from './uploadQueue';

/** "Uploading 2 of 3 files to “Clinic photos”" (Figma 18). */
interface UploadTrayProps {
    queue: UploadEntry[];
    folderName: string | null;
    onCancel: () => void;
    onDismiss: () => void;
    onAddAlt: (mediaId: number) => void;
}

export function UploadTray({ queue, folderName, onCancel, onDismiss, onAddAlt }: UploadTrayProps) {
    const visible = queue.filter((entry) => entry.state !== 'cancelled');
    const progress = uploadProgress(queue);

    if (visible.length === 0) {
        return null;
    }

    const target = folderName ? ` to “${folderName}”` : '';

    return (
        <section aria-label="Uploads" className="flex flex-col gap-3 rounded-sheet border border-line bg-white px-5 py-4">
            <div className="flex items-center justify-between gap-4">
                <p className="flex items-center gap-2.5 text-admin-body font-semibold text-ink" aria-live="polite">
                    <Upload className="size-4 text-royal" aria-hidden="true" />
                    {progress.active ? `Uploading ${progress.current} of ${progress.total} ${progress.total === 1 ? 'file' : 'files'}${target}` : `Uploaded ${visible.filter((e) => e.state === 'done').length} of ${progress.total}${target}`}
                </p>
                <button type="button" onClick={progress.active ? onCancel : onDismiss} className="text-admin-body font-semibold text-ink-soft hover:text-ink">
                    {progress.active ? 'Cancel' : 'Close'}
                </button>
            </div>
            <ul className="flex flex-col gap-2.5">
                {visible.map((entry) => (
                    <li key={entry.key} className="grid grid-cols-[36px_1fr_auto] items-center gap-3">
                        <span className="flex size-9 items-center justify-center rounded-[10px] bg-sky-soft text-royal">
                            <ImageIcon className="size-4" aria-hidden="true" />
                        </span>
                        <span className="flex min-w-0 flex-col gap-1.5">
                            <span className="truncate text-admin-body text-ink">{entry.name}</span>
                            <span className="h-1 w-full overflow-hidden rounded-full bg-stone/70" aria-hidden="true">
                                <span
                                    className={cn(
                                        'block h-full rounded-full transition-all',
                                        entry.state === 'done' && 'w-full bg-success',
                                        entry.state === 'uploading' && 'w-2/3 animate-pulse bg-royal',
                                        entry.state === 'failed' && 'w-full bg-danger',
                                        entry.state === 'waiting' && 'w-0',
                                    )}
                                />
                            </span>
                        </span>
                        <span className="min-w-[120px] text-right text-admin-small">
                            {entry.state === 'waiting' && <span className="text-ink-soft">Waiting…</span>}
                            {entry.state === 'uploading' && <span className="text-ink-soft">Uploading…</span>}
                            {entry.state === 'failed' && (
                                <span className="inline-flex items-center gap-1 text-danger" title={entry.error}>
                                    <CircleAlert className="size-3.5" aria-hidden="true" /> {entry.error ?? 'Failed'}
                                </span>
                            )}
                            {entry.state === 'done' &&
                                (entry.needsAlt && entry.mediaId !== undefined ? (
                                    <button type="button" onClick={() => onAddAlt(entry.mediaId as number)} className="inline-flex items-center gap-1 font-semibold text-royal hover:underline">
                                        <Check className="size-3.5" aria-hidden="true" /> Add alt text →
                                    </button>
                                ) : (
                                    <span className="inline-flex items-center gap-1 text-success">
                                        <Check className="size-3.5" aria-hidden="true" /> Done
                                    </span>
                                ))}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
