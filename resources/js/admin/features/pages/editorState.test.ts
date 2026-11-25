import { describe, expect, it } from 'vitest';
import { fingerprint, getPath, saveStatus, setPath, slugify, splitBlockErrors, toEditorBlocks, toPayload } from './editorState';
import { fieldVisible } from './fieldRules';
import type { BlockField } from './types';

describe('page editor state', () => {
    it('fingerprints content regardless of key order and ids', () => {
        const hero = { id: 1, type: 'hero', data: { headline: 'Hi', eyebrow: 'E' }, is_visible: true };
        const a = [hero];
        const b = [{ id: null, type: 'hero', data: { eyebrow: 'E', headline: 'Hi' }, is_visible: true }];

        expect(fingerprint(a)).toBe(fingerprint(b));
        expect(fingerprint(a)).not.toBe(fingerprint([{ ...hero, is_visible: false }]));
    });

    it('round-trips blocks through editor keys', () => {
        const blocks = [{ id: 4, type: 'faq', data: { group: 'home' }, is_visible: true }];
        const editor = toEditorBlocks(blocks);

        expect(editor[0]?.key).toBe('id4');
        expect(toPayload(editor)).toEqual(blocks);
    });

    it('reads and writes nested paths immutably', () => {
        const data = { facts: [{ value: 'Outpatient', label: 'x' }], primary: { label: 'Go', href: '/contact' } };
        const next = setPath(data, 'facts.0.value', 'Individualized');

        expect(getPath(next, 'facts.0.value')).toBe('Individualized');
        expect(getPath(data, 'facts.0.value')).toBe('Outpatient');
        expect(next.primary).toBe(data.primary);
        expect(getPath(setPath({}, 'steps.1.title', 'B'), 'steps.1.title')).toBe('B');
    });

    it('splits API validation errors by block and field path', () => {
        const split = splitBlockErrors({
            'blocks.2.data.primary.href': ['The link format is invalid.'],
            'blocks.0.data.headline': ['The headline field is required.'],
            'blocks.0.type': ['Unknown section type.'],
            lock_version: ['Expected 3.'],
        });

        expect(split.firstBlock).toBe(0);
        expect(split.byBlock[2]).toEqual({ 'primary.href': 'The link format is invalid.' });
        expect(split.byBlock[0]).toEqual({ headline: 'The headline field is required.', type: 'Unknown section type.' });
        expect(split.general).toEqual(['Expected 3.']);
    });

    it('describes the save state for the header', () => {
        const now = 100_000;

        expect(saveStatus({ dirty: true, saving: false, savedAt: now - 12_000, error: false }, now)).toBe('Unsaved changes · autosaved 12 s ago');
        expect(saveStatus({ dirty: false, saving: false, savedAt: now - 120_000, error: false }, now)).toBe('All changes saved · 2 min ago');
        expect(saveStatus({ dirty: true, saving: true, savedAt: null, error: false }, now)).toBe('Saving…');
        expect(saveStatus({ dirty: true, saving: false, savedAt: null, error: false, invalid: 2 }, now)).toBe('Not saved · 2 sections need attention');
    });

    it('makes clean addresses', () => {
        expect(slugify('Insurance & Coverage — 2026!')).toBe('insurance-and-coverage-2026');
        expect(slugify('Café Résumé')).toBe('cafe-resume');
    });

    it('shows the hero photo or team card fields, never both', () => {
        const photo: BlockField = { name: 'image', type: 'image', label: 'Photo', group: 'Photo' };
        const team: BlockField = { name: 'team_eyebrow', type: 'text', label: 'Eyebrow', group: 'Team card' };

        expect(fieldVisible('hero', photo, { aside: 'image' })).toBe(true);
        expect(fieldVisible('hero', team, { aside: 'image' })).toBe(false);
        expect(fieldVisible('hero', team, { aside: 'team' })).toBe(true);
        expect(fieldVisible('faq', team, {})).toBe(true);
    });
});
