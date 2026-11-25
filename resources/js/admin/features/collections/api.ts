import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, getData } from '@shared/api/client';
import type { CollectionItem, CollectionKey, CollectionTab, GroupCount } from './types';

export const collectionKeys = {
    all: ['collections'] as const,
    tabs: ['collections', 'tabs'] as const,
    list: (type: CollectionKey, query: Record<string, string>) => ['collections', 'list', type, query] as const,
};

export function useCollectionTabs() {
    return useQuery({
        queryKey: collectionKeys.tabs,
        queryFn: () => getData<CollectionTab[]>('admin/collections'),
    });
}

export function useCollectionItems<T extends CollectionItem>(type: CollectionKey, query: Record<string, string> = {}) {
    return useQuery({
        queryKey: collectionKeys.list(type, query),
        queryFn: async ({ signal }) => {
            const response = await api.get<T[]>(`admin/collections/${type}`, {
                query,
                signal,
            });

            return {
                items: response.data,
                groups: (response.meta.groups ?? []) as GroupCount[],
            };
        },
        placeholderData: keepPreviousData,
    });
}

function useInvalidate() {
    const queryClient = useQueryClient();

    return () => queryClient.invalidateQueries({ queryKey: collectionKeys.all });
}

export function useSaveItem<T extends CollectionItem>(type: CollectionKey) {
    const invalidate = useInvalidate();

    return useMutation({
        mutationFn: ({ id, body }: { id: number | null; body: Record<string, unknown> }) =>
            id === null ? api.post<T>(`admin/collections/${type}`, body) : api.put<T>(`admin/collections/${type}/${id}`, body),
        onSuccess: invalidate,
    });
}

export function useDeleteItem(type: CollectionKey) {
    const invalidate = useInvalidate();

    return useMutation({
        mutationFn: (id: number) => api.delete<null>(`admin/collections/${type}/${id}`),
        onSuccess: invalidate,
    });
}

export function useReorderItems(type: CollectionKey) {
    const invalidate = useInvalidate();

    return useMutation({
        mutationFn: ({ ids, group }: { ids: number[]; group?: string }) => api.put<null>(`admin/collections/${type}/reorder`, group === undefined ? { ids } : { ids, group }),
        onSettled: invalidate,
    });
}
