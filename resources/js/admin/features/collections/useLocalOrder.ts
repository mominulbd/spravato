import { useState } from 'react';
import { ApiError } from '@shared/api/client';
import { moveItem, useToast } from '@shared/ui';
import { useReorderItems } from './api';
import type { CollectionItem, CollectionKey } from './types';

/**
 * Drag-and-drop order that saves as soon as an item is dropped. The list
 * shows the new order at once and falls back to the server's on error.
 */
export function useLocalOrder<T extends CollectionItem>(type: CollectionKey, items: T[], group?: string) {
    const toast = useToast();
    const reorder = useReorderItems(type);
    const [order, setOrder] = useState<T[]>(items);
    const [source, setSource] = useState(items);

    // New server data (after a save or refetch) replaces the local order.
    // Compared item by item: callers may pass a fresh `[]` while loading.
    if (source !== items && sameIds(source, items) === false) {
        setSource(items);
        setOrder(items);
    }

    const move = (from: number, to: number) => {
        const next = moveItem(order, from, to);
        setOrder(next);
        reorder.mutate(
            { ids: next.map((item) => item.id), group },
            {
                onSuccess: () => toast.success('Order saved', 'The website shows the new order within seconds.'),
                onError: (error) => {
                    setOrder(items);
                    toast.error('Order not saved', error instanceof ApiError ? error.humanMessage : undefined);
                },
            }
        );
    };

    return { order, move, saving: reorder.isPending };
}

function sameIds(a: CollectionItem[], b: CollectionItem[]): boolean {
    return a.length === b.length && a.every((item, index) => item === b[index]);
}
