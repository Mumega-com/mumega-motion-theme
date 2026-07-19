import assert from 'node:assert/strict';
import { readdir, readFile } from 'node:fs/promises';
import test from 'node:test';

const root = new URL('../../', import.meta.url);
const requiredFormatHeadings = [
  '# ',
  '## Use when',
  '## Required inputs',
  '## Required public sections',
  '## Evidence gate',
  '## Stop conditions'
];
const requiredRoleHeadings = [
  '# ',
  '## Inputs',
  '## Output',
  '## May',
  '## May not',
  '## Allowed transition',
  '## Stop conditions'
];
const requiredRules = [
  'authorship-disclosure.md',
  'freshness-corrections.md',
  'seo-geo.md',
  'sources.md',
  'wordpress-handoff.md'
];
const humanAuthoritySections = new Map([
  [
    'wordpress-handoff.md',
    {
      body: '`human-editor only: publication, scheduling, redirects, deletions, canonical changes, exceptions`',
      nextHeading: '## Fail closed'
    }
  ],
  [
    'freshness-corrections.md',
    {
      body: '`human-editor only: redirects, deletions, canonical changes, retirement, public correction decisions`',
      nextHeading: '## Fail closed'
    }
  ],
  [
    'authorship-disclosure.md',
    {
      body: '`human-editor only: commercial conclusions`',
      nextHeading: null
    }
  ]
]);
const states = [
  'idea',
  'brief_ready',
  'brief_accepted',
  'research_ready',
  'research_accepted',
  'drafting',
  'technical_verification',
  'discovery_review',
  'human_review',
  'approved',
  'published',
  'update_due',
  'corrected',
  'retired'
];
const transitions = [
  { from: null, to: 'idea', actor: 'scout', human_only: false },
  { from: 'idea', to: 'brief_ready', actor: 'brief-creator', human_only: false },
  { from: 'brief_ready', to: 'brief_accepted', actor: 'human-editor', human_only: true },
  { from: 'brief_accepted', to: 'research_ready', actor: 'researcher', human_only: false },
  { from: 'research_ready', to: 'research_accepted', actor: 'human-editor', human_only: true },
  { from: 'research_accepted', to: 'drafting', actor: 'writer', human_only: false },
  { from: 'drafting', to: 'technical_verification', actor: 'technical-verifier', human_only: false },
  { from: 'technical_verification', to: 'discovery_review', actor: 'discovery-reviewer', human_only: false },
  { from: 'discovery_review', to: 'human_review', actor: 'editor-handoff', human_only: false },
  { from: 'human_review', to: 'approved', actor: 'human-editor', human_only: true },
  { from: 'approved', to: 'published', actor: 'human-editor', human_only: true },
  { from: 'published', to: 'update_due', actor: 'human-editor', human_only: true },
  { from: 'published', to: 'corrected', actor: 'human-editor', human_only: true },
  { from: 'published', to: 'retired', actor: 'human-editor', human_only: true }
];

const loadManifest = async () => JSON.parse(
  await readFile(new URL('editorial/manifest.json', root), 'utf8')
);

const loadRole = (name) => readFile(new URL(`editorial/agents/${name}.md`, root), 'utf8');

const collectMissing = async (directory, names) => {
  const existing = new Set(await readdir(new URL(directory, root)).catch(() => []));
  return names.filter((name) => !existing.has(name));
};

const section = (content, heading, nextHeading) => {
  const start = content.indexOf(`${heading}\n`);
  assert.notEqual(start, -1, `missing ${heading}`);
  const bodyStart = start + heading.length + 1;
  const end = nextHeading ? content.indexOf(`\n${nextHeading}\n`, bodyStart) : content.length;
  assert.notEqual(end, -1, `missing ${nextHeading}`);
  return content.slice(bodyStart, end).trim();
};

