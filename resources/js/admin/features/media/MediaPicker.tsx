import { useRef, useState } from 'react';
import { FileText, ImageOff, TriangleAlert, Upload } from 'lucide-react';
import { ApiError } from '@shared/api/client';
import { can } from '@shared/auth/can';
import { useDebouncedValue } from '@shared/hooks/useDebouncedValue';
import { Button, Dialog, EmptyState, ErrorState, SearchInput, Skeleton, cn, useToast } from '@shared/ui';
import { useCurrentUser } from '@admin/app/AuthProvider';
import { useMediaList, useUploadMedia } from './api';
import type { MediaItem } from './types';

/**
 * Choose a file from the library (or upload one). Images without alt text
 * are shown but can't be chosen — the API would refuse them — with a link
 * to fix them in the library (Figma 18 "Images without it can't be added").
 */
interface MediaPickerProps {
    open: boolean;
    onClose: () => void;
    onSelect: (item: MediaItem) => void;
    kind?: 'image' | 'document';
    title?: string;
}

export function MediaPicker({ open, onClose, onSelect, kind = 'image', title = 'Choose an image' }: MediaPickerProps) {
    const user = useCurrentUser();
    const toast = useToast();
    const [search, setSearch] = useState('');
    const debounced = useDebouncedValue(search, 250);
    const list = useMediaList({ 'filter[kind]': kind, search: debounced, per_page: 48 }, open);
    const upload = useUploadMedia();
    const input = useRef<HTMLInputElement>(null);
    const mayUpload = can(user, 'media.manage');

    const onFiles = (files: FileList | null) => {
        const file = files?.[0];

        if (!file) return;

        upload.mutate(
            { file },
            {
                onSuccess: (response) => {
                    toast.success('Uploaded', response.data.needs_alt_text ? 'Add alt text in the media library before placing it.' : response.data.filename);
                },
                onError: (error) => toast.error('Upload failed', error instanceof ApiError ? (error.fieldErrors.file?.[0] ?? error.humanMessage) : undefined),
            },
        );
    };

    return (
        <Dialog
            open={open}
            onClose={onClose}
            size="lg"
            title={title}
            description="Only images with alt text can be placed on the website."
            footer={
                <>
                    {mayUpload && (
                        <>
                            <input ref={input} type="file" accept={kind === 'image' ? 'image/jpeg,image/png,image/webp' : 'application/pdf'} className="sr-only" onChange={(event) => onFiles(event.target.files)} />
                            <Button variant="secondary" icon={<Upload className="size-4" />} loading={upload.isPending} onClick={() => input.current?.click()}>
                                Upload new
                            </Button>
                        </>
                    )}
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                </>
            }
        >
            <SearchInput label="Search the library" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search by name or alt text" />
            <div className="max-h-[52vh] min-h-48 overflow-y-auto pr-1">
                {list.isPending ? (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {Array.from({ length: 8 }, (_, index) => (
                            <Skeleton key={index} className="aspect-[4/3] w-full rounded-card" />
                        ))}
                    </div>
                ) : list.isError ? (
                    <ErrorState title="The library didn’t load" description="Check your connection and try again." onRetry={() => void list.refetch()} />
                ) : list.data.items.length === 0 ? (
                    <EmptyState icon={<ImageOff className="size-5" />} title="Nothing here yet" description={mayUpload ? 'Upload a file to use it here.' : 'Ask someone with media access to upload it.'} />
                ) : (
                    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {list.data.items.map((item) => (
                            <li key={item.id}>
                                <button
                                    type="button"
                                    disabled={item.needs_alt_text}
                                    onClick={() => {
                                        onSelect(item);
                                        onClose();
                                    }}
                                    className={cn(
                                        'group flex w-full flex-col overflow-hidden rounded-card border border-line bg-white text-left transition-colors',
                                        item.needs_alt_text ? 'cursor-not-allowed opacity-60' : 'hover:border-royal focus-visible:border-royal',
                                    )}
                                >
                                    <span className="relative flex aspect-[4/3] w-full items-center justify-center overflow-hidden bg-sky-soft">
                                        {item.kind === 'image' && item.thumbnail_url ? (
                                            <img src={item.thumbnail_url} alt="" loading="lazy" className="size-full object-cover" style={{ objectPosition: `${item.focal.x}% ${item.focal.y}%` }} />
                                        ) : (
                                            <FileText className="size-8 text-royal" aria-hidden="true" />
                                        )}
                                        {item.needs_alt_text && (
                                            <span className="absolute bottom-2 left-2 inline-flex items-center gap-1 rounded-full bg-warning-soft px-2 py-0.5 text-[11px] font-semibold text-warning">
                                                <TriangleAlert className="size-3" aria-hidden="true" /> No alt text
                                            </span>
                                        )}
                                    </span>
                                    <span className="truncate px-3 py-2 text-admin-small font-semibold text-ink">{item.filename}</span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Dialog>
    );
}
