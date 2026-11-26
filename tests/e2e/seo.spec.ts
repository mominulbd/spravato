import { expect, test, type Page, type Request } from '@playwright/test';
import { signInWithTwoFactor } from './auth';

/*
 * Phase 3 exit checks (docs/DEVELOPMENT-PLAN.md): the Spravato page carries
 * its metadata and JSON-LD, the sitemap lists it, robots.txt serves the CMS
 * rules, nothing is indexed before launch (the pages' robots meta: the e2e
 * site runs without ALLOW_INDEXING), and the page makes no
 * third-party request on load: a video's host is contacted only once the
 * visitor presses play.
 */
const site = process.env.E2E_SITE_URL ?? 'http://127.0.0.1:3124';

type JsonLdNode = { '@type'?: string | string[]; [key: string]: unknown };

test('/ carries its title, description, canonical, noindex and JSON-LD', async ({ page }) => {
    await page.goto(`${site}/`);

    expect((await page.title()).trim()).not.toBe('');
    expect((await page.locator('meta[name="description"]').getAttribute('content'))?.trim()).toBeTruthy();

    const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
    expect(canonical, 'a canonical link').toBeTruthy();
    expect(new URL(canonical ?? '', site).pathname).toBe('/');

    // Pre-launch: noindex until ALLOW_INDEXING=true.
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);

    const scripts = await page.locator('script[type="application/ld+json"]').allTextContents();
    expect(scripts.length, 'a JSON-LD script').toBeGreaterThan(0);

    const nodes: JsonLdNode[] = scripts.flatMap((text) => {
        const doc = JSON.parse(text) as JsonLdNode & { '@graph'?: JsonLdNode[] };

        return doc['@graph'] ?? [doc];
    });
    const types = new Set(nodes.flatMap((node) => [node['@type'] ?? []].flat()));

    expect([...types]).toEqual(expect.arrayContaining(['MedicalClinic', 'MedicalWebPage', 'FAQPage']));
    expect(types.has('Physician') || types.has('Person'), `a Physician or Person in ${[...types].join(', ')}`).toBe(true);
});

test('the sitemap lists /', async ({ request }) => {
    const sitemap = await request.get(`${site}/sitemap.xml`);
    expect(sitemap.ok()).toBe(true);
    const locs = [...(await sitemap.text()).matchAll(/<loc>\s*([^<\s]+)\s*<\/loc>/g)].map((match) => match[1] ?? '');
    expect(locs.map((loc) => new URL(loc).pathname)).toContain('/');
});

/*
 * robots.txt is the text staff edit in SEO › Sitemap & robots, plus a Sitemap
 * line (pre-launch noindex is the pages' robots meta, asserted above). An edit
 * saved in the admin reaches /robots.txt without a redeploy.
 */
