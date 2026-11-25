/** GET /admin/settings (Uptms\Settings\Http\Controllers\Admin\SettingsController). */
export type SocialNetwork = 'facebook' | 'instagram' | 'linkedin' | 'youtube' | 'x';

export interface ProfileSettings {
    name: string;
    tagline: string;
    street: string;
    city: string;
    state: string;
    zip: string;
    phone: string;
    email: string;
    latitude: number | string | null;
    longitude: number | string | null;
    service_area_label: string;
    service_area: string[];
    main_site_url: string;
    social: { network: SocialNetwork; url: string }[];
}

export interface AnnouncementSettings {
    enabled: boolean;
    text: string;
    link_label: string;
    link_url: string;
}

export interface DisclaimerSettings {
    crisis: string;
    crisis_short: string;
    insurance: string;
    testimonials: string;
    quiz: string;
}

export interface PrivacySettings {
    consultation_retention_days: number;
    staff_notification_email: string;
    staff_alert_details: boolean;
}

export interface SecuritySettings {
    two_factor_login: boolean;
}

export interface HoursDay {
    day: number;
    name: string;
    is_open: boolean;
    opens_at: string | null;
    closes_at: string | null;
}

export interface Closure {
    id?: number;
    date: string;
    label: string;
}

export interface HoursSettings {
    days: HoursDay[];
    closures: Closure[];
    short?: string;
}

export interface ClinicSettings {
    profile: ProfileSettings;
    announcement: AnnouncementSettings;
    disclaimers: DisclaimerSettings;
    privacy: PrivacySettings;
    security: SecuritySettings;
    hours: HoursSettings;
    phone_href: string;
}

export type SettingsGroup = 'profile' | 'hours' | 'announcement' | 'disclaimers' | 'privacy' | 'security';

export const SOCIAL_NETWORKS: { value: SocialNetwork; label: string }[] = [
    { value: 'facebook', label: 'Facebook' },
    { value: 'instagram', label: 'Instagram' },
    { value: 'linkedin', label: 'LinkedIn' },
    { value: 'youtube', label: 'YouTube' },
    { value: 'x', label: 'X' },
];
