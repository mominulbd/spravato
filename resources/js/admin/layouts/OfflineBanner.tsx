import { useSyncExternalStore } from 'react';
import { WifiOff } from 'lucide-react';

function subscribe(callback: () => void) {
    window.addEventListener('online', callback);
    window.addEventListener('offline', callback);

    return () => {
        window.removeEventListener('online', callback);
        window.removeEventListener('offline', callback);
    };
}

/** Figma 28, "Connection · offline". */
export function OfflineBanner() {
    const online = useSyncExternalStore(subscribe, () => navigator.onLine, () => true);

    if (online) {
        return null;
    }

    return (
        <div role="status" className="fixed bottom-4 left-1/2 z-[55] flex w-[min(560px,calc(100vw-2rem))] -translate-x-1/2 items-center gap-2.5 rounded-control bg-navy px-4 py-3 text-admin-body text-white shadow-lg">
            <WifiOff className="size-4 shrink-0" aria-hidden="true" />
            You’re offline. Changes can’t be saved until the connection is back.
        </div>
    );
}
