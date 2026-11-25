import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, getData } from '@shared/api/client';
import type { MenuDetail, MenuSummary, PageOption } from './types';

export const menuKeys = {
    all: ['menus'] as const,
    list: ['menus', 'list'] as const,
    item: (key: string) => ['menus', 'item', key] as const,
};

export function useMenus() {
    return useQuery({
        queryKey: menuKeys.list,
        queryFn: () => getData<MenuSummary[]>('admin/menus'),
    });
}

export function useMenu(key: string | null) {
    return useQuery({
        enabled: key !== null,
        queryKey: menuKeys.item(key ?? ''),
        queryFn: () => getData<MenuDetail>(`admin/menus/${key}`),
    });
}

/** Every page, for the "Add items" panel (the site has a few dozen at most). */
export function usePageOptions() {
    return useQuery({
        queryKey: ['menus', 'pages'],
        queryFn: () =>
            getData<PageOption[]>('admin/pages', {
                query: { per_page: 100, sort: 'path' },
            }),
        staleTime: 60_000,
    });
}

export function useSaveMenu(key: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (items: Record<string, unknown>[]) => api.put<MenuDetail>(`admin/menus/${key}`, { items }),
        onSuccess: (response) => {
            queryClient.setQueryData(menuKeys.item(key), response.data);
            void queryClient.invalidateQueries({ queryKey: menuKeys.list });
        },
    });
}
