# Reports

Launch evidence for Phase 4 (docs/DEVELOPMENT-PLAN.md).

## Lighthouse (`lighthouse/`)

Written by `scripts/lighthouse.mjs` (Lighthouse 13.6, system Chrome, mobile and desktop presets) against the
production build of the site in the Playwright e2e stack. Each folder holds `summary.md`, `summary.json` and the
HTML report of the median run.

| Run | What | Folder |
| --- | --- | --- |
| baseline | Before the performance pass (images blocked by CSP, see its caveat) | `lighthouse/baseline/` |
| e2e | After, as the e2e site runs (pre-launch `noindex`), median of 3 | `lighthouse/e2e/` |
| indexable | After, with `ALLOW_INDEXING=true`: confirms SEO 100 once indexing is allowed | `lighthouse/indexable/` |

SEO is 69 in `e2e` only because the page is deliberately `noindex` before launch (`is-crawlable`).

Re-run (holds ports 8124/3124 and the `spravato_e2e` database):

```sh
MEDIA_ORIGINS=http://127.0.0.1:8124 LIGHTHOUSE=1 npx playwright test tests/e2e/perf.spec.ts
ALLOW_INDEXING=true MEDIA_ORIGINS=http://127.0.0.1:8124 LIGHTHOUSE=1 LIGHTHOUSE_RUNS=1 npx playwright test tests/e2e/perf.spec.ts
```

Against any running production build: `node scripts/lighthouse.mjs --url https://example.test/ --label staging`.
