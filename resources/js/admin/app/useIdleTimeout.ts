import { useEffect, useRef } from 'react';

const EVENTS = ['pointerdown', 'keydown', 'wheel', 'touchstart'] as const;

/**
 * Calls `onIdle` after `minutes` without input. Mirrors the server's
 * 30-minute session lifetime so the screen locks before the next request
 * would fail (Figma 28, "Session · timed out").
 */
export function useIdleTimeout(minutes: number, onIdle: () => void, enabled = true): void {
    const callback = useRef(onIdle);

    useEffect(() => {
        callback.current = onIdle;
    }, [onIdle]);

    useEffect(() => {
        if (!enabled || minutes <= 0) {
            return;
        }

        let timer = 0;
        const reset = () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => callback.current(), minutes * 60_000);
        };

        reset();
        EVENTS.forEach((event) => window.addEventListener(event, reset, { passive: true }));

        return () => {
            window.clearTimeout(timer);
            EVENTS.forEach((event) => window.removeEventListener(event, reset));
        };
    }, [minutes, enabled]);
}
