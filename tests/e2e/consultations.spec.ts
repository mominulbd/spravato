import { expect, test } from '@playwright/test';
import { setUpTwoFactor, signIn } from './auth';

/*
 * Phase 3 exit check (docs/ROADMAP.md): a request submitted on the live
 * site appears in the admin inbox, opening it is logged, and staff can
 * mark it contacted.
 */
const site = process.env.E2E_SITE_URL ?? 'http://127.0.0.1:3124';

test('a request sent from the Contact page reaches the inbox and is marked contacted', async ({ page }) => {
    const name = 'Riley Morgan';

    // A self-check result links here with the topic chosen; it travels with the request.
    await page.goto(`${site}/contact?topic=insurance#consultation-form`);

    await page.getByRole('button', { name: 'Request My Free Phone Consultation' }).click();
    await expect(page.getByText('Enter your first name so the team knows who to ask for.')).toBeVisible();

    await page.getByLabel('First Name *').fill('Riley');
    await page.getByLabel('Last Name *').fill('Morgan');
    await page.getByLabel('Phone *').fill('(253) 555-0123');
    await page.getByLabel('Email *').fill('riley@example.com');
    await page.getByLabel('Insurance Name *').selectOption('Premera Blue Cross');
    await page.getByLabel('Have you tried therapy and antidepressants? *').selectOption('both');
    await page.getByLabel('Preferred method of communication *').selectOption('text');
    await page.getByRole('button', { name: 'Request My Free Phone Consultation' }).click();

    await expect(page.getByText('Thank you — your request is in.')).toBeVisible();
    const reference = (await page.getByText(/^CR-\d{4}-\d{4,}$/).innerText()).trim();

    await signIn(page, 'dev@agency.test');
    await setUpTwoFactor(page, 'dev@agency.test');

    // The dashboard counts it as waiting; the inbox shows it masked.
    await expect(page.getByRole('heading', { name: /waiting for a call back/ })).toBeVisible();
    await page.getByRole('navigation', { name: 'Admin' }).getByRole('link', { name: /Consultations/ }).click();
    await page.getByLabel('Search requests').fill(reference);
    const row = page.getByRole('row').filter({ hasText: reference });
    await expect(row).toContainText('Insurance & coverage');
    await expect(row).toContainText(`${name.split(' ')[0]} M. · (253) •••-0123`);
    await expect(row).not.toContainText('Morgan');

    // Opening it decrypts the details, and the view is logged.
    await row.getByRole('link', { name: 'Insurance & coverage' }).click();
    await expect(page.getByText(name, { exact: true })).toBeVisible();
    await expect(page.getByText('Premera Blue Cross')).toBeVisible();
    await expect(page.getByText('Text message')).toBeVisible();
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
