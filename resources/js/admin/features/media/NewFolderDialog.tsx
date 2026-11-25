import type { FormEvent } from 'react';
import { useState } from 'react';
import { ApiError } from '@shared/api/client';
import { Button, Dialog, TextInput, useToast } from '@shared/ui';
import { useCreateFolder } from './api';

export function NewFolderDialog({ open, onClose, onCreated }: { open: boolean; onClose: () => void; onCreated: (id: number) => void }) {
    const toast = useToast();
    const create = useCreateFolder();
    const [name, setName] = useState('');
    const [error, setError] = useState<string | undefined>();

    const close = () => {
        setName('');
        setError(undefined);
        onClose();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (name.trim() === '') return setError('Name the folder.');

        create.mutate(name.trim(), {
            onSuccess: (response) => {
                toast.success('Folder created', response.data.name);
                onCreated(response.data.id);
                close();
            },
            onError: (failure) => setError(failure instanceof ApiError ? (failure.fieldErrors.name?.[0] ?? failure.humanMessage) : 'That didn’t work.'),
        });
    };

    return (
        <Dialog
            open={open}
            onClose={close}
            title="New folder"
            description="Folders keep the library tidy. Files can live in one folder at a time."
            footer={
                <>
                    <Button variant="ghost" onClick={close}>
                        Cancel
                    </Button>
                    <Button type="submit" form="new-folder" loading={create.isPending}>
                        Create folder
                    </Button>
                </>
            }
        >
            <form id="new-folder" onSubmit={submit}>
                <TextInput label="Folder name" value={name} maxLength={80} autoFocus error={error} onChange={(event) => setName(event.target.value)} placeholder="e.g. Clinic photos" />
            </form>
        </Dialog>
    );
}
