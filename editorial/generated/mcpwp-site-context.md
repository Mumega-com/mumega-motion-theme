# MCPWP Site Context

Editorial Contract: 1.0.0
Contract SHA-256: da7e8a3aa02c1973ac6db48ed5ba98a247e94f11994e028491e5dc3130f201a4
Draft-only: true
Human publication required: true

## Active contract

- MCPWP profile: 1.0.0
- Required theme: >=0.2.0 <0.3.0
- MCPWP plugin required: false
- Tested MCPWP plugin: 3.6.1
- Workflow authority: `editorial/workflow.json`
- Required schemas: `content-brief`, `research-packet`, `validation-report`

## Workflow transitions

- `null` → `idea` — actor `scout` — gates `schema`, `scope-duplication` — next `brief-creator`
- `idea` → `brief_ready` — actor `brief-creator` — gates `schema`, `scope-duplication` — next `human-editor`
- `brief_ready` → `brief_accepted` — actor `human-editor` — gates `schema`, `scope-duplication`, `human` — next `researcher` — human-only
- `brief_accepted` → `research_ready` — actor `researcher` — gates `schema`, `evidence` — next `human-editor`
- `research_ready` → `research_accepted` — actor `human-editor` — gates `schema`, `evidence`, `human` — next `writer` — human-only
- `research_accepted` → `drafting` — actor `writer` — gates `schema`, `scope-duplication`, `evidence`, `template`, `wordpress` — next `technical-verifier`
- `drafting` → `technical_verification` — actor `technical-verifier` — gates `schema`, `evidence`, `template`, `wordpress` — next `discovery-reviewer`
- `technical_verification` → `discovery_review` — actor `discovery-reviewer` — gates `schema`, `scope-duplication`, `discovery` — next `editor-handoff`
- `discovery_review` → `human_review` — actor `editor-handoff` — gates `schema`, `scope-duplication`, `evidence`, `template`, `wordpress`, `discovery` — next `human-editor`
- `human_review` → `approved` — actor `human-editor` — gates `schema`, `scope-duplication`, `evidence`, `template`, `wordpress`, `discovery`, `human` — next `human-editor` — human-only
- `approved` → `published` — actor `human-editor` — gates `schema`, `scope-duplication`, `evidence`, `template`, `wordpress`, `discovery`, `human` — next `human-editor` — human-only
- `published` → `update_due` — actor `human-editor` — gates `evidence`, `human` — next `human-editor` — human-only
- `published` → `corrected` — actor `human-editor` — gates `schema`, `evidence`, `human` — next `human-editor` — human-only
- `published` → `retired` — actor `human-editor` — gates `scope-duplication`, `human` — next `human-editor` — human-only

## Role permissions and prohibitions

### `brief-creator`

**Inputs**

Accepted gap proposal.

**Output**

Schema-valid content brief.

**May**

Define intent, audience, format, and evidence requirements.

**May not**

Mark its own brief accepted; accept research; approve an exception; publish; create a redirect; delete content; change a canonical URL; or create or update a WordPress post. This role must use `wordpress_operation: none`.

**Stop conditions**

Stop and return field-level errors when required brief fields are invalid or absent. Stop before research when canonical intent conflicts, unique value or scope is insufficient, evidence cannot be obtained, commercial relationships are unknown, the human reviewer role is absent, or the workflow requires an unsupported capability.

### `discovery-reviewer`

**Inputs**

Technically verified draft and corpus map.

**Output**

SEO/GEO and knowledge-structure report.

**May**

Flag unclear intent, duplicate coverage, poor headings, missing internal links, crawl barriers, mismatched metadata, and inconsistent entities.

**May not**

Create, update, edit, or correct the WordPress draft or metadata; add keyword variants or unsupported FAQ content solely for ranking; accept artifacts; approve an exception; publish; create a redirect; delete content; change a canonical URL; or introduce a doorway variant. This role must use `wordpress_operation: none`.

**Stop conditions**

Stop with a failing report when intent is duplicated or unclear, visible copy and metadata diverge, headings or internal links misrepresent the hierarchy, crawl or canonical prerequisites are not testable, entities are inconsistent, or required facts lack sources. Return content or metadata defects and proposed corrections to the owning role; never solve a failure by creating query-fan-out pages.

### `editor-handoff`

**Inputs**

All artifacts and a draft that passed machine gates.

**Output**

Concise human decision packet.

**May**

Summarize remaining risks and required choices.

**May not**

Publish or impersonate the human reviewer; accept a brief or research packet; approve an exception; create a redirect; delete content; change a canonical URL; conceal unresolved risks; or advance a draft that failed a machine gate. This role must use `wordpress_operation: none`.

**Stop conditions**

