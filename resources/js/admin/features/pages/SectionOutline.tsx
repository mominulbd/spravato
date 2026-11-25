import { Eye, EyeOff, Plus } from 'lucide-react';
import { SortableList, cn, moveItem } from '@shared/ui';
import { BlockIcon } from './blockIcons';
import { blockSummary } from './editorState';
import type { BlockTypeDef, EditorBlock } from './types';

/** Left column of Figma 07: drag to reorder, eye to hide, click to edit. */
interface SectionOutlineProps {
    blocks: EditorBlock[];
    types: Map<string, BlockTypeDef>;
    selected: string | null;
    errorKeys: Set<string>;
    onSelect: (key: string) => void;
    onChange: (blocks: EditorBlock[]) => void;
    onAdd: () => void;
}

export function SectionOutline({ blocks, types, selected, errorKeys, onSelect, onChange, onAdd }: SectionOutlineProps) {
    const label = (block: EditorBlock) => types.get(block.type)?.label ?? block.type;

    return (
        <section aria-labelledby="sections-title" className="flex flex-col gap-3 rounded-sheet border border-line bg-white p-4 lg:sticky lg:top-6">
            <div className="flex items-center justify-between px-2 pt-1">
                <h2 id="sections-title" className="text-admin-card-title font-semibold text-ink">
                    Sections
                </h2>
                <span className="text-admin-small text-ink-soft">{blocks.length}</span>
            </div>
            <SortableList
                items={blocks}
                getKey={(block) => block.key}
                getLabel={label}
                onMove={(from, to) => onChange(moveItem(blocks, from, to))}
                className="gap-0.5"
                renderItem={(block, _index, handle) => {
                    const active = block.key === selected;
                    const name = label(block);

                    return (
                        <div className={cn('group flex items-center gap-1 rounded-[12px] pr-1', active ? 'bg-sky-soft' : 'hover:bg-paper', !block.is_visible && 'opacity-60')}>
                            {handle}
                            <button
                                type="button"
                                aria-current={active ? 'true' : undefined}
                                title={blockSummary(block, name)}
                                onClick={() => onSelect(block.key)}
                                className={cn('flex min-w-0 flex-1 items-center gap-2.5 py-2.5 text-left text-admin-body', active ? 'font-semibold text-royal' : 'text-ink')}
                            >
                                <span className={active ? 'text-royal' : 'text-ink-soft'}>
                                    <BlockIcon name={types.get(block.type)?.icon} />
                                </span>
                                <span className="min-w-0 truncate">{name}</span>
                                {errorKeys.has(block.key) && <span className="size-2 shrink-0 rounded-full bg-danger" aria-label="Has errors" />}
                            </button>
                            <button
                                type="button"
                                aria-pressed={!block.is_visible}
                                aria-label={block.is_visible ? `Hide ${name} on the website` : `Show ${name} on the website`}
                                onClick={() => onChange(blocks.map((item) => (item.key === block.key ? { ...item, is_visible: !item.is_visible } : item)))}
                                className="rounded-full p-1.5 text-ink-soft hover:bg-white hover:text-ink"
                            >
                                {block.is_visible ? <Eye className="size-4" aria-hidden="true" /> : <EyeOff className="size-4" aria-hidden="true" />}
                            </button>
                        </div>
                    );
                }}
            />
            <button
                type="button"
                onClick={onAdd}
                className="mt-1 flex h-11 items-center justify-center gap-2 rounded-[14px] border border-dashed border-line text-admin-body font-semibold text-royal hover:border-royal hover:bg-sky-soft"
            >
                <Plus className="size-4" aria-hidden="true" /> Add section
            </button>
        </section>
    );
}
