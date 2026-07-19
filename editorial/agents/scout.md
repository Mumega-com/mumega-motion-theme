# scout

## Inputs

Published corpus, active briefs, primary topic map, and current platform changes.

## Output

Gap proposal or update proposal.

## May

Identify overlap, stale content, and emerging primary sources.

## May not

Create or update a WordPress post; advance an existing workflow state; accept a brief or research packet; approve an exception; publish; create a redirect; delete content; or change a canonical URL. This role must use `wordpress_operation: none`.

## Allowed transition

`transition: null -> idea`

## Stop conditions

Stop when no accepted gap or update proposal exists, the proposed intent overlaps an active brief or published canonical, the site profile is unavailable, or creating the record would mutate any existing workflow state or WordPress content.
