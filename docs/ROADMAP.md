# UPTMS — Development Roadmap

Status: **Phase 3 complete** (2026-10-08) · supersedes §8 of `ANALYSIS.md`

Layout (decided 2026-10-08): **`~/Sites/uptms-ad`** = Laravel API + React admin SPA (`resources/js/admin`), domain modules in `packages/` following `~/Sites/decent-pos`; **`~/Sites/uptms-app`** = Next.js public site. See ARCHITECTURE.md §1.

Design sources — the source of truth for all UI (guideline §6, §32):
- **Admin (React)** — https://www.figma.com/design/ij7CwMEI0miNBTQn0lF0AE/UPTMS?node-id=52-2 · page "Admin · CMS (Version C)": screens 01–31 + 18b, component library, states board
- **Frontend (Next.js)** — https://www.figma.com/design/ij7CwMEI0miNBTQn0lF0AE/UPTMS?node-id=25-2 · page "Version C · Rounded panels": Home, TMS, Contact, Quiz × 1440/768/390 + menu-open states + components

---

## How the phases are cut

The guideline (§29) lists phases by layer: all backend, then all admin, then all frontend. Built that way, nothing works from end to end until Phase 6. That breaks two other rules in the same guideline: every phase must "produce a working result", and the per-feature workflow (§30) goes backend → admin → frontend → test for each feature.

So this roadmap keeps the guideline's analysis and architecture work (§29 phases 1–2, already done in `ANALYSIS.md`) and its QA work (phase 7). The build itself is cut into **vertical slices**: each phase ships Laravel API, React admin and Next.js together for one area of the product, with tests, and ends in a demo.

```text
Phase 0  Foundation ─────────────── API, admin & site run, checks green  ✅
Phase 1  Access & admin shell ───── staff sign in, roles enforced, public site shell   ✅
Phase 2  Content & public site ──── edit in admin → live on uptms.com        ✅
Phase 3  Consultation requests ──── form on site → encrypted inbox in admin   ✅
Phase 4  Posts & full SEO ───────── resources, SEO tabs, redirects, sitemap, audits
Phase 5  Hardening & launch ─────── QA, a11y, performance, security, deploy
```

Each phase lists: **Backend · Admin · Frontend · Tests · Done when**. Figma screen numbers refer to the admin page.

---

## Phase 0 — Foundation ✅ done 2026-10-08

**Goal:** three apps that boot, talk to each other, and are checked by CI. No features yet.

| Area | Deliverables |
| --- | --- |
| Repos | `uptms-ad` (Laravel + admin SPA) and `uptms-app` (Next.js), each with Git, `.editorconfig`, CI workflow |
| Backend | Laravel 13.35 (PHP 8.4), MySQL (`uptms`, `uptms_test`, `uptms_e2e`); Sanctum with `statefulApi()`; **`aminulbd/laravel-packages`** + `ModuleServiceProvider` (route files `api`, `api_public`, `api_admin` → `/api/v1`, `/api/v1/public`, `/api/v1/admin`); **response envelope** + **`ErrorCode`** + **`ExceptionRenderer`**; request ids; security headers; PHI-aware log redaction; rate limiters; `GET /api/v1/health` |
| Admin | Vite 8 + React 19 + strict TS + Tailwind 4 in `resources/js/admin`; Figma **design tokens** (`resources/css/tokens.css`); self-hosted Source Serif 4 / Nunito / Nunito Sans; decent-pos fetch client (CSRF, envelope, `ApiError`) + TanStack Query; router at `/admin`; Phase 0 status page |
| Frontend | Next.js 16.4 App Router with Cache Components in `uptms-app`; same tokens; `next/font`; `server-only` `lib/api.ts`; hero placeholder + streamed API status |
| Config | `.env.example` in both repos, every variable documented (SETUP.md §3–4) |
| Docs | `README`, `AGENTS.md`, `ARCHITECTURE.md`, `API.md`, `DATABASE.md`, `SETUP.md` |
| Quality | Pint + PHPStan level 8 + Pest (19 tests, incl. module-convention and architecture tests); ESLint + `tsc` + Vitest (admin 9, site 5); Playwright smoke test |

