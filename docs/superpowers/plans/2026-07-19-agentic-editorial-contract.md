# Agentic Editorial Contract Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Editorial Contract 1.0.0 as a machine-readable, testable system of schemas, templates, bounded agent instructions, validation rules, examples, GitHub intake, and synchronized MCPWP site context.

**Architecture:** Keep the full contract in the private theme repository under `editorial/`. Validate artifacts with Node's built-in test runner, Ajv JSON Schema validation, and a small cross-reference validator. Generate a condensed Markdown site context from the same manifest and source files so MCPWP agents can identify the active contract without duplicating policy by hand.

**Tech Stack:** JSON Schema 2020-12, Node.js 20, ESM, Ajv 8.20.0, ajv-formats 3.0.1, GitHub issue forms, Markdown, npm scripts.

## Global Constraints

- Treat `docs/superpowers/specs/2026-07-19-agentic-editorial-system-design.md` as the source of truth.
- Initial contract version is exactly `1.0.0`.
- The full contract remains private; generated site context contains no secrets.
- Small agents never publish, redirect, delete, or change canonical URLs.
- Contract validation must stop before WordPress mutation on incompatibility.
- `canonical_slug` is the first-release join key between GitHub and WordPress.
- Do not introduce a new database, custom WordPress post status, or workflow plugin.
- Keep the contract outside the installable theme allowlist.
- Every code task follows red, green, refactor, verification, focused commit.

---

## File and interface map

| File | Responsibility |
|---|---|
| `editorial/manifest.json` | Active contract/profile compatibility and source-file inventory |
| `editorial/workflow.json` | Canonical states, role-owned transitions, and human-only transitions |
| `editorial/schemas/*.schema.json` | Machine validation for brief, research packet, and validation report |
| `editorial/templates/*.md` | Six public article-format contracts |
| `editorial/agents/*.md` | Seven bounded role contracts |
| `editorial/rules/*.md` | Shared source, discovery, disclosure, freshness, and WordPress rules |
| `editorial/examples/valid/*.json` | One passing fixture for each schema and format |
| `editorial/examples/invalid/*.json` | Targeted rejection fixtures |
| `editorial/scripts/contract-lib.mjs` | Manifest loading, Ajv construction, cross-reference checks |
| `editorial/scripts/validate-contract.mjs` | CLI entry point; exits nonzero with field-level errors |
| `editorial/scripts/generate-site-context.mjs` | Deterministic condensed context generator |
| `editorial/generated/mcpwp-site-context.md` | Generated agent context with version and source hash |
| `editorial/tests/*.test.mjs` | Node contract, rejection, role, and synchronization tests |
| `.github/ISSUE_TEMPLATE/editorial-brief.yml` | Schema-aligned editorial intake form |
| `package.json` | Contract commands and dev dependencies |
| `.github/workflows/edge-release.yml` | CI execution of contract tests; no runtime packaging change |

## Interfaces

```js
// editorial/scripts/contract-lib.mjs
export async function loadManifest(root): Promise<object>
export async function createValidators(root): Promise<Map<string, ValidateFunction>>
export async function validateArtifact(root, schemaName, value): Promise<{ valid: boolean, errors: string[] }>
export async function validateContract(root): Promise<{ valid: boolean, errors: string[] }>
export async function contractSourceHash(root): Promise<string>

// editorial/scripts/generate-site-context.mjs
export async function generateSiteContext(root): Promise<string>
```

### Task 1: Establish the contract test harness and manifest

**Files:**
- Create: `editorial/manifest.json`
- Create: `editorial/tests/manifest.test.mjs`
- Modify: `package.json`
- Modify: `package-lock.json`

**Produces:** `npm run test:editorial-contract` and a manifest with exact 1.0.0 compatibility fields.

- [ ] **Step 1: Write the failing manifest test**

