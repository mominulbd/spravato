# Spravato · UPTMS — repository guide

The CMS, SEO console and consultation inbox for University Place TMS Clinic: Laravel 13 (PHP 8.4, MySQL) REST API + a React 19 admin SPA served from the same origin at `/admin`. The public Spravato website is the Next.js app in its own repository, `../spravato-app` (github.com/mominulbd/spravato-app), served on the **same domain** behind a reverse proxy, or on its own origin on Hostinger (`deploy/README.md`): Laravel answers `/admin`, `/api/v1`, `/sanctum`, `/build`, `/storage`, `/up`; Next.js everything else. Staff sign in at `/admin/login`. `../uptms-app` is a separate project: never modify it. The plan is `docs/DEVELOPMENT-PLAN.md`. Read `docs/ARCHITECTURE.md` before changing behaviour; `docs/DATABASE.md`, `docs/API.md` and `docs/ROADMAP.md` are the contracts for schema, API and phases.

Designs (source of truth for UI): admin https://www.figma.com/design/ij7CwMEI0miNBTQn0lF0AE/UPTMS?node-id=52-2 · public site https://www.figma.com/design/ij7CwMEI0miNBTQn0lF0AE/UPTMS?node-id=25-2

## Layout

- `app/Support/` — shared kernel: `ApiResponse` + `ErrorCode` + `ExceptionRenderer` (one envelope), `DomainException`, request ids, security headers, log redaction, `ModuleServiceProvider`. Knows no module.
- `packages/<module>/` — one domain module per package (`aminulbd/laravel-packages`), enabled in `config/packages.php`. Id `uptms.<snake>`, namespace `Uptms\<Pascal>`. Layout: `index.php` manifest, `migrations/`, `routes/{api,api_public,api_admin}.php`, `src/{Actions,Services,Models,Policies,Http,Domain,Events}`, `tests/{Feature,Unit}`.
- `resources/js/shared` (API client, UI kit), `resources/js/admin` (routes, layouts, `features/<feature>`). Aliases `@shared`, `@admin`.
- The public site is NOT in this repository: `../spravato-app`. `composer dev` starts it when it is checked out there (`SITE_DIR`); its dev rewrites put Laravel behind it on one origin. Playwright (`tests/e2e`) builds it from `SITE_DIR`.
- `resources/css/tokens.css` — Figma tokens for the admin; spravato-app keeps a copy in `app/tokens.css`. Change both together.
- `tests/e2e` — Playwright against a real server and the `uptms_e2e` database.

## Rules that are easy to break

- Every response uses the envelope from `ApiResponse`; every failure maps to an `ErrorCode`. Clients branch on `error_code`, never on messages.
- Every write goes through four gates: route `permission:` middleware → Form Request `authorize()` (policy) → the Action/Service → scoped queries. Never trust client ids, roles or status values.
- Consultation requests are health information: encrypted columns, no patient details in logs (`RedactSensitiveData`) or emails, every view audited.
- State lives in enums with `allowedTransitions()`; `status` is never fillable.
- A module never queries another module's models except through contracts/services; `tests/Unit/ArchitectureTest.php` enforces the boundaries.
- `public` routes (`/api/v1/public`) are read anonymously by the Next.js site and must never return unpublished content or personal data.
- Do not modify `~/Sites/decent-pos`; it is a read-only reference for conventions.

## Commands

```sh
composer lint                 # Pint (test mode) + PHPStan level 8
php artisan test --compact    # Pest on MySQL (spravato_test)
npm run lint && npm run types && npm test
npm run test:e2e              # build + Playwright (system Chrome, spravato_e2e)
composer openapi              # docs/api/openapi.json
composer dev                  # server, queue, logs, Vite and the site → http://localhost:3000
```

## Conventions

- PHP: `declare(strict_types=1)`, final classes where possible, constructor injection, typed properties, PHPDoc generics for collections and relations. Match the surrounding code.
- Tests beside the code they cover; every endpoint gets an allowed and a denied case.
- Commits: small and conventional, scope `uptms` — `feat(uptms): …`, `fix(uptms): …`, `docs(uptms): …`.
