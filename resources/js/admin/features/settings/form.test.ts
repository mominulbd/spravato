import { describe, expect, it } from 'vitest';
import { dirtyGroups, flattenErrors, localErrors, payloadFor } from './form';
import type { ClinicSettings } from './types';

const settings: ClinicSettings = {
    profile: {
        name: 'UPTMS', tagline: '', street: '4007 Bridgeport Way W', city: 'University Place', state: 'WA', zip: '98466', phone: '253-234-4199',
        email: 'contact@uptms.com', latitude: 47.2271, longitude: -122.5418, service_area_label: 'Greater Tacoma', service_area: ['Tacoma'], main_site_url: '', social: [],
    },
    announcement: { enabled: false, text: '', link_label: '', link_url: '' },
    disclaimers: { crisis: 'Call 988.', crisis_short: 'Call 988.', insurance: '', testimonials: '', quiz: '' },
    privacy: { consultation_retention_days: 365, staff_notification_email: '', staff_alert_details: true },
    security: { two_factor_login: false },
    hours: {
        days: [
            { day: 1, name: 'Monday', is_open: true, opens_at: '08:00', closes_at: '17:00' },
            { day: 6, name: 'Saturday', is_open: false, opens_at: '09:00', closes_at: null },
        ],
        closures: [{ id: 3, date: '2026-11-26', label: 'Thanksgiving' }],
    },
    phone_href: 'tel:+12532344199',
};

describe('clinic settings form', () => {
    it('keeps the first server message per field', () => {
        expect(flattenErrors({ 'days.0.closes_at': ['After opening.', 'Other'], crisis: [] })).toEqual({ 'days.0.closes_at': 'After opening.' });
    });

    it('sends hours without times for closed days and without closure ids', () => {
        expect(payloadFor('hours', settings)).toEqual({
            days: [
                { day: 1, is_open: true, opens_at: '08:00', closes_at: '17:00' },
                { day: 6, is_open: false, opens_at: null, closes_at: null },
            ],
            closures: [{ date: '2026-11-26', label: 'Thanksgiving' }],
        });
    });

    it('sends coordinates as numbers and an empty site URL as null', () => {
        const draft = { ...settings, profile: { ...settings.profile, latitude: '47.3', longitude: '' } };
        expect(payloadFor('profile', draft)).toMatchObject({ latitude: 47.3, longitude: null, main_site_url: null });
    });

    it('detects which groups changed', () => {
        const draft = { ...settings, disclaimers: { ...settings.disclaimers, crisis: 'Changed' } };
        expect(dirtyGroups(draft, settings)).toEqual(['disclaimers']);
    });

    it('catches closing before opening and an empty crisis disclaimer', () => {
        const draft = { ...settings, hours: { ...settings.hours, days: [{ day: 1, name: 'Monday', is_open: true, opens_at: '17:00', closes_at: '08:00' }] }, disclaimers: { ...settings.disclaimers, crisis: ' ' } };
        expect(localErrors('hours', draft)).toEqual({ 'days.0.closes_at': 'Closing time must be after opening time.' });
        expect(localErrors('disclaimers', draft)).toEqual({ crisis: 'The crisis disclaimer can’t be turned off.' });
    });
});
