import { describe, expect, it } from 'vitest';
import { faqSchemaReport, wordCount } from './faqSchema';
import type { Faq } from './types';

const faq = (id: number, group: string, question: string, extra: Partial<Faq> = {}): Faq => ({
    id,
    group,
    question,
    answer: 'Short answer.',
    in_schema: true,
    is_published: true,
    sort_order: id,
    updated_at: null,
    ...extra,
});

describe('the FAQ schema card', () => {
    it('counts published FAQs in the markup of one group', () => {
        const report = faqSchemaReport('home', [
            faq(1, 'home', 'A?'),
            faq(2, 'home', 'B?', { in_schema: false }),
            faq(3, 'home', 'C?', { is_published: false }),
            faq(4, 'tms', 'D?'),
        ]);

        expect(report.inMarkup).toBe(1);
        expect(report.valid).toBe(true);
    });

    it('flags a question repeated on another page, ignoring case and punctuation', () => {
        const report = faqSchemaReport('home', [faq(1, 'home', 'Does TMS hurt?'), faq(2, 'tms', 'does tms hurt')]);

        expect(report.duplicates).toEqual(['does tms hurt']);
        expect(report.valid).toBe(false);
        expect(faqSchemaReport('conditions/ocd', [faq(1, 'home', 'X?'), faq(2, 'tms', 'x')]).duplicates).toEqual([]);
    });

    it('flags answers over 300 words', () => {
        const long = Array.from({ length: 301 }, () => 'word').join(' ');

        expect(wordCount(long)).toBe(301);
        expect(faqSchemaReport('home', [faq(1, 'home', 'Q?', { answer: long })]).longAnswers).toEqual(['Q?']);
    });
});
