/**
 * The upload tray (Figma 18): files go up one at a time so each shows its
 * own state, and the rest can be cancelled.
 */
export type UploadState = 'waiting' | 'uploading' | 'done' | 'failed' | 'cancelled';

export interface UploadEntry {
    key: string;
    name: string;
    state: UploadState;
    mediaId?: number;
    needsAlt?: boolean;
    error?: string;
}

export type UploadAction =
    | { type: 'add'; files: { key: string; name: string }[] }
    | { type: 'start'; key: string }
    | { type: 'done'; key: string; mediaId: number; needsAlt: boolean }
    | { type: 'failed'; key: string; error: string }
    | { type: 'cancel' }
    | { type: 'clear' };

export function uploadReducer(queue: UploadEntry[], action: UploadAction): UploadEntry[] {
    switch (action.type) {
        case 'add':
            return [...queue.filter((entry) => entry.state !== 'cancelled'), ...action.files.map((file) => ({ ...file, state: 'waiting' as const }))];
        case 'start':
            return queue.map((entry) => (entry.key === action.key ? { ...entry, state: 'uploading' } : entry));
        case 'done':
            return queue.map((entry) => (entry.key === action.key ? { ...entry, state: 'done', mediaId: action.mediaId, needsAlt: action.needsAlt } : entry));
        case 'failed':
            return queue.map((entry) => (entry.key === action.key ? { ...entry, state: 'failed', error: action.error } : entry));
        case 'cancel':
            return queue.map((entry) => (entry.state === 'waiting' ? { ...entry, state: 'cancelled' } : entry));
        case 'clear':
            return queue.filter((entry) => entry.state === 'waiting' || entry.state === 'uploading');
    }
}

/** The next file to send, if nothing is in flight. */
export function nextUpload(queue: UploadEntry[]): UploadEntry | undefined {
    return queue.some((entry) => entry.state === 'uploading') ? undefined : queue.find((entry) => entry.state === 'waiting');
}

/** "Uploading 2 of 3 files" — position of the current file among the batch. */
export function uploadProgress(queue: UploadEntry[]): { current: number; total: number; active: boolean } {
    const batch = queue.filter((entry) => entry.state !== 'cancelled');
    const finished = batch.filter((entry) => entry.state === 'done' || entry.state === 'failed').length;
    const active = batch.some((entry) => entry.state === 'uploading' || entry.state === 'waiting');

    return { current: Math.min(batch.length, finished + (active ? 1 : 0)), total: batch.length, active };
}
