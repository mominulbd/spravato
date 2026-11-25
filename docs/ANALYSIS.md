# UPTMS — Phase 0: Design Analysis & Architecture

Source: Figma `UPTMS` (file `ij7CwMEI0miNBTQn0lF0AE`)
- Frontend design: https://www.figma.com/design/ij7CwMEI0miNBTQn0lF0AE/UPTMS?node-id=25-2
- Admin design: https://www.figma.com/design/ij7CwMEI0miNBTQn0lF0AE/UPTMS?node-id=52-2
Status: proposal — awaiting approval before Phase 1.

---

## 0. What the design actually is

The Figma file is a **public marketing / patient-education website** for *University Place TMS Clinic & General Psychiatry* (Deep TMS for depression and OCD, University Place / Greater Tacoma, WA).

| Present in Figma | Not present in Figma |
| --- | --- |
| 4 public pages × 3 breakpoints (1440 / 768 / 390) | **Any admin console screen** |
| Mobile/tablet menu-open states for every page | Login / auth screens |
| Component sheet (buttons, form fields, FAQ, quiz choice, header, footer, mobile CTA bar, menu link) | Dedicated depression / OCD guide pages (linked from Home as "Read the … guide →") |
| Design tokens (colours + 13 text styles) | Form success / error / loading states |
| | 404 / error page, privacy / legal pages |

Consequence: the **admin console is a CMS + lead inbox we must design ourselves**, using the site's tokens. Everything the admin manages is derived from the content that appears on the public pages.

---

## 1. Design Analysis

### 1.1 Public pages (Next.js)

| Page | Frames (D / T / M) | Sections |
| --- | --- | --- |
| **Home** `/` | 25:128 · 45:1070 · 45:1758 | Hero (eyebrow, display headline w/ italic accent, 2 CTAs, image + founder chip + floating card, trust strip) → Section nav (7 anchor pills) → 01 Why TMS (copy + "Does this sound familiar?" checklist panel) → 02 Specialties (4 condition cards, "Unsure where to begin?" close, Depression guide feature, OCD guide feature) → 03 TMS overview (figure + copy + 2 stats + risk note) → 04 Care team (founder card + 2 support staff) → Consultation band → 05 Insurance (4 numbered questions, 10-logo wall, disclaimer, CTA) → 06 Why UPTMS (6 value tiles) → 07 Journey (4 steps) → 08 Patient voices (4 testimonials + disclaimer) → 09 FAQ (16-item accordion + self-check aside) → 10 Location (map embed, address, hours, phone, email) → Final CTA → Footer |
| **TMS** `/tms` | 25:146 · 44:779 · 44:1148 | Hero → 01 The basics (copy + "What to bring" panel) → 02 Areas of care (4 long-form condition blocks) → 03 Treatment journey (3 phases + benefits & risks panel) → 04 Insurance & local team → 05 Treatment differences (depression vs OCD) → 06 Appointment planning (figure + copy) → 07 Treatment FAQ (10 items) → Final CTA → Footer |
| **Contact** `/contact` | 25:164 · 43:630 · 43:841 | Hero (hours, team mini-cards) → 01 Consultation (copy + list + email note + **consultation request form**) → 02 Location → Final CTA → Footer |
| **Quiz** `/quiz` (self-check) | 25:182 · 42:557 · 41:483 | Assessment (intro + 3-question form w/ progress badge + result panel) → 02 Keep exploring (3 link cards) → Footer |
| Menu-open overlays | 49:1532 … 49:2182 | Tablet: dropdown sheet + scrim; Mobile: full-screen menu |

### 1.2 Admin screens (to be designed — no Figma)

Login · Forgot/Reset password · Dashboard · Consultation requests (list, detail, status workflow) · Pages & sections editor (Home / TMS / Contact / Quiz copy) · Conditions/Specialties · Team members · FAQs (grouped by page) · Testimonials · Insurance partners · Journey/process steps · Value props · Quiz builder (questions, choices, result rules) · Clinic settings (address, hours, phone, email, map, social, legal disclaimers) · SEO (per-page meta, OG images, redirects) · Media library · Users · Roles & permissions · Audit log · Profile.

