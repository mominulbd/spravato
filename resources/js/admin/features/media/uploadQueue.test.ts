import { describe, expect, it } from 'vitest';
import { focalFromKey, focalFromPointer } from './focal';
import { nextUpload, uploadProgress, uploadReducer, type UploadEntry } from './uploadQueue';

describe('upload queue', () => {
    const added = uploadReducer([], { type: 'add', files: [{ key: 'a', name: 'a.jpg' }, { key: 'b', name: 'b.jpg' }, { key: 'c', name: 'c.jpg' }] });

    it('sends one file at a time, in order', () => {
        expect(nextUpload(added)?.key).toBe('a');
        const started = uploadReducer(added, { type: 'start', key: 'a' });
        expect(nextUpload(started)).toBeUndefined();
        const done = uploadReducer(started, { type: 'done', key: 'a', mediaId: 7, needsAlt: true });
        expect(nextUpload(done)?.key).toBe('b');
        expect(uploadProgress(uploadReducer(done, { type: 'start', key: 'b' }))).toEqual({ current: 2, total: 3, active: true });
    });

    it('cancels only what is still waiting and keeps failures visible', () => {
        let queue: UploadEntry[] = uploadReducer(added, { type: 'start', key: 'a' });
        queue = uploadReducer(queue, { type: 'cancel' });
        expect(queue.map((entry) => entry.state)).toEqual(['uploading', 'cancelled', 'cancelled']);
        queue = uploadReducer(queue, { type: 'failed', key: 'a', error: 'Too big' });
        expect(uploadProgress(queue)).toEqual({ current: 1, total: 1, active: false });
        expect(uploadReducer(queue, { type: 'clear' })).toEqual([]);
    });
});

describe('focal point', () => {
    it('maps a pointer to percentages, clamped to the image', () => {
        expect(focalFromPointer(150, 50, { left: 100, top: 0, width: 200, height: 100 })).toEqual({ x: 25, y: 50 });
        expect(focalFromPointer(999, -5, { left: 0, top: 0, width: 200, height: 100 })).toEqual({ x: 100, y: 0 });
    });

    it('moves with arrow keys and recentres with Home', () => {
        expect(focalFromKey({ x: 50, y: 50 }, 'ArrowRight')).toEqual({ x: 52, y: 50 });
        expect(focalFromKey({ x: 95, y: 50 }, 'ArrowRight', true)).toEqual({ x: 100, y: 50 });
        expect(focalFromKey({ x: 10, y: 10 }, 'Home')).toEqual({ x: 50, y: 50 });
        expect(focalFromKey({ x: 10, y: 10 }, 'a')).toBeNull();
    });
});
