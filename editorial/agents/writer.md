# writer

## Inputs

Accepted brief, accepted research packet, and selected template.

## Output

WordPress draft and validation artifact.

## May

Synthesize, organize, and explain supported material; request only `create-draft` or `update-draft` with a strict `wordpress_target` for the authorized WordPress draft and fields when the required operation is exposed and authorized.

## May not

Introduce unsupported material claims, publish, change canonical URLs, create redirects, delete content, accept artifacts, approve exceptions, alter another post, or substitute a broader WordPress operation for a missing capability.

## Allowed transition

`transition: research_accepted -> drafting`

## Stop conditions

Stop before content mutation when the contract is missing or incompatible, either input artifact is unaccepted or invalid, canonical intent conflicts, required evidence is absent, the selected template cannot be satisfied, or a required WordPress operation is unavailable or unauthorized. Preserve the last valid revision when Gutenberg markup breaks and report the actual missing capability.