### 1.3 Design system (from Figma variables)

| Token | Value | | Text style | Spec |
| --- | --- | --- | --- | --- |
| `navy` | `#0E304E` | | Display/XL | Source Serif 4 · 76/1.04 · −2.5 |
| `royal` | `#183CDC` (primary CTA) | | Display/L | Source Serif 4 · 56/1.08 · −2 |
| `sky` | `#C8E1F9` | | Heading/M | Source Serif 4 · 36/1.15 · −1.5 |
| `sky-soft` | `#EEF5FD` | | Heading/S | Source Serif 4 · 26/1.22 · −1 |
| `ink` | `#1D231C` | | Title | Nunito Sans Bold · 19/1.35 |
| `ink-soft` | `#4B524A` | | Body/L · M · S | Nunito Sans · 20 · 17 · 15 / ~1.55 |
| `line` | `#D9D6CE` | | Caption | Nunito Sans · 13/1.5 |
| `stone` | `#E1DFD9` | | Eyebrow | Nunito Bold 13 · tracked, uppercase |
| `paper` | `#F6F6F6` | | Nav / Button / Link | Nunito SemiBold/Bold 16 |
| `white` | `#FFFFFF` | | | |

Visual language: large rounded "panels" (~32px radius) inset 24px from viewport, navy and sky-soft panel variants, pill buttons, italic serif accents inside headlines, numbered section rails (`01 —— LABEL`).

### 1.4 Reusable components (public site)

`SiteHeader` (Page=Home/TMS/Contact/Quiz active pill; utility bar) · `SiteHeaderCompact` (tablet/mobile + menu toggle) · `SiteMenu` (tablet sheet / mobile fullscreen, `MenuLink` default/current) · `SiteFooter` / `SiteFooterCompact` · `MobileCtaBar` (sticky Call / Self-check) · `Button` (Primary, Secondary, Light, OutlineLight, Soft) · `TextLink` (arrow) · `SectionRail` · `Panel` (navy / sky / white) · `ListItem` (dot bullet) · `FaqAccordion` + `FaqItem` (closed/open) · `FormField` (Input / Select / Textarea) · `QuizChoice` (default/selected radio card) · `SectionNav` (anchor pills) · `TrustStrip` · `ConditionCard` · `ConditionFeature` (guide block w/ Q&A callout) · `TeamCard` (founder / staff variants) · `PersonChip` (avatar + name + role) · `StatPair` · `NumberedQuestionList` · `LogoWall` · `ValueTile` · `StepCard` (journey) · `TestimonialCard` · `LocationPanel` (map + hours + contact) · `ConsultationBand` · `FinalCta` · `Disclaimer`.

### 1.5 Responsive behaviour observed

- Desktop 1440: full header with centered pill nav + "Free TMS Self-Check" CTA; 2-column hero/rows; 4-up cards.
- Tablet 768: compact header (logo + CTA + menu button), menu opens as sheet over scrim; grids collapse to 2-up; footer reflows to 2 columns.
- Mobile 390: compact header (logo + menu), full-screen menu, **sticky bottom CTA bar** (Call Us / Free Self-Check), everything single column, section-nav pills scroll horizontally.

### 1.6 States the design does not specify (we will define in the existing visual language)

Consultation form: field-level errors, submitting, success confirmation, server failure, rate-limited. Quiz: unanswered/partial (badge shows "n of 3 answered"), result variants per answer combination (only one "Example result" shown). FAQ: keyboard/focus. Map: loading/consent fallback. Image loading. 404 & 500 pages.

---

## 2. Feature Matrix

