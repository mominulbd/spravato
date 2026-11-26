import type { BlockData, BlockField, EditorBlock, PageBlock } from './types';

/** Pure helpers behind the page editor, kept apart so they are easy to test. */

let counter = 0;

export function newKey(): string {
    counter += 1;

    return `b${Date.now().toString(36)}${counter}`;
}

export function toEditorBlocks(blocks: PageBlock[]): EditorBlock[] {
    return blocks.map((block) => ({ ...block, key: block.id !== null ? `id${block.id}` : newKey() }));
}

export function toPayload(blocks: EditorBlock[]): PageBlock[] {
    return blocks.map(({ id, type, data, is_visible }) => ({ id, type, data, is_visible }));
}

/** A stable fingerprint of the working copy: sorted keys, no React keys. */
export function fingerprint(blocks: PageBlock[]): string {
    const sort = (value: unknown): unknown => {
        if (Array.isArray(value)) return value.map(sort);
        if (value !== null && typeof value === 'object') {
            return Object.fromEntries(
                Object.keys(value as Record<string, unknown>)
                    .sort()
                    .map((key) => [key, sort((value as Record<string, unknown>)[key])]),
            );
        }

        return value;
    };

    return JSON.stringify(blocks.map((block) => [block.type, sort(block.data), block.is_visible]));
}

/** "First visit" → "first-visit" */
export function slugify(text: string): string {
    return text
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/&/g, ' and ')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 80);
}

/** Read `facts.0.value` out of block data. */
export function getPath(data: unknown, path: string): unknown {
    return path.split('.').reduce<unknown>((value, part) => {
        if (value === null || value === undefined) return undefined;
        if (Array.isArray(value)) return value[Number(part)];
        if (typeof value === 'object') return (value as Record<string, unknown>)[part];

        return undefined;
    }, data);
}

/** Immutable write of `facts.0.value`. */
export function setPath<T extends BlockData | unknown[]>(data: T, path: string, value: unknown): T {
    const [head, ...rest] = path.split('.');

    if (head === undefined) return data;

    const container: Record<string, unknown> | unknown[] = Array.isArray(data) ? [...data] : { ...(data as Record<string, unknown>) };
    const current = Array.isArray(container) ? container[Number(head)] : container[head];
    const next = rest.length === 0 ? value : setPath((current ?? (/^\d+$/.test(rest[0] ?? '') ? [] : {})) as BlockData, rest.join('.'), value);

    if (Array.isArray(container)) container[Number(head)] = next;
    else container[head] = next;

    return container as T;
}

export interface BlockErrors {
    /** block index → field path → first message */
    byBlock: Record<number, Record<string, string>>;
    firstBlock: number | null;
    /** Messages not tied to a block field. */
    general: string[];
}

/** Split a 422 `errors` object (`blocks.2.data.primary.href`) by block. */
export function splitBlockErrors(errors: Record<string, string[]>): BlockErrors {
    const result: BlockErrors = { byBlock: {}, firstBlock: null, general: [] };

    for (const [key, messages] of Object.entries(errors)) {
        const message = messages[0] ?? 'Check this field.';
        const match = key.match(/^blocks\.(\d+)\.(?:data\.)?(.+)$/);

        if (!match) {
            result.general.push(message);
            continue;
        }

        const index = Number(match[1]);
        const path = match[2] ?? '';
        result.byBlock[index] = { ...result.byBlock[index], [path]: message };
        result.firstBlock = result.firstBlock === null ? index : Math.min(result.firstBlock, index);
    }

    return result;
}

/** Empty value for one field, used when adding repeater rows. */
export function emptyValue(field: BlockField): unknown {
    switch (field.type) {
        case 'list':
        case 'repeater':
            return [];
        case 'link':
            return { label: '', href: '' };
        case 'toggle':
            return Boolean(field.default);
        case 'select':
        case 'source':
            return (field.default as string | undefined) ?? field.options?.[0]?.value ?? '';
        case 'image':
        case 'document':
            return null;
        default:
            return '';
    }
}

export function emptyRow(fields: BlockField[]): BlockData {
    return Object.fromEntries(fields.map((field) => [field.name, emptyValue(field)]));
}

/** A readable label for a block in the outline (its headline when set). */
export function blockSummary(block: PageBlock, fallback: string): string {
    const text = ['headline', 'rail', 'eyebrow', 'lead', 'title'].map((key) => block.data[key]).find((value): value is string => typeof value === 'string' && value.trim() !== '');

    return text ? text.replace(/\*/g, '') : fallback;
}

/** "Unsaved changes · autosaved 12 s ago" */
export function saveStatus(state: { dirty: boolean; saving: boolean; savedAt: number | null; error: boolean; invalid?: number }, now: number): string {
    if (state.saving) return 'Saving…';
    if (state.error) return 'Autosave failed — changes kept here';
    if (state.dirty && state.invalid) return `Not saved · ${state.invalid === 1 ? 'a section needs' : `${state.invalid} sections need`} attention`;

    const ago = state.savedAt === null ? null : Math.max(0, Math.round((now - state.savedAt) / 1000));
    const when = ago === null ? null : ago < 60 ? `${ago} s ago` : `${Math.round(ago / 60)} min ago`;

    if (state.dirty) return when ? `Unsaved changes · autosaved ${when}` : 'Unsaved changes';

    return when ? `All changes saved · ${when}` : 'All changes saved';
}
