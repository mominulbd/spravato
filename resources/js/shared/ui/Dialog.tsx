import type { ReactNode } from 'react';
import { useId, useRef } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';
import { useFocusTrap } from '../hooks/useFocusTrap';
import { cn } from './cn';

/**
 * Modal dialog and side drawer (Figma "Confirm" states). Focus is trapped,
 * Escape and the backdrop close, focus returns to the opener.
 */
interface DialogProps {
    open: boolean;
    onClose: () => void;
    title: ReactNode;
    description?: ReactNode;
    icon?: ReactNode;
    children?: ReactNode;
    footer?: ReactNode;
    size?: 'sm' | 'md' | 'lg';
    /** Block closing while a request is in flight. */
    dismissible?: boolean;
}

export function Dialog({ open, onClose, title, description, icon, children, footer, size = 'sm', dismissible = true }: DialogProps) {
    const ref = useRef<HTMLDivElement>(null);
    const titleId = useId();
    const descriptionId = useId();
    const close = () => dismissible && onClose();
    useFocusTrap(ref, open, close);

    if (!open) {
        return null;
    }

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">
            <div aria-hidden="true" className="fixed inset-0 bg-navy/55" onClick={close} />
            <div
                ref={ref}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                aria-describedby={description ? descriptionId : undefined}
                tabIndex={-1}
                className={cn(
                    'relative my-auto flex w-full flex-col gap-5 rounded-[20px] bg-white p-6 shadow-[0_24px_48px_rgba(14,48,78,0.22)] sm:p-7',
                    size === 'sm' ? 'max-w-[440px]' : size === 'md' ? 'max-w-[560px]' : 'max-w-[960px]',
                )}
            >
                <div className="flex flex-col gap-3">
                    {icon}
                    <h2 id={titleId} className="pr-8 font-serif text-admin-section-title text-ink">
                        {title}
                    </h2>
                    {description && (
                        <div id={descriptionId} className="text-admin-body text-ink-soft">
                            {description}
                        </div>
                    )}
                </div>
                {children}
                {footer && <div className="flex flex-wrap items-center justify-end gap-3">{footer}</div>}
                {dismissible && (
                    <button type="button" aria-label="Close" onClick={onClose} className="absolute top-5 right-5 rounded-full p-1.5 text-ink-soft hover:bg-paper hover:text-ink">
                        <X className="size-[18px]" aria-hidden="true" />
                    </button>
                )}
            </div>
        </div>,
        document.body,
    );
}

interface DrawerProps {
    open: boolean;
    onClose: () => void;
    title: ReactNode;
    description?: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
}

export function Drawer({ open, onClose, title, description, children, footer }: DrawerProps) {
    const ref = useRef<HTMLDivElement>(null);
    const titleId = useId();
    useFocusTrap(ref, open, onClose);

    if (!open) {
        return null;
    }

    return createPortal(
        <div className="fixed inset-0 z-50 flex justify-end">
            <div aria-hidden="true" className="fixed inset-0 bg-navy/45" onClick={onClose} />
            <div
                ref={ref}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                tabIndex={-1}
                className="relative m-3 flex w-full max-w-[520px] flex-col overflow-hidden rounded-sheet bg-paper shadow-[0_24px_48px_rgba(14,48,78,0.22)]"
            >
                <div className="flex items-start justify-between gap-4 border-b border-line bg-white px-7 py-6">
                    <div className="min-w-0">
                        <h2 id={titleId} className="font-serif text-admin-section-title text-ink">
                            {title}
                        </h2>
                        {description && <p className="mt-1 text-admin-body text-ink-soft">{description}</p>}
                    </div>
                    <button type="button" aria-label="Close" onClick={onClose} className="rounded-full p-1.5 text-ink-soft hover:bg-paper hover:text-ink">
                        <X className="size-[18px]" aria-hidden="true" />
                    </button>
                </div>
                <div className="flex-1 overflow-y-auto px-7 py-6">{children}</div>
                {footer && <div className="flex flex-wrap items-center justify-end gap-3 border-t border-line bg-white px-7 py-4">{footer}</div>}
            </div>
        </div>,
        document.body,
    );
}
