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
  {
    from: null,
    to: 'idea',
    actor: 'scout',
    human_only: false,
    required_gates: ['schema', 'scope-duplication'],
    next_allowed_role: 'brief-creator'
  },
  {
    from: 'idea',
    to: 'brief_ready',
    actor: 'brief-creator',
    human_only: false,
    required_gates: ['schema', 'scope-duplication'],
    next_allowed_role: 'human-editor'
  },
  {
    from: 'brief_ready',
    to: 'brief_accepted',
    actor: 'human-editor',
    human_only: true,
    required_gates: ['schema', 'scope-duplication', 'human'],
    next_allowed_role: 'researcher'
  },
  {
    from: 'brief_accepted',
    to: 'research_ready',
    actor: 'researcher',
    human_only: false,
    required_gates: ['schema', 'evidence'],
    next_allowed_role: 'human-editor'
  },
  {
    from: 'research_ready',
    to: 'research_accepted',
    actor: 'human-editor',
    human_only: true,
    required_gates: ['schema', 'evidence', 'human'],
    next_allowed_role: 'writer'
  },
  {
    from: 'research_accepted',
    to: 'drafting',
    actor: 'writer',
    human_only: false,
    required_gates: ['schema', 'scope-duplication', 'evidence', 'template'],
    next_allowed_role: 'technical-verifier'
  },
  {
    from: 'drafting',
    to: 'technical_verification',
    actor: 'technical-verifier',
    human_only: false,
    required_gates: ['schema', 'evidence', 'template', 'wordpress'],
    next_allowed_role: 'discovery-reviewer'
  },
  {
    from: 'technical_verification',
    to: 'discovery_review',
    actor: 'discovery-reviewer',
    human_only: false,
    required_gates: ['schema', 'scope-duplication', 'discovery'],
    next_allowed_role: 'editor-handoff'
  },
  {
    from: 'discovery_review',
    to: 'human_review',
    actor: 'editor-handoff',
    human_only: false,
    required_gates: ['schema', 'scope-duplication', 'evidence', 'template', 'wordpress', 'discovery'],
    next_allowed_role: 'human-editor'
  },
  {
    from: 'human_review',
    to: 'approved',
    actor: 'human-editor',
    human_only: true,
    required_gates: ['schema', 'scope-duplication', 'evidence', 'template', 'wordpress', 'discovery', 'human'],
    next_allowed_role: 'human-editor'
  },
  {
    from: 'approved',
    to: 'published',
    actor: 'human-editor',
    human_only: true,
    required_gates: ['schema', 'scope-duplication', 'evidence', 'template', 'wordpress', 'discovery', 'human'],
    next_allowed_role: 'human-editor'
  },
  {
    from: 'published',
    to: 'update_due',
    actor: 'human-editor',
    human_only: true,
    required_gates: ['evidence', 'human'],
    next_allowed_role: 'human-editor'
  },
  {
    from: 'published',
    to: 'corrected',
    actor: 'human-editor',
    human_only: true,
    required_gates: ['schema', 'evidence', 'human'],
    next_allowed_role: 'human-editor'
  },
  {
    from: 'published',
    to: 'retired',
    actor: 'human-editor',
    human_only: true,
    required_gates: ['scope-duplication', 'human'],
    next_allowed_role: 'human-editor'
  }
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

  const writer = roles.find(({ name }) => name === 'writer').content;
  assert.match(
    section(writer, '## May', '## May not'),
    /`create-draft` or `update-draft`[\s\S]*`wordpress_target`/
  );
  for (const { name, content } of roles.filter(({ name }) => name !== 'writer')) {
    assert.match(
      section(content, '## May not', '## Allowed transition'),
      /must use `wordpress_operation: none`/,
      `${name} must explicitly require a non-WordPress workflow attempt`
    );
  }

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

test('WordPress attempt contract documents the exact fail-closed shape', async () => {
  const content = await readFile(
    new URL('editorial/rules/wordpress-handoff.md', root),
    'utf8'
  );
  const attemptContract = section(
    content,
    '## Workflow attempt contract',
    '## Human-only authority'
  );

  assert.match(attemptContract, /`kind: workflow-attempt`/);
  assert.match(attemptContract, /`wordpress_operation: none \| create-draft \| update-draft`/);
  assert.match(attemptContract, /`validation_report_ref`/);
  assert.match(attemptContract, /repository-relative POSIX `path`/);
  assert.match(attemptContract, /`sha256`/);
  assert.match(attemptContract, /exact report file bytes/);
  assert.match(attemptContract, /no JSON reserialization, whitespace normalization, or key sorting/i);
  assert.doesNotMatch(attemptContract, /validation_report_status/);
  assert.match(attemptContract, /`wordpress_target`/);
  assert.match(attemptContract, /`canonical_slug`/);
  assert.match(attemptContract, /unique `authorized_fields`/);
  for (const field of ['title', 'content', 'excerpt', 'featured_media', 'categories', 'tags']) {
    assert.match(attemptContract, new RegExp('`' + field + '`'));
  }
  assert.match(attemptContract, /preflight[\s\S]*before WordPress mutation/i);
  assert.match(attemptContract, /`wordpress` gate[\s\S]*post-draft/i);
  assert.match(attemptContract, /Only `writer`[\s\S]*`research_accepted` to `drafting`/);
  assert.match(attemptContract, /all other roles and edges require `none`/);
});
