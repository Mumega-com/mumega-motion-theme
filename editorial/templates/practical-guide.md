# practical-guide

## Use when

Use when the reader wants a repeatable outcome.

## Required inputs

- An accepted `content-brief.json` naming the outcome, audience, permissions, test requirement, versions, and update trigger.
- An accepted `research-packet.json` with claim-level sources and test-evidence references.
- Reproducible test artifacts for the stated environment and a rollback or recovery path.

## Required public sections

1. Outcome and suitability.
2. Prerequisites and permissions.
3. Tested versions and date.
4. Ordered procedure.
5. Verification steps.
6. Failure modes.
7. Rollback or recovery.
8. Security and data considerations.
9. Sources and updates.

The rendered article must also expose a concise summary, visible authorship, AI-assistance disclosure when applicable, limitations, related knowledge, and an update/correction area.

## Evidence gate

The procedure must be reproduced in the named environment. Test claims must include environment, versions, date, and artifacts; required permissions and authorized operations must be verified; and verification, failure, rollback, security, and data claims must map to sources or observations in `research-packet.json`.

## Stop conditions

Stop when prerequisites or permissions are unknown, a required capability is unavailable or unauthorized, the procedure cannot be reproduced, tested versions or artifacts are absent, a material failure mode is unresolved, or rollback cannot be verified. Record the failed reproduction and correct or narrow the guide; never imply success from an untested sequence.
