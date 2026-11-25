import type { CollectionItem, CollectionKey, ContentItem, InsurancePartner, Testimonial } from './types';

/** Editor fields for the simple collections (everything but team and FAQs). */
export interface ListField {
    name: string;
    label: string;
    kind: 'text' | 'textarea' | 'url' | 'image' | 'rating';
    max?: number;
    help?: string;
    placeholder?: string;
}

export interface ListSpec {
    noun: string;
    fields: ListField[];
    title: (item: CollectionItem) => string;
    subtitle: (item: CollectionItem) => string | null;
    /** Media id for image fields, read from the presented item. */
    image?: (item: CollectionItem) => {
        name: string;
        id: number | null;
        url: string | null;
    };
    blank: Record<string, string | number | null>;
}

const linkHelp = 'A page (/contact), an anchor (#faq), https://, tel: or mailto:';

export const LIST_SPECS: Partial<Record<CollectionKey, ListSpec>> = {
    testimonials: {
        noun: 'testimonial',
        fields: [
            {
                name: 'quote',
                label: 'Quote',
                kind: 'textarea',
                max: 600,
                help: 'Only publish words the patient agreed to share.',
            },
            {
                name: 'author_name',
                label: 'Name shown',
                kind: 'text',
                max: 120,
                placeholder: 'Sarah M.',
            },
            {
                name: 'source_label',
                label: 'Source',
                kind: 'text',
                max: 120,
                placeholder: 'Clinic website testimonial',
            },
            {
                name: 'source_url',
                label: 'Source link',
                kind: 'url',
                max: 190,
                placeholder: 'https://',
            },
            { name: 'rating', label: 'Rating', kind: 'rating' },
        ],
        title: (item) => `“${(item as Testimonial).quote}”`,
        subtitle: (item) => [(item as Testimonial).author_name, (item as Testimonial).source_label].filter(Boolean).join(' · '),
        blank: {
            quote: '',
            author_name: '',
            source_label: '',
            source_url: '',
            rating: null,
        },
    },
    insurance: {
        noun: 'partner',
        fields: [
            { name: 'name', label: 'Name', kind: 'text', max: 120 },
            {
                name: 'logo_id',
                label: 'Logo',
                kind: 'image',
                help: 'Logos sit in a 150 × 64 box; wide images work best.',
            },
            {
                name: 'url',
                label: 'Website',
                kind: 'url',
                max: 190,
                placeholder: 'https://',
            },
            {
                name: 'note',
                label: 'Note',
                kind: 'text',
                max: 190,
                help: 'For the team only, e.g. which plans are in network.',
            },
        ],
        title: (item) => (item as InsurancePartner).name,
        subtitle: (item) => (item as InsurancePartner).note,
        image: (item) => ({
            name: 'logo_id',
            id: (item as InsurancePartner).logo?.id ?? null,
            url: (item as InsurancePartner).logo?.url ?? null,
        }),
        blank: { name: '', logo_id: null, url: '', note: '' },
    },
    'trust-points': {
        noun: 'trust point',
        fields: [
            {
                name: 'title',
                label: 'Text',
                kind: 'text',
                max: 160,
                help: 'A short pill under the home hero, e.g. “Free phone consultation”.',
            },
        ],
        title: (item) => (item as ContentItem).title,
        subtitle: () => null,
        blank: { title: '' },
    },
    'journey-steps': {
        noun: 'step',
        fields: [
            { name: 'title', label: 'Title', kind: 'text', max: 160 },
            { name: 'body', label: 'Text', kind: 'textarea', max: 600 },
            { name: 'link_label', label: 'Link label', kind: 'text', max: 60 },
            {
                name: 'link_url',
                label: 'Link',
                kind: 'text',
                max: 190,
                help: linkHelp,
            },
        ],
        title: (item) => (item as ContentItem).title,
        subtitle: (item) => (item as ContentItem).body,
        blank: { title: '', body: '', link_label: '', link_url: '' },
    },
};

/** Item → form values for the spec's fields. */
export function valuesOf(spec: ListSpec, item: CollectionItem | null): Record<string, string | number | null> {
    if (item === null) return { ...spec.blank };

    const values: Record<string, string | number | null> = {};

    for (const field of spec.fields) {
        if (field.kind === 'image') {
            values[field.name] = spec.image?.(item).id ?? null;
        } else {
            const value = (item as unknown as Record<string, unknown>)[field.name];
            values[field.name] = typeof value === 'number' ? value : typeof value === 'string' ? value : field.kind === 'rating' ? null : '';
        }
    }

    return values;
}

/** Form values → request body: blank optional strings become null. */
export function bodyOf(values: Record<string, string | number | null>): Record<string, string | number | null> {
    return Object.fromEntries(Object.entries(values).map(([key, value]) => [key, typeof value === 'string' ? (value.trim() === '' ? null : value.trim()) : value]));
}