**Done when:** `GET /api/v1/health` returns the envelope; the admin and frontend dev servers both render a page that uses the tokens; CI passes on the first PR.

---

## Phase 1 — Access, roles & admin shell ✅ done 2026-10-08

**As built** (where it differs from the plan below):
- RBAC and the audit log follow the in-house `decent-pos` modules instead of spatie/laravel-permission and spatie/activitylog: one role per user (`users.role_id`, as Figma 26 shows), a `{module}.{action}` registry seeded from Figma 26, `permission:` middleware, `Gate::before` for registry keys and Super Admin, an append-only `audit_logs` table written by `AuditRecorder`.
- 2FA is required **per role** (`roles.requires_two_factor`, all four built-in roles on), matching Figma 25's "Require 2FA" banner. TOTP is RFC 6238 in-house (`Uptms\Auth\Services\Totp`, tested against the RFC vectors) with single-use recovery codes and replay protection.
- The "Keep me signed in" checkbox of Figma 01 is left out: a remember cookie would defeat the 30-minute idle sign-out that protects patient data.
- `uptms.settings` moves to Phase 2 with the Clinic settings screen (nothing in Phase 1 reads settings).
- The audit log's "Location" column (Figma 27) shows the IP address: there is no GeoIP service, and adding one would send staff IPs to a third party.
- Top-bar global search, notifications and help arrive with the features they serve; nav items for later phases open a "coming in Phase N" page.
- OpenAPI-generated TS types are not wired yet; admin types are hand-written next to each feature (`features/*/types.ts`). Carried to Phase 2.


**Goal:** staff can sign in securely and permissions are enforced. The public site has its real header, footer and menus.

| Area | Deliverables |
| --- | --- |
| Backend | Users (soft delete); **spatie/laravel-permission** with roles Super Admin/Admin/Manager/Staff and the permission matrix from Figma 26; policies; `Gate::before` for Super Admin; Sanctum **SPA cookie auth**: login, logout, `/auth/me` (user + permissions), forgot/reset, login throttling; **TOTP 2FA**; 30-min idle timeout; **audit log** (spatie/activitylog), including logins; Users & Roles API |
| Admin | Component library first (§26): Button, Input, Select, Textarea, Checkbox, Radio, Toggle, Badge, Avatar, Tabs, Card, **DataTable** (server-side sort/filter/paginate, URL-synced), Pagination, SearchInput, FilterBar, Modal, Drawer, Dropdown, ConfirmDialog, Toast, EmptyState, Skeleton, ErrorState. **AppLayout** (navy shell + sheet, Sidebar, TopBar, PageHeader with rail). Screens **01 Login, 02 Forgot password, 25 Users, 26 Roles & permissions, 27 Audit log**, plus the 403 / session-expired states from 28. Central error handling: 401 → login, 403 → no-access view, 419 → refresh CSRF and retry, 422 → field errors, 429/5xx/offline → toast |
| Frontend | Shared site components: SiteHeader / SiteHeaderCompact, SiteMenu (tablet sheet / mobile full screen, focus trap), SiteFooter, MobileCtaBar, Button, Panel, SectionRail, FaqItem; layout at 1440/768/390 with placeholder copy |
| Tests | Pest: auth flows, throttling, 2FA, a **policy test for every permission × role**; Vitest: DataTable, form validation; RTL: Login |

**Done when:** a Staff user can't reach Users or Audit (403 from the API, hidden in the UI). An Admin can invite a user, who sets a password and 2FA. The public shell matches Figma at all three breakpoints.

---

## Phase 2 — Content & public site ✅ done 2026-10-08

