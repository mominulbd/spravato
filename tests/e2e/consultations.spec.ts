import { expect, test } from '@playwright/test';
import { setUpTwoFactor, signIn } from './auth';

/*
 * Phase 2 exit check (docs/DEVELOPMENT-PLAN.md): a request sent from a
 * button on the Spravato page reaches the admin inbox with its topic and
 * the button it came from, opening it is logged, and staff can mark it
 * contacted.
 */
const site = process.env.E2E_SITE_URL ?? 'http://127.0.0.1:3124';

test('a request sent from the consultation dialog reaches the inbox and is marked contacted', async ({ page }) => {
    const name = 'Riley Morgan';

    await page.goto(`${site}/`);

    // The coverage button opens the dialog on its topic; nothing is sent yet.
    const opener = page.getByRole('link', { name: 'Ask about coverage & cost' });
    await opener.click();
    const dialog = page.getByRole('dialog', { name: 'Request a SPRAVATO® consultation' });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByLabel('What would you like to discuss?')).toHaveValue('insurance');

    // Esc closes it and focus returns to the button.
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(opener).toBeFocused();

    await opener.click();
    await dialog.getByRole('button', { name: 'Request consultation' }).click();
    await expect(dialog.getByText('Enter your first name so the team knows who to ask for.')).toBeVisible();
    await expect(dialog.getByText('Please confirm you understand this form is not an emergency service.')).toBeVisible();
    await expect(dialog.getByLabel('First name')).toBeFocused();

    await dialog.getByLabel('First name').fill('Riley');
    await dialog.getByLabel('Last name').fill('Morgan');
    await dialog.getByLabel('Phone').fill('(253) 555-0123');
    await dialog.getByLabel('Email').fill('riley@example.com');
    await dialog.getByLabel('Preferred follow-up').selectOption('either');
    await dialog.getByLabel('Patient status').selectOption('new');
    await dialog.getByLabel('Best time to reach you').selectOption('early_evening');
    await dialog.getByLabel('Insurance provider').fill('Premera Blue Cross');
    await dialog.getByLabel('I understand this form is not an emergency service.').check();
    await dialog.getByRole('button', { name: 'Request consultation' }).click();

    await expect(page.getByRole('dialog', { name: 'Thank you. Your request is in.' })).toBeVisible();
    const reference = (await page.getByText(/^CR-\d{4}-\d{4,}$/).innerText()).trim();
    await page.getByRole('button', { name: 'Done' }).click();
    await expect(opener).toBeFocused();

    await signIn(page, 'dev@agency.test');
    await setUpTwoFactor(page, 'dev@agency.test');

    // The dashboard counts it as waiting; the inbox shows it masked.
    await expect(page.getByRole('heading', { name: /waiting for a call back/ })).toBeVisible();
    await page.getByRole('navigation', { name: 'Admin' }).getByRole('link', { name: /Consultations/ }).click();
    await page.getByLabel('Search requests').fill(reference);
    const row = page.getByRole('row').filter({ hasText: reference });
    await expect(row).toContainText('Insurance & cost');
    await expect(row).toContainText(`${name.split(' ')[0]} M. · (253) •••-0123`);
    await expect(row).not.toContainText('Morgan');

    // Opening it decrypts the details, and the view is logged.
    await row.getByRole('link', { name: 'Insurance & cost' }).click();
    await expect(page.getByText(name, { exact: true })).toBeVisible();
    await expect(page.getByText('Premera Blue Cross')).toBeVisible();
    await expect(page.getByText('New patient')).toBeVisible();
    await expect(page.getByText('Early evening')).toBeVisible();
    await expect(page.getByText('Ask about coverage & cost', { exact: true })).toBeVisible();
    await expect(page.getByText('Acknowledged')).toBeVisible();
    await expect(page.getByText('Opened 1 time')).toBeVisible();

    await page.getByRole('button', { name: 'Mark as contacted' }).click();
    await expect(page.getByRole('radio', { name: 'Contacted' })).toBeChecked();
    await expect(page.getByText('Marked contacted by Web partner')).toBeVisible();

    await page.getByLabel('Add a note').fill('Left a voicemail.');
    await page.getByRole('button', { name: 'Save note' }).click();
    await expect(page.getByRole('listitem').filter({ hasText: 'Left a voicemail.' })).toBeVisible();

    // The audit log lists who opened and changed it.
    await page.getByRole('link', { name: 'see audit log' }).click();
    await expect(page.getByText('Opened request').first()).toBeVisible();
    await expect(page.getByText('Changed request status').first()).toBeVisible();
});
