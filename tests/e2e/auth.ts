import { expect, type Page } from '@playwright/test';
import { totp } from './totp';

/** Sign-in helpers shared by the e2e specs (DemoUserSeeder accounts). */
const DEMO_PASSWORD = 'Uptms@2026';
const secrets = new Map<string, string>();
// The server takes codes from one step either side of now, each step once per account.
const lastStep = new Map<string, number>();
const now = () => Math.floor(Date.now() / 30_000);

/** A code for a step this account hasn't used, waiting for the clock when all usable steps are spent. */
async function freshCode(page: Page, email: string, secret: string): Promise<string> {
    const step = Math.max(now(), (lastStep.get(email) ?? -Infinity) + 1);
    while (step > now() + 1) await page.waitForTimeout(1_000);
    lastStep.set(email, step);

    return totp(secret, step - now());
}

export async function signIn(page: Page, email: string) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password').fill(DEMO_PASSWORD);
    await page.getByRole('button', { name: 'Sign in' }).click();

    const secret = secrets.get(email);
    if (secret) {
        await page.getByLabel('Authentication code').fill(await freshCode(page, email, secret));
        await page.getByRole('button', { name: 'Verify and sign in' }).click();
    }
}

export async function setUpTwoFactor(page: Page, email: string) {
    await expect(page.getByRole('heading', { name: 'Set up two-factor authentication' })).toBeVisible();
    await page.getByRole('button', { name: 'Start set-up' }).click();
    await expect(page.getByRole('img', { name: 'QR code for your authenticator app' })).toBeVisible();
    await page.getByText('Can’t scan? Enter the key by hand').click();
    const secret = (await page.locator('details p').innerText()).replace(/\s+/g, '');
    secrets.set(email, secret);

    await page.getByLabel('6-digit code').fill(await freshCode(page, email, secret));
    await page.getByRole('button', { name: 'Turn on two-factor' }).click();
    await expect(page.getByRole('list', { name: 'Recovery codes' }).getByRole('listitem')).toHaveCount(8);
    await page.getByLabel('I’ve saved these codes').check();
    await page.getByRole('button', { name: 'Continue to the admin' }).click();
}

/** Signed in at the dashboard whether or not an earlier spec already set up this account's second factor. */
export async function signInWithTwoFactor(page: Page, email: string) {
    await signIn(page, email);
    const setUp = page.getByRole('heading', { name: 'Set up two-factor authentication' });
    await expect(setUp.or(page.getByRole('navigation', { name: 'Admin' }))).toBeVisible();
    if (await setUp.isVisible()) await setUpTwoFactor(page, email);
}
