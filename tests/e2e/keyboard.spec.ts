import { expect, type Page, test } from '@playwright/test';

/*
 * Phase 4 keyboard pass (docs/DEVELOPMENT-PLAN.md): the consultation dialog
 * and the FAQ work from the keyboard alone.
 */
const site = process.env.E2E_SITE_URL ?? 'http://127.0.0.1:3124';

/** Tabs until `target` has focus, failing after `limit` presses. */
async function tabTo(page: Page, target: ReturnType<Page['locator']>, limit = 60) {
    for (let i = 0; i < limit; i++) {
        await page.keyboard.press('Tab');
        if (await target.evaluate((el) => el === document.activeElement)) return;
    }
    throw new Error('Focus never reached the target');
}

test('the consultation dialog opens, traps focus and closes from the keyboard', async ({ page }) => {
    await page.goto(`${site}/`);

    const opener = page.getByRole('main').getByRole('link', { name: /request a consultation/i }).first();
    await tabTo(page, opener);
    await page.keyboard.press('Enter');

    const dialog = page.getByRole('dialog', { name: 'Request a SPRAVATO® consultation' });
    await expect(dialog).toBeVisible();

    // Focus lands inside the dialog, and Tab never leaves it.
    const inside = () => page.evaluate(() => document.getElementById('consultation')?.contains(document.activeElement) ?? false);
    await expect.poll(inside).toBe(true);

    const seen = new Set<string>();
    for (let i = 0; i < 40; i++) {
        await page.keyboard.press('Tab');
        // Chrome passes through the browser UI between the last and first
        // element of a modal dialog; the page behind never gets focus.
        const where = await page.evaluate(() => {
            const el = document.activeElement;
            if (!el || el === document.body) return 'outside-document';
            return document.getElementById('consultation')?.contains(el) ? `${el.tagName}#${el.id}:${el.textContent?.slice(0, 20)}` : `page:${el.tagName}`;
        });
        expect(where).not.toMatch(/^page:/);
        seen.add(where);
    }
    expect([...seen].some((s) => s.startsWith('BUTTON#:Request consultation'))).toBe(true);

    // Shift+Tab goes back the other way, still inside.
    await dialog.getByLabel('First name').focus();
    await page.keyboard.press('Shift+Tab');
    await expect.poll(inside).toBe(true);

    // Submitting empty focuses the first invalid field; each error is linked.
    await dialog.getByRole('button', { name: 'Request consultation' }).focus();
    await page.keyboard.press('Enter');
    const first = dialog.getByLabel('First name');
    await expect(first).toBeFocused();
    await expect(first).toHaveAttribute('aria-invalid', 'true');
    await expect(first).toHaveAccessibleDescription('Enter your first name so the team knows who to ask for.');
    await expect(dialog.getByLabel('I understand this form is not an emergency service.')).toHaveAccessibleDescription(
        'Please confirm you understand this form is not an emergency service.',
    );

    // The checkbox toggles with Space.
    const ack = dialog.getByLabel('I understand this form is not an emergency service.');
    await ack.focus();
    await page.keyboard.press('Space');
    await expect(ack).toBeChecked();

    // Esc closes it and focus returns to the opener.
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(opener).toBeFocused();

    // The close button works from the keyboard too.
    await page.keyboard.press('Enter');
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Close' }).focus();
    await page.keyboard.press('Enter');
    await expect(dialog).toBeHidden();
    await expect(opener).toBeFocused();
});

test('FAQ answers open and close from the keyboard', async ({ page }) => {
    await page.goto(`${site}/`);

    const questions = page.locator('[data-faq]').getByRole('button');
    const count = await questions.count();
    expect(count).toBeGreaterThan(1);

    // The first answer starts open, the rest closed.
    const first = questions.nth(0);
    const second = questions.nth(1);
    await expect(first).toHaveAttribute('aria-expanded', 'true');
    await expect(second).toHaveAttribute('aria-expanded', 'false');

    const panel = async (button: typeof first) => page.locator(`#${await button.getAttribute('aria-controls')}`);
    await expect(await panel(first)).toBeVisible();
    await expect(await panel(second)).toBeHidden();

    // Enter opens, Space closes.
    await tabTo(page, second);
    await page.keyboard.press('Enter');
    await expect(second).toHaveAttribute('aria-expanded', 'true');
    await expect(await panel(second)).toBeVisible();

    await page.keyboard.press('Space');
    await expect(second).toHaveAttribute('aria-expanded', 'false');
    await expect(await panel(second)).toBeHidden();

    // Each open answer is a region named by its question.
    await page.keyboard.press('Space');
    const question = (await second.locator('span').first().textContent())!.trim();
    await expect(page.getByRole('region', { name: question })).toBeVisible();
});

test('the focus ring stays visible on dark surfaces', async ({ page }) => {
    await page.goto(`${site}/`);

    const ring = (locator: ReturnType<Page['locator']>) => locator.evaluate((el) => getComputedStyle(el).outlineColor);

    // The phone link in the navy top bar: sky (#c8e1f9), not royal.
    const call = page.getByRole('banner').getByRole('link', { name: /^Call / });
    await tabTo(page, call);
    await expect.poll(() => ring(call)).toBe('rgb(200, 225, 249)');

    // The consultation button on white: royal (#183cdc).
    const request = page.getByRole('banner').getByRole('link', { name: 'Request a consultation' });
    await tabTo(page, request);
    // Buttons ease their colours (transition-colors), the ring included.
    await expect.poll(() => ring(request)).toBe('rgb(24, 60, 220)');
});
