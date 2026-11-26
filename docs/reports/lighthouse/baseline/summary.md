# Lighthouse: baseline

http://127.0.0.1:3124/ · lighthouse@13.6.0 · 2026-11-26T04:54:14.995Z

| Form factor | Performance | Accessibility | Best practices | SEO | LCP | CLS | TBT | LCP element | Report |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| mobile | 100 | 96 | 92 | 69 | 0.95 s | 0 | 15 ms | — | [mobile.report.html](mobile.report.html) |
| desktop | 100 | 96 | 92 | 69 | 0.25 s | 0 | 0 ms | — | [desktop.report.html](desktop.report.html) |

Budget: performance ≥ 95, LCP < 2.5 s, CLS < 0.1. All met.

Audits below 90 (mobile): accessibility: target-size (Touch targets do not have sufficient size or spacing.); best-practices: errors-in-console (Browser errors were logged to the console); best-practices: inspector-issues (Issues were logged in the `Issues` panel in Chrome Devtools); seo: is-crawlable (Page is blocked from indexing)

Audits below 90 (desktop): accessibility: target-size (Touch targets do not have sufficient size or spacing.); best-practices: errors-in-console (Browser errors were logged to the console); best-practices: inspector-issues (Issues were logged in the `Issues` panel in Chrome Devtools); seo: is-crawlable (Page is blocked from indexing)

> **Caveat.** This baseline (before the Phase 4 performance changes) ran with the new CSP blocking every
> media-library image (`img-src 'self'`; in the e2e stack Laravel's `/storage` is another origin, :8124), so
> images never loaded: the scores flatter it and best practices lost points to the CSP console errors.
> The later runs set `MEDIA_ORIGINS=http://127.0.0.1:8124`; production serves both from one origin and needs no such setting.
