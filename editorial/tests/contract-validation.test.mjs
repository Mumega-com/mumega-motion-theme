import assert from 'node:assert/strict';
import { cp, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

import * as contractLib from '../scripts/contract-lib.mjs';

const root = new URL('../../', import.meta.url);

const invalidCases = [
  {
    fixture: 'content-brief-unknown-format.json',
    target: 'editorial/examples/valid/content-brief-explainer.json',
    expected: 'unknown format'
  },
  {
    fixture: 'content-brief-incompatible-contract-major.json',
    target: 'editorial/examples/valid/content-brief-explainer.json',
    expected: 'incompatible contract major'
  },
  {
    fixture: 'research-packet-missing-evidence.json',
    target: 'editorial/examples/valid/research-packet.json',
    expected: 'material claim requires a source URL or evidence reference'
  },
  {
    fixture: 'workflow-non-human-published.json',
    target: 'editorial/examples/valid/workflow-attempt.json',
    expected: 'non-human transition to published is prohibited'
  },
  {
    fixture: 'manifest-missing-file.json',
    target: 'editorial/manifest.json',
    expected: 'manifest item has no matching file'
  },
  {
    fixture: 'workflow-wordpress-before-pass.json',
    target: 'editorial/examples/valid/workflow-attempt.json',
    expected: 'WordPress mutation requires a passing validation report'
  }
];

const requireValidator = () => {
  assert.equal(
    typeof contractLib.validateContract,
    'function',
    'validateContract(root) must be implemented'
  );
  return contractLib.validateContract;
};

const withTemporaryContract = async (callback) => {
  const temporaryRoot = await mkdtemp(join(tmpdir(), 'editorial-contract-'));
  await cp(new URL('editorial/', root), join(temporaryRoot, 'editorial'), { recursive: true });

  try {
    return await callback(temporaryRoot);
  } finally {
    await rm(temporaryRoot, { recursive: true, force: true });
  }
};

const loadInvalidFixture = async (name) => {
  const fixture = JSON.parse(
    await readFile(new URL(`editorial/examples/invalid/${name}`, root), 'utf8')
  );
  const { _expected_errors: expectedErrors, ...artifact } = fixture;
  assert.ok(Array.isArray(expectedErrors) && expectedErrors.length > 0);
  return { artifact, expectedErrors };
};

const sectionBody = (content, heading, nextHeading = null) => {
  const start = content.indexOf(`${heading}\n`);
  assert.notEqual(start, -1, `missing ${heading}`);
  const bodyStart = start + heading.length + 1;
  const end = nextHeading ? content.indexOf(`\n${nextHeading}\n`, bodyStart) : content.length;
  if (nextHeading) assert.notEqual(end, -1, `missing ${nextHeading}`);
  return content.slice(bodyStart, end).trim();
};

const replaceSectionBody = (content, heading, nextHeading, body) => {
  const currentBody = sectionBody(content, heading, nextHeading);
  return content.replace(currentBody, body);
};

const setSectionBody = (content, heading, nextHeading, body) => {
  if (content.includes(`${heading}\n`)) {
    return replaceSectionBody(content, heading, nextHeading, body);
  }
  if (nextHeading) {
    return content.replace(`\n${nextHeading}\n`, `\n${heading}\n\n${body}\n\n${nextHeading}\n`);
  }
  return `${content.trimEnd()}\n\n${heading}\n\n${body}\n`;
};

test('the repository editorial contract and all declared examples agree', async () => {
  const validateContract = requireValidator();
  const result = await validateContract(root);

  assert.deepEqual(result, { valid: true, errors: [] });
});

for (const { fixture, target, expected } of invalidCases) {
  test(`targeted invalid fixture reports ${expected}`, async () => {
    const validateContract = requireValidator();
    const { artifact, expectedErrors } = await loadInvalidFixture(fixture);
    assert.ok(
      expectedErrors.some((fragment) => fragment.includes(expected)),
      `${fixture} must declare the targeted expected error`
    );

    const result = await withTemporaryContract(async (temporaryRoot) => {
      await writeFile(
        join(temporaryRoot, target),
        `${JSON.stringify(artifact, null, 2)}\n`
      );
      return validateContract(temporaryRoot);
    });

    assert.equal(result.valid, false);
    assert.match(result.errors.join('\n'), new RegExp(expected.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  });
}

test('WordPress mutation requires the actor to own the workflow transition', async () => {
  const validateContract = requireValidator();
  const { artifact } = await loadInvalidFixture('workflow-wordpress-before-pass.json');
  artifact.actor = 'researcher';
  artifact.validation_report_status = 'pass';

  const result = await withTemporaryContract(async (temporaryRoot) => {
    await writeFile(
      join(temporaryRoot, 'editorial/examples/valid/workflow-attempt.json'),
      `${JSON.stringify(artifact, null, 2)}\n`
    );
    return validateContract(temporaryRoot);
  });

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /workflow attempt actor does not own the workflow transition/);
});

for (const attempt of [
  {
    name: 'unknown actor',
    actor: 'publisher-bot',
    from_state: 'brief_ready',
    to_state: 'brief_accepted'
  },
  {
    name: 'known actor on another role\'s edge',
    actor: 'writer',
    from_state: 'brief_ready',
    to_state: 'brief_accepted'
  }
]) {
  test(`non-mutating workflow attempt rejects ${attempt.name}`, async () => {
    const validateContract = requireValidator();
    const artifact = {
      kind: 'workflow-attempt',
      actor: attempt.actor,
      from_state: attempt.from_state,
      to_state: attempt.to_state,
      wordpress_mutation: false,
      validation_report_status: 'pass'
    };

    const result = await withTemporaryContract(async (temporaryRoot) => {
      await writeFile(
        join(temporaryRoot, 'editorial/examples/valid/workflow-attempt.json'),
        `${JSON.stringify(artifact, null, 2)}\n`
      );
      return validateContract(temporaryRoot);
    });

    assert.equal(result.valid, false);
    assert.match(result.errors.join('\n'), /workflow attempt actor does not own the workflow transition/);
  });
}

test('workflow rejects an extra human transition outside the documented state machine', async () => {
  const validateContract = requireValidator();
  const result = await withTemporaryContract(async (temporaryRoot) => {
    const workflowFile = join(temporaryRoot, 'editorial/workflow.json');
    const workflow = JSON.parse(await readFile(workflowFile, 'utf8'));
    workflow.transitions.push({
      from: 'idea',
      to: 'approved',
      actor: 'human-editor',
      human_only: true
    });
    await writeFile(workflowFile, `${JSON.stringify(workflow, null, 2)}\n`);
    return validateContract(temporaryRoot);
  });

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /human transition graph does not match documented state machine/);
});

test('role contract rejects a conflicting second transition declaration', async () => {
  const validateContract = requireValidator();
  const result = await withTemporaryContract(async (temporaryRoot) => {
    const roleFile = join(temporaryRoot, 'editorial/agents/writer.md');
    const content = await readFile(roleFile, 'utf8');
    const changed = replaceSectionBody(
      content,
      '## Allowed transition',
      '## Stop conditions',
      `${sectionBody(content, '## Allowed transition', '## Stop conditions')}\n\n\`transition: idea -> brief_ready\``
    );
    await writeFile(roleFile, changed);
    return validateContract(temporaryRoot);
  });

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /Allowed transition section must be one canonical line/);
});

