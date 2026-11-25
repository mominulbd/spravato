# Spravato — Development Plan

Status: **Phase 0 done** (2026-11-25): `site/` (Next.js 16.4.6) on one origin with Laravel, `/admin/login`, Spravato databases, posts/quiz/conditions removed, CI, deploy configs (`deploy/`), docs. **Next: Phase 1** · supersedes the two-repository layout in `ROADMAP.md` and `ARCHITECTURE.md` §1

**Goal:** turn the static Spravato landing page (Figma file `Ok7lXCmg2hLZ8iurI1p85j`, page "Spravato"; the static HTML prototype was removed from the repo on 2026-11-25) into a CMS-driven page, served by a **Next.js front end that lives inside this Laravel project**, with the consultation modal feeding the existing encrypted inbox. Staff sign in at **`/admin/login`**.

---

## 1. Decisions this plan is built on

| # | Decision |
| --- | --- |
| D1 | **One repository.** Laravel (API + React admin) at the root, Next.js public site in `site/`, built new for Spravato. `../uptms-app` is a **separate project and is never modified**; at most it is read as a reference for conventions. |
| D2 | **One domain.** Laravel answers `/admin/*`, `/api/*`, `/sanctum/*`, `/build/*`, `/storage/*`, `/up`. Next.js answers everything else, starting with `/`. |
| D3 | **Admin sign-in is `/admin/login`.** Every unauthenticated admin path redirects there (`?next=` keeps the target). `/login` → 301 `/admin/login`. Laravel's `/` → `/admin` redirect is removed (the site owns `/`). |
| D4 | **Admin stays React + Vite** in `resources/js/admin` (already built, tested, matches Figma "Admin · CMS"). Only the public site is Next.js. |
| D5 | **Spravato is one standalone page**: header = logo + consultation button, no site navigation, no links to other pages or external sites (except the official PDFs). No italic text. |
| D6 | **Admin scope cut** (Figma, 2026-11-25): Posts & resources, Quiz builder and Conditions are removed — modules disabled, screens and nav items deleted. |

## 2. Target layout

```text
spravato/
├── app/ bootstrap/ config/ database/ routes/      Laravel 13 (unchanged)
├── packages/<module>/                             domain modules (unchanged convention)
├── resources/js/{admin,shared}                    React admin SPA at /admin (Vite)
├── resources/css/tokens.css                       ← single source of Figma tokens
├── site/                                          Next.js 16 public site (own package.json)
│   ├── app/ components/ lib/ public/
│   ├── next.config.mjs                            dev rewrites → Laravel
├── tests/e2e                                      Playwright: site + admin together
└── docs/
```

```text
             Browser (patients + staff) ── https://<domain>
                          │
              reverse proxy (nginx / Apache)
       ┌──────────────────┴────────────────────────┐
 /admin /api /sanctum /build /storage /up       everything else
       │                                            │
 Laravel (PHP-FPM)  ◄── server-side fetch ──  Next.js (node, site/)
       │                /api/v1/public/*
     MySQL
```

- **Dev:** `composer dev` starts Laravel (:8000), Vite, queue, logs **and** `next dev` (:3000). `site/next.config.mjs` adds fallback rewrites for the Laravel paths in D2, so `http://localhost:3000` serves the site *and* `/admin/login` on one origin, exactly like production.
- **Auth is unchanged:** the admin is same-origin with Laravel, Sanctum session cookie + CSRF. `SANCTUM_STATEFUL_DOMAINS` and `TrustProxies` are set for the proxied host.
- **Site → API** stays server-side only (`site/lib/api.ts`, `server-only`), revalidation webhook stays HMAC-signed, but now targets `http://127.0.0.1:3000/api/revalidate` on the same box. Note: `/api/revalidate` is a Next.js route, so the proxy maps `/api/v1/*` to Laravel and nothing else under `/api`.

---

## 3. Phases

Each phase ships backend, admin and site together, with tests, and ends in a demo.

```text
Phase 0  Restructure ─────────── one repo, one origin, /admin/login, scope cut
Phase 1  Spravato page ───────── every section from Laravel data, pixel-matched
Phase 2  Consultation modal ──── modal on site → encrypted inbox in admin
Phase 3  SEO & media ─────────── metadata, JSON-LD, PDFs, videos, sitemap
Phase 4  Hardening & launch ──── QA, a11y, performance, security, deploy
```

