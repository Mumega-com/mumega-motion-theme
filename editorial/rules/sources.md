Contract version: 1.0.0

# Sources and research packets

The researcher converts an accepted brief into a claim-level packet before prose is drafted.

## Claim-level records

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

## Evidence rules

- Material factual claims map to a source or observation.
- Primary sources are current enough for the claim.
- Test claims include environment, versions, date, and artifacts.
- Contradictory evidence is recorded rather than omitted.
- Claims use primary sources, original tests, or clearly labelled inference.
- Direct quotations from a single non-lyrical source remain short and are used only when the original wording is necessary. Agents primarily paraphrase and cite.

## Fail closed

- Weak or inaccessible sources return the work to research; do not fill gaps with model memory.
- Conflicting sources preserve disagreement and require editorial judgment.
- Failed reproduction is recorded and the article is corrected or narrowed.
- Evidence that cannot legally or practically be obtained stops the brief before research.
- Missing claim-level evidence stops drafting or validation; a warning does not advance the workflow.
