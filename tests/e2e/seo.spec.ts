import { expect, test } from '@playwright/test';
import { signInWithTwoFactor } from './auth';

const site = process.env.E2E_SITE_URL ?? 'http://127.0.0.1:3124';

/* Figma 22: robots.txt is tested before it is saved, then saved; sitemap groups switch off and on. */
test('an Admin tests and edits robots.txt and switches sitemap groups', async ({ page }) => {
    await signInWithTwoFactor(page, 'admin@uptms.com');

    await page.getByRole('navigation', { name: 'Admin' }).getByRole('link', { name: 'Sitemap & robots' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Sitemap & robots.');
    if (process.env.SHOTS) await page.screenshot({ path: `${process.env.SHOTS}/sitemap-1280.png`, fullPage: true });

    await page.getByRole('button', { name: 'Test', exact: true }).click();
    await expect(page.getByText('Blocked by line 2 · Disallow: /admin')).toBeVisible();

    // An unsaved rule is tested as typed.
    const editor = page.getByRole('textbox', { name: 'robots.txt' });
    await editor.fill('User-agent: *\nDisallow: /admin\nAllow: /admin/pages\n');
    await page.getByRole('button', { name: 'Test', exact: true }).click();
    await expect(page.getByText('Allowed by line 3 · Allow: /admin/pages')).toBeVisible();

    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.getByText('robots.txt saved')).toBeVisible();
    await expect(page.getByText(/Last changed .* by Admin/)).toBeVisible();

    // The switch is controlled: it flips when the save starts, so click and wait rather than uncheck().
    const lastmod = page.getByRole('switch', { name: 'Include last-modified dates' });
    await lastmod.click({ force: true });
    await expect(lastmod).not.toBeChecked();
    await expect(lastmod).toBeEnabled();
    await lastmod.click({ force: true });
    await expect(lastmod).toBeChecked();
    await expect(lastmod).toBeEnabled();

    if (process.env.SHOTS) {
        await page.setViewportSize({ width: 1440, height: 1065 });
        await page.screenshot({ path: `${process.env.SHOTS}/sitemap-1440.png`, fullPage: true });
        await page.setViewportSize({ width: 390, height: 844 });
        await page.screenshot({ path: `${process.env.SHOTS}/sitemap-390.png`, fullPage: true });
    }
});

/* Figma 19 and 03: a first audit run from the overview fills the score, the issue list and the dashboard. */
test('an Admin runs the site audit and sees the score on the overview and the dashboard', async ({ page }) => {
    await signInWithTwoFactor(page, 'admin@uptms.com');

    await page.getByRole('navigation', { name: 'Admin' }).getByRole('link', { name: 'SEO overview' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Search health.');
    await expect(page.getByRole('heading', { name: /No audit has run yet/ })).toBeVisible();

    await page.getByRole('button', { name: 'Run audit now' }).click();
    const score = page.getByRole('img', { name: /Site health \d+ out of 100/ });
    await expect(score).toBeVisible();
    await expect(page.getByText(/^Site health · checked today/)).toBeVisible();
    const findings = page.getByRole('list', { name: 'Audit findings' });
    await expect(findings.getByText('No duplicate titles or descriptions')).toBeVisible();
    await expect(findings.getByText('Clinical reviews are up to date')).toBeVisible();
    await expect(page.getByText('Connect Google Search Console', { exact: false })).toBeVisible();

    if (process.env.SHOTS) {
        await page.setViewportSize({ width: 1440, height: 1113 });
        await page.screenshot({ path: `${process.env.SHOTS}/seo-overview-1440.png`, fullPage: true });
        await page.setViewportSize({ width: 390, height: 844 });
        await page.screenshot({ path: `${process.env.SHOTS}/seo-overview-390.png`, fullPage: true });
        await page.setViewportSize({ width: 1280, height: 800 });
    }

    const label = (await score.getAttribute('aria-label')) ?? '';
    const value = label.match(/\d+/)?.[0] ?? '';
    await page.getByRole('navigation', { name: 'Admin' }).getByRole('link', { name: 'Dashboard' }).click();
    await expect(page.getByRole('heading', { name: 'SEO to-do' })).toBeVisible();
    await expect(page.getByRole('img', { name: `Score ${value} out of 100` })).toBeVisible();

    if (process.env.SHOTS) {
        await page.setViewportSize({ width: 1440, height: 1878 });
        await page.screenshot({ path: `${process.env.SHOTS}/dashboard-1440.png`, fullPage: true });
    }
});

/*
 * Phase 4 exit check (docs/ROADMAP.md): a renamed live page redirects
 * automatically. The old address answers 301 on the live site and the
 * sitemap lists the new one. Runs last: it moves the seeded /tms page.
 */
test('an Admin renames a live page, the old URL answers 301 and the sitemap lists the new one', async ({ page, request }) => {
    await signInWithTwoFactor(page, 'admin@uptms.com');
    const nav = page.getByRole('navigation', { name: 'Admin' });

    await nav.getByRole('link', { name: 'Pages' }).click();
    await page.getByRole('link', { name: 'TMS therapy', exact: true }).click();
    await page.getByRole('tab', { name: 'Settings' }).click();
    await page.getByLabel('Address').fill('tms-therapy');
    await expect(page.getByRole('note')).toContainText('/tms redirects there (301)');
    await page.getByRole('button', { name: 'Save settings' }).click();
    await expect(page.getByText('Page settings saved')).toBeVisible();

    // The move left an automatic 301 behind (Figma 20).
    await nav.getByRole('link', { name: 'Redirects' }).click();
    const rule = page.getByRole('row').filter({ hasText: '/tms-therapy' });
    await expect(rule.getByText('/tms', { exact: true })).toBeVisible();
    await expect(rule.getByText('Slug change')).toBeVisible();

    // The site's proxy re-reads its routing table at most every 30 s.
    await expect(async () => {
        const old = await request.get(`${site}/tms`, { maxRedirects: 0 });
        expect(old.status()).toBe(301);
        expect(new URL(old.headers()['location'] ?? '', site).pathname).toBe('/tms-therapy');
    }).toPass({ timeout: 45_000 });

    // Not prerendered at build time: the page renders on its first visit (streamed into the App Shell).
    await page.goto(`${site}/tms`);
    await expect(page).toHaveURL(`${site}/tms-therapy`);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

    await expect(async () => {
        const sitemap = await (await request.get(`${site}/sitemap.xml`)).text();
        expect(sitemap).toContain('/tms-therapy</loc>');
        expect(sitemap).not.toContain('/tms</loc>');
    }).toPass({ timeout: 20_000 });
});