### Phase 0 — Restructure

| Area | Deliverables |
| --- | --- |
| Repo | ✅ `git init`; create `site/` with `create-next-app` (Next.js 16, App Router, Cache Components, Tailwind 4). Where a piece of `../uptms-app` fits (API client, revalidate route, block renderer), write the equivalent in `site/`; never edit or move files in `uptms-app`. Root `package.json` gets npm workspaces or `site:*` scripts; one `.env.example` section for the site |
| Routing | ✅ `routes/web.php`: drop `Route::redirect('/', '/admin')`; keep the admin catch-all; `/login` → `/admin/login`. `site/next.config.mjs` dev rewrites for D2 paths. Proxy config in `deploy/` (nginx + Apache variants) |
| Admin login | ✅ Confirm `RequireAuth` / `SessionEndedScreen` / password-reset and invitation links all land on `/admin/login`; reset and invitation emails build URLs with `/admin/...`; test both |
| Scope cut | ✅ Remove `uptms.posts`, `uptms.quiz`, `uptms.conditions` from `config/packages.php`; delete `features/{posts,quiz,conditions}`, their routes, nav items, block sources and site components; drop their tables in a migration; update `ArchitectureTest` |
| Rebrand | ✅ `APP_NAME`, DB names `spravato`, `spravato_test`, `spravato_e2e`; README, AGENTS.md, ARCHITECTURE.md §1/§6, SETUP.md rewritten for one repo |
| Tokens | ✅ `resources/css/tokens.css` is the only copy; `site/app/globals.css` imports it (`turbopack.root` = the Laravel root) |
| CI | ✅ One workflow: Pint + PHPStan + Pest, admin lint/types/Vitest, site lint/types/Vitest, Playwright |

**Done when:** `composer dev` → `localhost:3000` shows the site shell, `localhost:3000/admin/login` signs in, `/admin/posts` is a 404, all checks are green.

### Phase 1 — Spravato page from the CMS

Map every section of the Figma Spravato page to a block type (reuse existing ones where the shape fits, add the rest to `BlockTypes`):

| Section (Figma) | Block | Data source |
| --- | --- | --- |
| Utility bar + header (logo, consultation button) | `site-header-minimal` *(new)* | Clinic settings (phone, area) |
| Hero (eyebrow, title + accent words, lead, actions, image + note) | `hero` (extend: image caption, note card) | page block + media |
| Trust strip | `trust-points` | collection |
| What is Spravato® | `split-text-media` | page block |
| Is Spravato right for you? (3 criteria cards) | `criteria-cards` *(new)* | page block |
| Your visit, one step at a time | `journey-steps` | collection |
| Treatment schedule (navy) | `schedule` *(new)* | page block |
| Video section (2 official videos) | `video-pair` *(new)* | page block; click-to-load facade |
| Team (Ayub Mwaura, Joy Kiongo) | `team` | collection |
| Near Tacoma + insurance / cost | `location-coverage` *(new)* | page block + settings |
| FAQ | `faq` | collection |
| Closing CTA | `cta-band` | page block |
| Footer (Visit / Contact / Official documents, ® line) | `site-footer-minimal` *(new)* | settings + media (PDFs) |

| Area | Deliverables |
| --- | --- |
| Backend | New block types with field schemas + validation; `SpravatoPageSeeder` with every line of copy from the Figma page; images (logo, hero, provider photos) and the three official PDFs (prescribing information, medication guide, instructions for use) imported into the media library; page published at path `/` |
| Admin | Block forms are generated from the schema, so new blocks need only field definitions; Figma "07b · Page editor · Spravato" for any layout that differs; collections tabs limited to team, FAQs, trust points, journey steps |
| Site | `site/components/blocks/*` for each new block; layout without nav; Source Serif 4 Regular headings with accent words in royal/sky (no italics); 1440 / 768 / 390 |
| Tests | Pest: block schemas, seeder output, public page shape; Vitest: each block renders from fixture; Playwright: edit hero title → publish → `/` shows it |

**Done when:** `/` matches the Figma Spravato page at all three widths, every word comes from the API, and a published edit appears without a redeploy.

### Phase 2 — Consultation modal