Stop when any required artifact or validation result is missing, a machine gate failed, reviewer identity or unresolved risks are hidden, or commercial and sensitive claims are not surfaced for explicit decision. If the human reviewer is missing, remain in `human_review` and never advance.

### `researcher`

**Inputs**

Accepted brief.

**Output**

Research packet with claim-level evidence.

**May**

Browse current primary sources and record uncertainty.

**May not**

Draft the publishable article or silently broaden scope; accept its own packet; approve an exception; publish; create a redirect; delete content; change a canonical URL; or create or update a WordPress post. This role must use `wordpress_operation: none`.

**Stop conditions**

Stop when the brief is not accepted or contract-compatible, material claims lack current primary sources or observations, sources are weak or inaccessible, conflicting evidence needs editorial judgment, scope would have to broaden, or evidence cannot legally or practically be obtained. Do not fill gaps with model memory.

### `scout`

**Inputs**

Published corpus, active briefs, primary topic map, and current platform changes.

**Output**

Gap proposal or update proposal.

**May**

Identify overlap, stale content, and emerging primary sources.

**May not**

Create or update a WordPress post; advance an existing workflow state; accept a brief or research packet; approve an exception; publish; create a redirect; delete content; or change a canonical URL. This role must use `wordpress_operation: none`.

**Stop conditions**

Stop when no accepted gap or update proposal exists, the proposed intent overlaps an active brief or published canonical, the site profile is unavailable, or creating the record would mutate any existing workflow state or WordPress content.

### `technical-verifier`

**Inputs**

Draft, brief, research packet, and test artifacts.

**Output**

Pass/fail report with corrections.

**May**

Reproduce supported procedures, inspect permissions, check versions, and validate rollback claims.

**May not**

Create, update, edit, or correct the WordPress draft, even when corrections are authorized; rewrite conclusions to hide failed verification; accept research; approve an exception; publish; create a redirect; delete content; or change a canonical URL. This role must use `wordpress_operation: none`.

**Stop conditions**

Stop with a failing report when procedures cannot be reproduced, environment or version evidence is missing, permissions are unknown, rollback is unsafe or unverified, artifacts conflict with claims, or corrections would conceal a failure. Record corrections in its report and return them to the writer or other owning role. Do not advance with a warning.

### `writer`

**Inputs**

Accepted brief, accepted research packet, and selected template.

**Output**

WordPress draft and validation artifact.

**May**

Synthesize, organize, and explain supported material; request only `create-draft` or `update-draft` with a strict `wordpress_target` for the authorized WordPress draft and fields when the required operation is exposed and authorized.

**May not**

Introduce unsupported material claims, publish, change canonical URLs, create redirects, delete content, accept artifacts, approve exceptions, alter another post, or substitute a broader WordPress operation for a missing capability.

**Stop conditions**

Stop before content mutation when the contract is missing or incompatible, either input artifact is unaccepted or invalid, canonical intent conflicts, required evidence is absent, the selected template cannot be satisfied, or a required WordPress operation is unavailable or unauthorized. Preserve the last valid revision when Gutenberg markup breaks and report the actual missing capability.

## Format evidence gates and stop conditions

### `analysis-opinion`

**Use when**

Use for a clearly labelled interpretation.

**Evidence gate**

Every factual premise must map to a current primary source or original observation. The strongest credible counterargument and contradictory evidence must be represented fairly; facts, inference, and opinion must be explicitly distinguishable; and authorship, AI assistance, human review, and commercial relationships must be disclosed.

**Stop conditions**

Stop when the thesis depends on an unsupported material claim, the strongest counterargument is missing, fact and inference cannot be separated, relevant contradictory evidence is suppressed, the public author or commercial relationship is unclear, or no observable condition could change the conclusion. Request editorial judgment rather than manufacturing certainty.

### `comparison-review`

**Use when**

Use for an actual reader decision, not affiliate inventory.

**Evidence gate**

Official documentation, pricing, terms, and changelogs are required for each compared product. Evidence must be current, collected against the same disclosed criteria, and mapped in `research-packet.json`. A product may receive a `Tested` label only when direct testing evidence records its environment, version, date, and artifacts. Commercial access and relationships must be visible.

**Stop conditions**

Stop when any option lacks the required official documentation, pricing, terms, or changelog evidence; criteria cannot be applied comparably; a `Tested` label lacks direct evidence; commercial relationships are unknown; or the conclusion is affiliate inventory rather than an independent reader decision. Preserve material disagreement and request editorial judgment.

### `explainer`

**Use when**

Use for a durable concept or ecosystem question.

**Evidence gate**

Every material factual claim must map to a current primary source or recorded observation in `research-packet.json`. Definitions must use consistent entity names, credible disagreement must remain visible, and the WordPress example must be supported by the stated version or environment.

**Stop conditions**

