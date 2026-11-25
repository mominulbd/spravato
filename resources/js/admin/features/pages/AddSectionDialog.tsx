import { Dialog } from '@shared/ui';
import { BlockIcon } from './blockIcons';
import type { BlockTypeDef } from './types';

/** The block library: only sections the website knows how to render. */
export function AddSectionDialog({ open, onClose, types, onPick }: { open: boolean; onClose: () => void; types: BlockTypeDef[]; onPick: (type: BlockTypeDef) => void }) {
    return (
        <Dialog open={open} onClose={onClose} size="lg" title="Add a section" description="Sections use the website’s own design. The new section goes after the one you’re editing.">
            <ul className="grid max-h-[60vh] gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                {types.map((type) => (
                    <li key={type.key}>
                        <button
                            type="button"
                            onClick={() => {
                                onPick(type);
                                onClose();
                            }}
                            className="flex h-full w-full items-start gap-3 rounded-[16px] border border-line bg-white p-4 text-left transition-colors hover:border-royal hover:bg-sky-soft/50 focus-visible:border-royal"
                        >
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-sky-soft text-royal">
                                <BlockIcon name={type.icon} />
                            </span>
                            <span className="flex min-w-0 flex-col gap-0.5">
                                <span className="text-admin-body font-semibold text-ink">{type.label}</span>
                                <span className="text-admin-small text-ink-soft">{type.description}</span>
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
        </Dialog>
    );
}
