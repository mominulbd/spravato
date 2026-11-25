import type { ReactNode } from 'react';
import { useState } from 'react';
import { Trash2 } from 'lucide-react';
import { Button } from './Button';
import { Dialog } from './Dialog';
import { TextInput } from './Field';

/**
 * Confirm a destructive action (Figma 28, "Confirm · destructive action").
 * With `confirmWord`, the person must type it first.
 */
interface ConfirmDialogProps {
    open: boolean;
    title: ReactNode;
    description: ReactNode;
    confirmLabel: string;
    onConfirm: () => void;
    onClose: () => void;
    busy?: boolean;
    tone?: 'danger' | 'primary';
    confirmWord?: string;
    error?: string | null;
}

export function ConfirmDialog({ open, title, description, confirmLabel, onConfirm, onClose, busy = false, tone = 'danger', confirmWord, error }: ConfirmDialogProps) {
    const [typed, setTyped] = useState('');
    const ready = confirmWord === undefined || typed.trim() === confirmWord;
    const close = () => {
        setTyped('');
        onClose();
    };

    return (
        <Dialog
            open={open}
            onClose={close}
            dismissible={!busy}
            title={title}
            description={description}
            icon={
                tone === 'danger' ? (
                    <span className="inline-flex size-10 items-center justify-center rounded-full bg-danger-soft text-danger">
                        <Trash2 className="size-[18px]" aria-hidden="true" />
                    </span>
                ) : undefined
            }
            footer={
                <>
                    <Button variant="ghost" onClick={close} disabled={busy}>
                        Cancel
                    </Button>
                    <Button
                        variant={tone === 'danger' ? 'danger' : 'primary'}
                        icon={tone === 'danger' ? <Trash2 className="size-4" aria-hidden="true" /> : undefined}
                        onClick={onConfirm}
                        disabled={!ready}
                        loading={busy}
                    >
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            {confirmWord !== undefined && (
                <TextInput label={`Type ${confirmWord} to confirm`} value={typed} onChange={(event) => setTyped(event.target.value)} autoComplete="off" data-autofocus />
            )}
            {error && (
                <p role="alert" className="rounded-control bg-danger-soft px-3 py-2.5 text-admin-small text-danger">
                    {error}
                </p>
            )}
        </Dialog>
    );
}
