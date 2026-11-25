import { expect, test } from '@playwright/test';
import { setUpTwoFactor, signIn } from './auth';

/*
 * Phase 2 exit check (docs/ROADMAP.md): an edit published in the admin
 * appears on the live website without a redeploy. The site is a
 * production build (playwright.config.ts) whose pages are cached and
 * refreshed by Laravel's signed revalidation webhook.
 */
const site = process.env.E2E_SITE_URL ?? 'http://127.0.0.1:3124';

test('a Manager edits the home hero, publishes, and the live site shows it', async ({ page }) => {
    const before = 'When depression won’t lift, explore a new way forward.';
    const after = `A calmer path forward, ${Date.now()}.`;

    // The live page is served from the cache with the seeded headline.
    await page.goto(site + '/');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(before);

    await signIn(page, 'cathy@uptms.test');
    await setUpTwoFactor(page, 'cathy@uptms.test');

    await page.getByRole('navigation', { name: 'Admin' }).getByRole('link', { name: 'Pages' }).click();
    await page.getByRole('link', { name: 'Home', exact: true }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Home' })).toBeVisible();

    const headline = page.getByLabel('Headline');
    await headline.fill(after);
    await page.getByRole('button', { name: 'Publish changes' }).click();
    await expect(page.getByText('The website updates within seconds.')).toBeVisible();

    // The webhook has expired the page's cache tag: the next visit is fresh.
    await expect(async () => {
        await page.goto(site + '/');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(after, { timeout: 2_000 });
    }).toPass({ timeout: 20_000 });
});
