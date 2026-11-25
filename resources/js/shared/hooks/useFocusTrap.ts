import { useEffect, type RefObject } from 'react';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Keep Tab inside `ref` while `active`, close on Escape, and hand focus back
 * to whatever had it when the trap closes.
 */
export function useFocusTrap(ref: RefObject<HTMLElement | null>, active: boolean, onEscape?: () => void): void {
    useEffect(() => {
        if (!active) {
            return;
        }

        const container = ref.current;
        const previous = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        const focusables = () => Array.from(container?.querySelectorAll<HTMLElement>(FOCUSABLE) ?? []);

        // Prefer an explicit autofocus target, then the first control.
        const initial = container?.querySelector<HTMLElement>('[data-autofocus]') ?? focusables()[0] ?? container;
        initial?.focus();

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape' && onEscape) {
                event.stopPropagation();
                onEscape();

                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const items = focusables();
            const first = items[0];
            const last = items[items.length - 1];

            if (!first || !last) {
                event.preventDefault();

                return;
            }

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };

        document.addEventListener('keydown', onKeyDown);
        const { overflow } = document.body.style;
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.body.style.overflow = overflow;
            previous?.focus();
        };
    }, [ref, active, onEscape]);
}
