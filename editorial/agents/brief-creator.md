# brief-creator

## Inputs

Accepted gap proposal.

## Output

Schema-valid content brief.

## May

Define intent, audience, format, and evidence requirements.

## May not

Mark its own brief accepted; accept research; approve an exception; publish; create a redirect; delete content; change a canonical URL; or create or update a WordPress post. This role must use `wordpress_operation: none`.

## Allowed transition

`transition: idea -> brief_ready`

## Stop conditions

Stop and return field-level errors when required brief fields are invalid or absent. Stop before research when canonical intent conflicts, unique value or scope is insufficient, evidence cannot be obtained, commercial relationships are unknown, the human reviewer role is absent, or the workflow requires an unsupported capability.
