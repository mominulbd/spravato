import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, getData } from '@shared/api/client';
import type { SeoAuditOverview, SeoPayload, SeoValues } from './types';

export const seoKeys = {
    subject: (type: string, id: number) => ['seo', type, id] as const,
    settings: ['seo', 'settings'] as const,
    audit: ['seo', 'audit'] as const,
};

export function useSeo(type: string, id: number) {
    return useQuery({ queryKey: seoKeys.subject(type, id), queryFn: () => getData<SeoPayload>(`admin/seo/${type}/${id}`), refetchOnWindowFocus: false });
}

/** The live checklist for unsaved values; nothing is stored. */
export function analyzeSeo(type: string, id: number, values: Partial<SeoValues>, signal?: AbortSignal) {
    return api.post<SeoPayload>(`admin/seo/${type}/${id}/analyze`, values, { signal }).then((response) => response.data);
}

export function useSaveSeo(type: string, id: number) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (values: Partial<SeoValues>) => api.put<SeoPayload>(`admin/seo/${type}/${id}`, values).then((response) => response.data),
        onSuccess: (payload) => queryClient.setQueryData(seoKeys.subject(type, id), payload),
    });
}

/** The latest site audit (Figma 19, dashboard). Polls while a run is in progress. */
export function useSeoAudit(enabled = true) {
    return useQuery({
        queryKey: seoKeys.audit,
        queryFn: () => getData<SeoAuditOverview>('admin/seo-audit'),
        enabled,
        refetchInterval: (query) => (query.state.data?.running ? 2000 : false),
    });
}

export function useRunSeoAudit() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: () => api.post('admin/seo-audit'),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: seoKeys.audit }),
    });
}
