# UPTMS — Setup

## 1. Requirements

PHP 8.4, Composer 2.8+, Node 24+ (26 works), npm 11, MySQL 8.4+ or 9.x, Google Chrome (for Playwright), Git.

## 2. API + admin (`uptms-ad`)

```sh
cd ~/Sites/uptms-ad
mysql -uroot -e "CREATE DATABASE uptms; CREATE DATABASE uptms_test; CREATE DATABASE uptms_e2e;"
composer setup          # install, .env, key, migrate, npm install, build
composer dev            # API on http://localhost:8000, Vite, queue, logs
```

Seed roles, permissions and the local demo accounts, then open http://localhost:8000/admin:

```sh
php artisan db:seed     # roles + permissions; locally also one account per role
```

| Demo account (local and e2e only) | Role |
| --- | --- |
| `admin@uptms.com` | Super Admin |
| `dev@agency.test` | Admin |
| `cathy@uptms.test` | Manager |
| `john@uptms.test` | Staff |

All use the password `Uptms@2026`. Every built-in role requires two-factor, so the first sign-in walks through 2FA set-up with any authenticator app.

Production seeds roles only. Create the first account with `php artisan uptms:create-super-admin you@clinic.com "Your Name"` (it asks for the password); 2FA is set up at first sign-in. Outgoing mail (invitations, password resets) uses the `MAIL_*` settings; locally it goes to the log.

Checks:

```sh
composer lint && php artisan test --compact
npm run lint && npm run types && npm test
npm run test:e2e
```

## 3. API environment (`uptms-ad/.env`)

Every variable is documented inline in `.env.example`. The ones that matter beyond Laravel's defaults:

| Variable | Purpose |
| --- | --- |
| `APP_URL` | Origin serving the API and `/admin` |
| `DB_*` | MySQL connection (`uptms`) |
| `SESSION_LIFETIME=30`, `SESSION_ENCRYPT=true` | Idle timeout and encryption for staff sessions |
| `SANCTUM_STATEFUL_DOMAINS` | Hosts that get cookie auth (the admin origin) |
| `MAIL_*` | Alerts and password resets; production provider must sign a HIPAA BAA |
| `AWS_*` | Media storage in production (Phase 2) |
| `FRONTEND_URL` | Public site origin (canonical URLs, "View site") |
| `FRONTEND_REVALIDATE_SECRET` | Signs the publish webhook to uptms-app (Phase 2) |
| `FRONTEND_SITE_TOKEN` | Shared with the site's `SITE_TOKEN`: lets the consultation form forward the visitor's IP, so the 5-a-minute limit applies per visitor rather than to the Next.js server |
| `TURNSTILE_SECRET_KEY` | Consultation form spam check; empty turns it off (honeypot and rate limit remain) |
| `CLINIC_TIMEZONE` | Office hours, the 1-business-day call-back target and the 6:00 site audit (default `America/Los_Angeles`) |
| `CONSULTATION_INDEX_KEY` | Key for searching encrypted names and phone numbers; defaults to one derived from `APP_KEY`. After changing it run `php artisan consultations:reindex` |
| `GOOGLE_SEARCH_CONSOLE_CREDENTIALS`, `INDEXNOW_KEY` | SEO integrations (Phase 4). Search Console also feeds the SEO overview's clicks, top searches and indexed count |
| `CRUX_API_KEY` | Chrome UX Report API key (Google Cloud, free) for the SEO overview's Core Web Vitals; empty hides them |

## 4. Public site (`uptms-app`)

```sh
cd ~/Sites/uptms-app
cp .env.example .env.local
npm install
npm run dev             # http://localhost:3000 (needs the API running)
```

| Variable | Purpose |
| --- | --- |
| `API_URL` | Laravel API base including `/api/v1`; server-side only |
| `NEXT_PUBLIC_SITE_URL` | Public origin for metadata, canonical URLs and the sitemap |
| `REVALIDATE_SECRET` | Same value as `FRONTEND_REVALIDATE_SECRET` (Phase 2) |
| `SITE_TOKEN` | Same value as `FRONTEND_SITE_TOKEN` (consultation form) |
| `NEXT_PUBLIC_TURNSTILE_SITE_KEY` | Consultation form widget; empty turns it off |

Checks: `npm run lint && npm run types && npm test && npm run build`.

