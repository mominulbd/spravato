import { FileText, RotateCcw, Trash2 } from 'lucide-react';
import { ApiError } from '@shared/api/client';
import { relativeTime } from '@shared/format/time';
import { Button, Dialog, EmptyState, ErrorState, Skeleton, useToast } from '@shared/ui';
import { useMediaTrash, useRestoreMedia } from './api';
import { fileFacts } from './describe';

/** Files waiting in the 30-day trash, with Restore. */
export function TrashDialog({ open, onClose, canManage }: { open: boolean; onClose: () => void; canManage: boolean }) {
    const toast = useToast();
    const trash = useMediaTrash(open);
    const restore = useRestoreMedia();
    const days = trash.data?.meta.purge_after_days ?? 30;

    return (
        <Dialog open={open} onClose={onClose} size="md" title="Trash" description={`Files here are deleted for good ${days} days after they were trashed.`}>
            <div className="max-h-[55vh] overflow-y-auto">
                {trash.isPending ? (
                    <div className="flex flex-col gap-2">
                        <Skeleton className="h-14 w-full rounded-card" />
                        <Skeleton className="h-14 w-full rounded-card" />
                    </div>
                ) : trash.isError ? (
                    <ErrorState title="Trash didn’t load" description="Try again in a moment." onRetry={() => void trash.refetch()} />
                ) : trash.data.items.length === 0 ? (
                    <EmptyState icon={<Trash2 className="size-5" />} title="Trash is empty" />
                ) : (
                    <ul className="flex flex-col gap-2">
                        {trash.data.items.map((item) => (
                            <li key={item.id} className="flex items-center gap-3 rounded-card border border-line p-2.5 pr-3">
                                <span className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-[10px] bg-sky-soft text-royal">
                                    {item.thumbnail_url ? <img src={item.thumbnail_url} alt="" className="size-full object-cover" /> : <FileText className="size-5" aria-hidden="true" />}
                                </span>
                                <span className="flex min-w-0 flex-1 flex-col">
                                    <span className="truncate text-admin-body font-semibold text-ink">{item.filename}</span>
                                    <span className="truncate text-admin-small text-ink-soft">
                                        {fileFacts(item)} · trashed {relativeTime(item.deleted_at)}
                                    </span>
                                </span>
                                {canManage && (
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        icon={<RotateCcw className="size-3.5" />}
                                        loading={restore.isPending && restore.variables === item.id}
                                        onClick={() =>
                                            restore.mutate(item.id, {
                                                onSuccess: () => toast.success('File restored', item.filename),
                                                onError: (error) => toast.error('Couldn’t restore it', error instanceof ApiError ? error.humanMessage : undefined),
                                            })
                                        }
                                    >
                                        Restore
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Dialog>
    );
}