```js
// editorial/tests/manifest.test.mjs
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const root = new URL('../../', import.meta.url);

test('manifest declares the approved independent release trains', async () => {
  const manifest = JSON.parse(await readFile(new URL('editorial/manifest.json', root), 'utf8'));
  assert.deepEqual(manifest, {
    editorial_contract: '1.0.0',
    mcpwp_profile: '1.0.0',
    requires_theme: '>=0.2.0 <0.3.0',
    mcpwp_plugin_required: false,
    tested_mcpwp_plugin: '3.6.1',
    schemas: ['content-brief', 'research-packet', 'validation-report'],
    formats: ['explainer', 'practical-guide', 'test-report', 'comparison-review', 'news-briefing', 'analysis-opinion'],
    roles: ['scout', 'brief-creator', 'researcher', 'writer', 'technical-verifier', 'discovery-reviewer', 'editor-handoff']
  });
});
```

- [ ] **Step 2: Add the test command and pinned development dependencies**

Add to `package.json`:

```json
{
  "scripts": {
    "test:editorial-contract": "node --test editorial/tests/*.test.mjs",
    "validate:editorial-contract": "node editorial/scripts/validate-contract.mjs",
    "generate:editorial-context": "node editorial/scripts/generate-site-context.mjs"
  },
  "devDependencies": {
    "ajv": "8.20.0",
    "ajv-formats": "3.0.1"
  }
}
```

Run `npm install --save-dev --save-exact ajv@8.20.0 ajv-formats@3.0.1` to update the lock file.

- [ ] **Step 3: Run the test and verify the missing manifest failure**

Run: `npm run test:editorial-contract`
Expected: FAIL with `ENOENT ... editorial/manifest.json`.

- [ ] **Step 4: Add the exact manifest**

```json
{
  "editorial_contract": "1.0.0",
  "mcpwp_profile": "1.0.0",
  "requires_theme": ">=0.2.0 <0.3.0",
  "mcpwp_plugin_required": false,
  "tested_mcpwp_plugin": "3.6.1",
  "schemas": ["content-brief", "research-packet", "validation-report"],
  "formats": ["explainer", "practical-guide", "test-report", "comparison-review", "news-briefing", "analysis-opinion"],
  "roles": ["scout", "brief-creator", "researcher", "writer", "technical-verifier", "discovery-reviewer", "editor-handoff"]
}
```

- [ ] **Step 5: Verify and commit**

Run: `npm run test:editorial-contract`
Expected: PASS, 1 test.

```bash
git add package.json package-lock.json editorial/manifest.json editorial/tests/manifest.test.mjs
git commit -m "feat: establish editorial contract manifest"
```

### Task 2: Define strict artifact schemas

**Files:**
- Create: `editorial/schemas/content-brief.schema.json`
- Create: `editorial/schemas/research-packet.schema.json`
- Create: `editorial/schemas/validation-report.schema.json`
- Create: `editorial/scripts/contract-lib.mjs`
- Create: `editorial/tests/schema.test.mjs`

**Produces:** strict Ajv validators with rejected unknown fields and field-level errors.

- [ ] **Step 1: Write failing schema tests**

Tests must assert:

```js
test('content brief accepts the complete controlled contract', async () => {
  const result = await validateArtifact(root, 'content-brief', validBrief);
  assert.equal(result.valid, true, result.errors.join('\n'));
});

test('content brief rejects unknown format, missing reviewer, duplicate sources, and extra fields', async () => {
  const result = await validateArtifact(root, 'content-brief', invalidBrief);
  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /content_format|human_reviewer_role|primary_sources|additionalProperties/);
});
```

Use a complete `validBrief` containing every field in the approved YAML contract. Use an `invalidBrief` with `content_format: 'listicle'`, blank reviewer, duplicate source URLs, and `publish_now: true`.

- [ ] **Step 2: Run the focused test**

Run: `node --test editorial/tests/schema.test.mjs`
Expected: FAIL because `contract-lib.mjs` and schemas do not exist.

- [ ] **Step 3: Implement the content-brief schema**

Use draft 2020-12, `additionalProperties: false`, exact enums from the design, `minLength: 1` for required strings, `uniqueItems: true` for arrays, URI format for public source URLs, and these conditional rules:

```json
{
  "if": { "properties": { "test_required": { "const": true } } },
  "then": { "required": ["versions_and_date"], "properties": { "versions_and_date": { "type": "string", "minLength": 1 } } }
}
```

