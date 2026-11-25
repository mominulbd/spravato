import { defineConfig } from '@playwright/test';

/**
 * End-to-end tests against a real Laravel server and MySQL database
 * (spravato_e2e, reset and seeded before the run). Uses the
 * locally installed Chrome, so no browser download is needed.
 */
// E2E_PORT / E2E_SITE_PORT move the run off ports another project's e2e run is using.
const port = Number(process.env.E2E_PORT ?? 8124);
const sitePort = Number(process.env.E2E_SITE_PORT ?? 3124);
process.env.E2E_SITE_URL = `http://127.0.0.1:${sitePort}`;
const secret = 'e2e-revalidate-secret';
const env = `APP_ENV=local AUTH_TWO_FACTOR_LOGIN=true DB_DATABASE=spravato_e2e SESSION_DRIVER=database CACHE_STORE=database QUEUE_CONNECTION=sync MAIL_MAILER=array APP_URL=http://127.0.0.1:${port} SANCTUM_STATEFUL_DOMAINS=127.0.0.1:${port} FRONTEND_URL=http://127.0.0.1:${sitePort} FRONTEND_REVALIDATE_SECRET=${secret}`;
const siteEnv = `API_URL=http://127.0.0.1:${port}/api/v1 REVALIDATE_SECRET=${secret} LARAVEL_URL=http://127.0.0.1:${port} PROXY_LARAVEL=true NEXT_DIST_DIR=.next-e2e NEXT_PUBLIC_SITE_URL=http://127.0.0.1:${sitePort}`;

export default defineConfig({
    testDir: 'tests/e2e',
    fullyParallel: false,
    workers: 1,
    timeout: 60_000,
    reporter: [['list']],
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        channel: 'chrome',
        headless: true,
        viewport: { width: 1280, height: 800 },
        trace: 'retain-on-failure',
    },
    webServer: [
        {
            command: `${env} php artisan migrate:fresh --seed --force && ${env} PHP_CLI_SERVER_WORKERS=4 php artisan serve --port=${port}`,
            url: `http://127.0.0.1:${port}/up`,
            reuseExistingServer: false,
            timeout: 120_000,
        },
        {
            // The public site (site/), built for production so caching and
            // on-demand revalidation behave as they will live. Waits for the API.
            command: `until curl -sf http://127.0.0.1:${port}/up > /dev/null; do sleep 1; done; cd site && ${siteEnv} npx next build && ${siteEnv} npx next start -p ${sitePort}`,
            url: `http://127.0.0.1:${sitePort}/`,
            reuseExistingServer: false,
            timeout: 300_000,
        },
    ],
});
