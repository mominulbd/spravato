import { useState } from 'react';
import { ArrowDown, ArrowUp, Copy, Ellipsis, Trash2, TriangleAlert, Undo2 } from 'lucide-react';
import { ConfirmDialog, Dropdown, EmptyState, cn } from '@shared/ui';
import { AddSectionDialog } from './AddSectionDialog';
import { BlockForm } from './BlockForm';
import { BlockPreview } from './BlockPreview';
import { SectionOutline } from './SectionOutline';
import { newKey } from './editorState';
import { fieldVisible } from './fieldRules';
import { shortDate, shortName } from './statusBadge';
import type { Editor } from './useEditor';
import type { BlockTypeDef } from './types';

interface ContentTabProps {
    editor: Editor;
    types: BlockTypeDef[];
    selected: string | null;
    onSelect: (key: string | null) => void;
    onOpenPreview: () => void;
}

const STATUS_LABEL = { published: 'Published', draft: 'Draft', scheduled: 'Scheduled' } as const;

/** Figma 07 · Content: outline, the selected section's form, preview and publishing facts. */
export function ContentTab({ editor, types, selected, onSelect, onOpenPreview }: ContentTabProps) {
    const typeMap = new Map(types.map((type) => [type.key, type]));
    const [adding, setAdding] = useState(false);
    const [deleting, setDeleting] = useState<string | null>(null);
    const index = editor.blocks.findIndex((block) => block.key === selected);
    const block = index >= 0 ? editor.blocks[index] : undefined;
    const type = block ? typeMap.get(block.type) : undefined;
    const errorKeys = new Set(Object.keys(editor.errors));
    const { meta } = editor;

    const move = (from: number, to: number) => {
        if (to < 0 || to >= editor.blocks.length) return;
        const next = [...editor.blocks];
        const [item] = next.splice(from, 1);
        if (item) next.splice(to, 0, item);
        editor.setBlocks(next);
    };

    const insert = (picked: BlockTypeDef) => {
        const key = newKey();
        const next = [...editor.blocks];
        next.splice(index >= 0 ? index + 1 : next.length, 0, { key, id: null, type: picked.key, data: structuredClone(picked.defaults), is_visible: true });
        editor.setBlocks(next);
        onSelect(key);
    };

    return (
        <div className="grid items-start gap-6 lg:grid-cols-[232px_minmax(0,1fr)] min-[87.5rem]:grid-cols-[232px_minmax(0,1fr)_288px]">
            <SectionOutline blocks={editor.blocks} types={typeMap} selected={selected} errorKeys={errorKeys} onSelect={onSelect} onChange={editor.setBlocks} onAdd={() => setAdding(true)} />

            <section aria-label="Section editor" className="min-w-0 rounded-sheet border border-line bg-white p-6 sm:p-8">
                {block && type ? (
                    <div className="flex flex-col gap-6">
                        <div className="flex items-start justify-between gap-4">
                            <div className="min-w-0">
                                <p className="font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-ink-soft uppercase">
                                    Section {index + 1} of {editor.blocks.length}
                                    {!block.is_visible && <span className="ml-2 text-warning">· Hidden on the website</span>}
                                </p>
                                <h2 className="mt-1 font-serif text-admin-section-title text-ink">{type.label}</h2>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <button
                                    type="button"
                                    aria-label="Undo changes to this section since the last save"
                                    title="Undo changes since the last save"
                                    disabled={!editor.hasSavedVersion(block.key)}
                                    onClick={() => editor.revertBlock(block.key)}
                                    className="flex size-10 items-center justify-center rounded-full border border-line text-ink hover:border-ink-soft disabled:opacity-40"
                                >
                                    <Undo2 className="size-4" aria-hidden="true" />
                                </button>
                                <Dropdown
                                    items={[
                                        {
                                            label: 'Duplicate',
                                            icon: <Copy className="size-4" aria-hidden="true" />,
                                            onSelect: () => {
                                                const key = newKey();
                                                const next = [...editor.blocks];
                                                next.splice(index + 1, 0, { ...block, key, id: null, data: structuredClone(block.data) });
                                                editor.setBlocks(next);
                                                onSelect(key);
                                            },
                                        },
                                        { label: 'Move up', icon: <ArrowUp className="size-4" aria-hidden="true" />, disabled: index === 0, onSelect: () => move(index, index - 1) },
                                        { label: 'Move down', icon: <ArrowDown className="size-4" aria-hidden="true" />, disabled: index === editor.blocks.length - 1, onSelect: () => move(index, index + 1) },
                                        { label: 'Delete section', icon: <Trash2 className="size-4" aria-hidden="true" />, tone: 'danger', onSelect: () => setDeleting(block.key) },
                                    ]}
                                    trigger={({ ref, ...props }) => (
                                        <button ref={ref} type="button" {...props} aria-label="More section actions" className="flex size-10 items-center justify-center rounded-full border border-line text-ink hover:border-ink-soft">
                                            <Ellipsis className="size-4" aria-hidden="true" />
                                        </button>
                                    )}
                                />
                            </div>
                        </div>
                        {Object.keys(editor.errors[block.key] ?? {}).length > 0 && (
                            <p role="alert" className="flex items-start gap-2 rounded-[14px] bg-danger-soft px-4 py-3 text-admin-body text-danger">
                                <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" /> Fix the highlighted fields — this section can’t be saved yet.
                            </p>
                        )}
                        <BlockForm key={block.key} fields={type.fields} data={block.data} errors={editor.errors[block.key]} isVisible={(field, data) => fieldVisible(block.type, field, data)} onChange={(data) => editor.updateBlock(block.key, { data })} />
                    </div>
                ) : (
                    <EmptyState icon={<Ellipsis className="size-5" aria-hidden="true" />} title={editor.blocks.length === 0 ? 'This page has no sections yet' : 'Choose a section'} description={editor.blocks.length === 0 ? 'Add the first section from the library.' : 'Pick a section on the left to edit it.'} />
                )}
            </section>

            <aside aria-label="Page facts" className="flex flex-col gap-5 lg:col-span-2 lg:grid lg:grid-cols-2 min-[87.5rem]:sticky min-[87.5rem]:top-6 min-[87.5rem]:col-span-1 min-[87.5rem]:flex">
                <section aria-labelledby="preview-title" className="flex flex-col gap-4 rounded-sheet border border-line bg-white p-5">
                    <div className="flex items-center justify-between">
                        <h2 id="preview-title" className="text-admin-card-title font-semibold text-ink">
                            Live preview
                        </h2>
                        <button type="button" onClick={onOpenPreview} className="text-admin-body font-semibold text-royal hover:text-royal-deep">
                            Open <span aria-hidden="true">→</span>
                        </button>
                    </div>
                    {block ? <BlockPreview type={type} data={block.data} /> : <p className="text-admin-small text-ink-soft">Select a section to preview it.</p>}
                </section>

                <section aria-labelledby="publishing-title" className="flex flex-col gap-3 rounded-sheet border border-line bg-white p-5">
                    <h2 id="publishing-title" className="mb-1 text-admin-card-title font-semibold text-ink">
                        Publishing
                    </h2>
                    <Fact label="Status" value={STATUS_LABEL[meta.status]} />
                    <Fact label="Visibility" value={meta.status === 'published' ? 'Public' : 'Not on the website'} />
                    <Fact label={meta.status === 'scheduled' || meta.scheduled_at ? 'Goes live' : 'Published'} value={meta.scheduled_at ? shortDate(meta.scheduled_at) : meta.published_at ? shortDate(meta.published_at) : '—'} />
                    <Fact label="Template" value={meta.template_label} />
                    <Fact label="Author" value={meta.created_by?.name ?? shortName(meta.updated_by?.name)} />
                    {meta.status === 'published' && meta.has_unpublished_changes && <p className="mt-1 rounded-[12px] bg-warning-soft px-3 py-2 text-admin-small text-warning">The website shows the last published version until you publish these changes.</p>}
                </section>

                <section aria-labelledby="seo-title" className="flex flex-col gap-2 rounded-sheet border border-line bg-white p-5">
                    <h2 id="seo-title" className="text-admin-card-title font-semibold text-ink">
                        SEO
                    </h2>
                    <p className="text-admin-small text-ink-soft">Focus keyphrase, score and checks arrive with the SEO tools in Phase 4.</p>
                </section>
            </aside>

            <AddSectionDialog open={adding} onClose={() => setAdding(false)} types={types} onPick={insert} />
            <ConfirmDialog
                open={deleting !== null}
                title="Delete this section?"
                description="It disappears from your draft now and from the website when you publish. You can bring it back from Revisions."
                confirmLabel="Delete section"
                onClose={() => setDeleting(null)}
                onConfirm={() => {
                    const next = editor.blocks.filter((item) => item.key !== deleting);
                    editor.setBlocks(next);
                    onSelect(next[Math.min(index, next.length - 1)]?.key ?? null);
                    setDeleting(null);
                }}
            />
        </div>
    );
}

function Fact({ label, value }: { label: string; value: string }) {
    return (
        <div className={cn('flex items-baseline justify-between gap-4 text-admin-body')}>
            <span className="text-ink-soft">{label}</span>
            <span className="min-w-0 truncate text-right text-ink">{value}</span>
        </div>
    );
}
