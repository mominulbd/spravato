import { keepPreviousData, useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, getData } from '@shared/api/client';
import type { PageMeta } from '@shared/ui';
import type { MediaCounts, MediaFolder, MediaItem, UnusedSummary } from './types';

export const mediaKeys = {
    all: ['media'] as const,
    list: (query: Record<string, string | number>) => ['media', 'list', query] as const,
    item: (id: number) => ['media', 'item', id] as const,
    folders: ['media', 'folders'] as const,
    trash: ['media', 'trash'] as const,
};

export interface MediaListMeta extends PageMeta {
    counts: MediaCounts;
    unused: UnusedSummary;
}

export function useMediaList(query: Record<string, string | number>, enabled = true) {
    return useQuery({
        enabled,
        queryKey: mediaKeys.list(query),
        queryFn: async ({ signal }) => {
            const response = await api.get<MediaItem[]>('admin/media', { query, signal });

            return { items: response.data, meta: response.meta as unknown as MediaListMeta };
        },
        placeholderData: keepPreviousData,
    });
}

export function useMediaItem(id: number | null) {
    return useQuery({
        enabled: id !== null,
        queryKey: mediaKeys.item(id ?? 0),
        queryFn: () => getData<MediaItem>(`admin/media/${id}`),
    });
}

export function useMediaFolders() {
    return useQuery({ queryKey: mediaKeys.folders, queryFn: () => getData<MediaFolder[]>('admin/media-folders') });
}

function useInvalidateMedia() {
    const queryClient = useQueryClient();

    return () => queryClient.invalidateQueries({ queryKey: mediaKeys.all });
}

/** One file per request so each can show its own progress and error. */
export function useUploadMedia() {
    const invalidate = useInvalidateMedia();

    return useMutation({
        mutationFn: ({ file, folderId }: { file: File; folderId?: number | null }) => {
            const body = new FormData();
            body.append('file', file);

            if (folderId) {
                body.append('folder_id', String(folderId));
            }

            return api.post<MediaItem>('admin/media', body);
        },
        onSuccess: invalidate,
    });
}

export function useUpdateMedia() {
    const invalidate = useInvalidateMedia();

    return useMutation({
        mutationFn: ({ id, ...body }: { id: number } & Partial<{ filename: string; alt: string | null; is_decorative: boolean; caption: string | null; focal_x: number; focal_y: number; folder_id: number | null }>) =>
            api.put<MediaItem>(`admin/media/${id}`, body),
        onSuccess: invalidate,
    });
}

/** The library grid: pages append with "Load more" (Figma 18). */
export function useInfiniteMedia(query: Record<string, string | number>) {
    return useInfiniteQuery({
        queryKey: [...mediaKeys.list(query), 'infinite'] as const,
        initialPageParam: 1,
        queryFn: async ({ pageParam, signal }) => {
            const response = await api.get<MediaItem[]>('admin/media', { query: { ...query, page: pageParam }, signal });

            return { items: response.data, meta: response.meta as unknown as MediaListMeta };
        },
        getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
        placeholderData: keepPreviousData,
    });
}

export function useMediaTrash(enabled: boolean) {
    return useQuery({
        enabled,
        queryKey: mediaKeys.trash,
        queryFn: async () => {
            const response = await api.get<MediaItem[]>('admin/media/trash', { query: { per_page: 100 } });

            return { items: response.data, meta: response.meta as unknown as PageMeta & { purge_after_days: number } };
        },
    });
}

export function useReplaceMedia() {
    const invalidate = useInvalidateMedia();

    return useMutation({
        mutationFn: ({ id, file }: { id: number; file: File }) => {
            const body = new FormData();
            body.append('file', file);

            return api.post<MediaItem>(`admin/media/${id}/replace`, body);
        },
        onSuccess: invalidate,
    });
}

export function useTrashMedia() {
    const invalidate = useInvalidateMedia();

    return useMutation({ mutationFn: (id: number) => api.delete(`admin/media/${id}`), onSuccess: invalidate });
}

export function useRestoreMedia() {
    const invalidate = useInvalidateMedia();

    return useMutation({ mutationFn: (id: number) => api.post<MediaItem>(`admin/media/${id}/restore`), onSuccess: invalidate });
}

export function useBulkMedia() {
    const invalidate = useInvalidateMedia();

    return useMutation({
        mutationFn: (body: { action: 'trash' | 'move'; ids: number[]; folder_id?: number | null }) => api.post<{ count: number }>('admin/media/bulk', body),
        onSuccess: invalidate,
    });
}

export function useCreateFolder() {
    const invalidate = useInvalidateMedia();

    return useMutation({ mutationFn: (name: string) => api.post<MediaFolder>('admin/media-folders', { name }), onSuccess: invalidate });
}
