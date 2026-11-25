/**
 * Shapes shared by every content screen (pages, collections, menus). They mirror the Laravel payloads in docs/API.md §7.
 */
export type PublishStatus = 'draft' | 'scheduled' | 'published';

export interface ContentLink {
    label: string;
    href: string;
}

export const emptyLink: ContentLink = { label: '', href: '' };

/** Media as the public API and block previews receive it. */
export interface PublicImage {
    id: number;
    url: string;
    width: number | null;
    height: number | null;
    alt: string;
    focal: { x: number; y: number };
    sources: Record<string, { width: number; url: string }[]>;
}

/** Tokens the website replaces from Clinic settings (labels and links). */
export const CONTENT_TOKENS_HELP = 'Use {phone} for the clinic’s number and tel:clinic to call it; {hours} and {email} work too.';

/** Where the public website lives (`<meta name="site-url">`, from FRONTEND_URL). */
export function siteUrl(path = '/'): string | null {
    const base = document.querySelector<HTMLMetaElement>('meta[name="site-url"]')?.content;

    return base ? base.replace(/\/+$/, '') + path : null;
}

/**
 * Splits "When depression won’t lift, *explore a new way forward.*" into
 * plain and accent runs, the way the website renders headlines.
 */
export function accentRuns(text: string): { text: string; accent: boolean }[] {
    return text
        .split(/(\*[^*]+\*)/g)
        .filter((part) => part !== '')
        .map((part) => (part.startsWith('*') && part.endsWith('*') && part.length > 2 ? { text: part.slice(1, -1), accent: true } : { text: part, accent: false }));
}
