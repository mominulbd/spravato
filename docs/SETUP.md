# Spravato — Setup

Two repositories side by side: this one (Laravel API + React admin at `/admin`) and the Next.js public site in `../spravato-app`. Staff sign in at `/admin/login`. See [DEVELOPMENT-PLAN.md](DEVELOPMENT-PLAN.md) §1.

## 1. Requirements

PHP 8.4, Composer 2.8+, Node 24+ (26 works), npm 11, MySQL 8.4+ or 9.x, Google Chrome (for Playwright), Git.

## 2. First run

```sh
mysql -uroot -e "CREATE DATABASE spravato; CREATE DATABASE spravato_test; CREATE DATABASE spravato_e2e;"
cp .env.example .env && php artisan key:generate
git clone https://github.com/mominulbd/spravato-app.git ../spravato-app   # the public site
cp ../spravato-app/.env.example ../spravato-app/.env.local   # REVALIDATE_SECRET / SITE_TOKEN = FRONTEND_* in .env (openssl rand -hex 32)
composer install && npm install && npm --prefix ../spravato-app install
php artisan migrate --seed               # roles, permissions, site content; locally one account per role
composer dev                             # Laravel :8000, Vite, queue, logs and the site :3000
```

Open **http://localhost:3000** for the site and **http://localhost:3000/admin/login** for the admin. In development the site's `next.config.mjs` forwards `/admin`, `/api/v1`, `/sanctum`, `/build`, `/storage`, `/up` and `/login` to Laravel on :8000, so both run on one origin with one session cookie, as in production.

| Demo account (local and e2e only) | Role |
| --- | --- |
| `admin@uptms.com` | Super Admin |
| `dev@agency.test` | Admin |
| `cathy@uptms.test` | Manager |
| `john@uptms.test` | Staff |

All use the password `Uptms@2026`. Two-factor sign-in is off by default (Clinic settings → Security).

Production seeds roles only. Create the first account with `php artisan uptms:create-super-admin you@clinic.com "Your Name"` (it asks for the password).

Checks:

```sh
composer lint && php artisan test --compact
npm run lint && npm run types && npm test
npm --prefix ../spravato-app run lint && npm --prefix ../spravato-app run types && npm --prefix ../spravato-app test
npm run test:e2e                         # builds ../spravato-app (SITE_DIR) and runs Playwright against spravato_e2e
```

## 3. Laravel environment (`.env`)

Every variable is documented inline in `.env.example`. The ones that matter beyond Laravel's defaults:

| Variable | Purpose |
| --- | --- |
| `APP_URL` | The public origin: the site's address (`http://localhost:3000` locally), since Laravel sits behind it |
| `DB_*` | MySQL connection (`spravato`) |
| `SESSION_LIFETIME=30`, `SESSION_ENCRYPT=true` | Idle timeout and encryption for staff sessions |
| `SANCTUM_STATEFUL_DOMAINS` | Hosts that get cookie auth: the public origin, which also serves `/admin` |
| `TRUSTED_PROXIES` | Proxies whose `X-Forwarded-*` headers Laravel trusts: `next dev` locally (`127.0.0.1,::1`) |
| `MAIL_*` | Alerts and password resets; production provider must sign a HIPAA BAA |
| `FRONTEND_URL` | Public site origin (canonical URLs, "View site") |
| `FRONTEND_REVALIDATE_SECRET` | Signs the publish webhook to the site's `/api/revalidate` |
| `FRONTEND_SITE_TOKEN` | Shared with the site's `SITE_TOKEN`: lets the consultation form forward the visitor's IP, so the 5-a-minute limit applies per visitor rather than to the Next.js server |
| `TURNSTILE_SECRET_KEY` | Consultation form spam check; empty turns it off (honeypot and rate limit remain) |
| `CLINIC_TIMEZONE` | Office hours, the 1-business-day call-back target and the 6:00 site audit (default `America/Los_Angeles`) |
| `CONSULTATION_INDEX_KEY` | Key for searching encrypted names and phone numbers; defaults to one derived from `APP_KEY`. After changing it run `php artisan consultations:reindex` |
| `GOOGLE_SEARCH_CONSOLE_CREDENTIALS`, `INDEXNOW_KEY` | SEO integrations (Phase 4). Search Console also feeds the SEO overview's clicks, top searches and indexed count |
| `CRUX_API_KEY` | Chrome UX Report API key (Google Cloud, free) for the SEO overview's Core Web Vitals; empty hides them |

## 4. Site environment (`../spravato-app/.env.local`)

| Variable | Purpose |
| --- | --- |
| `LARAVEL_URL` | Laravel's own address; development rewrites forward Laravel's paths to it |
| `API_URL` | Laravel API base including `/api/v1`; server-side only |
| `NEXT_PUBLIC_SITE_URL` | Public origin for metadata, canonical URLs and the sitemap |
| `ALLOW_INDEXING` | `true` only in production at launch; otherwise every page is `noindex` |
| `REVALIDATE_SECRET` | Same value as `FRONTEND_REVALIDATE_SECRET` |
| `SITE_TOKEN` | Same value as `FRONTEND_SITE_TOKEN` (consultation form) |

## 5. Production

Server configs and the release steps are in [`deploy/README.md`](../deploy/README.md): nginx (or Apache with mod_proxy) sends Laravel's paths to PHP-FPM and everything else to the Next.js standalone server. The host is still an open decision (DEVELOPMENT-PLAN.md §4). It needs HIPAA-eligible hosting with a BAA for Laravel and MySQL, a queue worker (staff alerts, media variants, revalidation) and the scheduler (`consultations:purge` daily, `pages:publish-scheduled` every minute, `media:purge-trash`).

The hosting decision is still open (ROADMAP "Decisions needed"): HIPAA-eligible hosting with a BAA for Laravel and MySQL, a queue worker (staff alerts, media variants, revalidation), a scheduler (`consultations:purge` daily, `pages:publish-scheduled` every minute, `media:purge-trash`), S3-compatible media storage, and Next.js on Vercel or the same host. The mail provider must sign a BAA too: the receipt sent after a submission goes to the person's own address, and the staff alert spells the whole request out whenever Clinic settings → Privacy → "Include the request in the alert" is on (the default), so the provider holds health information either way. Two matching rules: never run production with `MAIL_MAILER=log`, which would write those alerts into `laravel.log`, and keep the notification address a clinic mailbox, not a personal one.

Never change `APP_KEY` once production has data: it decrypts every consultation request.