test('role contract rejects a prose-negated transition declaration', async () => {
  const validateContract = requireValidator();
  const result = await withTemporaryContract(async (temporaryRoot) => {
    const roleFile = join(temporaryRoot, 'editorial/agents/writer.md');
    const content = await readFile(roleFile, 'utf8');
    const changed = replaceSectionBody(
      content,
      '## Allowed transition',
      '## Stop conditions',
      'The writer does not transition `research_accepted` to `drafting`.'
    );
    await writeFile(roleFile, changed);
    return validateContract(temporaryRoot);
  });

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /Allowed transition section must be one canonical line/);
});

test('role contract rejects extra prose after its canonical transition', async () => {
  const validateContract = requireValidator();
  const result = await withTemporaryContract(async (temporaryRoot) => {
    const roleFile = join(temporaryRoot, 'editorial/agents/writer.md');
    const content = await readFile(roleFile, 'utf8');
    const body = sectionBody(content, '## Allowed transition', '## Stop conditions');
    const changed = replaceSectionBody(
      content,
      '## Allowed transition',
      '## Stop conditions',
      `${body}\n\nThe writer may also advance compatible records.`
    );
    await writeFile(roleFile, changed);
    return validateContract(temporaryRoot);
  });

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /Allowed transition section must be one canonical line/);
});

const humanAuthorityCases = [
  {
    name: 'WordPress authority',
    file: 'editorial/rules/wordpress-handoff.md',
    nextHeading: '## Fail closed',
    declaration: '`human-editor only: publication, scheduling, redirects, deletions, canonical changes, exceptions`'
  },
  {
    name: 'freshness and corrections authority',
    file: 'editorial/rules/freshness-corrections.md',
    nextHeading: '## Fail closed',
    declaration: '`human-editor only: redirects, deletions, canonical changes, retirement, public correction decisions`'
  },
  {
    name: 'authorship and disclosure authority',
    file: 'editorial/rules/authorship-disclosure.md',
    nextHeading: null,
    declaration: '`human-editor only: commercial conclusions`'
  }
];

for (const { name, file, nextHeading, declaration } of humanAuthorityCases) {
  test(`contract rejects an additive agent grant in ${name}`, async () => {
    const validateContract = requireValidator();
    const result = await withTemporaryContract(async (temporaryRoot) => {
      const ruleFile = join(temporaryRoot, file);
      const content = await readFile(ruleFile, 'utf8');
      const changed = setSectionBody(
        content,
        '## Human-only authority',
        nextHeading,
        `${declaration}\n\n\`writer also: publication\``
      );
      await writeFile(ruleFile, changed);
      return validateContract(temporaryRoot);
    });

    assert.equal(result.valid, false);
    assert.match(result.errors.join('\n'), /Human-only authority section must be one canonical line/);
  });
}
