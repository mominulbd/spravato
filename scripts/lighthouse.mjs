#!/usr/bin/env node
// @ts-check
/*
 * Lighthouse for the public site (docs/DEVELOPMENT-PLAN.md, Phase 4:
 * performance ≥ 95, LCP < 2.5 s, CLS < 0.1). Runs a mobile and a desktop pass
 * with the system Chrome and writes, per pass, the HTML report plus one
 * summary.json / summary.md beside them in docs/reports/lighthouse/<label>/.
 *
 *   node scripts/lighthouse.mjs [--url http://127.0.0.1:3124/] [--label e2e] [--runs 1]
 *
 * Point it at a production build (`next build && next start`), never
 * `next dev`. tests/e2e/perf.spec.ts runs it against the Playwright stack
 * when LIGHTHOUSE=1. With --runs > 1 each form factor runs several times and
 * the median performance run is kept. Exits non-zero when a budget fails.
 */
import { execFileSync } from "node:child_process";
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { parseArgs } from "node:util";

const LIGHTHOUSE = "lighthouse@13.6.0";
const BUDGET = { performance: 0.95, lcp: 2500, cls: 0.1 };

const { values } = parseArgs({
  options: {
    url: { type: "string", default: process.env.E2E_SITE_URL ? `${process.env.E2E_SITE_URL}/` : "http://127.0.0.1:3124/" },
    label: { type: "string", default: process.env.ALLOW_INDEXING === "true" ? "indexable" : "e2e" },
    runs: { type: "string", default: "1" },
    out: { type: "string" },
  },
});

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const out = resolve(values.out ?? join(root, "docs/reports/lighthouse", values.label ?? "e2e"));
const runs = Math.max(1, Number(values.runs) || 1);
const chrome =
  process.env.CHROME_PATH ??
  ["/Applications/Google Chrome.app/Contents/MacOS/Google Chrome", "/usr/bin/google-chrome", "/usr/bin/google-chrome-stable", "/usr/bin/chromium"].find((path) => existsSync(path));

mkdirSync(out, { recursive: true });

/** @param {"mobile" | "desktop"} formFactor @param {number} run */
function lighthouse(formFactor, run) {
  const base = join(out, runs > 1 ? `${formFactor}-${run}` : formFactor);
  const args = [
    "--yes",
    LIGHTHOUSE,
    values.url ?? "",
    "--quiet",
    "--only-categories=performance,accessibility,best-practices,seo",
    "--output=json",
    "--output=html",
    `--output-path=${base}`,
    "--chrome-flags=--headless=new --no-first-run --no-default-browser-check",
    ...(formFactor === "desktop" ? ["--preset=desktop"] : []),
  ];

  execFileSync("npx", args, { stdio: ["ignore", "inherit", "inherit"], env: { ...process.env, ...(chrome ? { CHROME_PATH: chrome } : {}) } });

  const report = JSON.parse(readFileSync(`${base}.report.json`, "utf8"));

  return { base, report };
}

/** @param {any} report */
function summarise(report) {
  const audit = (/** @type {string} */ id) => report.audits[id];
  const score = (/** @type {string} */ id) => Math.round((report.categories[id]?.score ?? 0) * 100);
  // Lighthouse 13 reports the LCP element in the LCP breakdown insight.
  const lcpElement = audit("lcp-breakdown-insight")?.details?.items?.find((/** @type {any} */ item) => item.type === "node");
  const bytes = (/** @type {string} */ type) => audit("resource-summary")?.details?.items?.find((/** @type {any} */ item) => item.resourceType === type)?.transferSize ?? null;

  return {
    scores: { performance: score("performance"), accessibility: score("accessibility"), bestPractices: score("best-practices"), seo: score("seo") },
    metrics: {
      lcpMs: Math.round(audit("largest-contentful-paint").numericValue),
      cls: Number(audit("cumulative-layout-shift").numericValue.toFixed(3)),
      fcpMs: Math.round(audit("first-contentful-paint").numericValue),
      tbtMs: Math.round(audit("total-blocking-time").numericValue),
      speedIndexMs: Math.round(audit("speed-index").numericValue),
    },
    lcpElement: lcpElement ? { selector: lcpElement.selector, label: lcpElement.nodeLabel, snippet: lcpElement.snippet } : null,
    transfer: { totalBytes: bytes("total"), scriptBytes: bytes("script"), imageBytes: bytes("image"), fontBytes: bytes("font") },
    failedAudits: Object.values(report.categories)
      .flatMap((/** @type {any} */ category) => category.auditRefs.filter((/** @type {any} */ ref) => ref.weight > 0).map((/** @type {any} */ ref) => ({ category: category.id, audit: report.audits[ref.id] })))
      .filter(({ audit }) => audit.score !== null && audit.score < 0.9)
      .map(({ category, audit }) => `${category}: ${audit.id} (${audit.title})`),
  };
}

