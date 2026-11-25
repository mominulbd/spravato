import type { KeyboardEvent, ReactNode } from 'react';
import { useState } from 'react';
import { GripVertical } from 'lucide-react';
import { cn } from './cn';

/**
 * Drag-to-reorder list (Figma 07, 14, 15, 17). Mouse users drag the grip;
 * keyboard users focus the grip and press ↑ / ↓. Every move is announced.
 * The list is controlled: `onMove(from, to)` returns nothing and the parent
 * reorders its own state.
 */
interface SortableListProps<T> {
    items: T[];
    getKey: (item: T) => string | number;
    getLabel: (item: T) => string;
    onMove: (from: number, to: number) => void;
    renderItem: (item: T, index: number, handle: ReactNode) => ReactNode;
    className?: string;
    disabled?: boolean;
}

export function moveItem<T>(items: T[], from: number, to: number): T[] {
    const next = [...items];
    const [moved] = next.splice(from, 1);

    if (moved !== undefined) {
        next.splice(to, 0, moved);
    }

    return next;
}

export function SortableList<T>({ items, getKey, getLabel, onMove, renderItem, className, disabled = false }: SortableListProps<T>) {
    const [dragging, setDragging] = useState<number | null>(null);
    const [over, setOver] = useState<number | null>(null);
    const [announcement, setAnnouncement] = useState('');

    const move = (from: number, to: number) => {
        if (to < 0 || to >= items.length || from === to) {
            return;
        }

        const item = items[from];
        onMove(from, to);
        setAnnouncement(item ? `${getLabel(item)} moved to position ${to + 1} of ${items.length}.` : '');
    };

    const onKeyDown = (event: KeyboardEvent<HTMLButtonElement>, index: number) => {
        if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
            event.preventDefault();
            const to = event.key === 'ArrowUp' ? index - 1 : index + 1;
            move(index, to);
            // Keep focus on the moved item's grip.
            requestAnimationFrame(() => {
                const handles = (event.currentTarget.closest('[data-sortable]') ?? document).querySelectorAll<HTMLButtonElement>('[data-sortable-handle]');
                handles[Math.max(0, Math.min(items.length - 1, to))]?.focus();
            });
        }
    };

    return (
        <>
            <ul data-sortable className={cn('flex flex-col', className)}>
                {items.map((item, index) => {
                    const handle = disabled ? null : (
                        <button
                            type="button"
                            data-sortable-handle
                            aria-label={`Move ${getLabel(item)}. Use the up and down arrow keys.`}
                            onKeyDown={(event) => onKeyDown(event, index)}
                            className="flex size-7 shrink-0 cursor-grab items-center justify-center rounded-md text-ink-soft hover:bg-stone/60 hover:text-ink active:cursor-grabbing"
                        >
                            <GripVertical className="size-4" aria-hidden="true" />
                        </button>
                    );

                    return (
                        <li
                            key={getKey(item)}
                            draggable={!disabled}
                            onDragStart={(event) => {
                                setDragging(index);
                                event.dataTransfer.effectAllowed = 'move';
                            }}
                            onDragOver={(event) => {
                                if (dragging === null) return;
                                event.preventDefault();
                                setOver(index);
                            }}
                            onDrop={(event) => {
                                event.preventDefault();
                                if (dragging !== null) move(dragging, index);
                                setDragging(null);
                                setOver(null);
                            }}
                            onDragEnd={() => {
                                setDragging(null);
                                setOver(null);
                            }}
                            className={cn(
                                'relative transition-opacity',
                                dragging === index && 'opacity-40',
                                over === index && dragging !== null && dragging !== index && 'before:absolute before:inset-x-0 before:-top-px before:h-0.5 before:rounded-full before:bg-royal',
                            )}
                        >
                            {renderItem(item, index, handle)}
                        </li>
                    );
                })}
            </ul>
            <p aria-live="polite" className="sr-only">
                {announcement}
            </p>
        </>
    );
}
