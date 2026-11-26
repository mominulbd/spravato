import { expect, test } from '@playwright/test';
import { signInWithTwoFactor } from './auth';

/*
 * The Spravato landing page is built from the CMS (docs/DEVELOPMENT-PLAN.md,
 * Phase 1), and an edit published in the admin appears on the live site
 * without a redeploy. The site is a production build (playwright.config.ts)
 * whose pages are cached and refreshed by Laravel's signed revalidation webhook.
 */
const site = process.env.E2E_SITE_URL ?? 'http://127.0.0.1:3124';

const HERO = 'Could Spravato® be a next step for you?';

test('every section of the Spravato page renders from the seeded CMS content', async ({ page }) => {
    await page.goto(site + '/');

    await expect(page.getByRole('heading', { level: 1 })).toHaveText(HERO);

    // One heading per seeded block, in order; *accents* are coloured, not shown.
    await expect(page.getByRole('main').getByRole('heading', { level: 2 })).toHaveText([
        'What is Spravato® (esketamine) treatment?',
        'Is Spravato right for you?',
        'Your visit, one step at a time.',
        'Make a plan that fits your care.',
        'A clearer picture, before your visit.',
        'People who take time to listen.',
        'Local care. Practical answers.',
        'A little clarity can help.',
        'Let’s talk about your next step.',
    ]);

    // Header: the logo and the consultation button only (no site navigation).
    await expect(page.getByRole('banner').getByRole('link', { name: /consultation/i })).toBeVisible();
    await expect(page.getByRole('banner').getByRole('navigation')).toHaveCount(0);
});

test('a Manager edits the Spravato hero, publishes, and the live site shows it', async ({ page }) => {
    const after = `A calmer path forward, ${Date.now()}.`;

    // The live page is served from the cache with the seeded headline.
    await page.goto(site + '/');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(HERO);

    await signInWithTwoFactor(page, 'cathy@uptms.test');

    await page.getByRole('navigation', { name: 'Admin' }).getByRole('link', { name: 'Pages' }).click();
    await page.getByRole('link', { name: 'Spravato', exact: true }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Spravato' })).toBeVisible();

    await page.getByRole('region', { name: 'Sections' }).getByRole('button', { name: 'Landing hero', exact: true }).click();
    await page.getByRole('textbox', { name: /^Headline/ }).fill(after);
    await page.getByRole('button', { name: 'Publish changes' }).click();
    await expect(page.getByText('The website updates within seconds.')).toBeVisible();

    // The webhook has expired the page's cache tag: the next visit is fresh.
    await expect(async () => {
        await page.goto(site + '/');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(after, { timeout: 2_000 });
    }).toPass({ timeout: 20_000 });
});