| Feature | Admin | Frontend | Backend API | Database |
| --- | --- | --- | --- | --- |
| Consultation request (lead) | Inbox, filter, status, notes, assign, export | Contact form w/ validation, success/error | `POST /public/consultation-requests` (throttled, honeypot/captcha); admin CRUD | `consultation_requests`, `consultation_request_notes` |
| Self-check quiz | Edit questions, choices, result rules | Client-side scoring, nothing sent (per design copy) | `GET /public/quiz` (config only) | `quiz_questions`, `quiz_choices`, `quiz_results` |
| Page copy (heroes, section headings, CTAs) | Section editor per page, preview, publish | Rendered via Server Components | `GET /public/pages/{slug}`; admin CRUD | `pages`, `page_sections` (typed JSON) |
| Conditions / areas of care | CRUD, ordering, publish | Home cards + feature blocks, TMS long-form | `GET /public/conditions` | `conditions` |
| Care team | CRUD, photo upload, ordering | Founder card, staff cards, person chips | `GET /public/team-members` | `team_members` |
| FAQs | CRUD, group by page, ordering | Accordions on Home & TMS | `GET /public/faqs?page=` | `faqs` |
| Testimonials | CRUD, publish, ordering | Patient voices | `GET /public/testimonials` | `testimonials` |
| Insurance partners | CRUD, logo upload, ordering | Logo wall | `GET /public/insurance-partners` | `insurance_partners` |
| Journey / treatment steps, value props, checklists | CRUD lists | Steps, tiles, bullet lists | delivered inside page payload | `content_items` (typed lists) |
| Clinic settings | Address, phone, email, hours, map, footer, crisis disclaimer | Header utility bar, footer, location panel, mobile CTA | `GET /public/settings` | `settings`, `business_hours` |
| SEO | Per-page title/description/OG image, redirects | `generateMetadata`, sitemap, robots, JSON-LD (`MedicalClinic`, `FAQPage`) | inside page payload; `GET /public/redirects` | columns on `pages`, `redirects` |
| Media | Library, upload, alt text, replace/delete | `next/image` | `POST /admin/media` | `media` |
| Auth | Login, logout, forgot/reset, profile | — | `/auth/*` | `users`, `password_reset_tokens`, `sessions` |
| Roles & permissions | Manage roles, assign permissions | — | `/admin/roles`, `/admin/permissions` | spatie tables |
| Audit log | View/filter | — | `GET /admin/audit-logs` | `activity_log` |
| Dashboard | KPIs, new-request trend, recent requests, content status | — | `GET /admin/dashboard` | aggregates |
| Cache revalidation | Automatic on publish | On-demand ISR | Job → `POST {next}/api/revalidate` | — |

---

## 3. Database Architecture (MySQL 8+, utf8mb4)

All content tables: `id` BIGINT PK, `created_at`, `updated_at`; `deleted_at` where noted (soft delete); `sort_order` INT + `is_published` BOOL indexed together for ordered public listings.

| Table | Key fields | Relations / indexes |
| --- | --- | --- |
| `users` | name, email (unique), password, is_active, last_login_at, two_factor_* (nullable) | soft delete |
| `roles`, `permissions`, `model_has_roles`, `role_has_permissions`, … | spatie/laravel-permission | — |
| `pages` | slug (unique: home, tms, contact, quiz), title, meta_title, meta_description, og_image_id → media, canonical_url, noindex, status enum(draft, published), published_at | |
| `page_sections` | page_id FK, key (e.g. `hero`, `why-tms`), type, content JSON, sort_order, is_visible | unique(page_id, key); index(page_id, sort_order) |
| `conditions` | slug (unique), number_label, short_title, clinical_name, summary, card_link_label, feature_eyebrow, feature_title, feature_body (rich), callout_question, callout_answer, detail_body (rich, TMS page), source_url, image_id → media, sort_order, is_published | soft delete |
| `team_members` | name, credentials, role_title, bio (rich), tags JSON, photo_id → media, is_founder, show_on JSON (home/tms/contact), sort_order, is_published | soft delete |
| `faqs` | page_id FK, question, answer (rich), sort_order, is_published | index(page_id, is_published, sort_order) |
| `testimonials` | quote, author_name, source_label, source_url, sort_order, is_published | soft delete |
| `insurance_partners` | name, logo_id → media, url, note, sort_order, is_published | |
| `content_items` | group (enum: trust_strip, familiar_list, value_props, journey_steps, treatment_phases, insurance_questions, bring_list, keep_exploring…), page_id FK nullable, title, body, icon, link_label, link_url, sort_order, is_published | index(group, sort_order) |
| `quiz_questions` | position, prompt, layout enum(stack, grid) | |
| `quiz_choices` | quiz_question_id FK, label, value (slug), position | unique(question_id, value) |
| `quiz_results` | key, title, body, primary_cta JSON, secondary_cta JSON, conditions JSON (choice values that trigger it), priority, is_default | |
| `consultation_requests` | reference (unique, e.g. `CR-2026-00042`), name **(encrypted)**, phone **(encrypted)**, email **(encrypted, nullable)**, topic enum(tms_depression, insurance, ocd_specialty, loved_one, provider_referral), message **(encrypted, nullable)**, status enum(new, contacted, scheduled, closed, spam), assigned_to → users nullable, contacted_at, closed_at, source_page, utm JSON, ip_hash, user_agent_hash | index(status, created_at), index(assigned_to); soft delete; retention purge job |
| `consultation_request_notes` | consultation_request_id FK, user_id FK, body (encrypted) | |
| `settings` | group, key (unique per group), value JSON | cached forever, busted on update |
| `business_hours` | day_of_week 0–6, opens_at, closes_at, is_closed, label | unique(day_of_week) |
| `media` | disk, path, mime, size, width, height, alt, caption, uploaded_by → users | |
| `redirects` | from_path (unique), to_path, status_code (301/302) | |
| `activity_log` | spatie/laravel-activitylog | index(subject_type, subject_id), index(causer_id) |