const summary = { url: values.url, label: values.label, lighthouse: LIGHTHOUSE, date: new Date().toISOString(), runs, results: /** @type {Record<string, any>} */ ({}) };

for (const formFactor of /** @type {const} */ (["mobile", "desktop"])) {
  const passes = Array.from({ length: runs }, (_, run) => lighthouse(formFactor, run + 1)).map(({ base, report }) => ({ base, report, summary: summarise(report) }));
  const median = [...passes].sort((a, b) => a.summary.scores.performance - b.summary.scores.performance)[Math.floor(passes.length / 2)];

  if (!median) continue;

  summary.results[formFactor] = { ...median.summary, report: `${median.base.slice(out.length + 1)}.report.html`, performanceRuns: passes.map((pass) => pass.summary.scores.performance) };

  // The HTML report is the evidence; the multi-MB JSON is summarised above.
  for (const pass of passes) {
    rmSync(`${pass.base}.report.json`, { force: true });
    if (pass !== median) rmSync(`${pass.base}.report.html`, { force: true });
  }
}

const failures = Object.entries(summary.results).flatMap(([formFactor, result]) => [
  ...(result.scores.performance < BUDGET.performance * 100 ? [`${formFactor}: performance ${result.scores.performance} < ${BUDGET.performance * 100}`] : []),
  ...(result.metrics.lcpMs >= BUDGET.lcp ? [`${formFactor}: LCP ${result.metrics.lcpMs} ms ≥ ${BUDGET.lcp} ms`] : []),
  ...(result.metrics.cls >= BUDGET.cls ? [`${formFactor}: CLS ${result.metrics.cls} ≥ ${BUDGET.cls}`] : []),
]);

const rows = Object.entries(summary.results).map(
  ([formFactor, r]) =>
    `| ${formFactor} | ${r.scores.performance} | ${r.scores.accessibility} | ${r.scores.bestPractices} | ${r.scores.seo} | ${(r.metrics.lcpMs / 1000).toFixed(2)} s | ${r.metrics.cls} | ${r.metrics.tbtMs} ms | ${r.lcpElement ? `\`${r.lcpElement.snippet.slice(0, 60)}…\`` : "—"} | [${r.report}](${r.report}) |`,
);

writeFileSync(join(out, "summary.json"), `${JSON.stringify({ ...summary, budget: BUDGET, failures }, null, 2)}\n`);
writeFileSync(
  join(out, "summary.md"),
  [
    `# Lighthouse: ${summary.label}`,
    "",
    `${summary.url} · ${LIGHTHOUSE} · ${summary.date}${runs > 1 ? ` · median of ${runs} runs` : ""}`,
    "",
    "| Form factor | Performance | Accessibility | Best practices | SEO | LCP | CLS | TBT | LCP element | Report |",
    "| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |",
    ...rows,
    "",
    `Budget: performance ≥ ${BUDGET.performance * 100}, LCP < ${BUDGET.lcp / 1000} s, CLS < ${BUDGET.cls}. ${failures.length ? `**Failed:** ${failures.join("; ")}` : "All met."}`,
    "",
    ...Object.entries(summary.results).flatMap(([formFactor, r]) => (r.failedAudits.length ? [`Audits below 90 (${formFactor}): ${r.failedAudits.join("; ")}`, ""] : [])),
  ].join("\n"),
);

console.log(rows.join("\n"));

if (failures.length) {
  console.error(`Lighthouse budget failed: ${failures.join("; ")}`);
  process.exitCode = 1;
}
