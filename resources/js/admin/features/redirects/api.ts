import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@shared/api/client';
import type { PageMeta } from '@shared/ui';

export interface RedirectRow {
    id: number;
    from_path: string;
    to_path: string | null;
    status_code: 301 | 302 | 307 | 410;
    match_type: 'exact' | 'wildcard';
    is_enabled: boolean;
    source: 'manual' | 'slug_change' | 'imported' | 'not_found';
    note: string | null;
    hits: number;
    hits_30d: number;
    last_hit_at: string | null;
}

export interface RedirectCheck {
    loop: boolean;
    chain: string[];
    target: { state: 'ok' | 'missing' | 'external' | 'gone'; path: string | null };
    shadows_content: boolean;
}

export interface NotFoundRow {
    id: number;
    path: string;
    hits: number;
    google_hits: number;
    last_seen_at: string;
    referrer: { kind: 'google' | 'search' | 'internal' | 'external' | 'direct'; path: string | null };
    is_bot: boolean;
    status: 'open' | 'ignored' | 'resolved';
    suggestion: { path: string; score: number } | null;
}

export interface NotFoundMeta extends PageMeta {
    counts: Record<'open' | 'ignored' | 'resolved', number>;
    stats: { week: number; previous_week: number; change_percent: number | null; unique: number; from_google: number; fixed_this_month: number };
}

export interface RedirectInput {
    from_path: string;
    to_path: string | null;
    status_code: number;
    is_enabled?: boolean;
}

export function useRedirects(query: Record<string, string | number>) {
    return useQuery({
        queryKey: ['redirects', query],
        queryFn: async ({ signal }) => {
            const response = await api.get<RedirectRow[]>('admin/redirects', { query, signal });

            return { rows: response.data, meta: response.meta as unknown as PageMeta };
        },
        placeholderData: keepPreviousData,
    });
}

export function checkRedirect(body: { from_path: string; to_path: string | null; id?: number }, signal?: AbortSignal) {
    return api.post<RedirectCheck>('admin/redirects/check', body, { signal }).then((response) => response.data);
}

export function useRedirectMutations() {
    const queryClient = useQueryClient();
    const invalidate = () => {
        void queryClient.invalidateQueries({ queryKey: ['redirects'] });
        void queryClient.invalidateQueries({ queryKey: ['not-found'] });
    };

    return {
        create: useMutation({ mutationFn: (body: RedirectInput) => api.post<RedirectRow>('admin/redirects', body), onSuccess: invalidate }),
        update: useMutation({ mutationFn: ({ id, ...body }: RedirectInput & { id: number }) => api.put<RedirectRow>(`admin/redirects/${id}`, body), onSuccess: invalidate }),
        remove: useMutation({ mutationFn: (id: number) => api.delete(`admin/redirects/${id}`), onSuccess: invalidate }),
        importCsv: useMutation({
            mutationFn: (file: File) => {
                const body = new FormData();
                body.append('file', file);

                return api.post<{ imported: number; errors: string[] }>('admin/redirects/import', body);
            },
            onSuccess: invalidate,
        }),
    };
}

export function useNotFound(query: Record<string, string | number>) {
    return useQuery({
        queryKey: ['not-found', query],
        queryFn: async ({ signal }) => {
            const response = await api.get<NotFoundRow[]>('admin/not-found', { query, signal });

            return { rows: response.data, meta: response.meta as unknown as NotFoundMeta };
        },
        placeholderData: keepPreviousData,
    });
}

export function useNotFoundMutations() {
    const queryClient = useQueryClient();
    const invalidate = () => {
        void queryClient.invalidateQueries({ queryKey: ['not-found'] });
        void queryClient.invalidateQueries({ queryKey: ['redirects'] });
    };

    return {
        resolve: useMutation({ mutationFn: ({ id, ...body }: { id: number; to_path?: string | null; status_code?: 301 | 410 }) => api.post(`admin/not-found/${id}/resolve`, body), onSuccess: invalidate }),
        ignore: useMutation({ mutationFn: (id: number) => api.post(`admin/not-found/${id}/ignore`), onSuccess: invalidate }),
        restore: useMutation({ mutationFn: (id: number) => api.post(`admin/not-found/${id}/restore`), onSuccess: invalidate }),
        clearIgnored: useMutation({ mutationFn: () => api.delete('admin/not-found/ignored'), onSuccess: invalidate }),
    };
}
