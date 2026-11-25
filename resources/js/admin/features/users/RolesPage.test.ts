import { describe, expect, it } from 'vitest';
import { cellFor } from './RolesPage';
import type { Role } from './types';

const role = (slug: string, permissions: string[]): Role => ({
    id: 1, slug, name: slug, description: null, is_system: true, is_super_admin: slug === 'super_admin', requires_two_factor: true, permissions,
});

describe('the Figma 26 matrix cells', () => {
    it('marks staff as assigned-only and admins as not touching super admins', () => {
        const staff = role('staff', ['consultations.view', 'consultations.update']);
        const admin = role('admin', ['consultations.view', 'consultations.view_all', 'users.manage']);

        expect(cellFor(staff, 'consultations.view')).toBe('assigned-only');
        expect(cellFor(staff, 'consultations.export')).toBe('no');
        expect(cellFor(admin, 'consultations.view')).toBe('yes');
        expect(cellFor(admin, 'users.manage')).toBe('not-super-admins');
        expect(cellFor(role('super_admin', []), 'roles.manage')).toBe('yes');
    });
});
