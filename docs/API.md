# UPTMS — API

Base: `/api/v1`. JSON only. The machine-readable spec is generated with Scramble: `composer openapi` → `docs/api/openapi.json`.

## 1. Envelope

Every response, success or failure, uses one shape (`App\Support\Http\ApiResponse`).

```json
{ "success": true, "message": "OK", "data": {}, "meta": {} }
```

Paginated lists keep `data` a plain list. Page info is in `meta`, page URLs in `links`:

```json
{
  "success": true, "message": "OK", "data": [ … ],
  "meta": { "current_page": 1, "per_page": 25, "total": 128, "last_page": 6, "from": 1, "to": 25 },
  "links": { "first": "…", "prev": null, "next": "…", "last": "…" }
}
```

Errors:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "error_code": "VALIDATION_FAILED",
  "errors": { "email": ["The email field must be a valid email address."] },
  "meta": { "request_id": "01K…" }
}
```

Every response has an `X-Request-Id` header. A caller-supplied `X-Request-Id` (up to 64 characters) is reused.

## 2. Route groups

| Prefix | Middleware | Caller | Defined in |
| --- | --- | --- | --- |
| `/api/v1/health` | `api`, `throttle:public` | uptime checks, both apps | `routes/api.php` |
| `/api/v1/public/*` | `api`, `public` (`throttle:public`) | Next.js server (anonymous) | `packages/*/routes/api_public.php` |
| `/api/v1/admin/*` | `api`, `admin` (`auth:sanctum`, `active`, `throttle:admin`) | admin SPA | `packages/*/routes/api_admin.php` |
| `/api/v1/*` | `api` | either | `packages/*/routes/api.php` |

### `GET /api/v1/health`

200:

```json
{ "success": true, "message": "OK",
  "data": { "status": "ok", "app": "UPTMS Admin", "environment": "local", "database": "ok", "server_time": "2026-10-08T08:01:28+00:00" },
  "meta": {} }