Require all fields shown in the approved brief contract. Permit `null` only for `canonical_page_supported` and `versions_and_date` when no test is required.

- [ ] **Step 4: Implement research and validation schemas**

`research-packet.schema.json` requires:

```json
{
  "contract_version": "1.0.0",
  "canonical_slug": "string",
  "claims": [{
    "claim": "string",
    "claim_type": "observed | sourced-fact | calculation | interpretation | opinion",
    "source_url": "uri or null",
    "evidence_reference": "string or null",
    "publisher": "string",
    "published_at": "date or null",
    "accessed_at": "date",
    "version_environment": "string or null",
    "confidence": "high | medium | low",
    "uncertainty": "string",
    "stale_trigger": "string",
    "target_section": "string"
  }],
  "counterevidence": ["string"],
  "normalized_entities": ["string"],
  "forbidden_claims": ["string"],
  "artifacts": ["uri"]
}
```

Require at least one claim and enforce that each claim has either `source_url` or `evidence_reference` through `anyOf`.

`validation-report.schema.json` requires contract version, canonical slug, role, state transition, timestamp, artifact hashes, gate results, unresolved risks, next allowed role, and overall `pass | fail` status. If any gate result is `fail`, overall status must be `fail`.

- [ ] **Step 5: Implement `contract-lib.mjs`**

Create an Ajv 2020 instance with `allErrors: true`, add standard formats, load schemas by manifest name, and normalize errors as:

```js
const normalizeErrors = (errors = []) => errors.map(
  ({ instancePath, keyword, message }) => `${instancePath || '/'} ${keyword}: ${message}`
);
```

Reject a requested schema name not present in the manifest. Do not mutate input artifacts.

- [ ] **Step 6: Verify and commit**

Run: `node --test editorial/tests/schema.test.mjs`
Expected: PASS for valid fixtures and exact rejections.

```bash
git add editorial/schemas editorial/scripts/contract-lib.mjs editorial/tests/schema.test.mjs
git commit -m "feat: validate editorial artifacts"
```

### Task 3: Add six format contracts and seven bounded roles

**Files:**
- Create: `editorial/templates/*.md`
- Create: `editorial/agents/*.md`
- Create: `editorial/rules/*.md`
- Create: `editorial/workflow.json`
- Create: `editorial/tests/doc-contract.test.mjs`

**Produces:** human-readable contracts with stable headings that machines can inventory.

- [ ] **Step 1: Write the failing documentation contract test**

The test reads the manifest and asserts every listed format and role has one file. Each format file must contain these headings:

```js
const requiredFormatHeadings = ['# ', '## Use when', '## Required inputs', '## Required public sections', '## Evidence gate', '## Stop conditions'];
```

Each role file must contain:

```js
const requiredRoleHeadings = ['# ', '## Inputs', '## Output', '## May', '## May not', '## Allowed transition', '## Stop conditions'];
```

Assert every role file contains `May not` and no non-human file contains permission to publish, redirect, delete, or change a canonical URL.

- [ ] **Step 2: Run and verify failure**

Run: `node --test editorial/tests/doc-contract.test.mjs`
Expected: FAIL listing all missing format and role files.

- [ ] **Step 3: Add the six exact format files**

Use the required sections from the approved design verbatim. Each file begins with its stable format identifier, includes its required input artifact names, lists every public section in order, states its evidence gate, and repeats the format-specific stop conditions. `comparison-review.md` explicitly requires official documentation, pricing, terms, and changelogs for each compared product plus direct testing for a Tested label.

- [ ] **Step 4: Add the seven exact role files**

Use the approved role contracts verbatim. Encode these small-agent permissions:

```text
scout: create idea only
brief-creator: idea -> brief_ready
researcher: brief_accepted -> research_ready
writer: research_accepted -> drafting
technical-verifier: drafting -> technical_verification
discovery-reviewer: technical_verification -> discovery_review
editor-handoff: discovery_review -> human_review
```

The human editor remains outside the small-agent manifest. Add `editorial/workflow.json` with the complete ordered state list and transition objects containing `from`, `to`, `actor`, and `human_only`. It assigns `brief_ready -> brief_accepted`, `research_ready -> research_accepted`, `human_review -> approved`, `approved -> published`, and every post-publication transition exclusively to `human-editor`. The scout may create an `idea` record but may not advance an existing state.

