import type { Faq } from './types';

/** The checks on the "FAQ schema" card (Figma 15), computed from the loaded FAQs. */
export interface FaqSchemaReport {
    inMarkup: number;
    duplicates: string[];
    longAnswers: string[];
    valid: boolean;
}

const normalise = (question: string) =>
    question
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();

export function wordCount(text: string): number {
    return text.trim() === '' ? 0 : text.trim().split(/\s+/).length;
}

/**
 * `group` is the group the card describes; `all` are every FAQ, so a
 * question repeated on another page counts as a duplicate.
 */
export function faqSchemaReport(group: string, all: Faq[]): FaqSchemaReport {
    const inGroup = all.filter((faq) => faq.group === group && faq.is_published && faq.in_schema);
    const seen = new Map<string, string>();
    const duplicates = new Set<string>();

    for (const faq of all.filter((item) => item.is_published && item.in_schema)) {
        const key = normalise(faq.question);
        const other = seen.get(key);

        if (other !== undefined && other !== faq.group && (faq.group === group || other === group)) {
            duplicates.add(faq.question);
        }

        if (other === undefined) seen.set(key, faq.group);
    }

    const longAnswers = inGroup.filter((faq) => wordCount(faq.answer) > 300).map((faq) => faq.question);

    return {
        inMarkup: inGroup.length,
        duplicates: [...duplicates],
        longAnswers,
        valid: duplicates.size === 0 && longAnswers.length === 0,
    };
}
