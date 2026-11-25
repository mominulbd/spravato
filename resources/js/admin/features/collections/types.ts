import type { PublicImage } from '@admin/lib/content';

/** GET admin/collections — one tab per collection (Figma 14–15). */
export interface CollectionTab {
    key: CollectionKey;
    label: string;
    count: number;
    grouped: boolean;
    groups: GroupCount[];
}

export type CollectionKey = 'team' | 'faqs' | 'testimonials' | 'insurance' | 'trust-points' | 'journey-steps';

export interface GroupCount {
    value: string;
    count: number;
}

interface ItemBase {
    id: number;
    is_published: boolean;
    sort_order: number;
    updated_at: string | null;
}

export interface TeamMember extends ItemBase {
    name: string;
    credentials: string | null;
    role_title: string;
    initials: string;
    bio: string | null;
    highlights: string[];
    photo: PublicImage | null;
    show_on: string[];
    is_founder: boolean;
    schema_type: 'Physician' | 'Person';
    licence_number: string | null;
}

export interface Faq extends ItemBase {
    group: string;
    question: string;
    answer: string;
    in_schema: boolean;
}

export interface Testimonial extends ItemBase {
    quote: string;
    author_name: string;
    source_label: string | null;
    source_url: string | null;
    rating: number | null;
}

export interface InsurancePartner extends ItemBase {
    name: string;
    logo: PublicImage | null;
    url: string | null;
    note: string | null;
}

export interface ContentItem extends ItemBase {
    title: string;
    body: string | null;
    icon: string | null;
    link_label: string | null;
    link_url: string | null;
}

export type CollectionItem = TeamMember | Faq | Testimonial | InsurancePartner | ContentItem;

export const TEAM_PAGES = [
    { value: 'home', label: 'Home' },
    { value: 'tms', label: 'TMS' },
    { value: 'contact', label: 'Contact' },
] as const;

/** "home" → "Home", "conditions/late-life-depression" → "Late-life depression". */
export function groupLabel(group: string): string {
    if (group === 'home') return 'Home';
    if (group === 'tms') return 'TMS';

    const name = group.replace(/^conditions\//, '').replace(/-/g, ' ');

    return name.charAt(0).toUpperCase() + name.slice(1);
}

export function groupPath(group: string): string {
    return group === 'home' ? '/' : `/${group}`;
}
