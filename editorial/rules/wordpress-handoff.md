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