**As built** (where it differs from the plan below):
- A shared `uptms.publishing` module holds what every content module needs: publish status, immutable revisions, the public read cache (one version bump per publish), the block-source registry and the signed revalidation webhook. The planned `uptms.clinic` is folded into `uptms.settings`.
- Pages are built from a **library of 20 block types** that mirror the Figma sections. Each type's field schema lives in PHP; the admin generates its forms from it and the API validates with it. Staff edit a working copy (autosave, `lock_version` against lost updates); the site only ever reads the published snapshot. Drafts may be incomplete; publishing and scheduling require every field.
- The quiz is **one validated document** (questions, choices, first-match rules) instead of `quiz_*` tables, because it is always edited and published whole. Scoring runs in the browser; no endpoint accepts answers.
- **Preview** uses a signed 30-minute token and the site's dynamic `/preview` route instead of Next.js Draft Mode, so normal pages stay fully static. Revalidation expires tags immediately (`{ expire: 0 }`) rather than serving stale-while-revalidate.
- Section numbers ("01 —— LABEL") are assigned in page order, so reordering sections renumbers them. Button text and links may use `{phone}`, `{hours}`, `{email}` and `tel:clinic`, filled from Clinic settings.
- Media: JPG/PNG/WebP/PDF only (no SVG: script risk); images are re-encoded (metadata stripped, ≤ 2400 px) with AVIF + WebP at 480/960/1600 px. The "Replaced" reason of Figma 18b and the monthly unused-files email are not built (replacing keeps the same file id; no mail backend yet).
- The Contact form is UI only, as planned: it validates and explains that online requests open in Phase 3; nothing is sent. The Figma "Template preview" notice and "Preview My Consultation Request" label are seeded verbatim.
- SEO, Social and Schema tabs and every SEO score show "—" / "Phase 4". OpenAPI-generated TS types are still not wired (hand-written `types.ts` per feature); carried to Phase 3.
- **Content the clinic must confirm:** the Figma design defines no link targets. Links to the clinic's original pages (depression, OCD, team, testimonials, insurance listings) point at the main site URL until real URLs are supplied; NIMH and FDA links use their public pages. Figma designs one quiz result; the other four results (insurance, loved one, OCD, "learn more first") are drafted from the Figma 16 rule titles and need clinical review. Two testimonials read as truncated excerpts in the design ("Professional, caring", "TMS helped me get it back.").


**Goal:** every public page renders from Laravel data and is editable in the admin. Publishing updates the live site within seconds.

| Area | Deliverables |
| --- | --- |
| Backend | Migrations, models, factories and seeders for `pages`, `page_blocks`, `revisions`, `conditions`, collections (`team_members`, `faqs`, `testimonials`, `insurance_partners`, `content_items`), `menus`/`menu_items`, `settings`, `business_hours`, `quiz_*`, **media** (`media`, `media_folders`, `media_variants`, `media_usages`, 30-day trash). **Seed every piece of Figma copy.** Public read API (`/public/pages/{slug}`, `/public/settings`, …), cached. Publish / schedule / revision actions; `ContentPublished` event → queued **revalidation webhook** to Next.js. Media pipeline: MIME + size validation, EXIF strip, WebP/AVIF variants, focal point, usage tracking (blocks delete while used), unused detection |
| Admin | **06 Pages, 07 Page editor · Content** (block outline, block forms, live preview), **13 Conditions, 14–15 Collections, 16 Quiz builder, 17 Navigation menus, 18 / 18b Media library** (upload tray, alt-text queue, unused filter, clean-up), **24 Clinic settings** |
| Frontend | **Home, TMS, Contact (form UI only), Quiz** (client-side scoring, nothing sent), `/conditions/[slug]` detail pages; Server Components by default; client islands only for the menu, quiz, form and map facade; `"use cache"` + cache tags; `/api/revalidate` route secured with HMAC |
| Tests | Pest: publish/schedule, revisions, media validation & usage lock, public API shape; Vitest: block forms, quiz scoring; Playwright: edit hero → publish → live site shows the change |

