Contract version: 1.0.0

# WordPress handoff

The first release uses `canonical_slug` as the deterministic link between a GitHub editorial issue and its WordPress draft. It does not require private custom post metadata or an editorial-workflow plugin.

## Draft-only procedure

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

## Workflow attempt contract

Every bounded automation attempt is a strict object with `kind: workflow-attempt`, a manifest-declared `actor`, `from_state`, `to_state`, `wordpress_operation: none | create-draft | update-draft`, and a strict `validation_report_ref`. That reference contains only a safe repository-relative POSIX `path` to the repository-owned `editorial/` validation-report artifact namespace and a lowercase `sha256`. Report basenames are `validation-report.json` or `validation-report-<identifier>.json`; the path is never absolute, contains no `.` or `..` segments or backslashes, cannot escape through a symbolic link, and cannot name an arbitrary JSON artifact.

The SHA-256 covers the exact report file bytes as read from disk, with no JSON reserialization, whitespace normalization, or key sorting. It binds an attempt to the externally produced and repository-stored bytes; it is an integrity check, not an authenticity signature. Validation resolves that immutable report and requires Editorial Contract `1.0.0`, the WordPress target's `canonical_slug` when a target exists, the attempt actor as report role, the exact attempt edge, `overall_status: pass`, exactly one passing result for every edge-required gate with no extras, and the edge's exact next role. A status asserted by the attempt itself is never authority.

`wordpress_operation: none` omits `wordpress_target`. `create-draft` and `update-draft` require a strict `wordpress_target` containing the same `canonical_slug` join key and a non-empty, unique `authorized_fields` list. Authorized field names are exactly `title`, `content`, `excerpt`, `featured_media`, `categories`, and `tags`.

Only `writer` on its owned `research_accepted` to `drafting` edge may request `create-draft` or `update-draft`; all other roles and edges require `none`. Publication, scheduling, redirects, deletion, canonical changes, public corrections, and retirement have no WordPress operation value and cannot be represented by a bounded automation attempt.

The writer's bound report is a preflight completed before WordPress mutation. Its `research_accepted` to `drafting` edge proves the `schema`, `scope-duplication`, `evidence`, and `template` gates; it cannot claim that a draft which does not yet exist passed WordPress checks. The `wordpress` gate is post-draft: it begins on `drafting` to `technical_verification` and is retained by later workflow edges that require it.

## Human-only authority

`human-editor only: publication, scheduling, redirects, deletions, canonical changes, exceptions`

## Fail closed

- Missing or incompatible contract: stop before content mutation.
- Invalid or unaccepted brief or research packet: stop and return the defect to its owning role.
- Duplicate canonical intent: propose consolidation, update, or redirect; do not create another draft.
- Unauthorized WordPress operation: stop and report the actual missing capability.
- Missing required capability: do not infer tool names or use a broader operation as a substitute.
- Broken Gutenberg markup: keep the last valid revision and return to the writer.
- Missing human reviewer: remain in human review and never publish.