- [ ] **Step 5: Add shared rules**

Create exactly `sources.md`, `seo-geo.md`, `authorship-disclosure.md`, `freshness-corrections.md`, and `wordpress-handoff.md`. Copy the approved Required/Prohibited SEO-GEO rules, source-packet rules, disclosure boundaries, freshness classes, GitHub states, WordPress draft-only behavior, and fail-closed cases into their relevant file. Each rule file begins with `Contract version: 1.0.0`.

- [ ] **Step 6: Verify and commit**

Run: `node --test editorial/tests/doc-contract.test.mjs`
Expected: PASS with 6 formats, 7 roles, and 5 rule files.

```bash
git add editorial/templates editorial/agents editorial/rules editorial/workflow.json editorial/tests/doc-contract.test.mjs
git commit -m "docs: add bounded editorial agent contracts"
```

### Task 4: Add valid/invalid fixtures and cross-reference validation

**Files:**
- Create: `editorial/examples/valid/*.json`
- Create: `editorial/examples/invalid/*.json`
- Modify: `editorial/scripts/contract-lib.mjs`
- Create: `editorial/scripts/validate-contract.mjs`
- Create: `editorial/tests/contract-validation.test.mjs`

**Produces:** one command proving schemas, documentation inventory, transitions, compatibility, and examples agree.

- [ ] **Step 1: Write failing validator tests**

Assert `validateContract(root)` returns no errors for the repository and targeted temporary fixtures produce these failures:

- unknown format;
- incompatible contract major;
- missing evidence on a material claim;
- non-human transition to `published`;
- manifest item without a matching file;
- WordPress mutation requested before a passing validation report.

- [ ] **Step 2: Run and verify failure**

Run: `node --test editorial/tests/contract-validation.test.mjs`
Expected: FAIL because fixtures and cross-reference validation are absent.

- [ ] **Step 3: Add fixtures**

Create a valid brief for each of the six formats, one valid research packet, and one passing validation report. Create one invalid file per failure case and include an `_expected_errors` array used only by the test harness; strip that field before schema validation. The early WordPress mutation case is `workflow-wordpress-before-pass.json` with `kind: "workflow-attempt"`, `actor`, `from_state`, `to_state`, `wordpress_mutation`, and `validation_report_status`; cross-reference validation rejects any mutation attempt unless the active report status is `pass` and the workflow actor owns that transition.

- [ ] **Step 4: Implement cross-reference checks**

`validateContract(root)` must:

1. validate the manifest shape;
2. verify every manifest file exists exactly once;
3. validate all example artifacts;
4. ensure document `Contract version` values equal the manifest;
5. validate `workflow.json`, ensure role files declare the same assignments, and ensure the complete transition graph matches the approved state machine;
6. reject prohibited non-human transitions;
7. ensure every format has a valid brief fixture;
8. ensure every invalid fixture fails with every declared expected error fragment.

- [ ] **Step 5: Add the CLI**

```js
#!/usr/bin/env node
import process from 'node:process';
import { validateContract } from './contract-lib.mjs';

const root = new URL('../../', import.meta.url);
const result = await validateContract(root);
if (!result.valid) {
  process.stderr.write(`${result.errors.join('\n')}\n`);
  process.exitCode = 1;
} else {
  process.stdout.write('Editorial Contract 1.0.0 is valid.\n');
}
```

- [ ] **Step 6: Verify and commit**

Run: `npm run validate:editorial-contract && npm run test:editorial-contract`
Expected: `Editorial Contract 1.0.0 is valid.` and all tests pass.

```bash
git add editorial/examples editorial/scripts editorial/tests
git commit -m "test: prove editorial contract consistency"
```

### Task 5: Generate and verify condensed MCPWP site context

**Files:**
- Create: `editorial/scripts/generate-site-context.mjs`
- Create: `editorial/generated/mcpwp-site-context.md`
- Create: `editorial/tests/site-context.test.mjs`
- Modify: `.gitignore`

