# Self-check quiz (document v2)

The self-check is a one-question-per-card wizard modelled on a PHQ-9 / GAD-7
intake: welcome card → name → concerns → which test → scored questions →
treatment history (branching) → contact → results per scale → feedback →
thank you. It is one validated JSON document (`quizzes.draft`, published as a
revision), validated by `Uptms\Quiz\Services\QuizDocument` and evaluated by
`Uptms\Quiz\Services\QuizFlow`. The admin builder (`resources/js/admin/features/quiz`)
and the website (`uptms-app/components/quiz`) mirror the visibility rules below.

## Document

```jsonc
{
  "version": 2,
  "intro": { "title": "Let's evaluate your symptoms.", "body": "…", "start_label": "Start", "note": "Encrypted & confidential" },
  "privacy_note": "All information is securely stored and confidential.", // under contact steps
  "steps": [ /* Step, 1–60 */ ],
  "scales": [ /* Scale, 0–4 */ ],
  "crisis": { "title": "…", "body": "…" },          // shown when a crisis choice is picked
  "result_disclaimer": "…",                          // under every results card
  "done": { "title": "…", "body": "…", "primary": Link, "secondary": Link } // last card
}
```

`Link` is `{label, href}` (both may be empty). Hrefs allow `/path`, `#anchor`, `https://`, `tel:`, `mailto:`;
the site resolves `{phone}` in labels and `tel:clinic` like every other CMS link.

### Step

| Field | Notes |
| --- | --- |
| `key` | `^[a-z0-9-]{1,30}$`, unique. Answers and conditions refer to it. |
| `type` | `name` (first + last), `single`, `multi`, `email`, `phone`, `text` (long answer), `results` (shows one scale's result; no answer). |
| `prompt` | ≤ 200. `*word*` is shown emphasised (bold). Required except for `results` (empty → the scale's title is used). |
| `help` | ≤ 200, may be empty. The grey line under the prompt. |
| `required` | Bool. `name` and `phone` are always required. `results` is never required. |
| `layout` | `single`: `grid` · `stack` · `buttons` (big side-by-side buttons, e.g. Yes/No) · `select` (dropdown). `multi`: `grid` · `stack`. Others: `stack`. |
| `choices` | `single`/`multi` only, 2–14. `{label ≤120, value ^[a-z0-9-]{1,30}$ unique in step, points int 0–10 or null, exclusive bool, crisis bool}`. |
| `scale` | `single` steps: the scale key it scores into (every choice then needs `points`). `results` steps: the scale shown (required). Else null. |
| `show_if` | 0–4 conditions, all must hold: `{step, op: "in" \| "not_in", values: [choice values]}`. `step` must be an earlier `single`/`multi` step and the values must exist on it. |

- `exclusive` (multi only): picking it clears the other choices; picking another clears it ("None of the above", "I'm not sure").
- `crisis` (single only): picking it shows the `crisis` panel at once, and the submission is flagged for an immediate call-back.

### Scale

`{key, label ("Depression"), instrument ("PHQ-9"), title ("Results of your Depression Quiz"), bands: [{min, max, title, body}]}`.
The maximum is computed: the sum of the highest `points` of each step scored into the scale. Bands are sorted, start at 0, end at the maximum and leave no gaps or overlaps.

### Document-level rules

- Exactly one `name` and one `phone` step; at most one `email` step. They come before the first `results` step.
- The steps before the first `results` step are **the submission**. The steps after it are **feedback**, and may only be `single`, `multi`, `text` or `results`, without `scale` or `crisis`.
- With no `results` step, the whole quiz is the submission, sent from the last step.

## Visibility (mirrored in PHP and TypeScript)

Steps are walked in order. A hidden step's answer is ignored (treated as unanswered) by every later condition.

- A condition looks at the answer of `step` as a list of values (`single` → one value, `multi` → its values, unanswered/hidden → empty).
  `in` holds when the list shares a value with `values`; `not_in` holds when it shares none (so an unanswered or hidden step passes `not_in`).
- A step is visible when all its conditions hold.
- A scale is **taken** when at least one of its scored steps is visible. A `results` step is visible only when its scale is taken (and its own conditions hold).

## Scoring

A taken scale's score is the sum of the `points` of the chosen answers of its visible scored steps. The band is the one whose `min ≤ score ≤ max`.
Scores are always computed by Laravel from the **published** document; the website only displays what the API returns.

## API

- `GET /public/quiz` → the published document.
- `POST /public/quiz-submissions` (consultations module, throttle `public-forms`, Turnstile, honeypot `website`):
  `{answers: {<key>: "value" | ["v", …] | {first, last} | "text"}, source_page, referrer, utm, turnstile_token, website}`
  with the answers of the submission steps (contact steps included: `name` → `{first, last}`, `email`/`phone` → string).
  → `201 {reference, feedback_token, scales: [{key, label, instrument, title, score, max, band: {title, body, min, max}, bands: [{min, max, title}]}], crisis}`.
  422 errors use keys `answers.<key>` (and `answers.<key>.first` / `.last`).
  The request lands in the consultation inbox (encrypted `quiz_results`), so staff follow up like any other request.
- `POST /public/quiz-submissions/feedback` `{feedback_token, answers}` with the feedback steps' answers → `200 {}`. The token is valid for 2 hours.

Nothing about the person's answers is logged, and the receipt sent to the person names the reference, not the scores. The staff alert emails the scores (`PHQ-9 8/27 · GAD-7 14/21`, plus a note when a crisis answer was flagged) to the clinic's notification address while Clinic settings → Privacy → "Include the request in the alert" is on; with it off the alert is a link only.

## Admin

- `GET/PUT /admin/quiz`, `POST /admin/quiz/publish` are unchanged; `draft` is the v2 document. 422 keys follow the document,
  e.g. `draft.steps.4.choices.2.points`, `draft.steps.7.show_if.0.values`, `draft.scales.0.bands`.
- `GET /admin/consultation-requests/{id}` adds `origin` (`"form"` or `"quiz"`) and `quiz`:
  `null | {submitted_at, crisis, scales: [{key, label, instrument, score, max, band}], answers: [{prompt, answer}], feedback: [{prompt, answer}] | null}`
  (`answer` is the chosen labels joined with ", "; `band` is the band title). The list rows add `origin`.