```

503 `SERVICE_UNAVAILABLE` when MySQL can't be reached.

## 3. Rate limits

| Limiter | Limit | Key |
| --- | --- | --- |
| `login` | 5 / min | email + IP |
| `admin` | 240 / min | user id (or IP) |
| `public` | 300 / min | IP |
| `public-forms` | 5 / min | visitor IP (forwarded by the site with its token) |
| `media-uploads` | 60 / min | user id |

## 4. Error codes

Stable; clients branch on these, never on `message`. Mirrored in `resources/js/shared/api/errors.ts`.

| Code | HTTP | Meaning |
| --- | --- | --- |
| `VALIDATION_FAILED` | 422 | Field errors in `errors` |
| `UNAUTHENTICATED` | 401 / 419 | Not signed in / session or CSRF expired |
| `INVALID_CREDENTIALS` | 422 | Wrong email or password |
| `FORBIDDEN` | 403 | Signed in, not permitted |
| `NOT_FOUND` | 404 | Unknown route or record (also for records outside the caller's scope) |
| `RATE_LIMITED` | 429 | `meta.retry_after` seconds |
| `CONFLICT` | 409 | Stale write or duplicate |
| `INVALID_STATE_TRANSITION` | 409 | `errors.from`, `errors.to`, `errors.allowed` |
| `ACCOUNT_INACTIVE` | 403 | Account deactivated; session ended |
| `SERVER_ERROR` | 500 | Unexpected; quote `request_id` |
| `SERVICE_UNAVAILABLE` | 503 | A dependency (database) is down |

## 5. List conventions

`?search=…&filter[status]=draft&filter[topic]=insurance&sort=-created_at&page=2&per_page=25` (max `per_page` 100). Filtering, sorting and search always run in MySQL, never in the browser (guideline §14).

## 6. Phase 1 endpoints

Two-factor is behind the site-wide switch `security.two_factor_login` (Settings → Security, **off by default**; `AUTH_TWO_FACTOR_LOGIN` sets the initial value). While off, sign-in never asks for a code and no role forces set-up; confirmed secrets are kept. Error codes added: `TWO_FACTOR_SETUP_REQUIRED` (403: the switch is on, the role requires 2FA and the user has not set it up; every `/admin/*` route), `INVALID_TWO_FACTOR_CODE` (422), `INVITATION_INVALID` (410). Every `/admin/*` route also needs `admin.access`.

### Sign-in (`uptms.auth`, `/api/v1/auth/*`)

The SPA calls `GET /sanctum/csrf-cookie` first. Sign-in only works from hosts listed in `SANCTUM_STATEFUL_DOMAINS`.

| Method & path | Auth | Throttle | Notes |
| --- | --- | --- | --- |
| `POST auth/login` `{email, password}` | — | `login` 5/min email+IP | `data.two_factor: true` → no session yet, post the code next; otherwise `data.user` (= `/auth/me`). `INVALID_CREDENTIALS` never says which part was wrong; `ACCOUNT_INACTIVE` only after a correct password |
| `POST auth/two-factor-challenge` `{code}` or `{recovery_code}` | pending sign-in (5 min) | `two-factor` 5/min per account | Codes are single-use (replay rejected) |
| `POST auth/logout` | session | | |
| `GET auth/me` | session | `admin` | `{id, uuid, name, email, role{slug,name}, is_super_admin, permissions[], two_factor{available,enabled,required}, session{idle_timeout_minutes}}` — Super Admins get every key listed |
| `POST auth/forgot-password` `{email}` | — | `password-reset` 5/min | Same answer whether or not the account exists; invited and deactivated accounts get no mail |
| `POST auth/reset-password` `{token, email, password, password_confirmation}` | — | `password-reset` | Ends every session of that user |
| `GET auth/two-factor` | session | | `{available, enabled, required, confirmed_at, recovery_codes_left}` — `available` is the site-wide switch |
| `POST auth/two-factor` | session | | Starts set-up: `{secret, otpauth_uri}` |
| `POST auth/two-factor/confirm` `{code}` | session | `two-factor` | `{recovery_codes[8]}`, shown once |
| `DELETE auth/two-factor` `{password}` | session | | `FORBIDDEN` when the role requires 2FA |
| `POST auth/two-factor/recovery-codes` `{password}` | session | | New codes; old ones stop working |
| `PUT auth/password` `{current_password, password, password_confirmation}` | session | | Signs out other devices |

Passwords: at least 12 characters with letters and numbers.

### Invitations (`uptms.users`, anonymous, `throttle:invitations` 10/min/IP)

| Method & path | Notes |
| --- | --- |
| `GET invitations/{token}` | `{name, email, role, expires_at}` or `INVITATION_INVALID` |
| `POST invitations/{token}/accept` `{password, password_confirmation}` | Sets the password, signs in; the token works once |

### Users (`/api/v1/admin/users`, `permission:users.manage`)

| Method & path | Notes |
| --- | --- |
| `GET users?search=&filter[status]=invited\|active\|deactivated&filter[role]=slug&sort=name\|email\|last_seen_at\|created_at (- for desc)&page&per_page` | `meta.counts {all, invited, without_two_factor}` |
| `POST users` `{name, email, role_id}` | Creates the account without a password and emails a 7-day invitation |
| `GET / PUT users/{id}` `{name, email, role_id, is_active}` | Deactivating ends the user's sessions |
| `DELETE users/{id}` | Soft delete; sessions end |
| `POST users/{id}/invitation` | Resend; the previous link stops working. `CONFLICT` once accepted |
| `POST users/{id}/two-factor/reset` | For a lost phone; sessions end |

Guards (`StaffGuard`, `UpdateUserAction`): only a Super Admin touches a Super Admin or assigns that role; nobody assigns a role holding permissions they lack; nobody changes their own role, deactivates or removes themselves.

### Roles (`/api/v1/admin`)

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET permissions` | `users.manage` or `roles.manage` | The Figma 26 matrix: `{groups: [{key, label, permissions: [{key, label, description}]}]}` |
| `GET roles`, `GET roles/{id}` | `users.manage` or `roles.manage` | `{id, slug, name, description, is_system, is_super_admin, requires_two_factor, permissions[], users_count}` |
| `POST roles`, `PUT roles/{id}` `{name, slug (create only), description, requires_two_factor, permissions[]}` | `roles.manage` | The Super Admin role cannot be edited |
| `DELETE roles/{id}` | `roles.manage` | `FORBIDDEN` for built-in roles, `CONFLICT` while anyone holds it |
| `POST roles/require-two-factor` | `roles.manage` | Turns on 2FA for every role holding `consultations.view` |

### Audit log (`/api/v1/admin`, `permission:audit.view`)

| Method & path | Notes |
| --- | --- |
| `GET audit-logs?search=&filter[user_id]&filter[category]=sign_in\|users\|consultations\|content\|seo\|settings\|audit&filter[action]&filter[auditable_type]&filter[auditable_id]&filter[from]&filter[to]&page&per_page` | Newest first |
| `GET audit-logs/export` (same filters) | CSV download (≤ 50,000 rows, formula-safe); the export itself is audited |

## 7. Phase 2 endpoints

Error codes are unchanged. Every write below is audited. `content.edit` drafts; anything that changes what the website shows needs `content.publish` (route middleware or policy). Admin lists of short, hand-ordered collections are returned whole, not paginated.

### Website (`/api/v1/public`, anonymous, cached)

Responses are cached in Laravel (`PublicCache`, one version bump per publish) and in Next.js (`cacheTag`). Only published content is returned.

| Method & path | Returns |
| --- | --- |
| `GET settings` | `{clinic{name, tagline, service_area_label, service_area[], phone{display, href}, email, address{street, locality, region, postal_code, country, single_line}, geo, directions_url, main_site_url, social[]}, hours{short, long, days[], closures[]}, announcement, legal{copyright, crisis, crisis_short, insurance, testimonials, quiz}}` |
| `GET menus` | `{header, mobile, section-nav, footer-explore, footer-legal}`, each `[{label, href, children[]}]`. Hidden items and links to unpublished pages are left out |
| `GET page?path=/tms` | `{id, title, path, template, is_home, preview, published_at, updated_at, blocks[{id, type, anchor, number, data, sources}]}`. `number` is the section number ("01"…) in order; `data` follows the block's field schema with images expanded to `{id, url, width, height, alt, focal{x,y}, sources{avif[], webp[]}}`; `sources` holds collection data (FAQs, team, conditions…). `&preview=<token>` returns the working copy for a valid signed token (30 min), uncached |
| `GET pages` | Published `[{path, title, template, updated_at}]` |
| `GET conditions`, `GET conditions/{slug}` | Cards `[{id, slug, path, number, eyebrow, title, summary, card_link_label, image, detail_title, detail_body, source, cta, reviewed_at}]`; the detail adds `faqs[]` (group `conditions/<slug>`) |
| `GET collections/{team|faqs|testimonials|insurance|trust-points|journey-steps}?group=&page=` | Published items in order |
| `GET quiz` | The published self-check document v2: `{version, intro, privacy_note, steps[], scales[], crisis, result_disclaimer, done}` (docs/QUIZ.md). Answers go to `POST quiz-submissions` (consultations), which scores them |

Links in block data may use tokens the website fills from settings: `{phone}`, `{hours}`, `{email}` in text, `tel:clinic` / `mailto:clinic` as hrefs.

### Pages (`/api/v1/admin`, Figma 06–07)

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET pages?search=&filter[status]=draft\|published\|scheduled\|trash&filter[template]&filter[author]&sort=path\|title\|updated_at\|status&per_page` | content.edit | `meta.counts{all, published, draft, scheduled, trash}`, `meta.templates`, `meta.authors` |
| `GET pages/block-types` | content.edit | The block library: `[{key, label, icon, description, fields[], defaults}]`. Field types: text, headline, textarea, markdown, link, image, list, repeater, select, toggle, source (options included). The API validates block data with the same schema |
| `POST pages` `{title, slug, template, parent_id}` | content.edit | Draft with the template's starter blocks. Reserved first segments: api, admin, conditions, resources, storage … |
| `GET pages/{id}` | content.edit | Page + working-copy `blocks[]` + `lock_version` |
| `PUT pages/{id}` `{title, slug, template, parent_id}` | content.edit (+ content.publish once published) | Children follow a moved page; the home page stays at `/` |
| `PUT pages/{id}/content` `{lock_version, blocks[{id?, type, data, is_visible}], checkpoint?, note?}` | content.edit | Autosave (`checkpoint` false) or Save draft (records a revision). `CONFLICT` when `lock_version` is stale. Errors are keyed `blocks.{i}.data.{field}` |
| `POST pages/{id}/publish` `{lock_version?}` | content.publish | Snapshots the working copy as the live revision; revalidates the site |
| `POST pages/{id}/schedule` `{at}` · `DELETE pages/{id}/schedule` | content.publish | Freezes the current working copy; `pages:publish-scheduled` (every minute) makes it live |
| `POST pages/{id}/unpublish` | content.publish | Not for the home page |
| `GET pages/{id}/revisions` · `POST pages/{id}/revisions/{revision}/restore` `{lock_version}` | content.edit | Restore copies into the working copy, never straight to the site |
| `POST pages/{id}/preview` | content.edit | `{token, url, expires_in}`; `url` opens the site's `/preview` |
| `DELETE pages/{id}` · `POST pages/{id}/restore` | content.edit (+ publish when live) | Trash (soft delete); not the home page, not with child pages |

### Conditions (Figma 13)

`GET conditions` (content.edit or content.review) lists cards in order with `status, needs_review, faq_count`. `POST conditions`, `PUT conditions/{id}` (content.edit; content.publish once live) take `{slug, eyebrow, title, summary, card_link_label, image_id, detail_title, detail_body, source_label, source_url, cta_label, cta_href}`. `PUT conditions/{id}/status {status: draft|published}` (content.publish) — publishing needs a recorded review, else `CONFLICT`. `POST conditions/{id}/review` (content.review). `PUT conditions/reorder {ids[]}` (content.publish, the complete list). `DELETE conditions/{id}`.

### Collections (Figma 14–15)

`GET collections` → tabs `[{key, label, count, grouped, groups[{value, count}]}]`. For `{type}` in team, faqs, testimonials, insurance, trust-points, journey-steps: `GET collections/{type}?search=&filter[group]=`, `POST collections/{type}`, `PUT collections/{type}/{id}`, `DELETE collections/{type}/{id}`, `PUT collections/{type}/reorder {ids[], group?}` (FAQs reorder within a group). Bodies are the type's fields plus `is_published`. Publishing, editing or deleting a published item and reordering need content.publish. Photos and logos must have alt text and are recorded as media usage.

### Navigation menus (Figma 17)

`GET menus`, `GET menus/{key}` (content.edit) → `{key, name, items[{id, label, type: page|anchor|link, page_id, page_title, page_published, anchor, url, href, is_visible, children[]}], preview}`. `PUT menus/{key} {items[]}` (content.publish) replaces the tree (one level of children). Page and anchor items store the page id, so a changed address follows.

### Quiz (Figma 16)

`GET quiz` (content.edit) → `{key, title, draft, lock_version, has_unpublished_changes, published_at}`. `PUT quiz {lock_version, draft}` validates the v2 document (docs/QUIZ.md: unique keys and values, conditions on earlier answers, points on every scored answer, bands covering 0 to the maximum, one name and one phone step before the results). `POST quiz/publish` (content.publish).

### Media (Figma 18 / 18b)

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET media?search=&filter[folder]=<id>\|none&filter[show]=all\|in_use\|unused\|no_alt&filter[kind]=image\|document&filter[unused_days]=90&sort=newest\|oldest\|name\|largest&per_page` | media.manage or content.edit | `meta.counts{all, in_use, unused, no_alt, trash}`, `meta.unused{files, bytes, never_used, removed}` |
| `GET media/{id}` · `GET media/{id}/download` · `GET media/trash` · `GET media-folders` | media.manage or content.edit | Detail includes `usages[{label, context, admin_path}]` and `variants[]` |
| `POST media` (multipart `file`, `folder_id`) | media.manage | JPG/PNG/WebP up to 10 MB (≤ 40 MP) or PDF up to 20 MB, checked by content. Images are re-encoded (metadata stripped, capped at 2400 px); WebP and AVIF variants at 480/960/1600 px are queued. `throttle:media-uploads` 60/min |
| `PUT media/{id}` `{filename, alt, is_decorative, caption, focal_x, focal_y, folder_id}` | media.manage | |
| `POST media/{id}/replace` (multipart `file`) | media.manage | Same id, alt text and usages; new file and variants |
| `DELETE media/{id}` · `POST media/bulk {action: trash\|move, ids[], folder_id}` · `POST media/{id}/restore` | media.manage | Trashing a file in use → `CONFLICT` with `errors.in_use`. `media:purge-trash` deletes after 30 days |
| `POST/PUT/DELETE media-folders[/{id}]` | media.manage | Deleting a folder keeps its files |

### Clinic settings (Figma 24, `settings.manage`)

`GET settings` → `{profile, announcement, disclaimers, privacy, security{two_factor_login}, hours{days, closures, short}, phone_href}`. `PUT settings/{profile|announcement|disclaimers|privacy|security}` saves one tab (validated by `SettingsRegistry`). `PUT settings/hours {days[7]{day, is_open, opens_at, closes_at}, closures[{date, label}]}`. `POST settings/cache/clear {scope: website|application}` → `{scope, cleared_at, website_connected}`: `website` flushes the public read cache and revalidates the site's `content` tag; `application` also empties the Laravel cache store and compiled views (sessions and queues are in the database, so nobody is signed out). Audited as `settings.cache_cleared`.

### Revalidation webhook (Laravel → website)

`ContentPublished(tags)` → queued `RevalidateFrontend` → `POST {FRONTEND_URL}/api/revalidate` with body `{"tags":[…]}` and headers `X-Uptms-Timestamp`, `X-Uptms-Signature` = hex HMAC-SHA256 of `"{timestamp}.{body}"` with `FRONTEND_REVALIDATE_SECRET` (= the site's `REVALIDATE_SECRET`). The site refuses signatures older than five minutes. Without a secret the call is skipped and pages refresh within their one-hour cache life.

## 8. Phase 3 endpoints

Error codes are unchanged. Every write is audited without patient details; every opening of a request is audited as `consultation.viewed`.

### Website form (`/api/v1/public`, anonymous)

`POST consultation-requests` — `throttle:public-forms` (5/min per visitor) on top of `public`.

`POST quiz-submissions` and `POST quiz-submissions/feedback` — the self-check, same throttle, Turnstile and honeypot; see docs/QUIZ.md. The submission is checked and scored against the published quiz and filed as a consultation request (`origin: quiz`, encrypted `quiz_results`); a crisis answer makes its follow-up due at once.

| Field | Rules |
| --- | --- |
| `first_name`, `last_name` | required, ≤ 60 each; stored together as `name` |
| `phone` | required, 10-digit U.S. number (an 11-digit one must start with 1) |
| `email` | required, valid email ≤ 190 |
| `insurance` | required, ≤ 120: an insurance partner's name, `Other` or `No insurance` (**encrypted**) |
| `prior_treatment` | required: `both` · `therapy_only` · `medication_only` · `neither` (**encrypted**) |
| `contact_method` | required: `phone` · `text` · `email` |
| `topic` | optional (default `tms_depression`): `tms_depression` · `insurance` · `ocd_specialty` · `loved_one` · `provider_referral` |
| `best_time` | optional: `anytime` · `mornings` · `afternoons` |
| `referred_by` | optional, ≤ 160 |
| `message` | optional, ≤ 1,000 |
| `source_page` | optional path the form was on; `referrer` (full URL, reduced to a host or site path before storing); `utm{source, medium, campaign}` |
| `turnstile_token` | required when `TURNSTILE_SECRET_KEY` is set |
| `website` | honeypot: must stay empty |

201 → `{ reference }` only. A filled honeypot gets the same 201 with `reference: null` and stores nothing. The same phone number within 10 minutes returns the first request's reference. 422 messages are written for the patient. Headers `X-Uptms-Site-Token` (= `FRONTEND_SITE_TOKEN`) + `X-Uptms-Client-Ip` / `X-Uptms-Client-Agent` let the Next.js server pass on the visitor's address; without a valid token they are ignored.

The Contact page's `consultation` block, and a `hero` block with `primary_opens_form` on (its button opens the form in a popup), get `sources.form = {accepting, topics[{value, label}], best_times[], prior_treatments[], contact_methods[]}` and `sources.insurers` (the insurance partners); when the module is disabled `form` is null and the site shows the phone number (or the hero's link) instead.

### Consultation requests (`/api/v1/admin`, Figma 03–05)

Without `consultations.view_all` every list, count and lookup covers only the requests assigned to the caller; other ids are `NOT_FOUND`.

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET consultation-requests?search=&filter[status]=new\|contacted\|scheduled\|closed\|spam\|overdue&filter[topic]&filter[assigned_to]=me\|unassigned\|<id>&filter[received]=7\|30\|90&sort=received_at\|follow_up_at (- for desc)&per_page` | consultations.view | Rows carry masked `contact` ("Daniel R. · (253) •••-0198"), `topic{value,label}`, `origin` (form · quiz), `status`, `is_overdue`, `waiting_minutes` (new only), `assigned_to{id,name,initials}`, `follow_up_at`, `allowed_statuses[]`. `meta.counts{all,new,contacted,scheduled,closed,spam}`, `meta.topics`, `meta.assignees` (view_all only), `meta.can{update,assign,export,delete}`. Search matches the reference, a whole name word, the full phone number, its last four digits or the email |
| `GET consultation-requests/{id}` | consultations.view | Decrypted `name, phone, phone_href, email, message, referred_by, best_time, insurance, prior_treatment, contact_method, quiz` (self-check scores, answers and feedback, docs/QUIZ.md), `source{page, previous_page, first_touch, utm, device}`, `privacy{view_count, deletes_on, retention_days}`, `notes[]`, `activity[]`. **Audited** on every call |
| `PATCH consultation-requests/{id}` `{status?, assigned_to?, follow_up_at?}` | consultations.update (+ view_all for `assigned_to`) | Status moves only along `ConsultationStatus::allowedTransitions()`, else `INVALID_STATE_TRANSITION`. The assignee must be active staff with consultations.view. Returns the detail without logging another view |
| `POST consultation-requests/{id}/notes` `{body ≤ 1000}` | consultations.update | Returns the detail |
| `POST consultation-requests/bulk` `{action: assign\|contacted\|spam, ids[≤100], assigned_to?}` | consultations.update (+ view_all to assign) | `{updated, skipped}`; requests that can't move are skipped |
| `GET consultation-requests/export?filter[status]&filter[topic]&filter[received]` | consultations.export + view_all | Decrypted CSV (≤ 50,000 rows, formula-safe, `no-store`), times in the clinic's zone; the export is audited |
| `DELETE consultation-requests/{id}` | consultations.export | Permanent: details and notes go; the audit log keeps the reference |
| `GET consultation-requests/overview?range=7\|30\|90` | consultations.view | Figma 03: `queue{waiting, waiting_over_day, overdue, longest[4]{reference, topic, waiting_minutes, progress, is_overdue}}`, `new_requests{count, previous, change_percent}` (spam excluded), `daily[{date, count}]` in the clinic's zone, `topics[{value, label, count, percent}]`, `recent[5]` |
| `GET consultation-requests/waiting` | consultations.view | `{waiting}` for the sidebar badge |

Status workflow: new → contacted · scheduled · closed · spam; contacted → new (undo) · scheduled · closed · spam; scheduled → contacted · closed; closed → contacted · scheduled (re-open); spam → new. "Overdue" is not a status: a new request past `follow_up_at`, which is set to closing time of the next business day (office hours and closures from Clinic settings).

Staff alert: `ConsultationRequestSubmitted` → queued `SendStaffAlert` mails Clinic settings → Privacy → staff notification email, then records `consultation.alert_sent` with `{details: true|false}`. The mail always carries the reference and a link to the request; while Privacy → "Include the request in the alert" is on (the default), `StaffAlertDetails` adds the person's name, phone, email, preferred contact, best time, topic, insurance, prior treatment, referrer, self-check scores and message — health information, so the mail provider needs a BAA (docs/SETUP.md). Turn the switch off for a link-only alert. An empty notification email sends no alert.

Patient receipt: the same event → queued `SendPatientConfirmation` mails the address on the request with the reference, the clinic's phone, office hours and the crisis line — never the name, answers, topic or self-check results — then records `consultation.confirmation_sent`. A request without an email address (or a duplicate within the window, which raises no event) gets none.

## 9. Phase 4 endpoints

Error codes are unchanged. Admin writes are audited (model `Auditable` or an explicit `AuditRecorder` row), including every 404-monitor status change. `seo.edit` covers the SEO, Social and Schema tabs; `seo.manage` covers redirects, the 404 monitor, sitemap/robots.txt, SEO settings and running the site audit on demand (anyone with SEO access reads it). SEO values save straight to the website: they don't go through content publishing.

### Website (`/api/v1/public`, anonymous)

Read endpoints are cached in `PublicCache` (flushed by `ContentPublished`) unless noted. Only published content is returned.

| Method & path | Returns |
| --- | --- |
| `GET posts?category=<slug>&page=` | `{posts[{slug, path, title, dek, category{id, name, slug}, cover, reading_minutes, published_at}], page, last_page, total, category}`, 12 per page, newest first, from each post's published revision |
| `GET posts/{slug}` | The card fields + `body` (rich-text tree; image nodes gain `attrs.image`), `tags[]`, `author{name, credentials}`, `review{by{name, credentials}, at}` (health posts with a recorded review), `updated_at`, `related[3]` (same category first), `seo`, `preview: false`. `?preview=<token>` returns the working copy for a valid signed token, uncached, with `robots` noindex/nofollow and no `related`; a bad token is `NOT_FOUND` |
| `GET post-categories` | `[{name, slug, description, posts}]`: only categories with at least one live post |
| `GET seo` | `{site_name, separator, x_handle, verification{google?, bing?}, tag_manager}` for the root layout. `tag_manager` is `{id, exclude_paths[]}` when Google Tag Manager is switched on with a container id, otherwise `null`; the site must not load the container on `exclude_paths` (`/contact`, `/quiz` while the exclusion is on) |
| `GET sitemap` | `[{path, type, lastmod, priority, images[{url, alt}]}]`: published pages, conditions and posts (plus `/resources` when any post is listed), minus groups switched off in settings and subjects set to noindex or out of the sitemap |
| `GET robots` | `{text, indexnow_key}`. The key is null unless `SEO_LIVE` and the IndexNow setting are both on; the site serves it at `/indexnow-key.txt`. It is derived from `APP_KEY` (a key stored by an earlier version wins), so reading never writes |
| `GET redirects` | `{rules[{id, from, to, status, wildcard}], paths[]}`: enabled rules, and every address the website serves (`/`, `/resources` and all published subjects) so the proxy can answer real 404s |
| `POST redirects/{id}/hit` | Counts one use (total, `last_hit_at`, daily row). `throttle:redirect-hits` (60/min per visitor IP via `SiteClient`) |
| `POST not-found` `{path ≤ 500, referrer? ≤ 2000}` | Records a 404. `throttle:public-forms-404` (60/min per visitor IP via `SiteClient`). Paths over 190 characters, or ones a redirect now matches, are skipped. The referrer is reduced to a kind (google · search · internal · external · direct) and, for internal links, the linking path |

`hit` and `not-found` are refused (`FORBIDDEN`) unless the call carries a valid `X-Uptms-Site-Token`. In production they are refused while `FRONTEND_SITE_TOKEN` is unset; elsewhere they stay open until a token is configured.

`GET page` (and its preview) and `GET conditions/{slug}` now carry the same `seo` object as posts: `{title, description, canonical, robots{index, follow, max_image_preview, max_snippet}, open_graph{type, title, description, url, site_name, image, published_time, modified_time}, twitter{card, site}, json_ld}`. Previews always get noindex/nofollow.

### Posts (`/api/v1/admin`, Figma 11–12)

Writers draft with content.edit; anything that changes the website needs content.publish (`PostPolicy`); clinical sign-off needs content.review.

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET posts?search=&filter[status]=published\|scheduled\|in_review\|draft\|trash&filter[category]&filter[author]&filter[review]=not_medical\|draft\|needs_review\|reviewed\|overdue&sort=title\|updated_at\|published_at (- for desc)&per_page` | content.edit or content.review | Default 10 per page, `-updated_at`. Rows: `id, title, slug, path, category{id,name}, reading_minutes, author, status, review{state, label, date, self}, seo_score, cover, published_at, scheduled_at, updated_at, has_unpublished_changes, deleted_at`. `meta.counts{all, published, scheduled, in_review, draft, trash}`, `meta.categories`, `meta.authors`. `in_review` also matches live posts with changes waiting for review. `filter[review]` is derived state, filtered in MySQL by `Post::scopeInReviewState` (the same rules as `reviewState()`) |
| `GET posts/{id}` | content.edit or content.review | Row + `dek, body, body_media, cover_image_id, category_id, author_id, tags[], is_medical, word_count, lock_version, revisions_count, reviewer, reviewed_at, next_review_at, review_note, review_requested_at` |
| `POST posts` `{title, category_id?}` | content.edit | Draft |
| `PUT posts/{id}` `{lock_version, checkpoint?, title?, slug?, dek?, body?, cover_image_id?, category_id?, author_id?, is_medical?, tags[≤12]?}` | content.edit (+ content.publish to change a live post's slug) | Autosave, or Save draft (`checkpoint`, records a revision and re-scores SEO). `body` is a validated rich-text tree; its images must be placeable media. `CONFLICT` on a stale `lock_version`. A live post's slug change adds a 301 |
| `POST posts/{id}/review` | content.edit | Ask for clinical review: draft → in_review; a live or scheduled post sets `review_requested_at`. `CONFLICT` if not a health post or already approved |
| `POST posts/{id}/review/approve` `{lock_version?}` | content.review | `CONFLICT` unless a health post. Records reviewer, time and `reviewed_hash` = current `content_hash`; `next_review_at` = +6 months; in_review → draft. The author may approve their own post (a small clinic may have one clinician): `review.self` is then true, the admin shows "Self-reviewed", and the audit row carries `self_review: true` |
| `POST posts/{id}/review/changes` `{note ≤ 1000}` | content.review | Sends back with a note; in_review → draft |
| `POST posts/{id}/publish` `{lock_version?}` | content.publish | Needs title, dek, ≥ 50 words, a category (`VALIDATION_FAILED`), and for health posts a review of exactly this version (`CONFLICT`, `errors.review`) |
| `POST posts/{id}/schedule` `{at}` · `DELETE posts/{id}/schedule` | content.publish | Same checks; freezes the working copy. `posts:publish-scheduled` (every minute) makes it live |
| `POST posts/{id}/unpublish` | content.publish | `CONFLICT` if not live |
| `GET posts/{id}/revisions` · `POST posts/{id}/revisions/{revision}/restore` `{lock_version}` | content.edit (listing: or content.review) | Last 50. Restore copies into the working copy only |
| `POST posts/{id}/preview` | content.edit or content.review | `{token, url, expires_in}`; `url` is the site's `/preview?post=<slug>&token=` |
| `DELETE posts/{id}` · `POST posts/{id}/restore` | content.edit (+ publish when live) | Trash (soft delete) |
| `GET post-categories` | content.edit or content.review | `[{id, name, slug, description, posts}]` |
| `POST post-categories` · `PUT post-categories/{id}` `{name, slug?, description?}` · `DELETE post-categories/{id}` | content.publish | Return the full list. Slug defaults from the name. Deleting leaves posts uncategorised. Changes show on the website at once, hence publish rights |

Clinical review state (`ReviewState`, derived): not_medical; reviewed (approved hash = current content hash); overdue (reviewed, `next_review_at` passed); needs_review (in review, or changes requested on a live post, or edited since approval); draft (never reviewed). Status workflow (`PostStatus`): draft → in_review · scheduled · published; in_review → draft · scheduled · published; scheduled → draft · published; published → draft · scheduled.

### SEO tabs (`/api/v1/admin`, Figma 08–10)

`{type}` is a registered `SeoSource` morph alias: `page`, `condition`, `post`. Unknown types or ids are `NOT_FOUND`.

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET seo/{type}/{id}` | seo.edit or seo.manage | `{subject{type, id, label, title, path, url, is_home, published, template, template_key, default_schema_type}, meta{…editable fields}, resolved{title, description, default_title, default_description, canonical, share_title, share_description, share_image, site_name, site_host, x_handle}, analysis{score, readability, seo[], readability_checks[], title{chars, px, max_px}, description{…}, keyphrase_uses}, schema{graph…}, links{incoming[], suggestions[≤3]}, page_types[], reviewers[], analyzed_at}`. Uses the working copy |
| `POST seo/{type}/{id}/analyze` | seo.edit | Same body as `PUT`; returns the payload for the unsaved values. Nothing stored |
| `PUT seo/{type}/{id}` | seo.edit | Any subset of `meta_title ≤ 190, meta_description ≤ 320, focus_keyphrase ≤ 100, keyphrases[≤8], canonical_url (https), robots_index, robots_follow, in_sitemap, breadcrumb_title ≤ 80, max_image_preview (large · standard · none), max_snippet (0–1000 or null), og_custom, og_title ≤ 120, og_description ≤ 240, og_image_id, twitter_card (summary · summary_large_image), schema_type (WebPage · MedicalWebPage · AboutPage · ContactPage · CollectionPage · FAQPage · Article), schema_about, schema_disabled[], schema_custom (JSON-LD with an @type, ≤ 20 KB), reviewed_by, last_reviewed_at (≤ today), citations[≤12]{label, url (https)}`. Scores are computed on save, never accepted. Live at once (revalidates `content`, `seo`) |
| `GET seo/{type}/scores?ids[]=…` (≤ 200) | seo.edit or seo.manage | `{id: score\|null}`. Subjects never scored are scored once here |

Content lists carry the same score: `GET pages` and `GET conditions` rows (and `GET posts`) have `seo_score` (int|null; none for trashed pages). It is re-scored on `PUT pages/{id}/content` with `checkpoint`, `PUT posts/{id}` with `checkpoint`, and every `PUT conditions/{id}`.

### SEO overview & site audit (Figma 19, dashboard)

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET seo-audit` | seo.edit or seo.manage | The latest finished audit: `{audit{id, score, previous_score (≥ 7 days earlier), urls, indexable, indexed, trigger, started_at, finished_at}\|null, running, issues[{key, severity (urgent · warning · notice · passed), title, detail, count, items[{key, label, path, admin_path}], action{label, to}}], open, fixed_this_week, search{clicks, impressions, position, queries[5]}\|null, vitals\|null, connections{search_console, core_web_vitals}, site_host}`. The dashboard's SEO card reads this too |
| `POST seo-audit` | seo.manage | 202 `{running}`. Queues `RunSiteAudit`; a run already in progress is reused. `throttle:6,1` |
| `GET seo-audit/export` | seo.edit or seo.manage | CSV of the latest audit's findings (severity, issue, affected, address, checked_at) |

Score: the average checklist score of indexable published URLs minus 5 / 2 / 1 per open urgent / warning / notice issue kind, 0–100. Modules add checks through `SeoAuditChecks` (posts: review overdue; redirects: missing pages with visits in 7 days). `search`, `indexed` and `vitals` stay null unless Search Console credentials (`GOOGLE_SEARCH_CONSOLE_CREDENTIALS`, `GOOGLE_SEARCH_CONSOLE_PROPERTY`) and `CRUX_API_KEY` are set.

### Redirects (Figma 20, `seo.manage`)

| Method & path | Notes |
| --- | --- |
| `GET redirects?search=&filter[type]=301\|302\|307\|410&filter[source]=manual\|slug_change\|imported\|not_found&per_page` | Default 10 per page, most recently hit first. Rows `{id, from_path, to_path, status_code, match_type, is_enabled, source, note, hits, hits_30d, last_hit_at, created_at}`; `meta.total_all` |
| `POST redirects/check` `{from_path, to_path?, id?}` | `{loop, chain[], target{state: ok · missing · external · gone, path}, shadows_content}`; nothing saved |
| `POST redirects` · `PUT redirects/{id}` `{from_path, to_path?, status_code: 301\|302\|307\|410, is_enabled?, note?}` | Paths are lower-cased, trailing slash and query dropped; a trailing `/*` makes a wildcard whose remainder fills `$1`. Targets are a site path or `https://` URL (null for 410). Loops → `CONFLICT`; a published address as `from` → `CONFLICT`; chains are shortened (A→B→C saves A→C) and rules pointing at `from` are re-pointed. POST to an existing `from_path` updates that rule |
| `DELETE redirects/{id}` | |
| `POST redirects/import` (multipart `file`, CSV ≤ 1 MB) | Columns `from,to[,type]`, optional header, ≤ 5,000 lines. `{imported, errors[≤50]}`; bad rows are reported, not saved |
| `GET redirects/export` | CSV: from, to, type, source, enabled, hits, last_hit |

A live page, post or condition whose address changes (`ContentPathChanged`) gets a 301 from the old address (`source: slug_change`); a redirect from the new address is removed. Every change revalidates the site's `redirects` tag.

### 404 monitor (Figma 21, `seo.manage`)

| Method & path | Notes |
| --- | --- |
| `GET not-found?filter[status]=open\|ignored\|resolved&per_page` | Default open, 25 per page, busiest first. Rows `{id, path, hits, google_hits, last_seen_at, referrer{kind, path}, is_bot, status, suggestion{path, score}\|null}` (no suggestion for bot-looking paths). `meta.counts{open, ignored, resolved}`, `meta.stats{week, previous_week, change_percent, unique, from_google, fixed_this_month}` |
| `POST not-found/{id}/resolve` `{to_path?, status_code?: 301\|410}` | Creates the redirect (`source: not_found`) and marks the row resolved. A resolved path that 404s again re-opens |
| `POST not-found/{id}/ignore` · `POST not-found/{id}/restore` | Restore puts it back to open |
| `DELETE not-found/ignored` | Deletes every ignored row; `{deleted}` |
| `GET not-found/export` | CSV: path, hits, from_google, last_seen, referrer, status |

### Sitemap & robots (Figma 22, `seo.manage`)

`GET seo-sitemap` → `{groups[{key, label, setting, enabled, count}] (pages, conditions, posts, images), sitemap_url, sitemap{url, urls, updated_at, read_by_google_at}, robots{text, default, changed_at, changed_by}, notify{live, indexnow, indexnow_ready, search_console_submit, search_console{connected, account, property}, include_lastmod, last_notified_at}}`. `POST seo-sitemap/robots-test {path ≤ 500, content? ≤ 5000}` → `{allowed, line, rule}` for Googlebot against the given text or the saved one (longest match wins). Changes are saved with `PUT seo-settings/sitemap`.

Search-engine notification: `seo:notify` (every 10 minutes) sends URLs whose `lastmod` is newer than the last run to IndexNow and, if `search_console_submit` is on, resubmits the sitemap to Search Console. Nothing is sent unless `SEO_LIVE=true`.

### SEO settings (Figma 23, `seo.manage`)

`GET seo-settings` → `{values, groups[], separators[], default_robots, sensitive_paths[], examples{page, condition, post}, clinic, default_og_image, site_url}`. `PUT seo-settings/{group}` saves any subset of one tab and returns the same payload; an unknown group is `NOT_FOUND`:

| Group | Keys |
| --- | --- |
| `general` | `site_name ≤ 60`, `separator` (one of `\|`, `·`, `—`, `–`), `template_page`, `template_condition`, `template_post` (≤ 120, must contain `%title%`; also `%sep%`, `%site%`, `%category%`), `default_og_image_id` |
| `organization` | `schema_type` (MedicalClinic · MedicalBusiness · Physician), `medical_specialty`, `logo_id`, `price_range` |
| `social` | `x_handle` (stored with a leading @) |
| `verification` | `google`, `bing` |
| `analytics` | `gtm_enabled` (default off), `gtm_container_id` (`GTM-…`, stored upper-case; required when enabled), `gtm_exclude_sensitive` (default on) |
| `sitemap` | `include_pages`, `include_conditions`, `include_posts`, `include_images`, `include_lastmod`, `indexnow`, `search_console_submit`, `robots_txt ≤ 5000` |

Changed keys are audited as `seo.settings_updated` and revalidate the site.

### Google Search Console

There is no admin connect flow: the connection is a service account configured in `.env` (`GOOGLE_SEARCH_CONSOLE_CREDENTIALS` file path or JSON, `GOOGLE_SEARCH_CONSOLE_PROPERTY`). It is used for sitemap submission and "read by Google" time (Figma 22), URL inspection (`indexed`) and 28-day search totals (Figma 19). `connections.search_console` / `notify.search_console.connected` report whether it is configured.

## 10. Planned endpoints

See [ANALYSIS.md §4 and §10](ANALYSIS.md) for the full catalogue. Each phase in [ROADMAP.md](ROADMAP.md) moves its endpoints into this file as they're built.