test('documentation inventory and workflow match the editorial manifest', async () => {
  const manifest = await loadManifest();
  const formatFiles = manifest.formats.map((name) => `${name}.md`);
  const roleFiles = manifest.roles.map((name) => `${name}.md`);
  const missingFormats = await collectMissing('editorial/templates/', formatFiles);
  const missingRoles = await collectMissing('editorial/agents/', roleFiles);

  assert.deepEqual(
    { missingFormats, missingRoles },
    { missingFormats: [], missingRoles: [] },
    `missing contract files:\n${[
      ...missingFormats.map((name) => `editorial/templates/${name}`),
      ...missingRoles.map((name) => `editorial/agents/${name}`)
    ].join('\n')}`
  );
  assert.deepEqual((await readdir(new URL('editorial/templates/', root))).sort(), formatFiles.sort());
  assert.deepEqual((await readdir(new URL('editorial/agents/', root))).sort(), roleFiles.sort());

  for (const name of manifest.formats) {
    const content = await readFile(new URL(`editorial/templates/${name}.md`, root), 'utf8');
    assert.equal(content.startsWith(`# ${name}\n`), true, `${name} must begin with its identifier`);
    for (const heading of requiredFormatHeadings) {
      assert.equal(content.includes(heading), true, `${name} is missing ${heading}`);
    }
  }

  const expectedRoleTransitions = new Map(
    transitions
      .filter(({ actor }) => manifest.roles.includes(actor))
      .map(({ actor, from, to }) => [actor, `\`transition: ${from ?? 'null'} -> ${to}\``])
  );

  for (const name of manifest.roles) {
    const content = await readFile(new URL(`editorial/agents/${name}.md`, root), 'utf8');
    assert.equal(content.startsWith(`# ${name}\n`), true, `${name} must begin with its identifier`);
    for (const heading of requiredRoleHeadings) {
      assert.equal(content.includes(heading), true, `${name} is missing ${heading}`);
    }
    assert.notEqual(section(content, '## May not', '## Allowed transition'), '');
    assert.doesNotMatch(
      section(content, '## May', '## May not'),
      /\b(?:publish|redirect|delete|change (?:a )?canonical(?: URL)?)\b/i,
      `${name} grants reserved human authority`
    );
    assert.equal(
      section(content, '## Allowed transition', '## Stop conditions'),
      expectedRoleTransitions.get(name),
      `${name} must declare one canonical transition line`
    );
  }

  const ruleFiles = (await readdir(new URL('editorial/rules/', root))).sort();
  assert.deepEqual(ruleFiles, requiredRules);
  for (const file of ruleFiles) {
    const content = await readFile(new URL(`editorial/rules/${file}`, root), 'utf8');
    assert.equal(content.startsWith('Contract version: 1.0.0\n'), true, `${file} has the wrong version`);
    const authority = humanAuthoritySections.get(file);
    if (authority) {
      assert.equal(
        section(content, '## Human-only authority', authority.nextHeading),
        authority.body,
        `${file} must declare one canonical human-only authority line`
      );
    }
  }

  const workflow = JSON.parse(await readFile(new URL('editorial/workflow.json', root), 'utf8'));
  assert.deepEqual(workflow, { states, transitions });
});

test('writer is the only non-human role allowed to mutate a WordPress draft', async () => {
  const manifest = await loadManifest();
  const roles = await Promise.all(
    manifest.roles.map(async (name) => ({ name, content: await loadRole(name) }))
  );
  const draftMutators = roles
    .filter(({ content }) => (
      /\b(?:create|update|edit|correct)\b[\s\S]*\bWordPress draft\b/i.test(
        section(content, '## May', '## May not')
      )
    ))
    .map(({ name }) => name);

  assert.deepEqual(draftMutators, ['writer']);

  const technicalVerifier = roles.find(({ name }) => name === 'technical-verifier').content;
  assert.match(
    section(technicalVerifier, '## May not', '## Allowed transition'),
    /create, update, edit, or correct the WordPress draft/i
  );
  assert.match(
    section(technicalVerifier, '## Stop conditions'),
    /corrections? (?:in|to) (?:the|its) report[\s\S]*return[\s\S]*owning role/i
  );

  const discoveryReviewer = roles.find(({ name }) => name === 'discovery-reviewer').content;
  assert.match(
    section(discoveryReviewer, '## May not', '## Allowed transition'),
    /create, update, edit, or correct (?:the )?(?:WordPress )?draft or metadata/i
  );
  assert.match(
    section(discoveryReviewer, '## Stop conditions'),
    /return[\s\S]*(?:content|metadata)[\s\S]*(?:defects?|corrections?)[\s\S]*owning role/i
  );
});
