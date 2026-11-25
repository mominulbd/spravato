import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, getData } from '@shared/api/client';
import type { PageMeta } from '@shared/ui';
import type { BlockTypeDef, PageBlock, PageCounts, PageDetail, PageRow, Revision } from './types';

export const pagesKeys = {
    all: ['pages'] as const,
    list: (query: Record<string, string | number>) => ['pages', 'list', query] as const,
    detail: (id: number) => ['pages', 'detail', id] as const,
    revisions: (id: number) => ['pages', 'revisions', id] as const,
    blockTypes: ['pages', 'block-types'] as const,
};

export interface PagesMeta extends PageMeta {
    counts: PageCounts;
    templates: { value: string; label: string }[];
    authors: { id: number; name: string }[];
}

export function usePages(query: Record<string, string | number>) {
    return useQuery({
        queryKey: pagesKeys.list(query),
        queryFn: async ({ signal }) => {
            const response = await api.get<PageRow[]>('admin/pages', { query, signal });

            return { pages: response.data, meta: response.meta as unknown as PagesMeta };
        },
        placeholderData: keepPreviousData,
    });
}

export function usePage(id: number) {
    return useQuery({ queryKey: pagesKeys.detail(id), queryFn: () => getData<PageDetail>(`admin/pages/${id}`), staleTime: Infinity, refetchOnWindowFocus: false });
}

export function useBlockTypes() {
    return useQuery({ queryKey: pagesKeys.blockTypes, queryFn: () => getData<BlockTypeDef[]>('admin/pages/block-types'), staleTime: 5 * 60_000 });
}

export function useRevisions(id: number, enabled: boolean) {
    return useQuery({ enabled, queryKey: pagesKeys.revisions(id), queryFn: () => getData<Revision[]>(`admin/pages/${id}/revisions`) });
}

export interface PageSettings {
    title: string;
    slug: string;
    template: string;
    parent_id: number | null;
}

function useInvalidateList() {
    const queryClient = useQueryClient();

    return () => queryClient.invalidateQueries({ queryKey: ['pages', 'list'] });
}

export function useCreatePage() {
    const invalidate = useInvalidateList();

    return useMutation({ mutationFn: (body: PageSettings) => api.post<PageDetail>('admin/pages', body), onSuccess: invalidate });
}

export function useTrashPage() {
    const invalidate = useInvalidateList();

    return useMutation({ mutationFn: (id: number) => api.delete(`admin/pages/${id}`), onSuccess: invalidate });
}

export function useRestorePage() {
    const invalidate = useInvalidateList();

    return useMutation({ mutationFn: (id: number) => api.post<PageDetail>(`admin/pages/${id}/restore`), onSuccess: invalidate });
}

/** Editor calls return the fresh page; the editor stores it itself. */
export const pageRequests = {
    saveContent: (id: number, body: { lock_version: number; blocks: PageBlock[]; checkpoint?: boolean }) => api.put<PageDetail>(`admin/pages/${id}/content`, body),
    saveSettings: (id: number, body: PageSettings) => api.put<PageDetail>(`admin/pages/${id}`, body),
    publish: (id: number, lockVersion: number) => api.post<PageDetail>(`admin/pages/${id}/publish`, { lock_version: lockVersion }),
    schedule: (id: number, at: string, lockVersion: number) => api.post<PageDetail>(`admin/pages/${id}/schedule`, { at, lock_version: lockVersion }),
    unschedule: (id: number) => api.delete<PageDetail>(`admin/pages/${id}/schedule`),
    unpublish: (id: number) => api.post<PageDetail>(`admin/pages/${id}/unpublish`),
    restoreRevision: (id: number, revision: number, lockVersion: number) => api.post<PageDetail>(`admin/pages/${id}/revisions/${revision}/restore`, { lock_version: lockVersion }),
    preview: (id: number) => api.post<{ token: string; url: string; expires_in: number }>(`admin/pages/${id}/preview`),
};
