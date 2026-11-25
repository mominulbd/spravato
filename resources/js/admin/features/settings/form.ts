import type { ClinicSettings, HoursSettings, SettingsGroup } from './types';

/** First message per field from a 422 (`service_area.2`, `days.0.closes_at` …). */
export function flattenErrors(errors: Record<string, string[]>): Record<string, string> {
    const result: Record<string, string> = {};

    for (const [key, messages] of Object.entries(errors)) {
        if (messages[0] !== undefined) result[key] = messages[0];
    }

    return result;
}

/** The payload PUT /admin/settings/{group} expects. */
export function payloadFor(group: SettingsGroup, draft: ClinicSettings): Record<string, unknown> {
    if (group === 'hours') {
        return hoursPayload(draft.hours);
    }

    if (group === 'profile') {
        const profile = draft.profile;
        const coordinate = (value: number | string | null) => (value === null || value === '' ? null : Number(value));

        return { ...profile, latitude: coordinate(profile.latitude), longitude: coordinate(profile.longitude), main_site_url: profile.main_site_url.trim() || null };
    }

    return { ...draft[group] };
}

export function hoursPayload(hours: HoursSettings): Record<string, unknown> {
    return {
        days: hours.days.map((day) => ({
            day: day.day,
            is_open: day.is_open,
            opens_at: day.is_open ? day.opens_at : null,
            closes_at: day.is_open ? day.closes_at : null,
        })),
        closures: hours.closures.map(({ date, label }) => ({ date, label })),
    };
}

/** Which groups differ from what the server holds. */
export function dirtyGroups(draft: ClinicSettings, saved: ClinicSettings): SettingsGroup[] {
    const groups: SettingsGroup[] = ['profile', 'hours', 'announcement', 'disclaimers', 'privacy', 'security'];

    return groups.filter((group) => JSON.stringify(payloadFor(group, draft)) !== JSON.stringify(payloadFor(group, saved)));
}

/** Checks the browser can do before sending; the server validates again. */
export function localErrors(group: SettingsGroup, draft: ClinicSettings): Record<string, string> {
    const errors: Record<string, string> = {};

    if (group === 'hours') {
        draft.hours.days.forEach((day, index) => {
            if (!day.is_open) return;
            if (!day.opens_at || !day.closes_at) errors[`days.${index}.closes_at`] = 'Enter opening and closing times.';
            else if (day.opens_at >= day.closes_at) errors[`days.${index}.closes_at`] = 'Closing time must be after opening time.';
        });
        draft.hours.closures.forEach((closure, index) => {
            if (closure.date === '') errors[`closures.${index}.date`] = 'Choose a date.';
            if (closure.label.trim() === '') errors[`closures.${index}.label`] = 'Name the closure.';
        });
    }

    if (group === 'profile') {
        if (draft.profile.name.trim() === '') errors.name = 'Enter the clinic name.';
        if (draft.profile.phone.trim() === '') errors.phone = 'Enter the phone number.';
    }

    if (group === 'disclaimers' && draft.disclaimers.crisis.trim() === '') {
        errors.crisis = 'The crisis disclaimer can’t be turned off.';
    }

    return errors;
}
