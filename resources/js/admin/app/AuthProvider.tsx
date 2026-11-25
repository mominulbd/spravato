import type { ReactNode } from 'react';
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { type QueryClient, useQuery, useQueryClient } from '@tanstack/react-query';
import { ApiError, api } from '@shared/api/client';
import type { CurrentUser } from '@shared/auth/types';
import { authEvents } from './authEvents';

/**
 * The one piece of global client state (guideline §15): who is signed in.
 * `sessionEnded` is set when a signed-in session dies (idle timeout, 401,
 * deactivation) so the shell can show "Signed out for privacy".
 */
export type SessionEnd = 'idle' | 'expired' | 'inactive';

interface AuthContextValue {
    user: CurrentUser | null;
    loading: boolean;
    sessionEnded: SessionEnd | null;
    setUser: (user: CurrentUser) => void;
    refresh: () => Promise<void>;
    signOut: (reason?: SessionEnd) => Promise<void>;
    clearSessionEnded: () => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export const ME_KEY = ['auth', 'me'] as const;

/**
 * Forget everything the old session loaded. `me` is set to null rather than
 * removed, so the mounted observer sees the change.
 */
function forgetSession(queryClient: QueryClient): void {
    queryClient.setQueryData(ME_KEY, null);
    queryClient.removeQueries({ predicate: (query) => query.queryKey[0] !== ME_KEY[0] || query.queryKey[1] !== ME_KEY[1] });
}

async function fetchMe(): Promise<CurrentUser | null> {
    try {
        return (await api.get<CurrentUser>('auth/me')).data;
    } catch (error) {
        if (error instanceof ApiError && (error.status === 401 || error.code === 'ACCOUNT_INACTIVE')) {
            return null;
        }

        throw error;
    }
}

export function AuthProvider({ children }: { children: ReactNode }) {
    const queryClient = useQueryClient();
    const [sessionEnded, setSessionEnded] = useState<SessionEnd | null>(null);
    const me = useQuery({ queryKey: ME_KEY, queryFn: fetchMe, staleTime: Infinity, retry: 1 });
    const user = me.data ?? null;

    const endSession = useCallback(
        (reason: SessionEnd) => {
            const wasSignedIn = queryClient.getQueryData(ME_KEY) != null;
            forgetSession(queryClient);
            if (wasSignedIn) setSessionEnded(reason);
        },
        [queryClient],
    );

    useEffect(
        () =>
            authEvents.subscribe((event) => {
                if (event === 'unauthenticated') endSession('expired');
                if (event === 'inactive') endSession('inactive');
                if (event === 'two-factor-required') void queryClient.invalidateQueries({ queryKey: ME_KEY });
            }),
        [endSession, queryClient],
    );

    const value = useMemo<AuthContextValue>(
        () => ({
            user,
            loading: me.isPending,
            sessionEnded,
            setUser: (next) => {
                setSessionEnded(null);
                queryClient.setQueryData(ME_KEY, next);
            },
            refresh: async () => {
                await queryClient.invalidateQueries({ queryKey: ME_KEY });
            },
            signOut: async (reason) => {
                try {
                    await api.post('auth/logout');
                } catch {
                    // Already signed out on the server; finish locally.
                }
                if (reason) endSession(reason);
                else forgetSession(queryClient);
            },
            clearSessionEnded: () => setSessionEnded(null),
        }),
        [user, me.isPending, sessionEnded, queryClient, endSession],
    );

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
    const context = useContext(AuthContext);

    if (context === null) {
        throw new Error('useAuth() needs an <AuthProvider>.');
    }

    return context;
}

/** The signed-in user inside routes guarded by RequireAuth. */
export function useCurrentUser(): CurrentUser {
    const { user } = useAuth();

    if (user === null) {
        throw new Error('useCurrentUser() is only valid behind <RequireAuth>.');
    }

    return user;
}
