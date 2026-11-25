import { MutationCache, QueryCache, QueryClient } from '@tanstack/react-query';
import { ApiError } from './client';

/**
 * Retry only what retrying can fix: network errors and 5xx, never 4xx.
 * `onError` sees every failed query and mutation once, for central handling
 * (401 → sign-in, 2FA set-up, toasts for 429 / 5xx / offline).
 */
export function createQueryClient(onError?: (error: unknown, source: 'query' | 'mutation') => void): QueryClient {
    return new QueryClient({
        queryCache: new QueryCache({ onError: (error) => onError?.(error, 'query') }),
        mutationCache: new MutationCache({ onError: (error) => onError?.(error, 'mutation') }),
        defaultOptions: {
            queries: {
                staleTime: 30_000,
                refetchOnWindowFocus: false,
                retry: (count, error) => count < 2 && (!(error instanceof ApiError) || error.status === 0 || error.status >= 500),
            },
            mutations: { retry: false },
        },
    });
}