## 5. Production deployment (*Phase 5*)

The hosting decision is still open (ROADMAP "Decisions needed"): HIPAA-eligible hosting with a BAA for Laravel and MySQL, a queue worker (staff alerts, media variants, revalidation), a scheduler (`consultations:purge` daily, `pages:publish-scheduled` every minute, `media:purge-trash`), S3-compatible media storage, and Next.js on Vercel or the same host. The mail provider must sign a BAA too: the receipt sent after a submission goes to the person's own address, and the staff alert spells the whole request out whenever Clinic settings → Privacy → "Include the request in the alert" is on (the default), so the provider holds health information either way. Two matching rules: never run production with `MAIL_MAILER=log`, which would write those alerts into `laravel.log`, and keep the notification address a clinic mailbox, not a personal one.

### Hostinger (`.github/workflows/ci.yml`, job `deploy`)

Every push to `main` that passes the `php` and `js` jobs deploys over SSH: it builds `vendor/` (`--no-dev`) and `public/build` on the runner, rsyncs the tree with `--delete` (never `.env`, `storage/`, `public/storage`, the host's `.htaccess` or `.well-known/`), then on the host runs `migrate --force`, the `PermissionSeeder`, the full `db:seed` only while `pages` is empty, `storage:link`, the config/route/view/event caches and `queue:restart`. Interrupting it mid-run is never done for `main`.

Preferred layout — the framework outside the web root, only `public/` published into it (the deploy rewrites the published `index.php` to the absolute app path):

```
~/domains/<domain>/laravel/      HOSTINGER_DEPLOY_PATH  (artisan, .env, storage/)
~/domains/<domain>/public_html/  HOSTINGER_PUBLIC_PATH  (document root)
```

Without `HOSTINGER_PUBLIC_PATH` the project root is assumed to be served directly; the deploy then restores `deploy/hostinger/document-root.htaccess` when the root `.htaccess` is missing.

| Secret / variable | Value |
| --- | --- |
| `HOSTINGER_SSH_HOST` (secret) | Host or IP; a pasted `ssh -p 65002 u123@1.2.3.4` is parsed too |
| `HOSTINGER_SSH_USER` (secret) | SSH user (`u123…`) |
| `HOSTINGER_SSH_PORT` (secret) | Defaults to `65002` |
| `HOSTINGER_SSH_KEY` (secret) | Private key whose public half is in hPanel → SSH Access |
| `HOSTINGER_DEPLOY_PATH` (secret) | Absolute Laravel root on the host |
| `HOSTINGER_PUBLIC_PATH` (secret or variable) | Absolute document root, for the split layout |
| `HOSTINGER_PHP_BIN` (variable) | CLI PHP ≥ 8.3 when not under `/opt/alt/php8x/usr/bin/php` |
| `HOSTINGER_APP_URL` (variable) | API origin; enables the `GET /up` smoke test |

First deploy:

1. hPanel: set the domain's PHP to 8.4, create the MySQL database, enable SSH.
2. Create `HOSTINGER_DEPLOY_PATH/.env` by hand from `.env.example`: `APP_ENV=production`, `APP_DEBUG=false`, a fresh `APP_KEY` (`php artisan key:generate --show`; **never change it** — it decrypts every consultation request), `APP_URL`, `DB_*`, `SESSION_SECURE_COOKIE=true`, `SANCTUM_STATEFUL_DOMAINS`, `FRONTEND_*`, `MAIL_*`. Keep `CACHE_STORE`, `SESSION_DRIVER` and `QUEUE_CONNECTION` on `database` (no Redis on shared hosting). The deploy refuses to run without `.env` and warns on a production `.env` with debug on or a localhost `FRONTEND_URL`.
3. Push to `main` (or Actions → CI → Run workflow). The first run seeds roles and the designed site content.
4. Over SSH: `php artisan uptms:create-super-admin you@clinic.com "Your Name"` (asks for the password).
5. hPanel → Cron Jobs, both every minute, with the 8.x binary the deploy log printed:
   `cd <deploy path> && php artisan schedule:run` and
   `cd <deploy path> && php artisan queue:work --stop-when-empty --max-time=55`.

`reset_database` (manual run only) drops and re-migrates every table for first-deploy recovery; it refuses once any consultation request, post or user exists.
