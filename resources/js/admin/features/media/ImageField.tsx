import { useState } from 'react';
import { ImageIcon, RefreshCw, X } from 'lucide-react';
import { Button } from '@shared/ui';
import { useMediaItem } from './api';
import { formatBytes } from './types';
import { MediaPicker } from './MediaPicker';

/**
 * An image chosen from the library (Figma 07 hero image card, 14 headshot):
 * thumbnail, file facts, alt text read-only (edited in the library), and
 * Replace / Remove.
 */
interface ImageFieldProps {
    label: string;
    value: number | null;
    onChange: (id: number | null) => void;
    help?: string;
    error?: string;
}

export function ImageField({ label, value, onChange, help, error }: ImageFieldProps) {
    const [picking, setPicking] = useState(false);
    const media = useMediaItem(value);
    const item = media.data;

    return (
        <div className="flex flex-col gap-1.5">
            <span className="text-admin-label font-semibold text-ink">{label}</span>
            <div className={`flex flex-col gap-4 rounded-card border bg-white p-4 sm:flex-row sm:items-start ${error ? 'border-danger' : 'border-line'}`}>
                <div className="flex aspect-[4/3] w-full shrink-0 items-center justify-center overflow-hidden rounded-[14px] bg-sky sm:w-[120px]">
                    {item?.thumbnail_url ? (
                        <img src={item.thumbnail_url} alt="" className="size-full object-cover" style={{ objectPosition: `${item.focal.x}% ${item.focal.y}%` }} />
                    ) : (
                        <ImageIcon className="size-6 text-royal" aria-hidden="true" />
                    )}
                </div>
                <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                    {value === null ? (
                        <p className="text-admin-body text-ink-soft">No image chosen.</p>
                    ) : item ? (
                        <>
                            <p className="truncate text-admin-body font-semibold text-ink">{item.filename}</p>
                            <p className="text-admin-small text-ink-soft">
                                {item.width && item.height ? `${item.width} × ${item.height} · ` : ''}
                                {item.variants_ready ? 'WebP + AVIF generated' : 'Variants processing'} · {formatBytes(item.delivered_size)}
                            </p>
                            <p className="text-admin-small text-ink-soft">
                                <span className="font-semibold text-ink">Alt text:</span> {item.is_decorative ? 'Decorative — none needed' : (item.alt ?? '—')}
                            </p>
                        </>
                    ) : (
                        <p className="text-admin-small text-ink-soft">{media.isError ? 'This file is no longer in the library.' : 'Loading…'}</p>
                    )}
                    <div className="mt-1 flex flex-wrap gap-2">
                        <Button size="sm" variant="secondary" icon={<RefreshCw className="size-3.5" />} onClick={() => setPicking(true)}>
                            {value === null ? 'Choose image' : 'Replace'}
                        </Button>
                        {value !== null && (
                            <Button size="sm" variant="ghost" icon={<X className="size-3.5" />} onClick={() => onChange(null)}>
                                Remove
                            </Button>
                        )}
                    </div>
                </div>
            </div>
            {error && <p className="text-admin-small text-danger">{error}</p>}
            {help && !error && <p className="text-admin-small text-ink-soft">{help}</p>}
            <MediaPicker open={picking} onClose={() => setPicking(false)} onSelect={(picked) => onChange(picked.id)} />
        </div>
    );
}
