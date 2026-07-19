import { createHash } from 'node:crypto';
import { mkdir, readdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const outputPath = resolve(repositoryRoot, 'editorial/generated/mcpwp-site-context.md');

const sourceDirectories = [
  'editorial/schemas',
  'editorial/templates',
  'editorial/agents',
  'editorial/rules'
];

const listSourceFiles = async () => {
  const files = ['editorial/manifest.json', 'editorial/workflow.json'];
  for (const directory of sourceDirectories) {
    const names = await readdir(resolve(repositoryRoot, directory));
    files.push(...names.sort().map((name) => `${directory}/${name}`));
  }
  return files.sort();
};

const sourceHash = async () => {
  const hash = createHash('sha256');
  for (const file of await listSourceFiles()) {
    hash.update(file);
    hash.update('\0');
    hash.update(await readFile(resolve(repositoryRoot, file)));
    hash.update('\0');
  }
  return hash.digest('hex');
};

const markdownList = (items) => items.map((item) => `- \`${item}\``).join('\n');

const renderContext = ({ manifest, hash }) => {
  const formats = [...manifest.formats].sort();
  const roles = [...manifest.roles].sort();

  return `# MCPWP Site Context

Editorial Contract: ${manifest.editorial_contract}
Contract SHA-256: ${hash}
Draft-only: true
Human publication required: true

## Active contract

- MCPWP profile: ${manifest.mcpwp_profile}
- Required theme: ${manifest.requires_theme}
- MCPWP plugin required: ${manifest.mcpwp_plugin_required}
- Tested MCPWP plugin: ${manifest.tested_mcpwp_plugin}
- Workflow authority: \`editorial/workflow.json\`
- Required schemas: ${manifest.schemas.map((schema) => `\`${schema}\``).sort().join(', ')}

## Allowed roles

${markdownList(roles)}

Each role owns only its declared workflow transition. The human editor accepts artifacts, approves drafts, and controls every human-only transition.

## Formats

${markdownList(formats)}

Select only a listed format and satisfy its template-specific evidence gate before moving forward.

## Universal gates

- Use an active, compatible contract and schema-valid artifacts.
- Require an accepted brief and accepted research packet before drafting.
- Preserve one primary canonical intent; return duplicate intent for a human consolidation, update, or redirect decision.
- Map material factual claims to current primary sources or recorded observations; retain uncertainty and contradictory evidence.
- Keep facts, inference, opinion, authorship, AI assistance, human review, dates, versions, and commercial relationships accurate and visible.
- Require template, technical-verification, and discovery checks to pass before human review.
- Record the actor, contract version, artifact versions, validation result, unresolved risks, and next allowed role for every transition.

## WordPress handoff

- Confirm the active contract, accepted inputs, canonical URL, related posts, and authorized WordPress capabilities before writing.
- The writer may create or update only the authorized WordPress draft and fields; retain revisions and return the draft ID, preview URL, slug, and validation report.
- Preserve draft status, valid heading order, one public H1, valid links and images, and valid block markup.
- WordPress statuses remain standard; the machine workflow is represented by GitHub labels.

## Stop conditions

- Stop before mutation for a missing or incompatible contract, invalid or unaccepted inputs, duplicate intent, missing evidence, unsupported template, or unavailable or unauthorized capability.
- Stop and return defects to their owning role when a machine gate fails; warnings do not advance the workflow.
- Keep the last valid revision when block markup fails.
- Keep work in human review when reviewer identity, disclosure, or unresolved risks are missing.
- Do not infer unavailable WordPress operations or use broader operations as substitutes.

## Human authority

- Only a human editor may accept a brief or research packet, approve a draft, publish, schedule, redirect, delete, change canonicals, retire content, decide public corrections, or approve exceptions and commercial conclusions.
- Human review is required before approval and publication. Internal roles and ASTER do not hold publication authority.
`;
};

const manifest = JSON.parse(await readFile(resolve(repositoryRoot, 'editorial/manifest.json'), 'utf8'));
const context = renderContext({ manifest, hash: await sourceHash() });

await mkdir(dirname(outputPath), { recursive: true });
await writeFile(outputPath, context, 'utf8');
