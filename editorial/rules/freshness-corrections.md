Contract version: 1.0.0

# Freshness, corrections, and editorial state

## Freshness classes

- `evergreen`: reviewed at least every twelve months or when a named dependency changes.
- `version-sensitive`: reviewed when a named product, API, model, plugin, or platform version changes.
- `news`: interpreted as of a stated publication date and either connected to a durable page or retired from promotion when no longer current.

Every brief names its class and update trigger. Public content exposes sources, the relevant tested or publication date, limitations, correction and update history, and the next retest or review trigger.

## GitHub workflow states

GitHub issue labels represent these editorial workflow states in version 1, in canonical order:

1. `idea`
2. `brief_ready`
3. `brief_accepted`
4. `research_ready`
5. `research_accepted`
6. `drafting`
7. `technical_verification`
8. `discovery_review`
9. `human_review`
10. `approved`
11. `published`
12. `update_due`, `corrected`, or `retired`

The machine-readable authority is `editorial/workflow.json`. Only `human-editor` may perform `brief_ready` to `brief_accepted`, `research_ready` to `research_accepted`, `human_review` to `approved`, `approved` to `published`, or any post-publication transition from `published` to `update_due`, `corrected`, or `retired`.

Every transition records the actor or role, contract version, timestamp, input artifact versions, validation result, unresolved risks, and next allowed role. Failed validation returns the artifact to the role that owns the defect and does not advance with a warning.

## Corrections and retirement

Corrections preserve the prior evidence, reviewer decision, correction reason, correction date, and update history. Removed or changed URLs are reflected promptly in sitemaps and, when configured, IndexNow. Redirects, deletions, canonical changes, retirement, and public correction decisions require a human editor.

## Human-only authority

`human-editor only: redirects, deletions, canonical changes, retirement, public correction decisions`

## Fail closed

A missing or incompatible contract stops before mutation. Duplicate intent produces a consolidation, update, or redirect proposal rather than a new draft. A missing human reviewer remains in `human_review`. Stale evidence that no longer supports a material claim moves to human-controlled `update_due`; no agent silently changes published content or publication state.
