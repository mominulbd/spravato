# Deploying Spravato

One domain, two processes (docs/DEVELOPMENT-PLAN.md §1):

| Paths | Served by |
| --- | --- |
| `/admin`, `/admin/*`, `/api/v1/*`, `/sanctum/*`, `/up`, `/login` | Laravel via PHP-FPM (`public/index.php`) |
| `/build/*`, `/storage/*` | static files from Laravel's `public/` |
| everything else (`/`, `/_next/*`, `/api/revalidate`, …) | Next.js (`site/`), node on `127.0.0.1:3000` |

The server needs PHP 8.4-FPM, Node 24+, MySQL 8.4+, and nginx (`nginx/spravato.conf`) or Apache with mod_proxy (`apache/spravato.conf`). Shared hosting that can't proxy part of a domain to a node process can't run this layout. `hostinger/` is the old Laravel-only layout from UPTMS, kept for reference.

## Layout on the server

```text
/var/www/spravato/
├── current -> releases/<timestamp>
├── releases/<timestamp>/        the repository, built
└── shared/
    ├── .env                     Laravel (from .env.example; APP_URL = https://<domain>)
    ├── site.env                 Next.js (from site/.env.example)
    └── storage/                 Laravel storage, kept across releases
```

`site.env` in production:

```sh
API_URL=https://<domain>/api/v1
NEXT_PUBLIC_SITE_URL=https://<domain>
ALLOW_INDEXING=false            # true at launch
REVALIDATE_SECRET=...           # = FRONTEND_REVALIDATE_SECRET in .env
SITE_TOKEN=...                  # = FRONTEND_SITE_TOKEN in .env
```

In `.env`: `FRONTEND_URL=https://<domain>` (the revalidation webhook goes to `/api/revalidate`, which nginx passes to Next.js), `SANCTUM_STATEFUL_DOMAINS=<domain>`, `SESSION_SECURE_COOKIE=true`.

## Release

```sh
cd /var/www/spravato/releases/$(date +%Y%m%d%H%M%S)   # fresh checkout
ln -s ../../shared/.env .env && rm -rf storage && ln -s ../../shared/storage storage

composer install --no-dev --optimize-autoloader
npm ci && npm run build                       # admin → public/build

cd site
cp ../../../shared/site.env .env.production   # read by `next build`
npm ci && npm run build                       # → .next/standalone/site (assets copied by postbuild)
cd ..

php artisan migrate --force
php artisan storage:link
php artisan config:cache route:cache view:cache

ln -sfn "$PWD" /var/www/spravato/current
sudo systemctl reload php8.4-fpm
sudo systemctl restart spravato-site          # deploy/systemd/spravato-site.service
php artisan queue:restart
```

Laravel also needs a queue worker (`php artisan queue:work`, as a systemd unit or Supervisor) and the scheduler (`* * * * * php artisan schedule:run`).

## Check after a release

```sh
curl -sI https://<domain>/            | head -1   # 200 from Next.js
curl -sI https://<domain>/admin/login | head -1   # 200 from Laravel
curl -sI https://<domain>/login       | head -3   # 301 → /admin/login
curl -s  https://<domain>/up                      # Laravel health
```
