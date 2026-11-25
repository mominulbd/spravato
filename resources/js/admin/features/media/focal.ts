/** Focal point maths for the details panel (values are percentages 0–100). */
export interface Focal {
    x: number;
    y: number;
}

const clamp = (value: number) => Math.round(Math.min(100, Math.max(0, value)) * 10) / 10;

/** Pointer position inside the preview box → focal point. */
export function focalFromPointer(clientX: number, clientY: number, rect: { left: number; top: number; width: number; height: number }): Focal {
    if (rect.width <= 0 || rect.height <= 0) return { x: 50, y: 50 };

    return { x: clamp(((clientX - rect.left) / rect.width) * 100), y: clamp(((clientY - rect.top) / rect.height) * 100) };
}

/** Arrow keys move the dot 2% (10% with Shift); Home centres it. Null = key not handled. */
export function focalFromKey(focal: Focal, key: string, shift = false): Focal | null {
    const step = shift ? 10 : 2;

    switch (key) {
        case 'ArrowLeft':
            return { x: clamp(focal.x - step), y: focal.y };
        case 'ArrowRight':
            return { x: clamp(focal.x + step), y: focal.y };
        case 'ArrowUp':
            return { x: focal.x, y: clamp(focal.y - step) };
        case 'ArrowDown':
            return { x: focal.x, y: clamp(focal.y + step) };
        case 'Home':
            return { x: 50, y: 50 };
        default:
            return null;
    }
}
