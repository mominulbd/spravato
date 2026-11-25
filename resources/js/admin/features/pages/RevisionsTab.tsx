import { useState } from 'react';
import { History } from 'lucide-react';
import { ApiError } from '@shared/api/client';
import { relativeTime } from '@shared/format/time';
import { Badge, Button, ConfirmDialog, EmptyState, ErrorState, Skeleton, useToast, type BadgeTone } from '@shared/ui';
import { pageRequests, useRevisions } from './api';
import { shortDateTime } from './statusBadge';
import type { Revision } from './types';
import type { Editor } from './useEditor';

const KIND: Record<Revision['kind'], { label: string; tone: BadgeTone }> = {
    published: { label: 'Published', tone: 'success' },
    draft: { label: 'Draft saved', tone: 'neutral' },
    scheduled: { label: 'Scheduled', tone: 'info' },
    restored: { label: 'Restored', tone: 'warning' },
};

/** Every saved checkpoint and published version; restoring copies it into the draft. */
export function RevisionsTab({ editor }: { editor: Editor }) {
    const toast = useToast();
    const revisions = useRevisions(editor.meta.id, true);
    const [restoring, setRestoring] = useState<Revision | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const restore = async () => {
        if (!restoring) return;
        setBusy(true);

        try {
            if (!(await editor.save())) throw new Error('Save your current changes first.');
            const response = await pageRequests.restoreRevision(editor.meta.id, restoring.id, editor.lockVersion());
            editor.applyPage(response.data, true);
            toast.success('Revision restored to your draft', 'Publish to make it live.');
            setRestoring(null);
            void revisions.refetch();
        } catch (failure) {
            setError(failure instanceof ApiError ? failure.humanMessage : failure instanceof Error ? failure.message : 'That didn’t work.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <section aria-labelledby="revisions-title" className="flex max-w-[860px] flex-col overflow-hidden rounded-sheet border border-line bg-white">
            <div className="border-b border-line px-6 py-5 sm:px-8">
                <h2 id="revisions-title" className="font-serif text-admin-section-title text-ink">
                    Revisions
                </h2>
                <p className="mt-1 text-admin-body text-ink-soft">“Save draft” and every publish keep a copy. Restoring replaces your draft, never the live page.</p>
            </div>
            {revisions.isPending ? (
                <div className="flex flex-col gap-3 p-6">
                    {[0, 1, 2].map((row) => (
                        <Skeleton key={row} className="h-10 w-full rounded-[12px]" />
                    ))}
                </div>
            ) : revisions.isError ? (
                <ErrorState title="Revisions didn’t load" description="Try again in a moment." onRetry={() => void revisions.refetch()} />
            ) : revisions.data.length === 0 ? (
                <EmptyState icon={<History className="size-5" aria-hidden="true" />} title="No revisions yet" description="Use Save draft to keep a checkpoint." />
            ) : (
                <ul className="divide-y divide-line">
                    {revisions.data.map((revision) => (
                        <li key={revision.id} className="flex flex-wrap items-center gap-x-4 gap-y-2 px-6 py-4 sm:px-8">
                            <div className="flex min-w-0 flex-1 flex-col gap-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge tone={KIND[revision.kind].tone}>{KIND[revision.kind].label}</Badge>
                                    {revision.is_live && (
                                        <Badge tone="navy" dot>
                                            Live now
                                        </Badge>
                                    )}
                                    <span className="text-admin-body text-ink" title={shortDateTime(revision.created_at)}>
                                        {relativeTime(revision.created_at)}
                                    </span>
                                </div>
                                <p className="text-admin-small text-ink-soft">
                                    {revision.user?.name ?? 'System'} · {revision.blocks_count} sections{revision.note ? ` · ${revision.note}` : ''}
                                </p>
                            </div>
                            <Button size="sm" variant="secondary" onClick={() => setRestoring(revision)}>
                                Restore
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
            <ConfirmDialog
                open={restoring !== null}
                tone="primary"
                title="Restore this revision?"
                description={`Your draft is replaced with the version from ${restoring ? shortDateTime(restoring.created_at) : ''}. The website keeps showing the published page until you publish.`}
                confirmLabel="Restore to draft"
                busy={busy}
                error={error}
                onClose={() => {
                    setRestoring(null);
                    setError(null);
                }}
                onConfirm={() => void restore()}
            />
        </section>
    );
}
