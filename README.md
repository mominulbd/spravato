# Spravato · UPTMS

Laravel 13 API + React admin for **University Place TMS Clinic**; the Next.js Spravato landing page lives in its own repository, [spravato-app](https://github.com/mominulbd/spravato-app), checked out beside this one (`../spravato-app`). Staff sign in at `/admin/login`.

- Plan: [docs/DEVELOPMENT-PLAN.md](docs/DEVELOPMENT-PLAN.md)
- Docs: [Architecture](docs/ARCHITECTURE.md) · [API](docs/API.md) · [Database](docs/DATABASE.md) · [Setup](docs/SETUP.md)

```sh
cp .env.example .env && php artisan key:generate
git clone https://github.com/mominulbd/spravato-app.git ../spravato-app
cp ../spravato-app/.env.example ../spravato-app/.env.local   # same secrets as FRONTEND_* in .env
mysql -uroot -e "CREATE DATABASE spravato; CREATE DATABASE spravato_test; CREATE DATABASE spravato_e2e;"
composer install && npm install && npm --prefix ../spravato-app install
php artisan migrate --seed
composer dev                             # http://localhost:3000 and /admin/login
```
