import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

/*
 * Phase 4 performance (docs/DEVELOPMENT-PLAN.md): the hero image is the LCP
 * candidate and loads first, every other image waits until it is near the
 * viewport, every image reserves its box (CLS < 0.1), and LCP stays under
 * 2.5 s. LIGHTHOUSE=1 also runs scripts/lighthouse.mjs (mobile + desktop)
 * against this production build and writes docs/reports/lighthouse/.
 */
const site = process.env.E2E_SITE_URL ?? 'http://127.0.0.1:3124';

test('the hero image loads first; every other image is lazy and reserves its box', async ({ page }) => {
    await page.goto(`${site}/`);

    const hero = page.locator('#top img').first();
    await expect(hero).toHaveAttribute('fetchpriority', 'high');
    await expect(hero).toHaveAttribute('loading', 'eager');
    await expect(hero).toHaveAttribute('width', /^\d+$/);
    await expect(hero).toHaveAttribute('height', /^\d+$/);

    const images = await page.locator('main img, footer img, header img').evaluateAll((nodes) =>
        nodes.map((node) => {
            const img = node as HTMLImageElement;

            return {
                src: img.currentSrc || img.src,
                hero: img.closest('#top') !== null,
                header: img.closest('header') !== null,
                loading: img.getAttribute('loading'),
                fetchpriority: img.getAttribute('fetchpriority'),
                decoding: img.getAttribute('decoding'),
                sized: (img.hasAttribute('width') && img.hasAttribute('height')) || getComputedStyle(img).aspectRatio !== 'auto',
                sources: Array.from(img.closest('picture')?.querySelectorAll('source') ?? []).map((source) => source.type),
            };
        }),
    );

    for (const image of images) {
        expect(image.sized, `${image.src} has width/height or an aspect-ratio`).toBe(true);
        if (image.hero) continue;
        // The header logo is above the fold and loads eagerly.
        if (image.header) continue;
        expect(image.fetchpriority, `${image.src} is not high priority`).not.toBe('high');
        expect(image.loading, `${image.src} is lazy`).toBe('lazy');
        expect(image.decoding, `${image.src} decodes async`).toBe('async');
    }

    // Media-library images are served as AVIF and WebP.
    const media = images.filter((image) => image.sources.length > 0);
    expect(media.length, 'media-library images').toBeGreaterThan(0);
    for (const image of media) expect(image.sources).toEqual(['image/avif', 'image/webp']);
});

test('LCP is the hero, under 2.5 s, and the page does not shift (CLS < 0.1)', async ({ page }) => {
    for (const viewport of [
        { width: 390, height: 844 },
        { width: 1440, height: 900 },
    ]) {
        await page.setViewportSize(viewport);
        await page.goto(`${site}/`, { waitUntil: 'load' });
        // Let fonts swap and late content settle, then scroll through the page
        // so lazy images load (layout shifts from them would count).
        await page.evaluate(() => document.fonts.ready);
        await page.evaluate(async () => {
            for (let y = 0; y < document.body.scrollHeight; y += window.innerHeight / 2) {
                window.scrollTo(0, y);
                await new Promise((done) => setTimeout(done, 50));
            }
            window.scrollTo(0, 0);
        });

        const vitals = await page.evaluate(
            () =>
                new Promise<{ lcp: number; lcpTag: string; lcpInHero: boolean; cls: number }>((done) => {
                    let lcp = { time: 0, tag: '', inHero: false };
                    let cls = 0;
                    new PerformanceObserver((list) => {
                        for (const entry of list.getEntries() as (PerformanceEntry & { element?: Element | null })[]) {
                            lcp = { time: entry.startTime, tag: entry.element?.tagName ?? '', inHero: entry.element?.closest('#top') != null };
                        }
                    }).observe({ type: 'largest-contentful-paint', buffered: true });
                    new PerformanceObserver((list) => {
                        for (const entry of list.getEntries() as (PerformanceEntry & { value: number; hadRecentInput: boolean })[]) {
                            if (!entry.hadRecentInput) cls += entry.value;
                        }
                    }).observe({ type: 'layout-shift', buffered: true });
                    setTimeout(() => done({ lcp: lcp.time, lcpTag: lcp.tag, lcpInHero: lcp.inHero, cls }), 500);
                }),
        );

        expect(vitals.lcpInHero, `LCP element (${vitals.lcpTag}) is in the hero at ${viewport.width}px`).toBe(true);
        expect(vitals.lcp, `LCP at ${viewport.width}px`).toBeLessThan(2500);
        expect(vitals.cls, `CLS at ${viewport.width}px`).toBeLessThan(0.1);
    }
});

test('Lighthouse: performance ≥ 95, LCP < 2.5 s, CLS < 0.1 (mobile and desktop)', async () => {
    test.skip(process.env.LIGHTHOUSE !== '1', 'set LIGHTHOUSE=1 to run Lighthouse');
    test.setTimeout(600_000);

    // Throws (failing the test) when a budget is missed; the reports are written either way.
    execFileSync('node', ['scripts/lighthouse.mjs', '--url', `${site}/`, '--runs', process.env.LIGHTHOUSE_RUNS ?? '3', ...(process.env.LIGHTHOUSE_LABEL ? ['--label', process.env.LIGHTHOUSE_LABEL] : [])], { stdio: 'inherit' });
});
