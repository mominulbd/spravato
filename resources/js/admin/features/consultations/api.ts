import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, getData } from '@shared/api/client';
import type { ConsultationDetail, ConsultationListMeta, ConsultationOverview, ConsultationRow, ConsultationStatus } from './types';

export const consultationKeys = {
    all: ['consultations'] as const,
    list: (query: Record<string, string | number>) => ['consultations', 'list', query] as const,
    detail: (id: number) => ['consultations', 'detail', id] as const,
    overview: (range: number) => ['consultations', 'overview', range] as const,
    waiting: ['consultations', 'waiting'] as const,
};

export function useConsultations(query: Record<string, string | number>) {
    return useQuery({
        queryKey: consultationKeys.list(query),
        queryFn: async ({ signal }) => {
            const response = await api.get<ConsultationRow[]>('admin/consultation-requests', { query, signal });

            return { rows: response.data, meta: response.meta as unknown as ConsultationListMeta };
        },
        placeholderData: keepPreviousData,
        refetchInterval: 60_000,
    });
}

/**
 * Opening a request decrypts it and is audited server-side: one read per
 * visit, never in the background, and nothing kept in memory after leaving.
 */
export function useConsultation(id: number) {
    return useQuery({
        queryKey: consultationKeys.detail(id),
        queryFn: () => getData<ConsultationDetail>(`admin/consultation-requests/${id}`),
        staleTime: Infinity,
        gcTime: 0,
        refetchOnWindowFocus: false,
        refetchOnReconnect: false,
        retry: false,
    });
}

export function useConsultationOverview(range: number, enabled = true) {
    return useQuery({
        enabled,
        queryKey: consultationKeys.overview(range),
        queryFn: () => getData<ConsultationOverview>('admin/consultation-requests/overview', { query: { range } }),
        placeholderData: keepPreviousData,
        refetchInterval: 60_000,
    });
}

/** Who requests may be assigned to: the inbox list carries it in `meta.assignees`. */
export function useAssignees() {
    return useQuery({
        queryKey: ['consultations', 'assignees'],
        queryFn: async ({ signal }) => {
            const response = await api.get<ConsultationRow[]>('admin/consultation-requests', { query: { per_page: 1 }, signal });

            return (response.meta as unknown as ConsultationListMeta).assignees;
        },
        staleTime: 5 * 60_000,
    });
}

/** The sidebar badge. */
export function useWaitingCount(enabled: boolean) {
    return useQuery({
        enabled,
        queryKey: consultationKeys.waiting,
        queryFn: () => getData<{ waiting: number }>('admin/consultation-requests/waiting'),
        refetchInterval: 60_000,
        select: (data) => data.waiting,
    });
}

export interface ConsultationChanges {
    status?: ConsultationStatus;
    assigned_to?: number | null;
    follow_up_at?: string;
}

/** Mutations answer with the fresh detail, which replaces the cached one (no second, audited read). */
function useDetailMutation<V>(id: number, request: (variables: V) => Promise<{ data: ConsultationDetail }>) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: request,
        onSuccess: (response) => {
            queryClient.setQueryData(consultationKeys.detail(id), response.data);
            void queryClient.invalidateQueries({ queryKey: ['consultations', 'list'] });
            void queryClient.invalidateQueries({ queryKey: ['consultations', 'overview'] });
            void queryClient.invalidateQueries({ queryKey: consultationKeys.waiting });
        },
    });
}

export function useUpdateConsultation(id: number) {
    return useDetailMutation(id, (changes: ConsultationChanges) => api.patch<ConsultationDetail>(`admin/consultation-requests/${id}`, changes));
}

export function useAddNote(id: number) {
    return useDetailMutation(id, (body: string) => api.post<ConsultationDetail>(`admin/consultation-requests/${id}/notes`, { body }));
}

export function useDeleteConsultation() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => api.delete(`admin/consultation-requests/${id}`),
        onSuccess: (_, id) => {
            queryClient.removeQueries({ queryKey: consultationKeys.detail(id) });
            void queryClient.invalidateQueries({ queryKey: consultationKeys.all });
        },
    });
}

export type BulkAction = { action: 'contacted' | 'spam'; ids: number[] } | { action: 'assign'; ids: number[]; assigned_to: number | null };

export function useBulkConsultations() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (body: BulkAction) => api.post<{ updated: number; skipped: number }>('admin/consultation-requests/bulk', body),
        onSuccess: () => void queryClient.invalidateQueries({ queryKey: consultationKeys.all }),
    });
}

export function exportUrl(filters: Record<string, string>): string {
    const params = new URLSearchParams();

    for (const key of ['status', 'topic', 'received']) {
        const value = filters[key];
        if (value && value !== 'overdue') params.set(`filter[${key}]`, value);
    }

    const query = params.toString();

    return `/api/v1/admin/consultation-requests/export${query ? `?${query}` : ''}`;
}