Stop and return to research when a key definition, mechanism, or WordPress example lacks evidence; primary sources are weak, inaccessible, or contradictory without an editorial decision; the brief overlaps an existing canonical intent; or the concept cannot be bounded to the accepted audience and scope. Do not fill gaps with model memory.

### `news-briefing`

**Use when**

Use for a time-sensitive development that needs interpretation.

**Evidence gate**

The change, date, affected entities, and recommended actions must trace to a current primary announcement or artifact. Reporting must separate confirmed facts from interpretation, label unknowns, preserve conflicting accounts, and connect the briefing to a durable topic page.

**Stop conditions**

Stop when no primary announcement or artifact can verify the development, event timing or affected audience is uncertain without disclosure, the item duplicates a durable page without added interpretation, the durable-page connection is absent, or material unknowns would make the recommended action unsafe. Do not turn speculation into a dated fact.

### `practical-guide`

**Use when**

Use when the reader wants a repeatable outcome.

**Evidence gate**

The procedure must be reproduced in the named environment. Test claims must include environment, versions, date, and artifacts; required permissions and authorized operations must be verified; and verification, failure, rollback, security, and data claims must map to sources or observations in `research-packet.json`.

**Stop conditions**

Stop when prerequisites or permissions are unknown, a required capability is unavailable or unauthorized, the procedure cannot be reproduced, tested versions or artifacts are absent, a material failure mode is unresolved, or rollback cannot be verified. Record the failed reproduction and correct or narrow the guide; never imply success from an untested sequence.

### `test-report`

**Use when**

Use for original evidence.

**Evidence gate**

Each result and conclusion must trace to a dated artifact or sourced fact. The environment, versions, procedure, observations, failures, anomalies, counterevidence, and uncertainty must be recorded well enough for reproduction, and the conclusion may not extend beyond the observed evidence.

**Stop conditions**

Stop when environment or version data is missing, artifacts cannot be preserved, the procedure is not reproducible, observations cannot be separated from interpretation, contradictory results are omitted, or a failed reproduction would require hiding or broadening the evidence. Record failure honestly and narrow or reject the conclusion.

## Evidence and discovery rules

### Claim-level records

Each material claim records:

- claim text or bounded proposition;
- claim type: observed, sourced fact, calculation, interpretation, or opinion;
- source URL or test evidence reference;
- publisher or owner;
- publication and access dates;
- relevant version or environment;
- confidence and unresolved uncertainty;
- whether the claim may become stale;
- the exact article section that will use it.

The packet also records:

- counterevidence and credible disagreement;
- definitions that require consistent wording;
- terminology and entity-name normalization;
- copyright-sensitive quotations;
- screenshots, logs, or test artifacts;
- facts that must not be claimed.

### Evidence rules

- Material factual claims map to a source or observation.
- Primary sources are current enough for the claim.
- Test claims include environment, versions, date, and artifacts.
- Contradictory evidence is recorded rather than omitted.
- Claims use primary sources, original tests, or clearly labelled inference.
- Direct quotations from a single non-lyrical source remain short and are used only when the original wording is necessary. Agents primarily paraphrase and cite.

### Required

- One page owns one primary intent.
- Important content is server-rendered and available as text.
- Headings describe the actual information hierarchy.
- Internal links use descriptive anchors and connect current stories to durable topic pages.
- Metadata and structured data match visible content.
- Public pages are crawlable through WordPress, robots rules, hosting and CDN layers.
- Canonicals, redirects and sitemap entries identify one preferred URL.
- Claims use primary sources, original tests or clearly labelled inference.
- Dates, versions, entities and commercial relationships are consistent across text, metadata and media.
- Important images have useful alternative text; decorative imagery does not duplicate adjacent content.
- Updated and removed URLs are reflected promptly through sitemaps and, when configured, IndexNow.
- Search Console, Bing Webmaster Tools and available AI-referral/citation data inform audits after publication.

### Prohibited

- Publishing separate pages for minor keyword or query-fan-out variations.
- Mass-generating pages without original value.
- Adding unsupported FAQ questions for search coverage.
- Hiding machine-targeted summaries or keywords from readers.
- Claiming `llms.txt`, Markdown mirrors or special AI markup guarantee citation.
- Fabricating expertise, tests, quotations, statistics, author identities or update dates.
- Treating crawler access as proof of indexing, ranking or citation.

Generative visibility is stochastic. Measure crawlability, citations, referred visits, and content reuse where platforms expose data; never promise a ranking or citation position.

### Discovery gate

- Title, excerpt, visible copy, and schema describe the same page.
- Internal links support topic continuity without stuffing.
- No duplicate intent or thin query-variant page is introduced.
- Crawl and canonical prerequisites are testable.

## Freshness, corrections, and disclosure

### Freshness classes

