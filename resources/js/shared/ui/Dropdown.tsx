import type { KeyboardEvent, ReactNode, RefObject } from 'react';
import { useCallback, useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { cn } from './cn';

/**
 * Action menu (Figma "Feedback" board, row actions). Arrow keys move,
 * Escape and outside clicks close, focus returns to the trigger.
 *
 * The menu is portalled to the body and placed from the trigger's rect: a
 * row action lives inside the table's `overflow-hidden` card and its
 * horizontal scroller, which would otherwise clip the menu on the last row.
 * It opens downwards unless the viewport has more room above.
 */
export interface DropdownItem {
    label: string;
    icon?: ReactNode;
    onSelect: () => void;
    tone?: 'default' | 'danger';
    disabled?: boolean;
}

interface DropdownProps {
    trigger: (props: { 'aria-haspopup': 'menu'; 'aria-expanded': boolean; 'aria-controls': string; onClick: () => void; ref: RefObject<HTMLButtonElement | null> }) => ReactNode;
    items: DropdownItem[];
    align?: 'start' | 'end';
}

/** Breathing room between the trigger and the menu, and from the viewport edge. */
const GAP = 8;
const MARGIN = 12;

export function Dropdown({ trigger, items, align = 'end' }: DropdownProps) {
    const [open, setOpen] = useState(false);
    const id = useId();
    const triggerRef = useRef<HTMLButtonElement | null>(null);
    const menuRef = useRef<HTMLDivElement>(null);

    // Written straight to the node: the menu is positioned from measurements,
    // which React state would only turn into a second render.
    const place = useCallback(() => {
        const anchor = triggerRef.current?.getBoundingClientRect();
        const menu = menuRef.current;

        if (!anchor || !menu) {
            return;
        }

        const below = window.innerHeight - anchor.bottom - GAP - MARGIN;
        const above = anchor.top - GAP - MARGIN;
        const flip = menu.offsetHeight > below && above > below;

        menu.style.top = flip ? '' : `${anchor.bottom + GAP}px`;
        menu.style.bottom = flip ? `${window.innerHeight - anchor.top + GAP}px` : '';
        menu.style.left = align === 'start' ? `${anchor.left}px` : '';
        menu.style.right = align === 'end' ? `${window.innerWidth - anchor.right}px` : '';
        menu.style.maxHeight = `${Math.max(flip ? above : below, 0)}px`;
        menu.style.visibility = 'visible';
    }, [align]);

    useLayoutEffect(() => {
        if (open) {
            place();
        }
    }, [open, items.length, place]);

    useEffect(() => {
        if (!open) {
            return;
        }

        menuRef.current?.querySelector<HTMLElement>('[role="menuitem"]:not([disabled])')?.focus();

        const onPointer = (event: PointerEvent) => {
            if (!menuRef.current?.contains(event.target as Node) && !triggerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('pointerdown', onPointer);
        // Capture, so the table's own scroller moves the menu along with its row.
        window.addEventListener('scroll', place, true);
        window.addEventListener('resize', place);

        return () => {
            document.removeEventListener('pointerdown', onPointer);
            window.removeEventListener('scroll', place, true);
            window.removeEventListener('resize', place);
        };
    }, [open, place]);

    const close = (refocus = true) => {
        setOpen(false);
        if (refocus) {
            triggerRef.current?.focus();
        }
    };

    const onKeyDown = (event: KeyboardEvent) => {
        const options = Array.from(menuRef.current?.querySelectorAll<HTMLElement>('[role="menuitem"]:not([disabled])') ?? []);
        const index = options.indexOf(document.activeElement as HTMLElement);

        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            options[(index + 1) % options.length]?.focus();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            options[(index - 1 + options.length) % options.length]?.focus();
        } else if (event.key === 'Tab') {
            close(false);
        }
    };

    return (
        <div className="relative inline-flex">
            {trigger({
                'aria-haspopup': 'menu',
                'aria-expanded': open,
                'aria-controls': id,
                onClick: () => setOpen((value) => !value),
                ref: triggerRef,
            })}
            {open &&
                createPortal(
                    <div
                        ref={menuRef}
                        id={id}
                        role="menu"
                        onKeyDown={onKeyDown}
                        className="invisible fixed z-50 min-w-[200px] overflow-y-auto rounded-2xl border border-line bg-white p-1.5 shadow-[0_16px_32px_rgba(14,48,78,0.14)]"
                    >
                        {items.map((item) => (
                            <button
                                key={item.label}
                                type="button"
                                role="menuitem"
                                disabled={item.disabled}
                                onClick={() => {
                                    close();
                                    item.onSelect();
                                }}
                                className={cn(
                                    'flex w-full items-center gap-2.5 rounded-[10px] px-3 py-2 text-left text-admin-body outline-none disabled:opacity-40',
                                    item.tone === 'danger' ? 'text-danger hover:bg-danger-soft focus:bg-danger-soft' : 'text-ink hover:bg-paper focus:bg-paper',
                                )}
                            >
                                {item.icon}
                                {item.label}
                            </button>
                        ))}
                    </div>,
                    document.body,
                )}
        </div>
    );
}
