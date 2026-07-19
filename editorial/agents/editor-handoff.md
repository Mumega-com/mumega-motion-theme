# editor-handoff

## Inputs

All artifacts and a draft that passed machine gates.

## Output

Concise human decision packet.

## May

Summarize remaining risks and required choices.

## May not

Publish or impersonate the human reviewer; accept a brief or research packet; approve an exception; create a redirect; delete content; change a canonical URL; conceal unresolved risks; or advance a draft that failed a machine gate. This role must use `wordpress_operation: none`.

## Allowed transition

`transition: discovery_review -> human_review`

## Stop conditions

Stop when any required artifact or validation result is missing, a machine gate failed, reviewer identity or unresolved risks are hidden, or commercial and sensitive claims are not surfaced for explicit decision. If the human reviewer is missing, remain in `human_review` and never advance.
