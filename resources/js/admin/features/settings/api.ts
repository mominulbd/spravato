import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, getData } from '@shared/api/client';
import { payloadFor } from './form';
import type { ClinicSettings, SettingsGroup } from './types';

export const settingsKeys = { all: ['settings'] as const };

export function useClinicSettings() {
    return useQuery({ queryKey: settingsKeys.all, queryFn: () => getData<ClinicSettings>('admin/settings') });
}

/** Saves one group; the response is the whole settings object. */
export function useSaveSettings() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ group, draft }: { group: SettingsGroup; draft: ClinicSettings }) => api.put<ClinicSettings>(`admin/settings/${group}`, payloadFor(group, draft)),
        onSuccess: (response, { group }) => {
            queryClient.setQueryData(settingsKeys.all, response.data);
            // The two-factor switch changes what /auth/me and the account page report.
            if (group === 'security') void queryClient.invalidateQueries({ queryKey: ['auth'] });
        },
    });
}

export type CacheScope = 'website' | 'application';

export interface CacheCleared {
    scope: CacheScope;
    cleared_at: string;
    website_connected: boolean;
}

/** Clinic settings → Cache. Nothing in the settings object changes, so no query is refreshed. */
export function useClearCache() {
    return useMutation({
        mutationFn: (scope: CacheScope) => api.post<CacheCleared>('admin/settings/cache/clear', { scope }).then((response) => response.data),
    });
}