Status enums are backed by PHP enums (`App\Enums\ConsultationStatus`, `ConsultationTopic`, `PageStatus`) and cast on models; transitions are validated in a service, never trusted from the client.

---

## 4. API Architecture (`/api/v1`)

Envelope: `{ success, message, data, meta? }`; errors `{ success:false, message, errors? }`. Pagination meta: `current_page, per_page, total, last_page`. List params: `search`, `filter[field]=`, `sort=-created_at`, `page`, `per_page` (max 100).

**Public (no auth, cached, read-only — consumed by Next.js server-side)**
```
GET  /public/settings                 clinic info, hours, phone, footer, disclaimers
GET  /public/pages/{slug}             page meta + ordered sections + embedded lists
GET  /public/conditions               GET /public/conditions/{slug}
GET  /public/team-members
GET  /public/faqs?page=home|tms
GET  /public/testimonials
GET  /public/insurance-partners
GET  /public/quiz                     questions, choices, result rules
GET  /public/redirects
GET  /public/sitemap                  slugs + lastmod
POST /public/consultation-requests    throttle:5/min/IP, honeypot + Turnstile
```

**Auth** (Sanctum SPA, cookie session)
```
GET  /sanctum/csrf-cookie
POST /auth/login        POST /auth/logout       GET /auth/me (user + roles + permissions)
POST /auth/forgot-password                      POST /auth/reset-password
PUT  /auth/profile      PUT /auth/password
```

**Admin** (`auth:sanctum` + policies)
```
GET    /admin/dashboard
GET    /admin/consultation-requests           (search, filter[status|topic|assigned_to|date_from|date_to], sort)
GET    /admin/consultation-requests/{id}
PATCH  /admin/consultation-requests/{id}       status, assigned_to
POST   /admin/consultation-requests/{id}/notes
DELETE /admin/consultation-requests/{id}
GET    /admin/consultation-requests/export     (queued CSV, permission-gated)

GET|PUT        /admin/pages, /admin/pages/{id}
GET|POST|PUT|DELETE /admin/pages/{id}/sections[/{section}]
PATCH          /admin/pages/{id}/sections/reorder
POST           /admin/pages/{id}/publish

apiResource    /admin/conditions | team-members | faqs | testimonials | insurance-partners | content-items | redirects
PATCH          /admin/{resource}/reorder

apiResource    /admin/quiz/questions (+ nested choices) | /admin/quiz/results
GET|PUT        /admin/settings[/{group}]       GET|PUT /admin/business-hours
GET|POST|PUT|DELETE /admin/media
apiResource    /admin/users | /admin/roles      GET /admin/permissions
GET            /admin/audit-logs
```

