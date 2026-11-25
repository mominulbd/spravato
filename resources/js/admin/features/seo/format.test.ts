import { describe, expect, it } from 'vitest';
import { auditHeadline, checkedAt, displayUrl, lengthTone, renderTemplate, robotsLineTone, scoreLabel, vitalValue } from './format';

describe('SEO formatting', () => {
    it('labels scores like the design', () => {
        expect(scoreLabel(94)).toBe('Great');
        expect(scoreLabel(70)).toBe('Good');
        expect(scoreLabel(40)).toBe('Needs work');
    });

    it('judges lengths by rendered width', () => {
        expect(lengthTone(532, 580, 30, 54).tone).toBe('good');
        expect(lengthTone(640, 580, 30, 70).tone).toBe('long');
        expect(lengthTone(100, 580, 30, 10).tone).toBe('short');
    });

    it('renders templates and drops empty parts with their separator', () => {
        expect(renderTemplate('%title% %sep% %category% %sep% %site%', { title: 'OCD guide', site: 'UPTMS' }, '|')).toBe('OCD guide | UPTMS');
        expect(displayUrl('https://uptms.com/conditions/ocd')).toBe('uptms.com › conditions › ocd');
    });

    it('colours robots.txt rules like the editor in the design', () => {
        expect(robotsLineTone('Disallow: /admin')).toBe('block');
        expect(robotsLineTone('Disallow:')).toBe('allow');
        expect(robotsLineTone('Allow: /api/v1/public/sitemap')).toBe('allow');
        expect(robotsLineTone('Sitemap: https://uptms.com/sitemap.xml')).toBe('allow');
        expect(robotsLineTone('User-agent: *')).toBe('plain');
        expect(robotsLineTone('# staff only')).toBe('comment');
    });
});

describe('site audit wording', () => {
    it('sums up the score and the fixes that would help', () => {
        expect(auditHeadline('uptms.com', 86, 2)).toEqual({ lead: 'uptms.com is in good shape', accent: 'two fixes would help this week.' });
        expect(auditHeadline('uptms.com', 64, 1).accent).toBe('one fix would help this week.');
        expect(auditHeadline('uptms.com', 40, 0)).toEqual({ lead: 'uptms.com needs some attention', accent: 'nothing needs fixing this week.' });
        expect(auditHeadline('uptms.com', 70, 14).accent).toBe('14 fixes would help this week.');
    });

    it('says when the audit ran relative to today', () => {
        const now = new Date(2026, 9, 8, 14, 0);

        expect(checkedAt(new Date(2026, 9, 8, 6, 0).toISOString(), now)).toBe('today 6:00 AM');
        expect(checkedAt(new Date(2026, 9, 7, 6, 0).toISOString(), now)).toBe('yesterday 6:00 AM');
        expect(checkedAt(new Date(2026, 9, 2, 18, 30).toISOString(), now)).toBe('Oct 2, 6:30 PM');
    });

    it('formats Core Web Vitals like Google', () => {
        expect(vitalValue({ key: 'lcp', value: 1840 })).toBe('1.8 s');
        expect(vitalValue({ key: 'inp', value: 120 })).toBe('120 ms');
        expect(vitalValue({ key: 'cls', value: 0.02 })).toBe('0.02');
    });
});
