# MCPWP Site Context

Editorial Contract: 1.0.0
Contract SHA-256: 38c233a20dcb80daec03d53090f6ca11529db8358c35b1ed8f8c1a32f96305e5
Draft-only: true
Human publication required: true

## Active contract

- MCPWP profile: 1.0.0
- Required theme: >=0.2.0 <0.3.0
- MCPWP plugin required: false
- Tested MCPWP plugin: 3.6.1
- Workflow authority: `editorial/workflow.json`
- Required schemas: `content-brief`, `research-packet`, `validation-report`

## Allowed roles

- `brief-creator`
- `discovery-reviewer`
- `editor-handoff`
- `researcher`
- `scout`
- `technical-verifier`
- `writer`

Each role owns only its declared workflow transition. The human editor accepts artifacts, approves drafts, and controls every human-only transition.

## Formats

- `analysis-opinion`
- `comparison-review`
- `explainer`
- `news-briefing`
- `practical-guide`
- `test-report`

Select only a listed format and satisfy its template-specific evidence gate before moving forward.

## Universal gates

- Use an active, compatible contract and schema-valid artifacts.
- Require an accepted brief and accepted research packet before drafting.
- Preserve one primary canonical intent; return duplicate intent for a human consolidation, update, or redirect decision.
- Map material factual claims to current primary sources or recorded observations; retain uncertainty and contradictory evidence.
- Keep facts, inference, opinion, authorship, AI assistance, human review, dates, versions, and commercial relationships accurate and visible.
- Require template, technical-verification, and discovery checks to pass before human review.
- Record the actor, contract version, artifact versions, validation result, unresolved risks, and next allowed role for every transition.

## WordPress handoff

- Confirm the active contract, accepted inputs, canonical URL, related posts, and authorized WordPress capabilities before writing.
- The writer may create or update only the authorized WordPress draft and fields; retain revisions and return the draft ID, preview URL, slug, and validation report.
- Preserve draft status, valid heading order, one public H1, valid links and images, and valid block markup.
- WordPress statuses remain standard; the machine workflow is represented by GitHub labels.

## Stop conditions

- Stop before mutation for a missing or incompatible contract, invalid or unaccepted inputs, duplicate intent, missing evidence, unsupported template, or unavailable or unauthorized capability.
- Stop and return defects to their owning role when a machine gate fails; warnings do not advance the workflow.
- Keep the last valid revision when block markup fails.
- Keep work in human review when reviewer identity, disclosure, or unresolved risks are missing.
- Do not infer unavailable WordPress operations or use broader operations as substitutes.

## Human authority

- Only a human editor may accept a brief or research packet, approve a draft, publish, schedule, redirect, delete, change canonicals, retire content, decide public corrections, or approve exceptions and commercial conclusions.
- Human review is required before approval and publication. Internal roles and ASTER do not hold publication authority.
