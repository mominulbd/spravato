# UPTMS — Architecture

**Status:** Phases 0–3 implemented 2026-10-08. Sections marked *planned* describe later phases.
**Companions:** [API.md](API.md) · [DATABASE.md](DATABASE.md) · [SETUP.md](SETUP.md) · [ROADMAP.md](ROADMAP.md) · [ANALYSIS.md](ANALYSIS.md)

Priority order for trade-offs: **correctness → patient privacy & security → maintainability → performance → UX → extensibility.**

---

## 1. Repositories

Two repositories, side by side in `~/Sites`:

| Repository | What | Stack |
| --- | --- | --- |
| `uptms-ad` (this one) | REST API, admin SPA, database, jobs | Laravel 13 · PHP 8.4 · MySQL · Sanctum · React 19 + Vite + Tailwind 4 |
| `uptms-app` | Public website (Home, TMS, Contact, Quiz, Privacy, …) | Next.js 16 App Router · Cache Components · Tailwind 4 |

The admin SPA lives inside the Laravel app, following the module and SPA conventions of `~/Sites/decent-pos`. The guideline's separate `admin/` folder (§28) is replaced by `resources/js/admin`. The admin still talks to Laravel only through `/api/v1` (§3), and Blade renders an empty shell with no domain data.

## 2. System overview

```text
┌──── Browser: staff ─────────────┐        ┌──── Browser: patients ────────┐
│ /admin  React admin SPA         │        │ uptms.com  (HTML + RSC stream) │
│ TanStack Query · React Router   │        └──────────────┬─────────────────┘
└──────────────┬──────────────────┘                       │
               │ same origin, Sanctum session cookie      │
               │ + CSRF, JSON                             ▼
┌──────────────▼──────────────────────────┐   ┌──────────────────────────────┐
│ Laravel 13 (uptms-ad)                   │◄──┤ Next.js 16 (uptms-app)       │
│  /api/v1/admin/*   staff, authenticated │   │ Server Components fetch      │
│  /api/v1/public/*  anonymous, read-only │   │ /api/v1/public/* server-side │
│  /api/v1/health                         │   │ 'use cache' + cacheTag;      │
│  packages/* (modules) · app/Support     │──►│ webhook revalidates on       │
│  queue workers (database → Redis)       │   │ publish (Phase 2)            │
└──────────────┬──────────────────────────┘   └──────────────────────────────┘
               │
            MySQL 9 (uptms)
```

- The admin is same-origin, so auth uses httpOnly session cookies. No tokens live in JavaScript and there are no CORS rules for the admin.
- The public site calls the API **server-side only**: `API_URL` is never exposed to the browser and nothing on the public site authenticates. The consultation form posts through a Next.js server action (`lib/consultation.ts`) that forwards to `/api/v1/public/consultation-requests`, so Laravel stays the single validator. With the shared site token it also forwards the visitor's IP and user agent (`App\Support\Http\SiteClient`) for rate limiting and Turnstile; neither is stored.

## 3. Modules (`packages/`)

Every domain module is a package (`aminulbd/laravel-packages`), live only when its id is in `config/packages.php → enabled`.

```text
packages/<module>/
├── index.php             id uptms.<snake>, autoload Uptms\<Pascal>\ → src/, provider, require
├── migrations/
├── routes/
│   ├── api.php           → /api/v1/…          middleware: api
│   ├── api_public.php    → /api/v1/public/…   middleware: api, public   (Next.js reads)
│   └── api_admin.php     → /api/v1/admin/…    middleware: api, admin    (staff)
├── src/
│   ├── <Pascal>ServiceProvider.php   extends App\Support\Packages\ModuleServiceProvider
│   ├── Actions/  Services/  Models/  Policies/  Domain/  Events/
│   └── Http/{Controllers,Requests,Resources}
├── seeders/
└── tests/{Feature,Unit}
```

`ModuleServiceProvider` does the wiring. A module only declares `$policies`, `$morphMap`, `$observers`, `$listen` and `$scoped` as data; routes, migrations and commands are loaded by convention. Route names are prefixed `<module>.`, `public.<module>.` and `admin.<module>.`.

