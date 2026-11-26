# UPTMS — Database

MySQL 8.4+ / 9.x, InnoDB, `utf8mb4_unicode_ci`. Times are stored in UTC and shown in the clinic's time zone through settings.

| Database | Used by |
| --- | --- |
| `uptms` | local development (`.env`) |
| `uptms_test` | Pest feature tests (`phpunit.xml`, refreshed per test) |
| `uptms_e2e` | Playwright (`migrate:fresh --seed` before each run) |

## Conventions

- snake_case plural table names. Each module's migrations live in `packages/<module>/migrations`.
- `id` BIGINT primary keys; a `uuid` column on rows the clients reference in URLs.
- Status columns are backed by PHP enums with `allowedTransitions()`, and are never mass-assignable.
- Foreign keys always declared; `restrictOnDelete` by default, `cascadeOnDelete` only for owned children.
- Soft deletes on user-facing content (pages, posts, media in trash). Consultation requests are the exception: deleting one, or reaching the end of retention, removes it for good.
- Health information (consultation request name/phone/email/message, notes) uses Laravel `encrypted` casts; searchable lookups use separate keyed hashes, never plaintext.
- Indexes for every list filter and sort in `API.md §5`.

## Schema · Phase 1

Laravel skeleton tables: `password_reset_tokens`, `sessions` (database driver), `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, and `users`, which `uptms.users` extends.

| Table | Module | Columns | Notes |
| --- | --- | --- | --- |
| `audit_logs` | audit_log | user_id → users (null on delete), action, auditable_type/id/label, old_values JSON, new_values JSON, ip_address, user_agent, request_id, created_at(3) | Append-only (the model refuses update/delete). Indexes: (auditable_type, auditable_id), (user_id, created_at), (action, created_at), created_at. Secrets are redacted before writing; patient data never goes in |
| `permissions` | permissions | key `{module}.{action}` unique, module, action, label, description | Seeded from `PermissionRegistry` |
| `roles` | permissions | slug unique, name, description, is_system, requires_two_factor | Four built-in roles, all requiring 2FA |
| `role_permission` | permissions | role_id, permission_id (PK both, cascade) | |
| `users` (+ columns) | users | uuid unique, role_id → roles (restrict), password **nullable** (invited), is_active, two_factor_secret (**encrypted**), two_factor_recovery_codes (**encrypted** JSON of sha256 hashes), two_factor_confirmed_at, last_login_at, last_seen_at, deleted_at | Soft delete; index (is_active, deleted_at). Status is derived: invited / active / deactivated |
| `user_invitations` | users | user_id (cascade), invited_by → users (null on delete), token_hash sha256 unique, expires_at, accepted_at | The newest unaccepted, unexpired row is the live link |

## Schema · Phase 2

| Table | Module | Columns | Notes |
| --- | --- | --- | --- |
| `revisions` | publishing | revisionable_type/id, kind (draft · published · scheduled · restored), payload JSON, note, user_id → users (null on delete), created_at | Immutable snapshots. Index (revisionable_type, revisionable_id, id) |
| `media_folders` | media | name, slug unique, sort_order | |
| `media` | media | uuid unique, folder_id → media_folders (null on delete), disk, path, filename, original_name, mime, kind (image · document), size, original_size, width, height, alt, is_decorative, caption, focal_x/y (0–100), uploaded_by → users, first_used_at, last_used_at, deleted_at | Soft delete = 30-day trash. Index (deleted_at, created_at), (kind, deleted_at) |
| `media_variants` | media | media_id (cascade), format (avif · webp), width, height, path, size | unique (media_id, format, width) |
| `media_usages` | media | media_id (cascade), usable_type/id, context, label, admin_path | Written only by `MediaUsageRecorder`. unique (media_id, usable_type, usable_id, context) |
| `settings` | settings | group, key, value JSON | unique (group, key). Keys and rules live in `SettingsRegistry`; missing keys read as defaults |
| `business_hours` | settings | day_of_week (ISO 1–7) unique, is_open, opens_at, closes_at | |
| `business_closures` | settings | date unique, label | |
| `team_members` | collections | name, credentials, role_title, bio, highlights JSON, photo_id → media (null on delete), show_on JSON, is_founder, schema_type, licence_number, is_published, sort_order, deleted_at | Index (is_published, sort_order) |
| `faqs` | collections | group (home · tms · conditions/<slug>), question, answer, in_schema, is_published, sort_order | Index (group, is_published, sort_order) |
| `testimonials` | collections | quote, author_name, source_label, source_url, rating, is_published, sort_order, deleted_at | |
| `insurance_partners` | collections | name, logo_id → media, url, note, is_published, sort_order | |
| `content_items` | collections | group (trust_points · journey_steps), title, body, icon, link_label, link_url, is_published, sort_order | Index (group, is_published, sort_order) |
| `conditions` | conditions | slug unique, status (draft · published), eyebrow, title, summary, card_link_label, image_id → media, detail_title, detail_body, source_label/url, cta_label/href, reviewed_at, reviewed_by → users, published_at, sort_order, deleted_at | Index (status, sort_order) |
| `pages` | pages | uuid, parent_id → pages, title, slug, path unique, template, status (draft · scheduled · published), is_home, published_at, scheduled_at, published_revision_id / scheduled_revision_id → revisions, lock_version, draft_hash, published_hash, content_updated_at, created_by/updated_by → users, deleted_at | The website reads the published revision, never `page_blocks`. Index (status, deleted_at), (status, scheduled_at), (template, deleted_at) |
| `page_blocks` | pages | page_id (cascade), type, data JSON, is_visible, sort_order | The working copy. Index (page_id, sort_order). Links to official PDFs in `data` are stored by media id: a link field as `{label, href: "", media_id}`, a link row with a `document` field (FAQ `aside_links`) as `{label, href: "", media_id}`. Both count in `media_usages` |
| `menus` | menus | key unique (header · mobile · section-nav · footer-explore · footer-legal · documents), name, description, sort_order | |
| `menu_items` | menus | menu_id (cascade), parent_id → menu_items (cascade), label, type (page · anchor · link · document), page_id → pages (null on delete), media_id → media (null on delete; `document` items), anchor, url, is_visible, sort_order | One level of nesting. Index (menu_id, parent_id, sort_order). A document item stores the media id, never the file address: replacing the PDF changes its address (`media/{uuid}/{rev}-{slug}.pdf`) and deletes the old file, so the address is resolved through `MediaPresenter::presentDocument` when the menu is read. Saving a menu records its documents in `media_usages` (owner `menu`), so they can't be trashed while linked and replacing one refreshes the site |
| `quizzes` | quiz | key unique, title, draft JSON, lock_version, draft_hash, published_hash, published_revision_id → revisions, published_at, updated_by → users | The quiz is one validated document (v2: steps, scales, bands; docs/QUIZ.md) instead of the four `quiz_*` tables first planned: it is always edited and published whole |

`SiteContentSeeder` (database/seeders) fills all of the above with the Figma copy and images on a fresh install and never overwrites edits.

## Schema · Phase 3

| Table | Module | Columns | Notes |
| --- | --- | --- | --- |
| `consultation_requests` | consultations | reference unique (`CR-2026-0042`, set after insert), name · phone · email · message · insurance (**encrypted**), topic (spravato · eligibility · first_visit · insurance · question), origin (form), cta (label of the button that opened the dialog), best_time (morning · afternoon · early_evening · no_preference), contact_method (phone · email · either), patient_status (new · existing · not_sure), emergency_acknowledged, status (new · contacted · scheduled · closed · spam), assigned_to → users (null on delete), follow_up_at, first_viewed_at, contacted_at, scheduled_at, closed_at, source_page, previous_page, referrer_host, utm JSON, device, phone_hash, search_index | No IP address or raw user agent is stored. `search_index` holds 16-character HMACs of name words, the phone number, its last four digits and the email, so staff search without plaintext. Not model-audited: actions write audit rows without patient details. Indexes (status, created_at), (assigned_to, status), (phone_hash, created_at), created_at |
| `consultation_request_notes` | consultations | consultation_request_id (cascade), user_id → users (null on delete), body (**encrypted**) | Index (consultation_request_id, id) |

Retention: `consultations:purge` (daily, 03:30) deletes requests older than Clinic settings → Privacy → retention days (default 365) and spam after 30 days. The audit log keeps only the reference.

## Schema · Phase 4

| Table | Module | Columns | Notes |
| --- | --- | --- | --- |
| `seo_meta` | seo | seoable_type (page · post · condition), seoable_id, meta_title, meta_description, focus_keyphrase, keyphrases JSON, canonical_url, robots_index, robots_follow, in_sitemap, breadcrumb_title, max_image_preview (large · standard · none), max_snippet (null = no limit), og_custom, og_title, og_description, og_image_id → media (null on delete), twitter_card, schema_type, schema_about, schema_disabled JSON, schema_custom JSON, reviewed_by → users (null on delete), last_reviewed_at (date), citations JSON, seo_score, readability_score, analysis JSON, analyzed_at, updated_by → users (null on delete) | One row per subject, created on first use; empty fields fall back to content and title templates. Scores and `analysis` are written only by the server. No FK to the subject (polymorphic). unique (seoable_type, seoable_id); index focus_keyphrase |
| `seo_settings` | seo | key unique, value JSON | Keys and rules live in `SeoSettings` (general · organization · social · verification · sitemap); missing keys read as defaults. Also holds the internal key `notified_at` (and `indexnow_key` on installs that stored one; new ones derive it from `APP_KEY`) |
| `seo_audits` | seo | status (running · done · failed), trigger (schedule · manual), triggered_by → users (null on delete), score, urls, indexable, indexed (null without Search Console), issues JSON, search JSON (Search Console 28-day totals and top queries), vitals JSON (Chrome UX Report, phones), started_at, finished_at | Issues keep stable item keys so "fixed this week" can compare runs. Index (status, started_at) |
| `post_categories` | posts | name, slug unique, description, sort_order | Deleting one leaves its posts uncategorised |
| `tags` | posts | name, slug unique | |
| `posts` | posts | uuid unique, title, slug unique, dek, body JSON (validated rich-text tree), cover_image_id → media, category_id → post_categories, author_id → users (all null on delete), status (draft · in_review · scheduled · published), is_medical, reviewed_by → users, reviewed_at, reviewed_hash, next_review_at (date), review_note, review_requested_at, word_count, reading_minutes, published_revision_id / scheduled_revision_id → revisions, published_at, scheduled_at, lock_version, draft_hash, content_hash, published_hash, created_by/updated_by → users, deleted_at | Like pages, the website reads the published revision. A health post publishes only when `reviewed_hash` = `content_hash` (the words and images a clinician approved); approval lasts 6 months (`next_review_at`). Category and tag edits change `draft_hash` but not `content_hash`. Soft delete. Indexes (status, deleted_at, published_at), (category_id, status), (status, scheduled_at), (is_medical, next_review_at) |
| `post_tag` | posts | post_id, tag_id (PK both, cascade) | |
| `redirects` | redirects | from_path unique, to_path (null for 410), status_code (301 · 302 · 307 · 410), match_type (exact · wildcard), is_enabled, source (manual · slug_change · imported · not_found), note, hits, last_hit_at, created_by → users (null on delete) | Paths stored normalised (lower case, no trailing slash or query). Indexes (is_enabled, match_type), source |
| `redirect_hits` | redirects | redirect_id (cascade), day, hits | PK (redirect_id, day). Feeds "Hits · 30 d" |
| `not_found_logs` | redirects | path unique, hits, google_hits, referrer_kind (google · search · internal · external · direct, most recent), referrer_path (the site page linking here), status (open · ignored · resolved), is_bot (path pattern), first_seen_at, last_seen_at, resolved_at | No IP address, user agent or full referring URL is stored. Index (status, last_seen_at) |
| `not_found_hits` | redirects | not_found_log_id (cascade), day, hits, google_hits | PK (not_found_log_id, day); index day |

Scheduled jobs: `seo:audit` (daily 06:00 in `CLINIC_TIMEZONE`) records a `seo_audits` row and deletes audits older than 90 days; `seo:notify` (every 10 minutes, only with `SEO_LIVE=true`) pings IndexNow and Search Console; `posts:publish-scheduled` (every minute); `redirects:prune` (daily 03:45) deletes `redirect_hits` and `not_found_hits` rows older than 90 days and `not_found_logs` not seen for 180 days (redirect totals are kept). Ignored 404s can also be cleared from the admin.

## Planned schema

See [ANALYSIS.md §3 and §10.3](ANALYSIS.md). Each phase documents its tables here as they're migrated.