Laravel layers: `Controller` (thin) → `FormRequest` → `Action`/`Service` (e.g. `SubmitConsultationRequest`, `TransitionConsultationStatus`, `PublishPage`, `ReorderItems`) → `Model` → `JsonResource`. Events `ContentPublished` → job `RevalidateFrontend`; `ConsultationRequestSubmitted` → `NotifyStaffOfNewRequest` (queued).

---

## 5. Authentication Architecture

- **Admin**: Sanctum **SPA cookie** auth (not bearer tokens) — admin at `admin.<domain>`, API at `api.<domain>` on the same registrable domain; `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN=.domain`, `supports_credentials=true`. HttpOnly session cookie + XSRF-TOKEN. "Remember me" = Laravel remember cookie. Login throttled (5/min per email+IP); optional TOTP 2FA (recommended given PHI).
- **Public site**: no customer accounts (design has none). Next.js calls public endpoints server-side; no token needed. Revalidation webhook protected by shared secret (HMAC).
- **Roles** (spatie/laravel-permission, seeded):

| Permission group | Super Admin | Admin | Manager | Staff |
| --- | :-: | :-: | :-: | :-: |
| `consultations.view` / `.update` | ✓ | ✓ | ✓ | ✓ (assigned only) |
| `consultations.delete` / `.export` | ✓ | ✓ | — | — |
| `content.view` / `.edit` | ✓ | ✓ | ✓ | view |
| `content.publish` | ✓ | ✓ | ✓ | — |
| `media.manage` | ✓ | ✓ | ✓ | — |
| `settings.manage`, `seo.manage` | ✓ | ✓ | — | — |
| `users.manage` | ✓ | ✓ (not super admins) | — | — |
| `roles.manage`, `audit.view` | ✓ | audit only | — | — |

Super Admin bypass via `Gate::before`. Policies per model enforce everything; `/auth/me` returns the flat permission list so React can hide actions (`<Can permission="content.publish">`) — UX only.

---

## 6. React Admin Architecture (React 19 + Vite + TS + Tailwind 4)

Libraries: react-router 7 (data routers), TanStack Query (server state), axios (single instance, interceptors), react-hook-form + zod, Radix primitives (dialog, dropdown, tabs, select — accessibility), TanStack Table (headless), Recharts (dashboard only, lazy), Tiptap (rich text, lazy), sonner (toasts), dnd-kit (reordering).

```
admin/src/
  app/ (providers, router, query-client)
  routes/            route tree + guards (RequireAuth, RequirePermission)
  layouts/           AuthLayout, AppLayout (Sidebar, TopBar, Breadcrumbs)
  components/
    ui/              Button Input Select Textarea Checkbox Badge Card Modal Drawer Dropdown Tabs Tooltip Skeleton EmptyState ErrorState
    forms/           FormField, ImageUpload, RichTextEditor, SortableList
    tables/          DataTable, Pagination, SearchInput, FilterBar, BulkActions
    modals/          ConfirmDialog
    navigation/      Sidebar, TopBar, Breadcrumbs, UserMenu
    charts/          TrendChart, StatCard
  features/
    auth/ dashboard/ consultations/ pages/ conditions/ team/ faqs/
    testimonials/ insurance/ content-items/ quiz/ settings/ seo/ media/ users/ roles/ audit/
      (each: api.ts, queries.ts, schemas.ts, types.ts, components/, pages/)
  services/          http.ts (axios + error normaliser), endpoints
  hooks/             usePermission, useDebounce, useTableParams (URL-synced)
  store/             auth context only (user + permissions)
  types/             api.ts (ApiResponse<T>, Paginated<T>, ApiError), shared enums
  utils/
```

Routes: `/login`, `/forgot-password`, `/reset-password/:token`, `/` dashboard, `/consultations`, `/consultations/:id`, `/content/pages/:slug`, `/content/conditions`, `/content/team`, `/content/faqs`, `/content/testimonials`, `/content/insurance`, `/content/lists`, `/quiz`, `/media`, `/settings/clinic`, `/settings/seo`, `/settings/redirects`, `/users`, `/roles`, `/audit-log`, `/profile`. All feature pages lazy-loaded.

