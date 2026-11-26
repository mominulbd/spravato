# Deploying Spravato

> The public site moved to its own repository, [spravato-app](https://github.com/mominulbd/spravato-app). The Hostinger section at the end is current. The VPS layout below (`deploy.sh`, systemd/pm2 site units) still builds the site from `site/` inside this checkout and needs updating to clone spravato-app before it is used.

One domain, two processes (docs/DEVELOPMENT-PLAN.md §1, D2):

| Paths | Served by |
| --- | --- |
| `/admin`, `/admin/*`, `/api/v1/*`, `/sanctum/*`, `/up`, `/login` | Laravel via PHP-FPM (`public/index.php`) |
| `/build/*`, `/storage/*` | static files from Laravel's `public/` |
| `/_next/static/*` | static files from `shared/next-static/` (every release's build assets) |
| everything else (`/`, `/robots.txt`, `/sitemap.xml`, `/indexnow-key.txt`, `/api/revalidate`, …) | Next.js (`site/`), node on `127.0.0.1:3000` or `:3001` |

**Target:** a plain Ubuntu 24.04 VPS with nginx, PHP 8.4-FPM, Node 24 LTS, MySQL 8.4, everything running as one `spravato` user. The host is not chosen yet (docs/DEVELOPMENT-PLAN.md §4, decision 1); anything that gives a root shell works (Hetzner, DigitalOcean, Linode, Vultr, Lightsail, or Forge/Ploi on top of one). Shared hosting that can't proxy part of a domain to a node process can't run this layout; for Hostinger shared/Cloud hosting see [Hostinger](#hostinger-shared--cloud-hosting) at the end, which deploys Laravel alone (the uptms-ad layout) and runs the site on its own origin.

## What is in here

| File | Installed as | Does |
| --- | --- | --- |
| `nginx/spravato.conf` | `/etc/nginx/sites-available/spravato` | TLS, HTTP→HTTPS, gzip (brotli ready), the D2 split, real visitor IP, cache headers for static files |
| `nginx/cloudflare-realip.conf` | `/etc/nginx/snippets/spravato-cloudflare-realip.conf` | only if Cloudflare proxies the domain |
| `nginx/site-upstream.conf.example` | written by `deploy.sh` to `shared/nginx/site-upstream.conf` | which Next.js port is live |
| `apache/spravato.conf` | `/etc/apache2/sites-available/spravato.conf` | secondary variant, with `SITE_MANAGER=pm2` |
| `php-fpm/spravato.conf` | `/etc/php/8.4/fpm/pool.d/spravato.conf` | PHP pool as `spravato`, OPcache without timestamp checks |
| `systemd/spravato-site@.service` | `/etc/systemd/system/` | the Next.js standalone server, one instance per port (3000/3001) |
| `systemd/spravato-queue.service` | `/etc/systemd/system/` | `queue:work --max-time=3600` |
| `systemd/spravato-scheduler.{service,timer}` | `/etc/systemd/system/` | `schedule:run` every minute |
| `pm2/ecosystem.config.cjs` | used in place | alternative to the site unit (cluster mode, `pm2 reload`) |
| `sudoers/spravato` | `/etc/sudoers.d/spravato` | the few root commands `deploy.sh` needs, nothing else |
| `deploy.sh` | used in place | setup, zero-downtime deploy, rollback, status |

Security headers (CSP, HSTS, frame options) are set by the apps, not here; the web server only adds `nosniff`/cache headers to the static files that never reach an app.

## Layout on the server

```text
/var/www/spravato/
├── current -> releases/<id>            what nginx and PHP-FPM serve
├── releases/<id>/                      one built checkout per release (last 5 kept)
├── site-3000, site-3001 -> releases/<id>/site/.next/standalone/site
└── shared/
    ├── .env                            Laravel           → releases/<id>/.env
    ├── site.env                        Next.js           → releases/<id>/site/.env.production + systemd EnvironmentFile
    ├── storage/                        Laravel storage   → releases/<id>/storage
    ├── next-static/                    /_next/static of every release (pruned after 14 days unused)
    ├── nginx/site-upstream.conf        the live Next.js port (generated)
    ├── site-port                       the live Next.js port (generated)
    ├── repo.git                        bare mirror of the repository
    └── deploy.env                      settings for deploy.sh
```

## First-time server setup

As root, once:

```sh
# Packages (PHP from ppa:ondrej/php, Node from NodeSource)
apt install nginx mysql-server php8.4-fpm php8.4-{cli,mysql,mbstring,xml,curl,zip,intl,gd,bcmath} \
            git unzip certbot
# Node 24 LTS: https://github.com/nodesource/distributions; Composer: https://getcomposer.org/download/
# Images: the media library re-encodes with GD/Imagick, so also php8.4-imagick if AVIF is wanted.

# The app user (owns the code, runs PHP-FPM, node, the queue and the scheduler)
adduser --system --group --home /var/www/spravato --shell /bin/bash spravato
usermod -aG spravato www-data           # nginx reads public/ and storage/app/public

# Database
mysql -e "CREATE DATABASE spravato CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
          CREATE USER 'spravato'@'localhost' IDENTIFIED BY '<password>';
          GRANT ALL ON spravato.* TO 'spravato'@'localhost';"

# Let the server reach its own public name without leaving the box: the site's
# server-side API calls (API_URL) and Laravel's revalidation webhook
# (FRONTEND_URL) then go straight to this nginx, with a valid certificate,
# whatever DNS or a CDN says.
echo "127.0.0.1 <domain>" >> /etc/hosts
```

As `spravato` (`sudo -iu spravato`): get the repository once so the deploy files exist, then lay out the directories.

```sh
git clone <repo-url> /tmp/spravato-bootstrap
cat > /var/www/spravato/shared/deploy.env <<'EOF'   # mkdir -p shared first
REPO_URL=git@github.com:<org>/spravato.git          # needs a read-only deploy key in ~/.ssh
DEPLOY_REF=main
DOMAIN=<domain>
KEEP_RELEASES=5
SITE_MANAGER=systemd
EOF
bash /tmp/spravato-bootstrap/deploy/deploy.sh setup
```

Then write `shared/.env` and `shared/site.env` (below), `php artisan key:generate --show` for `APP_KEY` (run from the bootstrap checkout after `composer install`, or `echo "base64:$(openssl rand -base64 32)"`).

As root, install the configs from the bootstrap checkout (`cd /tmp/spravato-bootstrap/deploy`):

```sh
cp php-fpm/spravato.conf /etc/php/8.4/fpm/pool.d/ && php-fpm8.4 -t && systemctl reload php8.4-fpm
cp systemd/spravato-site@.service systemd/spravato-queue.service \
   systemd/spravato-scheduler.service systemd/spravato-scheduler.timer /etc/systemd/system/
systemctl daemon-reload
visudo -cf sudoers/spravato && install -m 0440 sudoers/spravato /etc/sudoers.d/spravato

# TLS first, with a temporary HTTP-only server, or: certbot certonly --standalone -d <domain>
mkdir -p /var/www/letsencrypt
sed 's/example.com/<domain>/g' nginx/spravato.conf > /etc/nginx/sites-available/spravato
ln -s ../sites-available/spravato /etc/nginx/sites-enabled/spravato
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
# Renewals: certbot's timer; webroot mode keeps nginx running:
#   certbot certonly --webroot -w /var/www/letsencrypt -d <domain> --deploy-hook "systemctl reload nginx"
```

Optional: brotli (`apt install libnginx-mod-http-brotli-filter libnginx-mod-http-brotli-static`, then uncomment the `brotli` lines); Cloudflare (copy `nginx/cloudflare-realip.conf` into `/etc/nginx/snippets/` and uncomment its `include`; without Cloudflare, never include it).

First release, as `spravato`:

```sh
bash /tmp/spravato-bootstrap/deploy/deploy.sh deploy
# from now on: bash /var/www/spravato/current/deploy/deploy.sh deploy
```

Then, as root, start the Laravel workers (they need `current` to exist):

```sh
systemctl enable --now spravato-queue spravato-scheduler.timer
```

Seed roles, permissions and the designed site content once, from `/var/www/spravato/current`: `php artisan db:seed --force` (production seeds no user accounts; create the first admin with `php artisan tinker` or whatever the team settles on).

## Environment

`shared/.env` (Laravel, from `.env.example`):

```sh
APP_NAME="Spravato · UPTMS"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://<domain>                 # the public domain, not Laravel's own port
LOG_CHANNEL=daily
LOG_LEVEL=info
DB_DATABASE=spravato
DB_USERNAME=spravato
DB_PASSWORD=...
SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=<domain>
TRUSTED_PROXIES=127.0.0.1,::1            # nginx talks to PHP directly and drops client X-Forwarded-*
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=smtp                         # + MAIL_HOST/PORT/USERNAME/PASSWORD/FROM_ADDRESS
CLINIC_TIMEZONE=America/Los_Angeles
FRONTEND_URL=https://<domain>            # public links + the webhook to /api/revalidate (nginx → Next.js)
FRONTEND_REVALIDATE_SECRET=<openssl rand -hex 32>
FRONTEND_SITE_TOKEN=<openssl rand -hex 32>
TURNSTILE_SECRET_KEY=...
```

`shared/site.env` (Next.js, from `site/.env.example`). The build reads it as `site/.env.production` — `API_URL` and `NEXT_PUBLIC_*` are inlined at build time, so a change needs a deploy — and the site unit reads it at runtime.

```sh
API_URL=https://<domain>/api/v1          # through this server's nginx (/etc/hosts line above)
NEXT_PUBLIC_SITE_URL=https://<domain>    # = APP_URL
ALLOW_INDEXING=false                     # "true" at launch, then deploy (robots.txt, sitemap, meta robots)
REVALIDATE_SECRET=...                    # = FRONTEND_REVALIDATE_SECRET
SITE_TOKEN=...                           # = FRONTEND_SITE_TOKEN (forwards the visitor IP for rate limits)
NEXT_PUBLIC_TURNSTILE_SITE_KEY=...       # pairs with TURNSTILE_SECRET_KEY
```

`MEDIA_ORIGINS` (site CSP) is only needed if media is ever served from another origin; on this layout it is the same domain.

`shared/deploy.env` (deploy.sh): `REPO_URL`, `DEPLOY_REF`, `DOMAIN` are required; optional `KEEP_RELEASES` (5), `SITE_MANAGER` (`systemd` | `pm2`), `WEB_SERVER` (`nginx` | `apache`), `PHP_FPM_SERVICE` (`php8.4-fpm`), `DRAIN_SECONDS` (10), `HEALTH_TIMEOUT` (60), `STATIC_RETENTION_DAYS` (14), `PRUNE_NODE_MODULES` (true).

## Deploy

```sh
sudo -iu spravato
bash /var/www/spravato/current/deploy/deploy.sh deploy            # DEPLOY_REF (main)
bash /var/www/spravato/current/deploy/deploy.sh deploy v1.2.0     # a tag, branch or commit
bash /var/www/spravato/current/deploy/deploy.sh deploy --archive /tmp/spravato.tar.gz   # CI upload (git archive)
```

What it does, in order (`set -euo pipefail`, one deploy at a time):

1. Fetch the ref into `releases/<UTC timestamp>` (`git archive` from `shared/repo.git`, no `.git` in the release); `REVISION` records the commit.
2. Link `shared/.env`, `shared/storage`, `shared/site.env` → `site/.env.production`.
3. `composer install --no-dev -o`, `npm ci && npm run build` (admin → `public/build`).
4. `php artisan migrate --force`, `storage:link`, `config:cache`, `route:cache`, `view:cache`, `event:cache`.
5. `npm ci && npm run build` in `site/` (postbuild runs `site/scripts/standalone-assets.mjs`); checks `server.js` and `.next/static` exist; copies `.next/static` into `shared/next-static`; drops the build-only `node_modules`.
6. Starts `spravato-site@<idle port>` from the new release and waits for `/` to answer 200 on that port. If it doesn't, it stops it and exits: **nothing was switched**.
7. Swaps `current` atomically (`mv -T`), points `shared/nginx/site-upstream.conf` at the new port, reloads PHP-FPM and nginx (both graceful), `php artisan queue:restart`.
8. Checks `https://<domain>/up`, `/` and `/admin/login` through this server's nginx (`curl --resolve`). On a failure it **switches back** to the previous release and port (the old site is still running) and exits non-zero.
9. Keeps the old site for `DRAIN_SECONDS`, stops it, enables the new instance for reboots, removes releases beyond `KEEP_RELEASES` and assets unused for 14 days.

A failed build removes its half-built release; a release whose checks failed is kept for its logs. On the very first deploy, Laravel goes live before the site build (`next build` prerenders from the API, and there is nothing to break).

**Migrations run before the switch** against the live database while the previous release still serves, so they must be backward compatible (add a column in one release, stop using/drop it in a later one). A rollback never reverts them.

With `SITE_MANAGER=pm2` steps 6–9 become: swap `current`, `pm2 startOrReload deploy/pm2/ecosystem.config.cjs --update-env` (cluster mode, two workers, replaced one at a time on port 3000), reload, check, and switch back the same way on failure. Set up pm2 once: `npm i -g pm2`, then `pm2 save && pm2 startup systemd`. Apache (`WEB_SERVER=apache`) needs pm2 mode, since its proxy target is one fixed port; add the commented Apache lines to the sudoers file.

## Rollback

```sh
bash /var/www/spravato/current/deploy/deploy.sh status               # releases, * = live, the live port
bash /var/www/spravato/current/deploy/deploy.sh rollback             # newest earlier release that went live
bash /var/www/spravato/current/deploy/deploy.sh rollback 20261201093000
```

Same blue/green switch and checks as a deploy, without building. Database changes stay; restore from backup if a migration has to be undone.

## Backups

`scripts/backup.sh` dumps MySQL and `storage/app/public` into `storage/app/backups` (inside the shared storage, so it survives release swaps); `scripts/restore.sh` restores one. Run nightly as the `spravato` user:

```cron
15 2 * * *  cd /var/www/spravato/current && BACKUP_GPG_RECIPIENT=<ops key id> scripts/backup.sh >> /var/log/spravato-backup.log 2>&1
```

- Copy each backup off the server (it holds health information: always gpg-encrypted).
- **APP_KEY is not in the backup** and the encrypted consultation columns are unreadable without it. Keep it, and every key that was ever live, in the secrets store, never beside the backups.
- The restore drill and its evidence: `docs/reports/restore-drill.md`.

## Logs

```sh
journalctl -u 'spravato-site@*' -f          # Next.js (or: pm2 logs spravato-site)
journalctl -u spravato-queue -f             # queue worker
journalctl -u spravato-scheduler -n 50      # scheduler runs
tail -f /var/www/spravato/shared/storage/logs/laravel-*.log   # Laravel (patient data redacted)
tail -f /var/log/nginx/spravato.error.log /var/log/nginx/spravato.access.log
tail -f /var/log/php8.4-fpm.log /var/www/spravato/shared/storage/logs/php-fpm.log
systemctl list-timers spravato-scheduler.timer
```

Back up `shared/.env` (it holds `APP_KEY`, without which encrypted consultation data is unreadable), `shared/storage/app` and a nightly `mysqldump` — off the server.

## Check after a release

```sh
curl -sI https://<domain>/            | head -1   # 200 from Next.js
curl -sI https://<domain>/admin/login | head -1   # 200 from Laravel
curl -sI https://<domain>/login       | head -3   # 301 → /admin/login
curl -s  https://<domain>/up                      # Laravel health
curl -s  https://<domain>/robots.txt              # Disallow: / until ALLOW_INDEXING=true

# An official PDF (URL from Media → Documents): 200, application/pdf,
# Content-Disposition: inline; filename="….pdf", X-Content-Type-Options: nosniff,
# Cache-Control: public, max-age=31536000, immutable
curl -sI https://<domain>/storage/media/<uuid>/<rev>-spravato-medication-guide.pdf
```

## Uptime monitoring

Three checks cover both processes and the database; none needs an account in the app:

| Check | URL | Expect | Proves |
| --- | --- | --- | --- |
| Laravel | `https://<domain>/up` | 200 | PHP-FPM + Laravel boot (Laravel's health route) |
| Site | `https://<domain>/` | 200, body contains `SPRAVATO` | nginx → Next.js, and the site can read its data |
| API + database | `https://<domain>/api/v1/public/settings` | 200, body contains `"success":true` | Laravel → MySQL, the path the site reads |

Plus certificate expiry (alert at 14 days) and, optionally, a heartbeat for the scheduler.

**UptimeRobot** (free tier: 50 monitors, 5-minute interval) — Add New Monitor → *HTTP(s)* for `/up`; *Keyword* monitors for `/` (`SPRAVATO`, "alert when not exists") and `/api/v1/public/settings` (`"success":true`). Turn on SSL expiry alerts in the monitor's advanced settings; alert contacts: the clinic's IT email and an SMS for the on-call person.

**Better Stack (Better Uptime)** (free tier: 10 monitors, 3-minute interval, status page) — Monitors → *Create*: "URL becomes unavailable" for `/up`, "URL doesn't contain keyword" for the other two; optionally a *Heartbeat* (5-minute period) pinged by a cron line as `spravato`, `*/5 * * * * curl -fsS -m 10 <heartbeat-url> >/dev/null`, which catches a box that is up but whose timers stopped.

Monitoring requests hit `public` rate limits like anyone (300/min per IP), far above any check interval. Neither provider sees patient data: none of these URLs carries any.

## Checking these files locally

- nginx: `nginx -t -p <tmp> -c <tmp>/nginx.conf` with the paths and certificate replaced (Homebrew nginx).
- Apache: `httpd -t -f <tmp>/httpd.conf` with the needed modules loaded (Homebrew httpd).
- PHP-FPM pool: `php-fpm -t -y <tmp>/fpm.conf`.
- sudoers: `visudo -cf deploy/sudoers/spravato`.
- `deploy.sh`: `bash -n` and `shellcheck deploy/deploy.sh`; it runs on macOS against stub `systemctl`/`nginx`/`composer`/`npm`/`php`/`curl` (`SUDO=` and `SYSTEMCTL=`/`NGINX_BIN=` pointing at the stubs).
- systemd units: `systemd-analyze verify deploy/systemd/*` on a Linux box (not available on macOS).

## Hostinger (shared / Cloud hosting)

hPanel hosting runs PHP but cannot send part of one domain to a node process, nor keep a worker running. So on Hostinger the two apps live on **two origins**:

| Origin | Runs | Deployed by |
| --- | --- | --- |
| `https://admin.<domain>` (any subdomain) | Laravel: `/admin`, `/api/v1`, `/storage`, `/up` | `.github/workflows/ci.yml`, job `deploy`, on every green push to `main` |
| `https://<domain>` | the Next.js site ([spravato-app](https://github.com/mominulbd/spravato-app), its own repository) | a Node host of its own: hPanel → Websites → Node.js app (Business/Cloud plans), Vercel, or any VPS with `deploy/systemd/spravato-site@.service` |

The site only talks to Laravel server-side (`API_URL`), and staff use the admin on Laravel's origin, so nothing in the code changes; only the environment does. On a Hostinger **VPS** use the one-domain layout above instead.

### What the deploy job does

Same as uptms-ad: builds `vendor/` (`--no-dev`) and `public/build` on the runner, rsyncs the tree with `--delete` (never `.env`, `storage/`, `public/storage`, the host's `.htaccess`, `.well-known/`, nor `site/`, `docs/`, tests), then on the host: `migrate --force`, the `PermissionSeeder`, the full `db:seed` only while `pages` is empty, `storage:link`, the config/route/view/event caches, `queue:restart`. With `HOSTINGER_PUBLIC_PATH` set it publishes only `public/` into the document root and rewrites that copy of `index.php` to the absolute app path. Last, a smoke test: `/up` and `/admin/login` answer 200, `/api/v1/public/settings` answers `"success":true`. A run on `main` is never cancelled mid-way.

Preferred layout, framework outside the web root:

```text
~/domains/admin.<domain>/laravel/       HOSTINGER_DEPLOY_PATH  (artisan, .env, storage/)
~/domains/admin.<domain>/public_html/   HOSTINGER_PUBLIC_PATH  (document root)
```

Without `HOSTINGER_PUBLIC_PATH` the project root is assumed to be the document root; the deploy then restores `hostinger/document-root.htaccess` as `.htaccess` when it is missing.

| GitHub secret / variable | Value |
| --- | --- |
| `HOSTINGER_SSH_HOST` (secret) | host or IP; a pasted `ssh -p 65002 u123@1.2.3.4` is parsed too |
| `HOSTINGER_SSH_USER` (secret) | SSH user (`u123…`) |
| `HOSTINGER_SSH_PORT` (secret) | defaults to `65002` |
| `HOSTINGER_SSH_KEY` (secret) | private key whose public half is in hPanel → SSH Access |
| `HOSTINGER_DEPLOY_PATH` (secret) | absolute Laravel root on the host |
| `HOSTINGER_PUBLIC_PATH` (secret or variable) | absolute document root, for the split layout |
| `HOSTINGER_PHP_BIN` (variable) | CLI PHP ≥ 8.3 when it is not under `/opt/alt/php8x/usr/bin/php` |
| `HOSTINGER_APP_URL` (variable) | Laravel's origin, e.g. `https://admin.<domain>`; enables the smoke test |

### First deploy

1. hPanel: add the subdomain, set its PHP to **8.4**, create the MySQL database, enable SSH and add the deploy key's public half.
2. Create `HOSTINGER_DEPLOY_PATH/.env` by hand (the deploy never uploads one and refuses to run without it). As in [Environment](#environment), except:
   ```sh
   APP_URL=https://admin.<domain>          # Laravel's own origin: media and PDF URLs are built from it
   SANCTUM_STATEFUL_DOMAINS=admin.<domain>  # where staff sign in
   SESSION_DOMAIN=null
   FRONTEND_URL=https://<domain>            # the site: revalidation webhook and preview links
   CACHE_STORE=database                     # no Redis on shared hosting; same for sessions and queue
   ```
   `APP_KEY`: generate once (`php artisan key:generate --show`) and **never change it** — it decrypts every consultation request.
3. Add the secrets and variables above, then push to `main` (or Actions → CI → Run workflow). The first run seeds roles and the designed site content.
4. Over SSH: `cd <deploy path> && <php from the deploy log> artisan uptms:create-super-admin you@clinic.com "Your Name"`.
5. hPanel → Cron Jobs, both every minute, with the PHP binary the deploy log printed:
   - `cd <deploy path> && /opt/alt/php84/usr/bin/php artisan schedule:run`
   - `cd <deploy path> && /opt/alt/php84/usr/bin/php artisan queue:work --stop-when-empty --max-time=55`
6. The site. On Hostinger: hPanel → Websites → Add website → **Node.js app** on `<domain>`, connected to the spravato-app repository, branch `main` (its README has the same steps):

   | Setting | Value |
   | --- | --- |
   | Framework | Next.js |
   | Repository | `mominulbd/spravato-app` (the site's own repository; root directory: the repository root) |
   | Node version | 24 (22 at the least) |
   | Install / build | `npm ci` / `npm run build` |
   | Start | `npm start` (listens on the `PORT` Hostinger assigns) |

   If `<domain>` already exists as a PHP website (the Laravel deploy started there), back up its `.env` — `APP_KEY` decrypts every consultation request — and move Laravel to the admin subdomain *before* removing that website. Environment variables for the app (from `site/.env.example`):
   ```sh
   API_URL=https://admin.<domain>/api/v1
   NEXT_PUBLIC_SITE_URL=https://<domain>
   MEDIA_ORIGINS=https://admin.<domain>     # CSP: media and PDFs come from Laravel's origin
   REVALIDATE_SECRET=...                    # = FRONTEND_REVALIDATE_SECRET
   SITE_TOKEN=...                           # = FRONTEND_SITE_TOKEN
   NEXT_PUBLIC_TURNSTILE_SITE_KEY=...
   ALLOW_INDEXING=false                     # "true" on launch day
   ```
   The site prerenders from the API at build time, so deploy Laravel first.

`reset_database` (manual run only) drops and re-migrates every table for first-deploy recovery; it refuses once any consultation request or user exists.

Backups: `scripts/backup.sh` works over SSH as a nightly hPanel cron job too; Hostinger's own daily backups do not replace an off-host, encrypted copy.
