/**
 * Signals from the API layer to the auth context, without a global store:
 * the query client reports, AuthProvider decides what the screen shows.
 */
export type AuthEvent = 'unauthenticated' | 'two-factor-required' | 'inactive';

type Listener = (event: AuthEvent) => void;

const listeners = new Set<Listener>();

export const authEvents = {
    emit(event: AuthEvent): void {
        listeners.forEach((listener) => listener(event));
    },
    subscribe(listener: Listener): () => void {
        listeners.add(listener);

        return () => listeners.delete(listener);
    },
};