State: TanStack Query for all API data; table filters in URL search params; auth in one context; everything else local. Central error handling: 401 → clear auth + redirect to login; 403 → toast + "no access" view; 422 → mapped into react-hook-form `setError`; 419 → refresh CSRF and retry once; 429/5xx/network → toast with friendly copy.

---

## 7. Next.js Architecture (App Router, Next 16, TS, Tailwind 4)

```
frontend/
  app/
    layout.tsx               fonts (next/font: Source Serif 4, Nunito, Nunito Sans), header/footer, MobileCtaBar
    page.tsx                 Home
    tms/page.tsx
    contact/page.tsx
    quiz/page.tsx
    not-found.tsx, error.tsx
    sitemap.ts, robots.ts
    api/revalidate/route.ts  HMAC-verified on-demand revalidation
  components/
    layout/   SiteHeader, SiteHeaderCompact, SiteMenu (client), SiteFooter, MobileCtaBar
    ui/       Button, TextLink, Panel, SectionRail, ListItem, Disclaimer
    sections/ Hero, TrustStrip, SectionNav, ConditionCards, ConditionFeature, TeamSection, InsuranceSection,
              ValueGrid, Journey, Testimonials, FaqSection, LocationPanel, ConsultationBand, FinalCta …
    forms/    ConsultationForm (client), FormField
    quiz/     SelfCheck (client), QuizChoice, QuizResult
  lib/        api.ts (typed fetch, tags), seo.ts, jsonld.ts
  types/
```

Server/client boundary: **everything is a Server Component** except `SiteMenu` (toggle + focus trap), `FaqItem` (native `<details>` where possible → zero JS), `ConsultationForm` (react-hook-form + zod, posts to Laravel), `SelfCheck` (local state, scoring rules from API, nothing persisted), `MapEmbed` (click-to-load facade).

Data fetching: `fetch` in Server Components with Cache Components / `"use cache"` + `cacheTag('page:home')` etc.; Laravel fires the revalidation webhook on publish → instant updates with static-speed pages. Fallback `cacheLife('hours')`. SEO: `generateMetadata` from page payload, canonical, OG/Twitter, JSON-LD (`MedicalClinic` with address/hours/phone, `FAQPage`, `Person` for providers), dynamic sitemap from `/public/sitemap`, redirects applied in `proxy.ts`.

---

## 8. Development Roadmap (each phase ends runnable)

> **Superseded** by [`ROADMAP.md`](ROADMAP.md) (6 phases, vertical slices, guideline compliance check). Kept for history.

| # | Phase | Working result |
| --- | --- | --- |
| 1 | **Foundation** — monorepo (`backend/`, `admin/`, `frontend/`), git, Laravel 13 + MySQL, Sanctum, CORS/sessions, API envelope + exception handler, `.env.example`s, docs skeleton | `GET /api/v1/health` works; three apps boot locally |
| 2 | **Auth & RBAC** — users, roles/permissions seeder, login/logout/me/reset, policies, tests; React admin shell (layout, sidebar, login, guards, error handling) | Log into admin; menu reflects permissions |
| 3 | **Content model** — migrations/models/factories/seeders for settings, hours, pages, sections, conditions, team, FAQs, testimonials, insurance, content items, quiz, media; seed with **all copy from Figma**; public read endpoints + tests | `/public/pages/home` returns full Figma content |
| 4 | **Public site — design system & layout** — tokens, fonts, Button/Panel/SectionRail/etc., header/compact header/menu/footer/mobile CTA at 3 breakpoints | Shell matches Figma on all breakpoints |
| 5 | **Public pages** — Home, TMS, Contact (form UI), Quiz (client scoring) wired to API; SEO, sitemap, robots, JSON-LD | Full public site renders from Laravel data |
| 6 | **Consultation requests end-to-end** — public submit (validation, throttle, Turnstile, encryption), staff notification (link-only or the whole request, per Clinic settings → Privacy), admin inbox, detail, status workflow, notes, assignment, audit | Submit on site → appears in admin |
| 7 | **Admin CMS** — CRUD screens for every content type, reorder, media library/uploads, page section editor, quiz builder, settings, SEO, redirects; publish → revalidate | Edit in admin → live on site |
| 8 | **Dashboard, users, roles, audit log** | Complete admin |
| 9 | **QA & hardening** — Playwright E2E, a11y (axe), Lighthouse, security review, retention job, deployment docs | Release candidate |