**Produces:** deterministic context containing contract version, hash, role boundaries, formats, gates, and stop behavior without secrets.

- [ ] **Step 1: Write failing generation tests**

Assert two generations are byte-identical and contain:

```text
Editorial Contract: 1.0.0
Contract SHA-256: <64 lowercase hex>
Draft-only: true
Human publication required: true
```

Assert the generated file contains all six format and seven role identifiers, does not contain environment-variable assignments, API-key-like strings, or the words `publish automatically`, and matches the committed output exactly.

- [ ] **Step 2: Run and verify failure**

Run: `node --test editorial/tests/site-context.test.mjs`
Expected: FAIL because the generator is absent.

- [ ] **Step 3: Implement deterministic generation**

Sort manifest arrays before rendering. Hash the manifest, schemas, templates, agent files, and rules by relative path plus bytes. Generate concise sections: Active contract, Allowed roles, Formats, Universal gates, WordPress handoff, Stop conditions, Human authority. Do not read process environment or Git history.

- [ ] **Step 4: Generate, verify and commit**

Run: `npm run generate:editorial-context && npm run test:editorial-contract && git diff --exit-code -- editorial/generated/mcpwp-site-context.md`
Expected: all tests pass and the generated file is clean after regeneration.

```bash
git add editorial/generated editorial/scripts/generate-site-context.mjs editorial/tests/site-context.test.mjs .gitignore
git commit -m "feat: generate MCPWP editorial site context"
```

### Task 6: Add GitHub editorial intake and CI enforcement

**Files:**
- Create: `.github/ISSUE_TEMPLATE/editorial-brief.yml`
- Create: `.github/ISSUE_TEMPLATE/editorial-correction.yml`
- Modify: `.github/workflows/edge-release.yml`
- Modify: `package.json`
- Modify: `scripts/test-package-release.sh`

**Produces:** consistent intake plus CI validation without shipping private editorial files in the theme ZIP.

- [ ] **Step 1: Write failing package/workflow assertions**

Extend `scripts/test-package-release.sh` to assert:

```bash
assert_step_contains 'Validate Editorial Contract' 'npm run validate:editorial-contract'
assert_step_contains 'Run Editorial Contract tests' 'npm run test:editorial-contract'
assert_not_contains 'editorial/' "${ROOT_DIR}/scripts/package-theme.sh"
```

Also assert an archive produced by `package-theme.sh 0.2.0` contains no `editorial/` or Markdown file.

- [ ] **Step 2: Run and verify failure**

Run: `./scripts/test-package-release.sh`
Expected: FAIL because the named workflow steps are absent.

- [ ] **Step 3: Add the issue forms**

The brief form contains required fields aligned to the content brief schema and reminds the submitter that the machine-readable artifact is authoritative. The correction form requires public URL, previous claim, corrected evidence, impact, reviewer and correction date. Neither form contains a publish action.

- [ ] **Step 4: Add CI steps**

After `npm ci` in the verify job, add:

```yaml
- name: Validate Editorial Contract
  run: npm run validate:editorial-contract

- name: Run Editorial Contract tests
  run: npm run test:editorial-contract
```

Run the same two commands before packaging in the release job.

- [ ] **Step 5: Verify the complete subsystem**

Run:

```bash
npm ci
npm run validate:editorial-contract
npm run test:editorial-contract
npm run test:js
vendor/bin/phpunit -c phpunit.xml.dist
./scripts/test-package-release.sh
```

Expected: every command exits 0; package ZIP contains no private editorial contract files.

- [ ] **Step 6: Commit**

```bash
git add .github/ISSUE_TEMPLATE .github/workflows/edge-release.yml package.json package-lock.json scripts/test-package-release.sh
git commit -m "ci: enforce editorial contract"
```

## Completion evidence

- `npm run validate:editorial-contract` prints the exact active version and exits 0.
- All valid and invalid fixtures behave as declared.
- Generated site context is deterministic and synchronized.
- GitHub issue forms cover briefs and corrections.
- CI runs contract tests before theme verification and packaging.
- Runtime ZIP contains no `editorial/`, Markdown, tests, secrets, or development dependencies.
