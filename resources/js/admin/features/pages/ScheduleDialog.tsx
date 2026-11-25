import { useState } from 'react';
import { Button, Dialog, TextInput } from '@shared/ui';

function localValue(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** Freeze the current draft and publish it later. */
export function ScheduleDialog({ open, onClose, onSchedule, busy, error }: { open: boolean; onClose: () => void; onSchedule: (iso: string) => void; busy: boolean; error: string | null }) {
    const [value, setValue] = useState(() => {
        const tomorrow = new Date(Date.now() + 24 * 3600 * 1000);
        tomorrow.setHours(9, 0, 0, 0);

        return localValue(tomorrow);
    });

    return (
        <Dialog
            open={open}
            onClose={onClose}
            dismissible={!busy}
            title="Schedule publishing"
            description="The draft as it is now goes live at this time. Later edits aren’t included unless you schedule again."
            footer={
                <>
                    <Button variant="ghost" onClick={onClose} disabled={busy}>
                        Cancel
                    </Button>
                    <Button loading={busy} onClick={() => onSchedule(new Date(value).toISOString())} disabled={value === ''}>
                        Schedule
                    </Button>
                </>
            }
        >
            <TextInput type="datetime-local" label="Publish on" value={value} min={localValue(new Date())} error={error ?? undefined} help="Your computer’s time zone." onChange={(event) => setValue(event.target.value)} />
        </Dialog>
    );
}
