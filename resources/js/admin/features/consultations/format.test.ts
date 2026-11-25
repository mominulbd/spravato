import { describe, expect, it } from 'vitest';
import { receivedLabel, shortStaffName, splitTitle, statusBadge, waitingLabel, waitingTone } from './format';

describe('consultation formatting', () => {
    it('writes waiting times like the design', () => {
        expect(waitingLabel(42)).toBe('42 min');
        expect(waitingLabel(114)).toBe('1 h 54 min');
        expect(waitingLabel(120)).toBe('2 h');
        expect(waitingLabel(1500)).toBe('1 d 1 h');
        expect(waitingLabel(2880)).toBe('2 d');
    });

    it('colours waiting time by urgency', () => {
        expect(waitingTone({ is_overdue: true, waiting_minutes: 30 })).toBe('danger');
        expect(waitingTone({ is_overdue: false, waiting_minutes: 1500 })).toBe('warning');
        expect(waitingTone({ is_overdue: false, waiting_minutes: 90 })).toBeNull();
    });

    it('shows Overdue instead of New', () => {
        expect(statusBadge({ status: 'new', is_overdue: true })).toEqual({ label: 'Overdue', tone: 'danger' });
        expect(statusBadge({ status: 'scheduled', is_overdue: false })).toEqual({ label: 'Scheduled', tone: 'success' });
    });

    it('formats names, titles and received times', () => {
        const now = new Date(2026, 9, 8, 16, 0);

        expect(shortStaffName('Joy Kiongo')).toBe('Joy K.');
        expect(splitTitle('Provider referral')).toEqual({ title: 'Provider', accent: 'referral.' });
        expect(splitTitle('Insurance & coverage')).toEqual({ title: 'Insurance &', accent: 'coverage.' });
        expect(receivedLabel(new Date(2026, 9, 8, 9, 14).toISOString(), now)).toBe('Today, 9:14 AM');
        expect(receivedLabel(new Date(2026, 9, 6, 14, 5).toISOString(), now)).toBe('Oct 6, 2:05 PM');
    });
});
