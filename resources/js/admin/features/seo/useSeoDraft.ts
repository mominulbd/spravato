import { useEffect, useMemo, useRef, useState } from 'react';
import { ApiError } from '@shared/api/client';
import { useDebouncedValue } from '@shared/hooks/useDebouncedValue';
import { analyzeSeo, useSaveSeo } from './api';
import type { SeoPayload, SeoValues } from './types';

/**
 * Unsaved SEO edits for one subject. The checklist, previews and JSON-LD
 * re-run on the server 400 ms after typing stops, so what staff see is
 * exactly what will be published. Saving makes the values live.
 */
export function useSeoDraft(type: string, id: number, saved: SeoPayload) {
    const [changes, setChanges] = useState<Partial<SeoValues>>({});
    const [live, setLive] = useState<SeoPayload>(saved);
    // The changes the live view was last computed for; anything newer is still being checked.
    const [analyzed, setAnalyzed] = useState<Partial<SeoValues>>({});
    const [errors, setErrors] = useState<Record<string, string>>({});
    const save = useSaveSeo(type, id);
    const debounced = useDebouncedValue(changes, 400);
    const request = useRef<AbortController | null>(null);
    const dirty = Object.keys(changes).length > 0;

    const values = useMemo<SeoValues>(() => ({ ...saved.meta, ...changes }), [saved.meta, changes]);

    // A fresh save replaces both the saved copy and the live view.
    const [lastSaved, setLastSaved] = useState(saved);
    if (saved !== lastSaved) {
        setLastSaved(saved);
        setLive(saved);
    }

    useEffect(() => {
        if (Object.keys(debounced).length === 0) return;

        request.current?.abort();
        const controller = new AbortController();
        request.current = controller;

        analyzeSeo(type, id, debounced, controller.signal)
            .then((payload) => {
                setLive(payload);
                setErrors({});
            })
            .catch((error: unknown) => {
                if (error instanceof ApiError && error.code === 'VALIDATION_FAILED') {
                    setErrors(Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [key, messages[0] ?? ''])));
                }
            })
            .finally(() => {
                if (request.current === controller) setAnalyzed(debounced);
            });

        return () => controller.abort();
    }, [debounced, type, id]);

    useEffect(() => {
        if (!dirty) return;
        const warn = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty]);

    return {
        values,
        live,
        dirty,
        checking: dirty && analyzed !== changes,
        errors,
        saving: save.isPending,
        set: <K extends keyof SeoValues>(key: K, value: SeoValues[K]) => {
            setChanges((current) => ({ ...current, [key]: value }));
            setErrors((current) => (key in current ? Object.fromEntries(Object.entries(current).filter(([field]) => field !== key)) : current));
        },
        discard: () => {
            setChanges({});
            setErrors({});
            setLive(saved);
        },
        save: async (): Promise<boolean> => {
            try {
                await save.mutateAsync(changes);
                setChanges({});
                setErrors({});

                return true;
            } catch (error) {
                if (error instanceof ApiError && error.code === 'VALIDATION_FAILED') {
                    setErrors(Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [key, messages[0] ?? ''])));
                }

                throw error;
            }
        },
    };
}

export type SeoDraft = ReturnType<typeof useSeoDraft>;
