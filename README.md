# Spravato · UPTMS

Laravel 13 API + React admin and the Next.js Spravato landing page (`site/`), on one domain, for **University Place TMS Clinic**. Staff sign in at `/admin/login`.

- Plan: [docs/DEVELOPMENT-PLAN.md](docs/DEVELOPMENT-PLAN.md)
- Docs: [Architecture](docs/ARCHITECTURE.md) · [API](docs/API.md) · [Database](docs/DATABASE.md) · [Setup](docs/SETUP.md)

```sh
cp .env.example .env && php artisan key:generate
cp site/.env.example site/.env.local     # same secrets as FRONTEND_* in .env
mysql -uroot -e "CREATE DATABASE spravato; CREATE DATABASE spravato_test; CREATE DATABASE spravato_e2e;"
composer install && npm install && npm --prefix site install
php artisan migrate --seed
composer dev                             # http://localhost:3000 and /admin/login
```
