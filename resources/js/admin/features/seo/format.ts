import type { CheckStatus } from './types';

/** "Great" ≥ 80, "Good" ≥ 65, "OK" ≥ 50, else "Needs work" (Figma 08 score card). */
export function scoreLabel(score: number): string {
    if (score >= 80) return 'Great';
    if (score >= 65) return 'Good';
    if (score >= 50) return 'OK';

    return 'Needs work';
}

export function scoreTone(score: number | null): 'success' | 'warning' | 'danger' | 'neutral' {
    if (score === null) return 'neutral';
    if (score >= 80) return 'success';
    if (score >= 50) return 'warning';

    return 'danger';
}

export const STATUS_DOT: Record<CheckStatus, string> = {
    good: 'bg-success',
    improve: 'bg-warning',
    problem: 'bg-danger',
};

/** Width bar for titles and descriptions: green up to the limit, amber when short, red past it. */
export function lengthTone(px: number, max: number, minChars: number, chars: number): { tone: 'good' | 'short' | 'long'; label: string } {
    if (chars === 0) return { tone: 'short', label: 'Empty' };
    if (px > max) return { tone: 'long', label: 'Too long — Google will cut it' };
    if (chars < minChars) return { tone: 'short', label: 'Could be longer' };

    return { tone: 'good', label: 'Good length' };
}

/** "uptms.com › conditions › ocd" like Google's result breadcrumb. */
export function displayUrl(url: string): string {
    try {
        const parsed = new URL(url);
        const parts = parsed.pathname.split('/').filter(Boolean);

        return [parsed.host.replace(/^www\./, ''), ...parts].join(' › ');
    } catch {
        return url;
    }
}

/** Fill %title% %sep% %site% (and any extra variables) the way Laravel does, for instant previews. */
export function renderTemplate(template: string, vars: Record<string, string>, separator: string): string {
    return template
        .split(/\s*%sep%\s*/)
        .map((part) => part.replace(/%([a-z_]+)%/g, (_, key: string) => vars[key] ?? '').replace(/\s{2,}/g, ' ').trim())
        .filter((part) => part !== '')
        .join(` ${separator} `);
}

/** Colour of a robots.txt line in Figma 22's editor: blocks red, allows and sitemaps green. */
export function robotsLineTone(line: string): 'block' | 'allow' | 'comment' | 'plain' {
    const field = line.trim().toLowerCase();
    if (field.startsWith('#')) return 'comment';
    if (field.startsWith('disallow:')) return field.slice(9).trim() === '' ? 'allow' : 'block';
    if (field.startsWith('allow:') || field.startsWith('sitemap:')) return 'allow';

    return 'plain';
}

const NUMBER_WORDS = ['no', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];

/** Figma 19's hero: "uptms.com is in good shape — two fixes would help this week." */
export function auditHeadline(host: string, score: number, fixes: number): { lead: string; accent: string } {
    const lead = score >= 80 ? `${host} is in good shape` : score >= 60 ? `${host} is doing fine` : `${host} needs some attention`;
    const count = NUMBER_WORDS[fixes] ?? String(fixes);
    const accent = fixes === 0 ? 'nothing needs fixing this week.' : `${count} ${fixes === 1 ? 'fix' : 'fixes'} would help this week.`;

    return { lead, accent };
}

/** "today 6:00 AM", "yesterday 6:00 AM", "Oct 6, 6:00 AM". */
export function checkedAt(iso: string, now: Date = new Date()): string {
    const when = new Date(iso);
    const time = when.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    const day = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
    const days = Math.round((day(now) - day(when)) / 86_400_000);

    if (days === 0) return `today ${time}`;
    if (days === 1) return `yesterday ${time}`;

    return `${when.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}, ${time}`;
}

/** Core Web Vitals as Google shows them: "1.8 s", "120 ms", "0.02". */
export function vitalValue(metric: { key: string; value: number }): string {
    if (metric.key === 'cls') return metric.value.toFixed(2);
    if (metric.key === 'lcp') return `${(metric.value / 1000).toFixed(1)} s`;

    return `${Math.round(metric.value)} ms`;
}
