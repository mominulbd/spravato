import { Check, Clock, FileText, Link2, TriangleAlert } from 'lucide-react';
import { cn } from '@shared/ui';
import { fileFacts, isLogo, usageLine } from './describe';
import type { MediaItem } from './types';

/** One tile of the library grid (Figma 18 / 18b). */
interface MediaCardProps {
    item: MediaItem;
    active: boolean;
    checked: boolean;
    selectable: boolean;
    unusedView: boolean;
    onOpen: () => void;
    onToggle: () => void;
}

export function MediaCard({ item, active, checked, selectable, unusedView, onOpen, onToggle }: MediaCardProps) {
    const inUse = (item.usages_count ?? 0) > 0;

    return (
        <article
            className={cn(
                'group relative flex min-w-0 flex-col overflow-hidden rounded-[18px] border bg-white p-2 transition-shadow',
                active || checked ? 'border-royal ring-1 ring-royal' : 'border-line hover:shadow-[0_8px_24px_rgba(14,48,78,0.08)]',
            )}
        >
            <button
                type="button"
                onClick={unusedView ? onToggle : onOpen}
                aria-label={`${unusedView ? (checked ? 'Deselect' : 'Select') : 'Open details for'} ${item.filename}`}
                aria-pressed={unusedView ? checked : active}
                className="relative flex aspect-[6/5] w-full items-center justify-center overflow-hidden rounded-[12px] bg-sky-soft"
            >
                {item.kind === 'image' && item.thumbnail_url ? (
                    <img
                        src={item.thumbnail_url}
                        alt=""
                        loading="lazy"
                        className={cn('size-full', isLogo(item) ? 'bg-white object-contain p-4' : 'object-cover')}
                        style={{ objectPosition: `${item.focal.x}% ${item.focal.y}%` }}
                    />
                ) : (
                    <span className="flex flex-col items-center gap-1.5 text-royal">
                        <FileText className="size-8" aria-hidden="true" />
                        <span className="text-[11px] font-bold tracking-[0.08em]">PDF</span>
                    </span>
                )}
                {item.needs_alt_text && (
                    <span className="absolute bottom-2 left-2 inline-flex items-center gap-1 rounded-full bg-warning-soft px-2 py-0.5 text-[11px] font-semibold text-warning">
                        <TriangleAlert className="size-3" aria-hidden="true" /> No alt text
                    </span>
                )}
                {unusedView && (
                    <span className="absolute top-2 right-2 rounded-full bg-white/90 px-2 py-0.5 text-[11px] font-semibold text-ink-soft">Unused</span>
                )}
            </button>
            {selectable && (
                <button
                    type="button"
                    role="checkbox"
                    aria-checked={checked}
                    aria-label={`Select ${item.filename}`}
                    onClick={onToggle}
                    className={cn(
                        'absolute top-4 left-4 flex size-[22px] items-center justify-center rounded-full border-2 transition-opacity',
                        checked ? 'border-royal bg-royal text-white opacity-100' : 'border-white bg-white/70 text-transparent opacity-0 group-hover:opacity-100 focus-visible:opacity-100',
                    )}
                >
                    <Check className="size-3" strokeWidth={3} aria-hidden="true" />
                </button>
            )}
            <div className="flex min-w-0 flex-col gap-0.5 px-1.5 pt-2.5 pb-1.5">
                <p className="truncate text-admin-body font-semibold text-ink" title={item.filename}>
                    {item.filename}
                </p>
                <p className="truncate text-admin-small text-ink-soft">{fileFacts(item)}</p>
                <p className={cn('mt-1 flex items-start gap-1.5 text-admin-small', inUse ? 'text-royal' : 'text-ink-soft')}>
                    {inUse ? <Link2 className="mt-px size-3.5 shrink-0" aria-hidden="true" /> : <Clock className="mt-px size-3.5 shrink-0" aria-hidden="true" />}
                    <span className="line-clamp-2">{usageLine(item)}</span>
                </p>
            </div>
        </article>
    );
}