---

## 9. Risks & Recommendations (decisions needed before Phase 1)

1. **No admin design exists.** I'll build the admin with the same tokens (navy/royal/sky, Nunito Sans) in a clean SaaS layout. *Confirm this is acceptable, or supply admin Figma.*
2. **HIPAA / health privacy.** Consultation requests (name + phone + "TMS for depression/OCD") are very likely PHI for a covered clinic. Required: hosting, DB, email and backup vendors under a **BAA**; field-level encryption (done above); access audit log; retention/purge policy; **no ad/analytics pixels** on contact/quiz pages (HHS online-tracking guidance). *Confirm hosting target and whether a BAA-capable provider is in place.* Staff alerts were link-only at first; the clinic has since asked for the request itself in the alert, so it is a Privacy switch (default on) and the mail provider now holds PHI — a BAA for it is no longer optional.
3. **The design says the form "does not send"** ("Template preview…") and the quiz "nothing is sent or saved". I propose: form **does** submit in production (copy updated via CMS), quiz stays **100% client-side** — the one place scoring logic runs in the browser, because the privacy promise is part of the design. *Confirm both.*
4. **Missing pages linked from the design**: "Read the depression guide →", "Read the OCD guide →", "How TMS works →", "Visit our main clinic website →", privacy policy, 404. Is this site a microsite alongside an existing uptms.com, or the replacement? I propose condition detail pages at `/conditions/{slug}` built from the same `conditions` data. *Confirm.*
5. **Google Maps embed** loads third-party cookies; use a click-to-load facade (static image + "Open in Google Maps"). Needs a Maps API key either way.
6. **Spam protection**: Cloudflare Turnstile recommended (needs keys) + honeypot + throttling.
7. **Email delivery**: need a transactional provider (Postmark/SES/etc. — ideally BAA-covered) for staff notifications and password resets.
8. **Content granularity trade-off**: one-off headings/copy live in typed `page_sections` JSON (validated server-side per section type), repeated content in real tables. This keeps the admin usable without building a generic page builder. Layout/order of sections stays code-defined to protect design fidelity.
9. **Scale**: 4 pages + a lead inbox is small for a three-app stack; the architecture is deliberately kept lean (no repositories layer, no global client store, minimal deps). Hosting: Laravel on a VPS/Forge (PHP 8.4, MySQL 8, Redis for queue/cache), Next.js on Vercel or the same VPS, admin as static build.
10. **Assets**: photos (founder, treatment, clinic) and 10 insurance logos come from Figma; confirm licensing/approval for use (logos especially) and that testimonials are approved for republication.
11. **Local toolchain verified**: PHP 8.4.7, Composer 2.8, Node 26, npm 11, MySQL 9.3, Git 2.50 — all compatible with Laravel 13 / React 19 / Next 16.

---

## 10. Scope update — Full CMS + Full SEO (requested 2026-10-08)

The admin is no longer a fixed-page content editor; it is a full CMS. This supersedes the narrower content model in §3–§6 where they conflict.

### 10.1 CMS capabilities