**Done when:** the four Figma pages match the design at 1440/768/390, all content comes from the API, and an edit published in the admin appears on the site without a redeploy.

---

## Phase 3 — Consultation requests ✅ done 2026-10-08

**As built** (where it differs from the plan below):
- **Search without plaintext.** Encrypted columns can't be searched with SQL, so each request stores keyed HMAC tokens (name words, full phone, last four digits, email) in `search_index`. Staff can search by a whole name word, phone number, last four digits, email or reference, but not by part of a name.
- **No IP address is stored**, not even hashed. The Next.js server forwards the visitor's IP with a shared site token (`FRONTEND_SITE_TOKEN` = `SITE_TOKEN`); Laravel uses it only for the rate limit and Turnstile. "Where it came from" keeps coarse labels: the page, the previous page on the site, the referring host or UTM source, and "Mobile · Safari".
- **Deleting is permanent** (no soft delete) for both manual deletion and retention; the audit log keeps the reference. Spam is purged after 30 days, everything else after Clinic settings → Privacy → retention days (default 365).
- **CSV export streams synchronously** (≤ 50,000 rows, like the audit-log export) instead of a queued job: at the clinic's volume it finishes in well under a second. It needs `consultations.export` *and* `view_all`.
- **The follow-up target** is closing time of the next business day, from the office hours and closures in Clinic settings, in the clinic's zone (`CLINIC_TIMEZONE`). "Overdue" = still new after it. Staff can move the follow-up time.
- **Workflow extras:** whoever first marks an unassigned request contacted or scheduled becomes its owner; "contacted → new" undoes a mistaken click; closed requests can be re-opened. A second submission with the same phone within 10 minutes returns the first request's reference instead of a duplicate.
- **Form fields:** the Figma form plus two optional ones the admin design shows: "Best time to call" and "Referring provider" (only for provider referrals). Topics now come from the consultation module (block source `consultation-form`) rather than free text in the page block, so a submitted topic is always one the inbox understands. Self-check results link to `/contact?topic=…#consultation-form` with the topic chosen.
- **Dashboard (Figma 03):** organic clicks, SEO health and the SEO to-do card wait for Phase 4 (shown as "—"); "New post" waits for posts. The publishing queue lists scheduled and draft pages.
- Figma 05's "@mention a teammate" in notes is not built; notes are plain text. Admin mobile screens 29–31 stay in Phase 5.
- **Not verified here:** Turnstile with real keys (tests fake Cloudflare's answer) and email delivery (tests fake the mailer; e2e uses the array mailer).

**Goal:** the clinic's main workflow runs end to end, with health-privacy safeguards.

| Area | Deliverables |
| --- | --- |
| Backend | `consultation_requests` + `consultation_request_notes`, with name/phone/email/message **encrypted** (encrypted casts); `POST /public/consultation-requests` (FormRequest validation, honeypot, Cloudflare Turnstile, `throttle:5,1`); status workflow in `TransitionConsultationStatus` (status changes never trust client values); assignment; notes; **view logging**; queued staff notification (link-only, or the whole request once Privacy → "Include the request in the alert" is on); CSV export (permission-gated, queued); retention purge job; dashboard aggregates API |
| Admin | **03 Dashboard** (call-back queue hero, metrics, chart, recent requests), **04 Consultations** (tabs, filters, bulk actions), **05 Request detail** (status, assign, notes, privacy, activity); empty/loading/error states from 28 |
| Frontend | Contact form wired: client + server validation, submitting / success / error / rate-limited states; quiz result buttons link to the form; no analytics on `/contact` or `/quiz` |
| Tests | Pest: submit happy path, validation, throttling, encryption at rest (raw DB value is not plaintext), Staff sees only assigned requests, audit entry on view, purge job; Playwright: submit on site → appears in inbox → mark contacted |

**Done when:** a request submitted on the live site appears in the admin within seconds, the email alert carries what Clinic settings → Privacy says it may, and every view shows up in the audit log.

---

## Phase 4 — Posts & full SEO

**Goal:** patient resources and every SEO feature in the Figma screens, wired into the public site.

| Area | Deliverables |
| --- | --- |
| Backend | `posts`, `categories`, `tags`, clinical-review workflow (reviewer + review dates; health posts can't publish unreviewed); polymorphic **`seo_meta`**; SEO analysis service (title/description pixel length, keyphrase checks, readability) shared by every editor; JSON-LD graph builder (MedicalClinic, MedicalWebPage, Physician, FAQPage, BreadcrumbList, Article); `redirects` (regex, hits, loop/chain detection, auto-created on slug change); `not_found_logs` (no IPs); sitemap generator + IndexNow ping; robots.txt (staging forced to `Disallow: /`); SEO site audit job; Google Search Console integration |
| Admin | **11 Posts, 12 Post editor, 08 SEO tab, 09 Social tab, 10 Schema tab, 19 SEO overview, 20 Redirects, 21 404 monitor, 22 Sitemap & robots, 23 SEO settings** |
| Frontend | `/resources` + `/resources/[slug]`; `generateMetadata` from `seo_meta` (title templates, canonical, robots, OG, X card); JSON-LD per page; `sitemap.ts` and `robots.ts` from the API; redirects applied in `proxy.ts`; 404 page that logs misses to the API |
| Tests | Pest: analysis rules, redirect loop detection, sitemap contents, schema output validated against fixtures; Playwright: change a slug → old URL returns 301 → new URL is in the sitemap |

**Done when:** every public URL has correct metadata and JSON-LD, the sitemap matches published content, a renamed page redirects automatically, and the SEO overview score matches the audit job.

---

## Phase 5 — Hardening & launch

**Goal:** pass the guideline's final checklist (§34) and go live.

| Area | Deliverables |
| --- | --- |
| Responsive | Admin **tablet behaviour** (not designed in Figma yet): sidebar becomes an icon rail below 1280 px and a drawer below 1024 px; tables become cards below 768 px. Admin mobile screens 29–31 built. Public site re-checked at 390/768/1024/1440/1920 |
| Accessibility | axe in CI on every page; keyboard pass (menu, dialogs, drawer, DataTable, quiz); focus states; contrast; error messages linked with `aria-describedby` |
| Performance | No N+1 queries (Larastan + query-count assertions); indexes reviewed; Lighthouse ≥ 95 on public pages; CWV budget (LCP < 2.5 s, INP < 200 ms, CLS < 0.1); admin feature routes lazy-loaded |
| Security | OWASP pass; security headers / CSP; upload fuzzing; rate limits reviewed; dependency audit; secrets only in env; backups + restore drill |
| Launch | Production deploy (see Risks), redirects from the current uptms.com URLs imported, GSC verified, sitemap submitted, uptime monitoring; docs finalized (`README`, `ARCHITECTURE`, `API`, `DATABASE`, `SETUP`, deployment runbook) |

**Done when:** every box in guideline §34 is checked with evidence (CI links, Lighthouse and axe reports, test run).

---

## Guideline compliance check

| § | Requirement | Where it's covered | Status |
| --- | --- | --- | --- |
| 1 | Analyze before coding | `ANALYSIS.md` §0–10, Figma admin design | ✅ Done |
| 2 | Laravel layers, `/api/v1`, logic out of controllers | Phase 0 (envelope, versioning) and every phase (Requests → Actions/Services → Resources) | ✅ Planned |
| 3–4 | React 19 admin, SaaS UI kit | Phase 1 component library; screens by phase | ✅ Planned |
| 5 | Next.js consumes the API, Server Components by default | Phases 2–4 | ✅ Planned |
| 6 / 32 | Design fidelity | Public: Figma Version C. Admin: Figma page "Admin · CMS" | ⚠️ Admin tablet and several public pages not designed (see Risks) |
| 7 | Fully responsive | Public: 3 breakpoints designed. Admin: desktop + 3 mobile screens | ⚠️ Admin tablet rules proposed in Phase 5, need sign-off |
| 8 | Migrations, FKs, indexes, soft deletes | Phases 2–4; `DATABASE.md` | ✅ Planned |
| 9 | Consistent REST + envelope + pagination | Phase 0 envelope; DataTable query contract `search`, `filter[]`, `sort`, `page`, `per_page` | ✅ Planned |
| 10–11 | Sanctum, roles & permissions from Laravel | Phase 1 (SPA cookie auth, spatie, policies, `/auth/me` permissions) | ✅ Planned |
| 12 | Forms: client + server validation and all states | Zod + FormRequest everywhere; 422 → field errors | ✅ Planned |
| 13 | Secure uploads | Phase 2 media pipeline | ✅ Planned |
| 14 | Server-side search/filter | DataTable is server-driven only | ✅ Planned |
| 15 | Minimal global state | TanStack Query + auth context only | ✅ Planned |
| 16 | Central error handling | Phase 0 (API) + Phase 1 (admin client) | ✅ Planned |
| 17 | Performance | Caching/ISR (Phase 2), budgets (Phase 5) | ✅ Planned |
| 18 | Security | Phase 1 auth, Phase 3 PHI controls, Phase 5 review. "Client pricing/totals" doesn't apply (no payments) | ✅ Planned |
| 19–20 | Code quality, TypeScript, shared types | OpenAPI spec exported by Scramble (`composer openapi`); TS types generated from it into each app from Phase 1, so admin and site can't drift from the API | ✅ Planned |
| 21 | `.env.example` | Phase 0 | ✅ Planned |
| 22 | Tests | Pest, Vitest + RTL, Playwright in each phase | ✅ Planned |
| 23 | SEO, sitemap, robots | Phase 4 | ✅ Planned |
| 24 | Accessibility | Built in from Phase 1; audited in Phase 5 | ✅ Planned |
| 28 | Project structure | `uptms-ad` (Laravel + `resources/js/admin` + `packages/` modules, per decent-pos) and `uptms-app` (Next.js) | ✅ Adapted on request (admin SPA inside Laravel, same origin) |
| 29 | Phase order | Vertical slices instead of layer-by-layer (see "How the phases are cut") | ⚠️ Deliberate deviation, needs approval |
| 30–31 | Per-feature workflow, read before editing | Applied in every phase | ✅ |
| 33 | Docs | Skeletons in Phase 0, kept current each phase | ✅ Planned |
| 34 | Final checklist | Phase 5 exit criteria | ✅ Planned |
| — | Build tooling: Vite | Admin uses Vite. Next.js uses its own built-in bundler (Turbopack); Vite doesn't apply there | ℹ️ Clarification |
| — | "Customer" frontend | The design has no customer accounts; the public site is anonymous | ℹ️ Confirm |

---

## Decisions needed before Phase 0

1. **Phase order.** Approve vertical slices (recommended) or keep the guideline's layer-by-layer order.
2. **Hosting & BAA.** Where Laravel and MySQL run (e.g. a VPS via Forge, or AWS with a BAA), where Next.js runs, and which email provider sends alerts. This drives the Sanctum domain setup and HIPAA compliance.
3. **Missing designs.** Condition detail, resources list/article, 404 and admin tablet. Options: design them in Figma first (recommended for the public pages), or build them from the existing Version C components and review in the browser. The privacy policy (`/privacy`, Legal template, seeded by `PagesContentSeeder`, linked from the footer's legal menu) was built this way; the clinic's counsel should review its text.
4. ~~**Contact form behaviour.**~~ The form sends from Phase 3; the "Template preview" notice is replaced by call-back copy. Confirm the wording of the notice and success message.
5. **Domain & migration.** Does this replace the current uptms.com? If yes, export the old URLs for redirects (Phase 4).
6. **Accounts & keys.** Cloudflare Turnstile, Google Maps, Google Search Console access, email provider.
