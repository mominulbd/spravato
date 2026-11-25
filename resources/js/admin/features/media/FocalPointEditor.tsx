import type { KeyboardEvent, PointerEvent } from 'react';
import { useRef, useState } from 'react';
import { GripVertical } from 'lucide-react';
import { focalFromKey, focalFromPointer, type Focal } from './focal';

/**
 * Drag the dot to set what stays visible when the image is cropped for
 * cards and phones (Figma 18). Arrow keys move it; Home recentres.
 * `onCommit` fires once per drag / key press, not on every pixel.
 */
interface FocalPointEditorProps {
    src: string;
    focal: Focal;
    disabled: boolean;
    /** Show the whole image (logos) instead of a crop. */
    contain?: boolean;
    onCommit: (focal: Focal) => void;
}

export function FocalPointEditor({ src, focal, disabled, contain = false, onCommit }: FocalPointEditorProps) {
    const box = useRef<HTMLDivElement>(null);
    const [draft, setDraft] = useState<Focal | null>(null);
    const shown = draft ?? focal;

    const fromEvent = (event: PointerEvent) => {
        const rect = box.current?.getBoundingClientRect();

        return rect ? focalFromPointer(event.clientX, event.clientY, rect) : shown;
    };

    const onKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
        const next = focalFromKey(shown, event.key, event.shiftKey);

        if (next) {
            event.preventDefault();
            onCommit(next);
        }
    };

    return (
        <div
            ref={box}
            className="relative aspect-[4/3] w-full touch-none overflow-hidden rounded-[16px] bg-sky-soft select-none"
            onPointerDown={(event) => {
                if (disabled) return;
                (event.target as HTMLElement).setPointerCapture?.(event.pointerId);
                setDraft(fromEvent(event));
            }}
            onPointerMove={(event) => draft && setDraft(fromEvent(event))}
            onPointerUp={(event) => {
                if (!draft) return;
                const next = fromEvent(event);
                setDraft(null);
                onCommit(next);
            }}
        >
            <img src={src} alt="" draggable={false} className={contain ? 'size-full bg-white object-contain p-6' : 'size-full object-cover'} style={{ objectPosition: `${shown.x}% ${shown.y}%` }} />
            <button
                type="button"
                disabled={disabled}
                onKeyDown={onKeyDown}
                aria-label={`Focal point at ${Math.round(shown.x)}% across, ${Math.round(shown.y)}% down. Use arrow keys to move it.`}
                className="absolute flex size-6 -translate-x-1/2 -translate-y-1/2 cursor-grab items-center justify-center rounded-full border-[3px] border-white bg-royal shadow-[0_0_0_4px_rgba(24,60,220,0.25)] disabled:cursor-default"
                style={{ left: `${shown.x}%`, top: `${shown.y}%` }}
            />
            {!disabled && (
                <span className="pointer-events-none absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-navy/85 px-3 py-1.5 text-[12px] font-semibold text-white">
                    <GripVertical className="size-3.5" aria-hidden="true" /> Drag the dot to set the focus
                </span>
            )}
        </div>
    );
}
