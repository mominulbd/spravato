import type { BlockData, BlockField } from './types';

/**
 * Fields that only matter for one choice of another field (the hero's photo
 * vs team card, inline vs collection steps). Hidden fields keep their
 * values, so switching back restores them.
 */
const RULES: Record<string, (field: BlockField, data: BlockData) => boolean> = {
    hero: (field, data) => {
        const team = data.aside === 'team';
        if (field.group === 'Photo') return !team;
        if (field.group === 'Team card') return team;

        return true;
    },
    steps: (field, data) => (field.name === 'steps' ? data.steps_source === 'inline' : true),
};

export function fieldVisible(blockType: string, field: BlockField, data: BlockData): boolean {
    return RULES[blockType]?.(field, data) ?? true;
}
