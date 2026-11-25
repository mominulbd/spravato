import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, getData } from '@shared/api/client';
import type { PageMeta } from '@shared/ui';
import type { PermissionGroup, Role, StaffUser, UserCounts } from './types';

export const usersKeys = {
    all: ['users'] as const,
    list: (query: Record<string, string | number>) => ['users', 'list', query] as const,
    roles: ['roles'] as const,
    registry: ['roles', 'registry'] as const,
};

export function useUsers(query: Record<string, string | number>, enabled = true) {
    return useQuery({
        enabled,
        queryKey: usersKeys.list(query),
        queryFn: async ({ signal }) => {
            const response = await api.get<StaffUser[]>('admin/users', { query, signal });

            return { users: response.data, meta: response.meta as unknown as PageMeta & { counts: UserCounts } };
        },
        placeholderData: keepPreviousData,
    });
}

export function useRoles() {
    return useQuery({ queryKey: usersKeys.roles, queryFn: () => getData<Role[]>('admin/roles') });
}

export function usePermissionRegistry() {
    return useQuery({ queryKey: usersKeys.registry, queryFn: () => getData<{ groups: PermissionGroup[] }>('admin/permissions'), staleTime: Infinity });
}

/** Mutations refresh users and roles (counts change) together. */
function useInvalidate() {
    const queryClient = useQueryClient();

    return () => Promise.all([queryClient.invalidateQueries({ queryKey: usersKeys.all }), queryClient.invalidateQueries({ queryKey: usersKeys.roles })]);
}

export function useInviteUser() {
    const invalidate = useInvalidate();

    return useMutation({
        mutationFn: (body: { name: string; email: string; role_id: number }) => api.post<StaffUser>('admin/users', body),
        onSuccess: invalidate,
    });
}

export function useUpdateUser(id: number) {
    const invalidate = useInvalidate();

    return useMutation({
        mutationFn: (body: { name: string; email: string; role_id: number; is_active: boolean }) => api.put<StaffUser>(`admin/users/${id}`, body),
        onSuccess: invalidate,
    });
}

export function useUserAction() {
    const invalidate = useInvalidate();

    return useMutation({
        mutationFn: ({ user, action }: { user: StaffUser; action: 'remove' | 'resend' | 'reset-2fa' | 'deactivate' | 'reactivate' }) => {
            switch (action) {
                case 'remove':
                    return api.delete(`admin/users/${user.id}`);
                case 'resend':
                    return api.post(`admin/users/${user.id}/invitation`);
                case 'reset-2fa':
                    return api.post(`admin/users/${user.id}/two-factor/reset`);
                default:
                    return api.put(`admin/users/${user.id}`, { name: user.name, email: user.email, role_id: user.role?.id, is_active: action === 'reactivate' });
            }
        },
        onSuccess: invalidate,
    });
}

export function useSaveRole(id: number | null) {
    const invalidate = useInvalidate();

    return useMutation({
        mutationFn: (body: { name: string; slug?: string; description: string | null; requires_two_factor: boolean; permissions: string[] }) =>
            id === null ? api.post<Role>('admin/roles', body) : api.put<Role>(`admin/roles/${id}`, body),
        onSuccess: invalidate,
    });
}

export function useDeleteRole() {
    const invalidate = useInvalidate();

    return useMutation({ mutationFn: (id: number) => api.delete(`admin/roles/${id}`), onSuccess: invalidate });
}

export function useRequireTwoFactor() {
    const invalidate = useInvalidate();

    return useMutation({ mutationFn: () => api.post<{ updated: string[] }>('admin/roles/require-two-factor'), onSuccess: invalidate });
}