| Module | Capability |
| --- | --- |
| **Pages** | Create unlimited pages from a **block library** that mirrors the Version C sections (Hero, Section rail + copy, Condition cards, Team, Insurance, Value grid, Journey, Testimonials, FAQ, Location, CTA band, Rich text, Image + text, Logo wall, Quiz embed). Draft → Review → Scheduled → Published; revisions + restore; live preview (Next.js draft mode); parent/child hierarchy; per-page template |
| **Posts / Resources** | Blog/education articles (e.g. "Depression guide", "OCD guide"); categories, tags, authors (linked to team members as medically-reviewed-by), reading time, related posts |
| **Conditions / Services** | Structured service pages at `/conditions/{slug}` with long-form body + FAQs |
| **Reusable collections** | Team, FAQs, Testimonials, Insurance partners, Locations, Trust items — referenced by blocks |
| **Navigation** | Menu builder for header, mobile menu, footer columns, section nav |
| **Forms** | Consultation form fields/topics configurable; submissions inbox (encrypted) |
| **Quiz** | Question/choice/result builder |
| **Media** | Library with folders, alt text (required), focal point, auto WebP/AVIF variants, usage tracking |
| **Global settings** | Clinic NAP, hours, contact, announcement bar, footer copy, crisis disclaimer, scripts/consent |
| **Workflow** | Roles gain `content.review` / `content.publish`; scheduled publishing via queue; every change audited |

### 10.2 SEO capabilities

| Area | Capability |
| --- | --- |
| **Per-entry SEO panel** (pages, posts, conditions) | Meta title + description with live character/pixel counters; **Google SERP preview** (desktop/mobile); focus keyphrase + secondary keyphrases; SEO & readability analysis checklist with score; canonical URL; robots (index/noindex, follow/nofollow, max-snippet, max-image-preview); OG title/description/image + **Facebook/LinkedIn preview**; Twitter/X card + preview; breadcrumb title |
| **Structured data** | Auto JSON-LD: `MedicalClinic`/`MedicalBusiness` (NAP, hours, geo), `Physician`/`Person` for providers, `MedicalCondition`, `MedicalWebPage` (+ `lastReviewed`, `reviewedBy`), `FAQPage`, `Article`, `BreadcrumbList`, `WebSite`; per-entry schema type override + custom JSON-LD with validation |
| **Global SEO settings** | Title templates (`%title% · %site%`), separator, default OG image, organization/knowledge-graph data, social profiles, Google/Bing verification codes, default robots |
| **Sitemaps** | Auto XML sitemap index (pages, posts, conditions, images), per-type include/exclude, priority/changefreq, lastmod from `updated_at`; ping on publish |
| **robots.txt** | Editable in admin with safe defaults; staging always `Disallow: /` |
| **Redirects** | 301/302/410 manager, regex rules, hit counts, CSV import/export, auto-redirect on slug change, loop detection |
| **404 monitor** | Logs 404 paths (no PII, hashed IP) with hit count → one-click "create redirect" |
| **Content audit** | Site-wide SEO dashboard: missing meta, duplicate titles/descriptions, missing alt text, noindex pages, thin content, broken internal links, orphan pages |
| **Internal linking** | Link suggestions in editor; broken-link checker job |
| **Performance/SEO hygiene** | Clean slugs (editable, auto-generated), trailing-slash policy, canonical host, image `alt`/dimensions enforced, `next/image`, Core Web Vitals budget |
| **YMYL / medical trust** | Medically reviewed by + review date on every health page (E-E-A-T), source citations block, disclaimers |
| **Integrations** | Google Search Console + GA4 (consent-gated, never on form/quiz pages for HIPAA), IndexNow on publish |

### 10.3 Data model additions

`pages` (+ `parent_id`, `template`, `status`, `scheduled_at`), `page_blocks` (page_id, type, data JSON, sort_order), `posts`, `categories`, `tags`, `post_tag`, `menus`, `menu_items` (nested), `revisions` (polymorphic, JSON snapshot), **`seo_meta`** (polymorphic `seoable`: meta_title, meta_description, focus_keyphrase, keyphrases JSON, canonical_url, robots JSON, og_* , twitter_*, schema_type, schema_custom JSON, breadcrumb_title, seo_score, readability_score, analysis JSON), `seo_settings`, `redirects` (+ `match_type`, `hits`, `last_hit_at`), `not_found_logs`, `media_folders`, `media_variants`, `link_checks`.

### 10.4 Additional admin screens

Pages list · Page editor (blocks canvas + settings/SEO side panel) · Block picker · Revisions · Posts list/editor · Categories & tags · Menus · Media library · SEO dashboard (audit) · Global SEO settings · Sitemap settings · robots.txt editor · Redirects · 404 monitor · Schema/Organization settings · Integrations.