test('robots.txt serves the CMS rules and a Sitemap line, and an admin edit reaches it', async ({ page, request }) => {
    const robots = await request.get(`${site}/robots.txt`);
    expect(robots.ok()).toBe(true);
    expect(robots.headers()['content-type']).toContain('text/plain');
    const seeded = await robots.text();
    // SeoSettings::DEFAULT_ROBOTS
    expect(seeded).toMatch(/^User-agent: \*$/m);
    expect(seeded).toMatch(/^Disallow: \/admin$/m);
    expect(seeded).toMatch(/^Disallow: \/api\/$/m);
    expect(seeded).toContain(`Sitemap: ${site}/sitemap.xml`);

    await signInWithTwoFactor(page, 'admin@uptms.com');
    await page.getByRole('navigation', { name: 'Admin' }).getByRole('link', { name: 'Sitemap & robots' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Sitemap & robots.');

    const rule = `Disallow: /e2e-${Date.now()}`;
    const editor = page.getByRole('textbox', { name: 'robots.txt' });
    await editor.fill(`User-agent: *\nDisallow: /admin\nDisallow: /api/\n${rule}\n`);
    await page.getByRole('button', { name: 'Test', exact: true }).click();
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.getByText('robots.txt saved')).toBeVisible();

    await expect(async () => {
        const text = await (await request.get(`${site}/robots.txt`)).text();
        expect(text).toContain(rule);
        expect(text).toContain(`Sitemap: ${site}/sitemap.xml`);
    }).toPass({ timeout: 20_000 });
});

/**
 * Requests to hosts other than the site's. In production Laravel's /storage
 * shares the site's origin; the e2e run serves Laravel on its own port of the
 * same host (playwright.config.ts), so the host, not the port, is compared.
 */
function thirdPartyRecorder(page: Page): Request[] {
    const firstParty = new Set([new URL(site).hostname, 'localhost', '127.0.0.1']);
    const seen: Request[] = [];

    page.on('request', (request) => {
        const url = new URL(request.url());
        if ((url.protocol === 'http:' || url.protocol === 'https:') && !firstParty.has(url.hostname)) seen.push(request);
    });

    return seen;
}

/** Loads / and scrolls to the end, so lazy images and late scripts have their chance to load too. */
async function loadWholePage(page: Page) {
    await page.goto(`${site}/`, { waitUntil: 'load' });
    await page.evaluate(async () => {
        for (let y = 0; y < document.body.scrollHeight; y += 600) {
            window.scrollTo(0, y);
            await new Promise((resolve) => setTimeout(resolve, 50));
        }
    });
    await page.waitForLoadState('networkidle');
}

test('/ makes no third-party request on load; a video host is contacted only after play', async ({ page }) => {
    const videoUrl = 'https://www.youtube.com/watch?v=M7lc1UVf-VE';

    // No video address is seeded (the cards show their cover only), so give the first one an address.
    await page.goto(`${site}/`);
    const videos = page.locator('section', { has: page.getByRole('heading', { name: 'A clearer picture, before your visit.' }) });
    await expect(videos.getByRole('article')).toHaveCount(2);
    await expect(videos.getByRole('article').first().locator('img')).toBeAttached();

    if ((await videos.getByRole('button', { name: /^Play video:/ }).count()) === 0) {
        await signInWithTwoFactor(page, 'cathy@uptms.test');
        await page.getByRole('navigation', { name: 'Admin' }).getByRole('link', { name: 'Pages' }).click();
        await page.getByRole('link', { name: 'Spravato', exact: true }).click();
        await page.getByRole('region', { name: 'Sections' }).getByRole('button', { name: 'Videos', exact: true }).click();
        await page.getByRole('textbox', { name: /^Video address/ }).first().fill(videoUrl);
        await page.getByRole('button', { name: 'Publish changes' }).click();
        await expect(page.getByText('The website updates within seconds.')).toBeVisible();

        await expect(async () => {
            await page.goto(`${site}/`);
            await expect(videos.getByRole('button', { name: 'Play video: The treatment journey' })).toBeVisible({ timeout: 2_000 });
        }).toPass({ timeout: 20_000 });
    }

    const thirdParty = thirdPartyRecorder(page);
    await loadWholePage(page);
    expect(thirdParty.map((request) => request.url()), 'third-party requests on load').toEqual([]);

    // The facade: a cover and a named button, no iframe until it is pressed.
    const play = videos.getByRole('button', { name: /^Play video:/ }).first();
    await expect(videos.locator('iframe')).toHaveCount(0);
    await play.focus();
    await page.keyboard.press('Enter');

    const player = videos.locator('iframe').first();
    await expect(player).toHaveAttribute('src', /^https:\/\/www\.youtube-nocookie\.com\/embed\/M7lc1UVf-VE\?/);
    await expect(player).toHaveAttribute('title', /^Video: /);
    await expect(player).toHaveAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    await expect(player).toBeFocused();
    await expect.poll(() => thirdParty.some((request) => new URL(request.url()).hostname === 'www.youtube-nocookie.com')).toBe(true);
});
