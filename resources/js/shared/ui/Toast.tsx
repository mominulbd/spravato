import type { ReactNode } from 'react';
import { createContext, useCallback, useContext, useMemo, useRef, useState } from 'react';
import { CircleCheck, Info, TriangleAlert, X } from 'lucide-react';
import { cn } from './cn';

/** Toasts (Figma 28): success, info, error. Errors stay until dismissed. */
export type ToastTone = 'success' | 'info' | 'error';

interface ToastMessage {
    id: number;
    tone: ToastTone;
    title: string;
    description?: ReactNode;
}

interface ToastApi {
    show: (toast: Omit<ToastMessage, 'id'>) => void;
    success: (title: string, description?: ReactNode) => void;
    error: (title: string, description?: ReactNode) => void;
    info: (title: string, description?: ReactNode) => void;
}

const ToastContext = createContext<ToastApi | null>(null);

const ICONS: Record<ToastTone, ReactNode> = {
    success: <CircleCheck className="size-4" aria-hidden="true" />,
    info: <Info className="size-4" aria-hidden="true" />,
    error: <TriangleAlert className="size-4" aria-hidden="true" />,
};

const ICON_TONES: Record<ToastTone, string> = {
    success: 'bg-success-soft text-success',
    info: 'bg-sky-soft text-royal',
    error: 'bg-danger-soft text-danger',
};

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<ToastMessage[]>([]);
    const nextId = useRef(1);

    const dismiss = useCallback((id: number) => setToasts((list) => list.filter((toast) => toast.id !== id)), []);

    const show = useCallback(
        (toast: Omit<ToastMessage, 'id'>) => {
            const id = nextId.current++;
            setToasts((list) => [...list.slice(-3), { ...toast, id }]);

            if (toast.tone !== 'error') {
                window.setTimeout(() => dismiss(id), 5000);
            }
        },
        [dismiss],
    );

    const api = useMemo<ToastApi>(
        () => ({
            show,
            success: (title, description) => show({ tone: 'success', title, description }),
            error: (title, description) => show({ tone: 'error', title, description }),
            info: (title, description) => show({ tone: 'info', title, description }),
        }),
        [show],
    );

    return (
        <ToastContext.Provider value={api}>
            {children}
            <div className="pointer-events-none fixed right-4 bottom-4 z-[60] flex w-[min(380px,calc(100vw-2rem))] flex-col gap-3" aria-live="polite" role="status">
                {toasts.map((toast) => (
                    <div key={toast.id} className="pointer-events-auto flex items-start gap-3 rounded-2xl border border-line bg-white p-4 shadow-[0_12px_28px_rgba(14,48,78,0.14)]">
                        <span className={cn('inline-flex size-7 shrink-0 items-center justify-center rounded-full', ICON_TONES[toast.tone])}>{ICONS[toast.tone]}</span>
                        <div className="min-w-0 flex-1">
                            <p className="text-admin-body font-semibold text-ink">{toast.title}</p>
                            {toast.description && <div className="mt-0.5 text-admin-small break-words text-ink-soft">{toast.description}</div>}
                        </div>
                        <button type="button" aria-label="Dismiss" onClick={() => dismiss(toast.id)} className="rounded-full p-1 text-ink-soft hover:bg-paper hover:text-ink">
                            <X className="size-4" aria-hidden="true" />
                        </button>
                    </div>
                ))}
            </div>
        </ToastContext.Provider>
    );
}

export function useToast(): ToastApi {
    const context = useContext(ToastContext);

    if (context === null) {
        throw new Error('useToast() needs a <ToastProvider>.');
    }

    return context;
}