**Planned catalogue** (dependencies point down; `tests/Unit/ArchitectureTest.php` gets a rule per module):

| Phase | Module | Owns |
| --- | --- | --- |
| 1 ✅ | `uptms.audit_log` | append-only `audit_logs`, `AuditRecorder`, `Auditable` model trait, list + CSV export |
| 1 ✅ | `uptms.permissions` | roles, the `{module}.{action}` registry (Figma 26), `permission:` middleware, `Gate::before`; pushes `permission:admin.access` onto the `admin` group |
| 1 ✅ | `uptms.users` | staff accounts (one role each), invitations, activation, soft delete, escalation guards |
| 1 ✅ | `uptms.auth` | sign-in with TOTP challenge, recovery codes, password reset, `/auth/me`; pushes `RequireTwoFactor` and `TouchLastSeen` onto the `admin` group |
| 2 ✅ | `uptms.publishing` | shared content kernel: `PublishStatus`, immutable `revisions`, `PublicCache` (one version bump per publish), the `BlockSource` registry other modules fill, `Reorderer`, `ContentPublished` → signed `RevalidateFrontend` webhook |
| 2 ✅ | `uptms.media` | content-checked uploads re-encoded without metadata, queued AVIF/WebP variants, focal point, `MediaUsageRecorder` (blocks deleting placed files), folders, 30-day trash; `MediaPresenter` is how other modules show images |
| 2 ✅ | `uptms.settings` | typed `SettingsRegistry` (defaults = the clinic's real details), office hours and closures with the website's summaries (the planned `uptms.clinic` is folded in here) |
| 2 ✅ | `uptms.collections` | team, FAQs, testimonials, insurance partners, trust points, journey steps — one registry-driven API (`CollectionType`), each also a block source |
| 2 ✅ | `uptms.conditions` | condition cards and detail pages, clinical review before publishing; block source `conditions` |
| 2 ✅ | `uptms.pages` | the block library (`BlockTypes`, field schema shared by admin forms and validation), working copy with `lock_version`, revisions, publish/schedule, signed previews, `PageDirectory` for other modules |
| 2 ✅ | `uptms.menus` | header, mobile, section and footer menus linking to pages by id; block source `menus` |
| 2 ✅ | `uptms.quiz` | the self-check as one validated document (docs/QUIZ.md), drafted and published; `QuizFlow` checks and scores submissions; block source `quiz` |
| 3 ✅ | `uptms.consultations` | encrypted requests with a keyed search index, `TransitionConsultationStatus` workflow, assignment (via `Users\Services\StaffDirectory`), notes, audited views (activity via `AuditLog\Services\AuditTrail`), queued staff alert (the request itself, or a link only — Clinic settings → Privacy) and a queued receipt to the person (reference only), CSV export, dashboard overview, retention purge; self-check submissions scored through `Quiz\Services\QuizFlow`; block source `consultation-form` |
| 4 | `uptms.posts`, `uptms.seo`, `uptms.redirects` | resources, SEO meta, schema, sitemap, redirects, 404 log |

## 4. Admin SPA (`resources/js`)

- `shared/api/client.ts` is the one fetch wrapper. It sends the same-origin cookie and `X-XSRF-TOKEN`, refreshes CSRF once on 419, unwraps the envelope, and throws `ApiError` (status, `code`, `fieldErrors`, `requestId`). `errors.ts` maps codes to human messages.
- Server state goes through TanStack Query (`queryClient.ts`), which retries network errors and 5xx only. UI state stays local. There is no global store unless a later phase proves it's needed (guideline §15).
- Routing: React Router with `basename: '/admin'`. `Root` (session-ended screen, offline banner, toast bridge) → `GuestOnly` sign-in pages | `RequireAuth` → `AppLayout` (sidebar, top bar, sheet) → feature pages, each behind `RequirePermission` (the 403 view of Figma 28).
- Central error handling (`admin/app/handleApiError.ts`, wired into the QueryClient caches): 401 → session ended; `TWO_FACTOR_SETUP_REQUIRED` → set-up; `ACCOUNT_INACTIVE` → signed out; failed mutations with 403/429/5xx/offline → toast; 422 stays with the form; failed queries render `ErrorState`. 419 is retried once by the client after refreshing CSRF.
- UI kit (`shared/ui`, Figma "Admin Components"): Button, IconButton, TextInput, Select, Textarea, SearchInput, Checkbox, Radio, Toggle, Badge, Count, Avatar, Tabs, Card, DataTable (server-sorted, grouped rows, skeleton/empty/error), Pagination, FilterBar, Dialog, Drawer, Dropdown, ConfirmDialog, Toast, EmptyState, ErrorState, NoAccessState, Skeleton, QrCode. Table state lives in the URL (`shared/hooks/useTableParams`).
- Fonts are bundled with `@fontsource-variable`, so the admin makes no external font requests.

## 5. Authentication & authorisation

- **Sessions.** Sanctum SPA cookie auth on the API origin. Sessions are encrypted, stored in the database, and expire after 30 idle minutes (`SESSION_LIFETIME`). The SPA mirrors that with an idle timer and shows "Signed out for privacy" (Figma 28). Deactivating a user, resetting their 2FA, removing them or resetting their password deletes their `sessions` rows.
- **Sign-in.** `POST /auth/login` checks the password. If the two-factor switch is on and the account has 2FA set up, it stores a 5-minute pending marker in the session and asks for a code; the session is only authenticated after `POST /auth/two-factor-challenge`. TOTP (RFC 6238, ±1 step, a used step cannot be replayed) or a single-use recovery code. Throttles: `login` (email+IP), `two-factor` (per account), `password-reset`, `invitations`.
- **2FA policy.** A site-wide switch, `security.two_factor_login` (Clinic settings → Security, `settings.manage`, audited), is **off by default** (`AUTH_TWO_FACTOR_LOGIN` sets the starting value). While off, sign-in never asks for a code and no role forces set-up; confirmed secrets are kept, so turning it back on needs no new set-up. Auth reads it through `TwoFactorGate` (the `Settings` service). While on, 2FA is required per role (`roles.requires_two_factor`). `RequireTwoFactor` sits on the `admin` group and answers `TWO_FACTOR_SETUP_REQUIRED` until the user confirms a code; `/auth/me` and the `/auth/two-factor*` routes stay reachable so the SPA can run set-up.
- **Invitations.** Admins create accounts without a password; the person gets a 7-day single-use link (`/admin/invitation/{token}`; only its sha256 is stored), chooses a password, and is sent on to 2FA set-up.
- **Four gates.** Route `permission:` → Form Request `authorize()` (policy) → Action (escalation guards) → scoped query. `Gate::before` answers every registry key from the role's grants and lets a Super Admin through everything else. `/auth/me` lists the permissions so the SPA can hide what the API would refuse.
- **Consultation scope.** `{consultation}` is bound through `ConsultationRequest::visibleTo()`: without `consultations.view_all` a person only resolves requests assigned to them, so anything else is `NOT_FOUND` before a controller runs. Assigning to others also needs `view_all`; export and delete need `consultations.export`. Whoever first marks an unassigned request contacted or scheduled becomes its owner.
- **Audit.** Every opening of a request's details (`consultation.viewed`), and every status, assignment, follow-up, note, export and deletion. Sign-ins (successful, failed — attributed to the account when the email exists — and blocked), sign-outs, 2FA and password changes, invitations, user and role changes (model diffs via `Auditable`, role/permission changes as `permission.changed`) and audit exports.

## 6. Public site (`uptms-app`)

- Server Components by default; client components only for the menu, quiz, consultation form and map facade.
- `lib/api.ts` (marked `server-only`) reads the envelope and throws `ApiError`; `lib/cms.ts` holds every cached read.
- Cache Components (`cacheComponents: true`): published content is read with `'use cache'` + `cacheLife('hours')` + `cacheTag('content', 'page:home' …)`. Laravel's `ContentPublished` event queues a webhook to `/api/revalidate`, signed with HMAC-SHA256 over `{timestamp}.{body}` (`FRONTEND_REVALIDATE_SECRET` = `REVALIDATE_SECRET`, five-minute window); the route expires the tags at once, so a publish shows on the next visit. Uncached reads are wrapped in `<Suspense>`.
- Pages are data: `app/page.tsx` (home) and `app/[...path]/page.tsx` render the published blocks of `GET /public/page?path=` through `components/blocks/BlockRenderer` (one component per block type). `/conditions/[slug]` renders condition detail pages. `/preview?path=&token=` renders a working copy for the admin's signed 30-minute token (dynamic, never cached or indexed).
- Content tokens: `{phone}`, `{hours}`, `{email}` and `tel:clinic` in block text and links are filled from Clinic settings (`lib/tokens.ts`). Rich text is a small Markdown dialect parsed into React elements; no HTML from the API is ever injected.
- Images come pre-optimised from Laravel (AVIF/WebP `srcset`, focal-point `object-position`) through `components/content/Picture`.
- Fonts come from `next/font/google`, self-hosted at build time, so patients' browsers never contact Google.
- Nothing is indexed until launch (`robots: noindex` in the root layout).

## 7. Shared design tokens

`resources/css/tokens.css` (here) and `app/tokens.css` (uptms-app) are identical Tailwind 4 `@theme` blocks generated from the Figma variables. They give both apps the same utilities: `bg-navy`, `text-royal`, `font-serif`, `text-admin-body`, `rounded-panel`, and so on.

## 8. Errors

One envelope, one renderer (`App\Support\Http\ExceptionRenderer`): validation → `VALIDATION_FAILED` 422; domain rules → the exception's own code; auth → `UNAUTHENTICATED` 401 or 419; policy → `FORBIDDEN` 403; missing → `NOT_FOUND` 404; throttled → `RATE_LIMITED` 429; anything else → `SERVER_ERROR` 500 with no internals unless `APP_DEBUG`. Every response carries `X-Request-Id`; errors repeat it in `meta.request_id` so the UI can show it.

## 9. Security & privacy baseline

- `SecurityHeaders`: `nosniff`, `SAMEORIGIN`, a strict referrer policy, and a CSP outside local/testing.
- `RedactSensitiveData` is tapped into the `single`, `daily` and `stderr` channels. It redacts passwords, tokens, cookies, authorization headers, and patient contact fields (`phone`, `email`, `dob`, `message`, `note`, …) by whole key segment.
- ESLint bans `dangerouslySetInnerHTML` in the admin.
- Consultation requests: name, phone, email, message, referring provider and notes use `encrypted` casts (AES-256-CBC with `APP_KEY`); lists show masked contacts ("Daniel R. · (253) •••-0198"); the admin never keeps a decrypted request in its cache after leaving the screen; staff alerts carry the request to the clinic's own notification address while Clinic settings → Privacy → "Include the request in the alert" is on, and the reference and a link only when it is off; the receipt emailed to the person carries the reference and the clinic's own details only. Nothing in either mail reaches the audit log or the application log. Spam protection: honeypot, Cloudflare Turnstile (when keys are set; an outage lets requests through), `throttle:public-forms` per visitor, and a 10-minute duplicate check on the phone hash.
- Third-party scripts on the public site are opt-in and listed: Turnstile, loaded on the Contact page alone when its key is set, and one Google Tag Manager container, loaded only while SEO settings → Analytics is switched on with a container id. While "Keep tags off /contact and /quiz" is on (the default), `GET /public/seo` returns those paths in `tag_manager.exclude_paths` and the site renders no container there, so nothing a patient types into the consultation form or answers in the self-check reaches Google (HHS online-tracking guidance). Consent and the tags themselves live in Tag Manager.

## 10. Performance guards

`Model::preventLazyLoading()` and `preventSilentlyDiscardingAttributes()` throw outside production. Named rate limiters: `login`, `admin`, `public`, `public-forms`.
