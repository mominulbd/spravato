import { expect, test } from '@playwright/test';
import { setUpTwoFactor, signIn } from './auth';

/*
 * Phase 1 exit check (docs/ROADMAP.md): staff sign in with 2FA, roles are
 * enforced by the API and reflected in the UI. Runs against the seeded demo
 * accounts (DemoUserSeeder), password "Uptms@2026", 2FA not yet set up.
 */
test.describe.configure({ mode: 'serial' });

test('a Super Admin signs in, sets up 2FA and manages the team', async ({ page }) => {
    await signIn(page, 'admin@uptms.com');
    await setUpTwoFactor(page, 'admin@uptms.com');

    await expect(page.getByRole('heading', { name: /Good (morning|afternoon|evening)/ })).toBeVisible();
    await page.getByRole('navigation', { name: 'Admin' }).getByRole('link', { name: 'Users & roles' }).click();
    await expect(page.getByRole('heading', { name: 'Users & roles.' })).toBeVisible();
    await expect(page.getByRole('cell', { name: 'John Kiongo john@uptms.test' })).toBeVisible();

    await page.getByRole('button', { name: 'Invite user' }).click();
    const dialog = page.getByRole('dialog', { name: 'Invite a team member' });
    await dialog.getByLabel('Full name').fill('Dana Osei');
    await dialog.getByLabel('Email address').fill('dana@uptms.test');
    await dialog.getByLabel('Role').selectOption({ label: 'Staff' });
    await dialog.getByRole('button', { name: 'Send invitation' }).click();
    await expect(page.getByText('Invitation sent', { exact: true })).toBeVisible();

    await page.getByRole('link', { name: /Pending invitations/ }).click();
    await expect(page.getByRole('cell', { name: 'Dana Osei dana@uptms.test' })).toBeVisible();

    await page.getByRole('link', { name: /Roles & permissions/ }).click();
    await expect(page.getByRole('rowheader', { name: /View requests/ })).toBeVisible();
    await expect(page.getByText('Assigned only').first()).toBeVisible();

    await page.getByRole('navigation', { name: 'Admin' }).getByRole('link', { name: 'Audit log' }).click();
    await expect(page.getByRole('button', { name: 'Turned on two-factor' }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Invited user' }).first()).toBeVisible();
});

test('Staff cannot reach users or the audit log', async ({ page }) => {
    await signIn(page, 'john@uptms.test');
    await setUpTwoFactor(page, 'john@uptms.test');

    const nav = page.getByRole('navigation', { name: 'Admin' });
    await expect(nav.getByRole('link', { name: 'Dashboard' })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Users & roles' })).toHaveCount(0);
    await expect(nav.getByRole('link', { name: 'Audit log' })).toHaveCount(0);

    await page.goto('/admin/audit-log');
    await expect(page.getByRole('heading', { name: 'You don’t have access to the audit log' })).toBeVisible();

    const status = await page.evaluate(async () => (await fetch('/api/v1/admin/users', { headers: { Accept: 'application/json' } })).status);
    expect(status).toBe(403);
});

test('a returning user signs in with a code, and signing out ends the session', async ({ page }) => {
    await signIn(page, 'admin@uptms.com');
    await expect(page.getByRole('heading', { name: /Good (morning|afternoon|evening)/ })).toBeVisible();

    await page.getByRole('complementary').getByRole('button', { name: 'Sign out' }).click();
    await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();

    await page.goto('/admin/users');
    await expect(page).toHaveURL(/\/admin\/login\?next=%2Fusers/);
});