The prototype's consultation modal has fields the current form doesn't. Extend `uptms.consultations` rather than add a second inbox.

| Area | Deliverables |
| --- | --- |
| Backend | New fields: `preferred_contact` (phone/email/either), `patient_status` (new/existing/not sure), `insurance_provider` (**encrypted**, optional), `emergency_acknowledged` (required true); `best_time` values Morning / Afternoon / Early evening / No preference; topics seeded from the modal list; `cta_id` stored as the request's source label. Migration, Form Request, enums, search index unchanged |
| Admin | Request detail (Figma 05) shows the new fields; inbox filter by topic; CSV export columns added (insurance stays out unless `consultations.export`) |
| Privacy | The form is a pop-up on `/`, not a `/contact` page, so the Tag Manager exclusion (`SeoSettings::SENSITIVE_PATHS`, ARCHITECTURE.md §9) no longer protects it: keep tags off `/` entirely while the dialog exists there, or load no tag inside the dialog and never forward its fields |
| Site | `ConsultationDialog` client island: opens from any `data-topic` button with that topic preselected; focus trap, Esc, return focus; client + server validation; submitting / success (reference number) / error / rate-limited states; Turnstile; message field keeps the "don't include health details" hint |
| Tests | Pest: new fields validated + encrypted at rest, acknowledgement required, topic must be a known one; Vitest: dialog a11y + validation; Playwright: open modal from hero → submit → appears in `/admin/consultations` → mark contacted |

**Done when:** a request sent from any button on `/` appears in the inbox with its topic and source, and no patient detail is in logs or the alert email beyond what Privacy settings allow.

### Phase 3 — SEO, documents & video

| Area | Deliverables |
| --- | --- |
| Backend | SEO meta for the page (title, description, OG image: the hero photo); JSON-LD: `MedicalClinic` + `MedicalWebPage` + `FAQPage` + `Physician`/`Person` for the team; sitemap with `/` only; robots `noindex` until launch |
| Admin | SEO / Social / Schema tabs on the page editor (already built) |
| Site | `generateMetadata`, JSON-LD, `sitemap.ts`, `robots.ts`; PDFs served from Laravel `/storage` with correct `Content-Type`; videos behind a click-to-play facade (no third-party request until the visitor asks) |
| Tests | Schema fixtures; Lighthouse SEO 100 |

**Done when:** metadata and JSON-LD validate, the PDFs open, and the page makes no third-party request on load.

### Phase 4 — Hardening & launch

| Area | Deliverables |
| --- | --- |
| Responsive & a11y | 390 / 768 / 1024 / 1440 / 1920; axe in CI; keyboard pass on the modal and FAQ; contrast |
| Performance | Lighthouse ≥ 95; LCP < 2.5 s (hero image priority, AVIF/WebP), CLS < 0.1 |
| Security | CSP covering both apps behind the proxy; rate limits; dependency audit; backups + restore drill |
| Deploy | Proxy config live; Next.js as a managed node process (pm2/systemd or host's Node app); Laravel queue worker + scheduler; zero-downtime deploy script for both; uptime monitoring |
| Cleanup | Docs final |

**Done when:** the launch checklist is complete with evidence (CI run, Lighthouse and axe reports).

---

## 4. Decisions needed

1. **Hosting.** One server must run PHP-FPM *and* a Node process behind one domain (D2). A VPS (Forge / Ploi / plain nginx) does this easily; Hostinger shared hPanel, used for `uptms-app` so far, may not allow proxying paths between a Node app and PHP on one domain. Which host?
2. **Important Safety Information.** The Figma page leaves the ISI section out (your earlier request), but the original prototype had it, its FAQ linked to `#important-safety-information`, and a page promoting a prescription medicine normally needs it for fair balance. Keep it (recommended, as an editable block), or confirm with the clinic that it can go?
3. **Other UPTMS pages.** Do Home / TMS / Contact pages stay in this project, or is the site *only* the Spravato page? The plan assumes only Spravato; the rest of the block library stays available but unseeded.
4. **Source assets.** The images and the three official PDFs were only in `HTML/`. Phase 1 needs them back (from the Trash, Figma exports, or the clinic).
5. **Videos.** Which official SPRAVATO® videos (URLs) and is embedding them permitted?