- `evergreen`: reviewed at least every twelve months or when a named dependency changes.
- `version-sensitive`: reviewed when a named product, API, model, plugin, or platform version changes.
- `news`: interpreted as of a stated publication date and either connected to a durable page or retired from promotion when no longer current.

Every brief names its class and update trigger. Public content exposes sources, the relevant tested or publication date, limitations, correction and update history, and the next retest or review trigger.

### Corrections and retirement

Corrections preserve the prior evidence, reviewer decision, correction reason, correction date, and update history. Removed or changed URLs are reflected promptly in sitemaps and, when configured, IndexNow. Redirects, deletions, canonical changes, retirement, and public correction decisions require a human editor.

### Public disclosure

Public pages identify:

- the intentional public author or editorial desk;
- the human reviewer when review is material;
- how AI assisted research, drafting, or media creation;
- the responsible methodology and correction path;
- commercial relationships, including affiliate, vendor-access, and sponsored arrangements.

AI-assistance and human-review disclosure must agree across the brief, research packet, draft, metadata, media, and handoff. Fabricated expertise, tests, quotations, statistics, author identities, or update dates are prohibited.

## WordPress handoff

Before writing, the writer must:

1. confirm the active contract version from MCPWP site context;
2. read the accepted brief and research packet;
3. inspect the current canonical URL and related posts;
4. confirm that the required WordPress operations are actually exposed and authorized;
5. create or update a draft only;
6. preserve WordPress revisions;
7. return the draft ID, preview URL, slug, and validation report.

The writer changes only the authorized post and fields. Draft status is preserved, heading order is valid, exactly one public H1 is owned by the template, and links, images, and block markup remain valid.

WordPress uses only its normal `draft`, `pending`, `scheduled`, `published`, and `private` statuses. The system adds no custom WordPress statuses in version 1. GitHub labels, not WordPress statuses, represent the editorial workflow. Only a human editor may authorize publication, scheduling, redirects, deletions, canonical changes, or exceptions.

**Workflow attempt contract**

Every bounded automation attempt is a strict object with `kind: workflow-attempt`, a manifest-declared `actor`, `from_state`, `to_state`, `validation_report_status: pass`, and `wordpress_operation: none | create-draft | update-draft`. Every transition attempt requires `pass`, including attempts that do not touch WordPress.

`wordpress_operation: none` omits `wordpress_target`. `create-draft` and `update-draft` require a strict `wordpress_target` containing the same `canonical_slug` join key and a non-empty, unique `authorized_fields` list. Authorized field names are exactly `title`, `content`, `excerpt`, `featured_media`, `categories`, and `tags`.

Only `writer` on its owned `research_accepted` to `drafting` edge may request `create-draft` or `update-draft`; all other roles and edges require `none`. Publication, scheduling, redirects, deletion, canonical changes, public corrections, and retirement have no WordPress operation value and cannot be represented by a bounded automation attempt.

## Stop conditions

### Fail closed

- Weak or inaccessible sources return the work to research; do not fill gaps with model memory.
- Conflicting sources preserve disagreement and require editorial judgment.
- Failed reproduction is recorded and the article is corrected or narrowed.
- Evidence that cannot legally or practically be obtained stops the brief before research.
- Missing claim-level evidence stops drafting or validation; a warning does not advance the workflow.

### Fail closed

A failed SEO/GEO check returns the content or metadata for correction; it never creates doorway variants. Duplicate intent produces a consolidation, update, or redirect proposal for human decision, not another draft. Non-testable crawl or canonical prerequisites fail the discovery gate.

### Fail closed

A missing or incompatible contract stops before mutation. Duplicate intent produces a consolidation, update, or redirect proposal rather than a new draft. A missing human reviewer remains in `human_review`. Stale evidence that no longer supports a material claim moves to human-controlled `update_due`; no agent silently changes published content or publication state.

### Fail closed

- Missing or incompatible contract: stop before content mutation.
- Invalid or unaccepted brief or research packet: stop and return the defect to its owning role.
- Duplicate canonical intent: propose consolidation, update, or redirect; do not create another draft.
- Unauthorized WordPress operation: stop and report the actual missing capability.
- Missing required capability: do not infer tool names or use a broader operation as a substitute.
- Broken Gutenberg markup: keep the last valid revision and return to the writer.
- Missing human reviewer: remain in human review and never publish.

## Human authority

**Authority boundary**

Only the human editor may approve exceptions, public authorship, publication, redirects, deletions, and commercial conclusions. ASTER and every internal role lack that authority. A missing reviewer identity or unresolved disclosure remains in human review and never advances to publication.

- `human-editor only: commercial conclusions`
- `human-editor only: redirects, deletions, canonical changes, retirement, public correction decisions`
- `human-editor only: publication, scheduling, redirects, deletions, canonical changes, exceptions`
