import { Info, Trash2 } from 'lucide-react';
import { Button } from '@shared/ui';
import type { UnusedSummary } from './types';
import { formatBytes } from './types';

/** "CLEAN UP · Unused files" (Figma 18b). */
interface CleanUpPanelProps {
    summary: UnusedSummary;
    selected: number;
    canManage: boolean;
    trashCount: number;
    busy: boolean;
    onTrashSelected: () => void;
    onOpenTrash: () => void;
}

export function CleanUpPanel({ summary, selected, canManage, trashCount, busy, onTrashSelected, onOpenTrash }: CleanUpPanelProps) {
    return (
        <aside aria-label="Clean up unused files" className="flex flex-col gap-5 rounded-sheet border border-line bg-white p-6">
            <div>
                <p className="font-display text-admin-overline font-bold tracking-[1.1px] text-ink-soft uppercase">Clean up</p>
                <h2 className="mt-1 font-serif text-admin-section-title text-ink">Unused files</h2>
            </div>
            <dl className="grid grid-cols-2 gap-3">
                <div className="rounded-card bg-paper px-4 py-3.5">
                    <dt className="text-admin-small text-ink-soft">Files</dt>
                    <dd className="mt-1 font-serif text-admin-stat text-ink">{summary.files}</dd>
                </div>
                <div className="rounded-card bg-paper px-4 py-3.5">
                    <dt className="text-admin-small text-ink-soft">Space</dt>
                    <dd className="mt-1 font-serif text-admin-stat text-ink">{formatBytes(summary.bytes)}</dd>
                </div>
            </dl>
            <div className="flex flex-col gap-2.5">
                <p className="font-display text-admin-overline font-bold tracking-[1.1px] text-ink-soft uppercase">Why they’re unused</p>
                <Reason title="Never used" description="Uploaded but never added to a page" count={summary.never_used} />
                <Reason title="Removed from pages" description="Used before, then taken off" count={summary.removed} />
            </div>
            <p className="flex gap-2.5 rounded-card bg-sky-soft px-4 py-3.5 text-admin-small text-navy">
                <Info className="mt-0.5 size-4 shrink-0 text-royal" aria-hidden="true" />
                Insurance logos come and go. Move ones you may need again to a folder instead of deleting them.
            </p>
            {canManage && (
                <Button variant="danger" icon={<Trash2 className="size-4" />} disabled={selected === 0} loading={busy} onClick={onTrashSelected}>
                    {selected === 0 ? 'Select files to move to Trash' : `Move ${selected} selected to Trash`}
                </Button>
            )}
            <p className="text-admin-small text-ink-soft">Trash empties after 30 days. You can restore files until then.</p>
            <button type="button" onClick={onOpenTrash} className="self-start text-admin-body font-semibold text-royal hover:underline">
                Open Trash ({trashCount}) →
            </button>
        </aside>
    );
}

function Reason({ title, description, count }: { title: string; description: string; count: number }) {
    return (
        <div className="flex items-center justify-between gap-3 rounded-card border border-line px-4 py-3">
            <span className="flex min-w-0 flex-col">
                <span className="text-admin-body font-semibold text-ink">{title}</span>
                <span className="text-admin-small text-ink-soft">{description}</span>
            </span>
            <span className="text-admin-card-title font-semibold text-ink">{count}</span>
        </div>
    );
}
