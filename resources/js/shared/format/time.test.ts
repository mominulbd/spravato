import { describe, expect, it } from 'vitest';
import { dayLabel, relativeTime } from './time';

const now = new Date(2026, 9, 8, 15, 0);
const ago = (minutes: number) => new Date(now.getTime() - minutes * 60_000).toISOString();

describe('relativeTime', () => {
    it('reads like the Figma users table', () => {
        expect(relativeTime(null, now)).toBe('—');
        expect(relativeTime(ago(1), now)).toBe('Now');
        expect(relativeTime(ago(12), now)).toBe('12 min ago');
        expect(relativeTime(ago(180), now)).toBe('3 h ago');
        expect(relativeTime(new Date(2026, 9, 7, 9).toISOString(), now)).toBe('Yesterday');
        expect(relativeTime(new Date(2026, 9, 1, 9).toISOString(), now)).toBe('Oct 1');
        expect(relativeTime(new Date(2025, 9, 1, 9).toISOString(), now)).toBe('Oct 1, 2025');
    });
});

describe('dayLabel', () => {
    it('groups by day', () => {
        expect(dayLabel(ago(30), now)).toBe('Today');
        expect(dayLabel(new Date(2026, 9, 7, 23).toISOString(), now)).toBe('Yesterday');
        expect(dayLabel(new Date(2026, 9, 5, 12).toISOString(), now)).toBe('Monday');
        expect(dayLabel(new Date(2026, 8, 1, 12).toISOString(), now)).toBe('Sep 1, 2026');
    });
});
