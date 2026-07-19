import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

import { contractSourceHash, loadManifest } from './contract-lib.mjs';

const artifactPath = (root, relativePath) => (
  root instanceof URL
    ? new URL(relativePath, root)
    : resolve(root, relativePath)
);

const loadJson = async (root, relativePath) => JSON.parse(
  await readFile(artifactPath(root, relativePath), 'utf8')
);

const markdownSections = (content) => {
  const headings = [...content.matchAll(/^## (.+)$/gm)];
  return new Map(headings.map((heading, index) => {
    const bodyStart = heading.index + heading[0].length;
    const bodyEnd = headings[index + 1]?.index ?? content.length;
    return [heading[1], content.slice(bodyStart, bodyEnd).trim()];
  }));
};

const loadSections = async (root, relativePath) => markdownSections(
  await readFile(artifactPath(root, relativePath), 'utf8')
);

const labelledSection = (label, body) => body ? `**${label}**\n\n${body}` : '';

const renderRole = ({ name, sections }) => `### \`${name}\`

${[
    labelledSection('Inputs', sections.get('Inputs')),
    labelledSection('Output', sections.get('Output')),
    labelledSection('May', sections.get('May')),
    labelledSection('May not', sections.get('May not')),
    labelledSection('Stop conditions', sections.get('Stop conditions'))
  ].filter(Boolean).join('\n\n')}`;

const renderFormat = ({ name, sections }) => `### \`${name}\`

${[
    labelledSection('Use when', sections.get('Use when')),
    labelledSection('Evidence gate', sections.get('Evidence gate')),
    labelledSection('Stop conditions', sections.get('Stop conditions'))
  ].filter(Boolean).join('\n\n')}`;

const renderTransition = (transition) => {
  const from = transition.from ?? 'null';
  const gates = transition.required_gates.map((gate) => `\`${gate}\``).join(', ');
  const authority = transition.human_only ? ' — human-only' : '';
  return `- \`${from}\` → \`${transition.to}\` — actor \`${transition.actor}\` — gates ${gates} — next \`${transition.next_allowed_role}\`${authority}`;
};

const renderRuleSections = (sectionsByRule, selections) => selections.flatMap(
  ({ rule, headings }) => headings.map((heading) => {
    const body = sectionsByRule.get(rule).get(heading);
    return body ? `### ${heading}\n\n${body}` : '';
  }).filter(Boolean)
).join('\n\n');

export const generateSiteContext = async (root) => {
  const manifest = await loadManifest(root);
  const workflow = await loadJson(root, 'editorial/workflow.json');
  const roles = await Promise.all([...manifest.roles].sort().map(async (name) => ({
    name,
    sections: await loadSections(root, `editorial/agents/${name}.md`)
  })));
  const formats = await Promise.all([...manifest.formats].sort().map(async (name) => ({
    name,
    sections: await loadSections(root, `editorial/templates/${name}.md`)
  })));
  const rules = new Map(await Promise.all([
    'authorship-disclosure',
    'freshness-corrections',
    'seo-geo',
    'sources',
    'wordpress-handoff'
  ].map(async (name) => [
    name,
    await loadSections(root, `editorial/rules/${name}.md`)
  ])));

  const writer = roles.find(({ name }) => name === 'writer');
  const wordpressSections = rules.get('wordpress-handoff');
  const humanAuthority = [
    rules.get('authorship-disclosure').get('Human-only authority'),
    rules.get('freshness-corrections').get('Human-only authority'),
    wordpressSections.get('Human-only authority')
  ].filter(Boolean);
  const draftOnly = Boolean(wordpressSections.get('Draft-only procedure'))
    && /WordPress draft/i.test(writer?.sections.get('May') ?? '');
  const humanPublicationRequired = humanAuthority.some((body) => /publication/i.test(body));

  const evidenceAndDiscovery = renderRuleSections(rules, [
    { rule: 'sources', headings: ['Claim-level records', 'Evidence rules'] },
    { rule: 'seo-geo', headings: ['Required', 'Prohibited', 'Discovery gate'] }
  ]);
  const freshnessAndDisclosure = renderRuleSections(rules, [
    { rule: 'freshness-corrections', headings: ['Freshness classes', 'Corrections and retirement'] },
    { rule: 'authorship-disclosure', headings: ['Public disclosure'] }
  ]);
  const ruleStopConditions = renderRuleSections(rules, [
    { rule: 'sources', headings: ['Fail closed'] },
    { rule: 'seo-geo', headings: ['Fail closed'] },
    { rule: 'freshness-corrections', headings: ['Fail closed'] },
    { rule: 'wordpress-handoff', headings: ['Fail closed'] }
  ]);

  return `# MCPWP Site Context

Editorial Contract: ${manifest.editorial_contract}
Contract SHA-256: ${await contractSourceHash(root)}
Draft-only: ${draftOnly}
Human publication required: ${humanPublicationRequired}

## Active contract

- MCPWP profile: ${manifest.mcpwp_profile}
- Required theme: ${manifest.requires_theme}
- MCPWP plugin required: ${manifest.mcpwp_plugin_required}
- Tested MCPWP plugin: ${manifest.tested_mcpwp_plugin}
- Workflow authority: \`editorial/workflow.json\`
- Required schemas: ${manifest.schemas.map((schema) => `\`${schema}\``).join(', ')}

## Workflow transitions

${workflow.transitions.map(renderTransition).join('\n')}

## Role permissions and prohibitions

${roles.map(renderRole).join('\n\n')}

## Format evidence gates and stop conditions

${formats.map(renderFormat).join('\n\n')}

## Evidence and discovery rules

${evidenceAndDiscovery}

## Freshness, corrections, and disclosure

${freshnessAndDisclosure}

## WordPress handoff

${wordpressSections.get('Draft-only procedure')}

${labelledSection(
    'Workflow attempt contract',
    wordpressSections.get('Workflow attempt contract')
  )}

## Stop conditions

${ruleStopConditions}

## Human authority

${labelledSection(
    'Authority boundary',
    rules.get('authorship-disclosure').get('Authority boundary')
  )}

${humanAuthority.map((body) => `- ${body}`).join('\n')}
`;
};

const modulePath = fileURLToPath(import.meta.url);
if (process.argv[1] && resolve(process.argv[1]) === modulePath) {
  const repositoryRoot = resolve(dirname(modulePath), '../..');
  const outputPath = resolve(repositoryRoot, 'editorial/generated/mcpwp-site-context.md');
  await mkdir(dirname(outputPath), { recursive: true });
  await writeFile(outputPath, await generateSiteContext(repositoryRoot), 'utf8');
}
