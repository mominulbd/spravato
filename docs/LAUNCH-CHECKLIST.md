# Spravato — Launch checklist

Phase 4 "Hardening & launch" (docs/DEVELOPMENT-PLAN.md §3) is **done when this checklist is complete with evidence**.
Tick an item only when its evidence column links something that exists. `pending` means the work or the evidence is
not in the repository yet.

Last reviewed: 2026-11-26 · Status: **open**

## 1. Continuous integration

| # | Item | Evidence |
| --- | --- | --- |
| [ ] 1.1 | `.github/workflows/ci.yml` green on `main`: **php** (Pint, PHPStan 8, Pest on MySQL 8.4), **admin** (ESLint, tsc, Vitest, Vite build), **site** (ESLint, tsc, Vitest, `next build`), **e2e** (Playwright + Chrome on `spravato_e2e`, axe included) | CI run link: pending (the workflow has not run on GitHub yet) |
| [ ] 1.2 | Playwright HTML report uploaded by the e2e job (`playwright-report` artifact, 14 days) | Artifact link from 1.1: pending |
| [ ] 1.3 | Branch protection on `main` requires the four jobs | Screenshot or settings link: pending |

## 2. Responsive & accessibility

| # | Item | Evidence |
| --- | --- | --- |
| [ ] 2.1 | `/` reviewed at 390 / 768 / 1024 / 1440 / 1920 (no horizontal scroll, nothing clipped) | Screenshots in `docs/reports/`: pending |
| [ ] 2.2 | axe (WCAG 2.1 AA) passes on `/`, the consultation dialog and `/admin/login` in CI (`tests/e2e/a11y.spec.ts`) | axe results (CI run from 1.1 or `docs/reports/axe*`): pending |
| [ ] 2.3 | Keyboard pass: consultation dialog (open, focus trap, Esc, focus return) and FAQ (`tests/e2e/keyboard.spec.ts`) | CI run from 1.1: pending |
| [ ] 2.4 | Contrast: no axe `color-contrast` violation; Lighthouse accessibility ≥ 95 | `docs/reports/lighthouse/e2e/summary.md` (accessibility 96 mobile and desktop). Open: `target-size` audit below 90 |

## 3. Performance

| # | Item | Evidence |
| --- | --- | --- |
| [x] 3.1 | Lighthouse performance ≥ 95 (mobile and desktop) | `docs/reports/lighthouse/e2e/summary.md`: 100 / 100 |
| [x] 3.2 | LCP < 2.5 s, CLS < 0.1 | Same report: LCP 0.91 s mobile, 0.71 s desktop; CLS 0 |
| [x] 3.3 | Hero image has priority; other images lazy with reserved boxes; AVIF/WebP variants | `tests/e2e/perf.spec.ts` |
| [x] 3.4 | SEO 100 once indexing is allowed (`ALLOW_INDEXING=true`) | `docs/reports/lighthouse/indexable/summary.md` |
| [ ] 3.5 | Lighthouse re-run against the production host after deploy | `node scripts/lighthouse.mjs --url https://<domain>/ --label production` → `docs/reports/lighthouse/production/`: pending |

## 4. Security

| # | Item | Evidence |
| --- | --- | --- |
| [ ] 4.1 | CSP covering both apps behind the proxy (Laravel admin + Next.js site), no violations in the console on `/` and `/admin` | docs/ARCHITECTURE.md security section + e2e check: pending |
| [ ] 4.2 | Rate limits: consultation submit, sign-in, password reset, media uploads | Pest tests / ARCHITECTURE.md: pending |
| [ ] 4.3 | Dependency audit clean or triaged: `composer audit`, `npm audit`, `npm --prefix site audit` | Output in `docs/reports/`: pending |
| [ ] 4.4 | Backups scheduled (database + `storage/app/public`) | `scripts/backup.sh`, deploy/README.md: pending confirmation on the server |
| [ ] 4.5 | Restore drill done and timed | `docs/reports/restore-drill.md`: pending |
| [ ] 4.6 | Consultation privacy: no patient details in logs or alert emails; Tag Manager off `/` | `RedactSensitiveData`, Phase 2 tests; verified on production logs: pending |

## 5. Deploy

| # | Item | Evidence |
| --- | --- | --- |
| [ ] 5.1 | Hosting decided (DEVELOPMENT-PLAN.md §4.1) | pending |
| [ ] 5.2 | Proxy config live (nginx or Apache from `deploy/`): Laravel answers `/admin`, `/api/v1`, `/sanctum`, `/build`, `/storage`, `/up`; Next.js the rest | `deploy/README.md` "Check after a release" run on the host: pending |
| [ ] 5.3 | Next.js runs as a managed node process (pm2 / systemd / host Node app) and restarts on failure | deploy/README.md: pending |
| [ ] 5.4 | Laravel queue worker and scheduler running (scheduled publishing, media variants, revalidation) | deploy/README.md: pending |
| [ ] 5.5 | Zero-downtime release script for both apps, with rollback | deploy/README.md "Release": pending a production run |
| [ ] 5.6 | Uptime monitoring on `/` and `/up`, alerts to the clinic | Monitor link: pending |
| [ ] 5.7 | Official PDFs open inline as `application/pdf` from `/storage` | deploy/README.md "Check after a release": pending on the host |
| [ ] 5.8 | `ALLOW_INDEXING=true` set and page robots `index` on launch day only; sitemap submitted | pending |

## 6. Content

| # | Item | Evidence |
| --- | --- | --- |
| [ ] 6.1 | The three official PDFs (Medication Guide, Prescribing Information, Instructions for Use) uploaded; footer `documents` menu and FAQ card show them | pending: files are still missing from `database/seeders/assets/spravato` |
| [x] 6.2 | Document links survive a file replacement (links store the media id) | `packages/menus/tests/Feature/MenuDocumentsTest.php`, `packages/pages/tests/Feature/DocumentLinksTest.php` |
| [ ] 6.3 | Important Safety Information decision (DEVELOPMENT-PLAN.md §4.2) | pending |
| [ ] 6.4 | Official video URLs and embedding permission (§4.5) | pending |
| [ ] 6.5 | Real Turnstile keys and email delivery verified (Phase 2 "Not verified here") | pending |

## 7. Docs

| # | Item | Evidence |
| --- | --- | --- |
| [ ] 7.1 | AGENTS.md, ARCHITECTURE.md, DATABASE.md, API.md, SETUP.md, deploy/README.md match what is deployed | Review sign-off: pending |
| [ ] 7.2 | `composer openapi` regenerated (`docs/api/openapi.json`) | pending |
