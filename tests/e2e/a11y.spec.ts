import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

/*
 * Phase 4 accessibility and responsive checks (docs/DEVELOPMENT-PLAN.md):
 * axe (WCAG 2.1 A + AA) on the Spravato page at every design width, with the
 * consultation dialog closed and open, and on the admin sign-in; and no
 * horizontal scroll or overflowing header, hero or footer at any width.
 */
const site = process.env.E2E_SITE_URL ?? 'http://127.0.0.1:3124';

const WIDTHS = [390, 768, 1024, 1440, 1920] as const;
// WCAG 2.1 A + AA, plus 2.2 AA (target size, as Lighthouse checks).
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

async function audit(page: Page, include?: string) {
    let builder = new AxeBuilder({ page }).withTags(TAGS);
    if (include) builder = builder.include(include);
    const { violations, incomplete } = await builder.analyze();

    // Checks axe could not decide (text over images, for one) need a person:
    // AXE_VERBOSE=1 lists them.
    if (process.env.AXE_VERBOSE) {
        for (const item of incomplete) console.log(`[axe incomplete] ${item.id}: ${item.nodes.map((n) => n.target.join(' ')).join(', ')}`);
    }

    // A readable failure: rule, impact and the offending nodes.
    const summary = violations.map((v) => `${v.id} (${v.impact}): ${v.help}\n${v.nodes.map((n) => `  ${n.target.join(' ')} — ${n.failureSummary?.split('\n').slice(1).join(' ')}`).join('\n')}`);
    expect(summary, summary.join('\n\n')).toEqual([]);
}

/** Lazy images and fonts settle before measuring contrast and layout. */
async function settle(page: Page) {
    await page.evaluate(async () => {
        await document.fonts.ready;
        window.scrollTo(0, document.body.scrollHeight);
        await new Promise((resolve) => setTimeout(resolve, 200));
        window.scrollTo(0, 0);
    });
}

for (const width of WIDTHS) {
    test.describe(`at ${width}px`, () => {
        test.use({ viewport: { width, height: 900 } });

        test('the Spravato page has no axe violations', async ({ page }) => {
            await page.goto(`${site}/`);
            await settle(page);
            await audit(page);
        });

        test('the open consultation dialog has no axe violations', async ({ page }) => {
            await page.goto(`${site}/`);
            await settle(page);
            await page.getByRole('banner').getByRole('link', { name: 'Request a consultation' }).click();
            const dialog = page.getByRole('dialog', { name: 'Request a SPRAVATO® consultation' });
            await expect(dialog).toBeVisible();
            await audit(page, '#consultation');

            // The dialog fits the width too.
            const fits = await page.locator('#consultation').evaluate((el) => el.scrollWidth <= el.clientWidth);
            expect(fits).toBe(true);

            // With every field in error, too.
            await dialog.getByRole('button', { name: 'Request consultation' }).click();
            await expect(dialog.getByLabel('First name')).toBeFocused();
            await audit(page, '#consultation');
        });

        test('nothing scrolls sideways or overflows the page', async ({ page }) => {
            await page.goto(`${site}/`);
            await settle(page);

            const layout = await page.evaluate(() => {
                const viewport = document.documentElement.clientWidth;
                // Every element wider than the viewport or poking out past its edge.
                const offenders = [...document.querySelectorAll<HTMLElement>('body *')]
                    .filter((el) => {
                        if (el.closest('dialog:not([open]), [aria-hidden="true"]')) return false;
                        const box = el.getBoundingClientRect();
                        return box.width > 0 && (box.right > viewport + 1 || box.left < -1);
                    })
                    .map((el) => `${el.tagName.toLowerCase()}.${el.className.toString().split(' ').slice(0, 3).join('.')}`);

                return { scrollWidth: document.documentElement.scrollWidth, innerWidth: window.innerWidth, viewport, offenders: offenders.slice(0, 10) };
            });

            expect(layout.scrollWidth, layout.offenders.join('\n')).toBeLessThanOrEqual(layout.innerWidth);
            expect(layout.offenders).toEqual([]);

            for (const region of [page.getByRole('banner'), page.locator('main section').first(), page.getByRole('contentinfo')]) {
                const box = await region.boundingBox();
                expect(box).not.toBeNull();
                expect(box!.x).toBeGreaterThanOrEqual(0);
                expect(box!.x + box!.width).toBeLessThanOrEqual(layout.viewport + 1);
            }
        });
    });
}

test('the admin sign-in has no axe violations', async ({ page }) => {
    await page.goto('/admin/login');
    await expect(page.getByLabel('Email address')).toBeVisible();
    await audit(page);
});

test('the admin sign-in fits a phone', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/admin/login');
    await expect(page.getByLabel('Email address')).toBeVisible();
    const { scrollWidth, innerWidth } = await page.evaluate(() => ({ scrollWidth: document.documentElement.scrollWidth, innerWidth: window.innerWidth }));
    expect(scrollWidth).toBeLessThanOrEqual(innerWidth);
});
