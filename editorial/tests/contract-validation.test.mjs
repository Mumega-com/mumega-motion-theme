import assert from 'node:assert/strict';
import { cp, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

import * as contractLib from '../scripts/contract-lib.mjs';

const root = new URL('../../', import.meta.url);
const validReportRef = {
  path: 'editorial/examples/valid/validation-report.json',
  sha256: 'd8a6803c20d607456c70df2be553d86cc970a769263726fdf6eafd5ff12085af'
};

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
    expected: 'workflow attempts cannot represent human-only transitions'
  },
  {
    fixture: 'manifest-missing-file.json',
    target: 'editorial/manifest.json',
    expected: 'manifest item has no matching file'
  },
  {
    fixture: 'workflow-researcher-draft-mutation.json',
    target: 'editorial/examples/valid/workflow-attempt.json',
    expected: 'only writer may perform a WordPress draft operation'
  },
  {
    fixture: 'workflow-forbidden-operation.json',
    target: 'editorial/examples/valid/workflow-attempt.json',
    expected: 'wordpress_operation'
  },
  {
    fixture: 'workflow-report-hash-mismatch.json',
    target: 'editorial/examples/valid/workflow-attempt.json',
    expected: 'validation report SHA-256 does not match exact file bytes'
  },
  {
    fixture: 'workflow-report-path-traversal.json',
    target: 'editorial/examples/valid/workflow-attempt.json',
    expected: 'validation_report_ref/path'
  },
  {
    fixture: 'validation-report-missing-required-gate.json',
    target: 'editorial/examples/valid/validation-report.json',
    expected: 'missing required gate'
  },
  {
    fixture: 'validation-report-duplicate-gate.json',
    target: 'editorial/examples/valid/validation-report.json',
    expected: 'duplicate gate identifier'
  },
  {
    fixture: 'validation-report-wrong-next-role.json',
    target: 'editorial/examples/valid/validation-report.json',
    expected: 'next_allowed_role does not match workflow transition'
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

test('WordPress draft operation requires the actor to own the workflow transition', async () => {
  const validateContract = requireValidator();
  const artifact = JSON.parse(
    await readFile(new URL('editorial/examples/valid/workflow-attempt.json', root), 'utf8')
  );
  artifact.from_state = 'idea';
  artifact.to_state = 'brief_ready';

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
      wordpress_operation: 'none',
      validation_report_ref: validReportRef
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
      human_only: true,
      required_gates: ['human'],
      next_allowed_role: 'human-editor'
    });
    await writeFile(workflowFile, `${JSON.stringify(workflow, null, 2)}\n`);
    return validateContract(temporaryRoot);
  });

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /human transition graph does not match documented state machine/);
});

test('workflow rejects missing gate metadata without crashing report validation', async () => {
  const validateContract = requireValidator();
  const result = await withTemporaryContract(async (temporaryRoot) => {
    const workflowFile = join(temporaryRoot, 'editorial/workflow.json');
    const workflow = JSON.parse(await readFile(workflowFile, 'utf8'));
    const handoff = workflow.transitions.find(({ from, to }) => (
      from === 'discovery_review' && to === 'human_review'
    ));
    delete handoff.required_gates;
    await writeFile(workflowFile, `${JSON.stringify(workflow, null, 2)}\n`);
    return validateContract(temporaryRoot);
  });

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /required property 'required_gates'/);
});

for (const { name, transitions } of [
  { name: 'missing transitions', transitions: undefined },
  { name: 'non-array transitions', transitions: {} },
  { name: 'a malformed transition entry', transitions: [null] }
]) {
  test(`workflow returns validation errors for ${name} without throwing`, async () => {
    const validateContract = requireValidator();
    const result = await withTemporaryContract(async (temporaryRoot) => {
      const workflowFile = join(temporaryRoot, 'editorial/workflow.json');
      const workflow = JSON.parse(await readFile(workflowFile, 'utf8'));
      if (transitions === undefined) {
        delete workflow.transitions;
      } else {
        workflow.transitions = transitions;
      }
      await writeFile(workflowFile, `${JSON.stringify(workflow, null, 2)}\n`);
      return validateContract(temporaryRoot);
    });

    assert.equal(result.valid, false);
    assert.ok(result.errors.length > 0);
    assert.match(result.errors.join('\n'), /editorial\/workflow\.json/);
  });
}

test('WordPress target rejects duplicate fields and unknown target properties', async () => {
  const validateContract = requireValidator();
  const artifact = {
    kind: 'workflow-attempt',
    actor: 'writer',
    from_state: 'research_accepted',
    to_state: 'drafting',
    wordpress_operation: 'update-draft',
    wordpress_target: {
      canonical_slug: 'prepare-safe-wordpress-draft',
      authorized_fields: ['title', 'title'],
      post_id: 42
    },
    validation_report_ref: validReportRef
  };

  const result = await withTemporaryContract(async (temporaryRoot) => {
    await writeFile(
      join(temporaryRoot, 'editorial/examples/valid/workflow-attempt.json'),
      `${JSON.stringify(artifact, null, 2)}\n`
    );
    return validateContract(temporaryRoot);
  });

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /wordpress_target.*additionalProperties/);
  assert.match(result.errors.join('\n'), /authorized_fields.*uniqueItems/);
});

for (const wordpressOperation of [
  'publish',
  'schedule',
  'redirect',
  'delete',
  'canonical-change',
  'correct',
  'retire'
]) {
  test(`workflow attempt rejects forbidden WordPress operation ${wordpressOperation}`, async () => {
    const validateContract = requireValidator();
    const artifact = {
      kind: 'workflow-attempt',
      actor: 'writer',
      from_state: 'research_accepted',
      to_state: 'drafting',
      wordpress_operation: wordpressOperation,
      wordpress_target: {
        canonical_slug: 'prepare-safe-wordpress-draft',
        authorized_fields: ['content']
      },
      validation_report_ref: validReportRef
    };

    const result = await withTemporaryContract(async (temporaryRoot) => {
      await writeFile(
        join(temporaryRoot, 'editorial/examples/valid/workflow-attempt.json'),
        `${JSON.stringify(artifact, null, 2)}\n`
      );
      return validateContract(temporaryRoot);
    });

    assert.equal(result.valid, false);
    assert.match(result.errors.join('\n'), /wordpress_operation.*enum/);
  });
}

test('a non-WordPress attempt rejects a target object', async () => {
  const validateContract = requireValidator();
  const artifact = {
    kind: 'workflow-attempt',
    actor: 'scout',
    from_state: null,
    to_state: 'idea',
    wordpress_operation: 'none',
    wordpress_target: {
      canonical_slug: 'prepare-safe-wordpress-draft',
      authorized_fields: ['content']
    },
    validation_report_ref: validReportRef
  };

  const result = await withTemporaryContract(async (temporaryRoot) => {
    await writeFile(
      join(temporaryRoot, 'editorial/examples/valid/workflow-attempt.json'),
      `${JSON.stringify(artifact, null, 2)}\n`
    );
    return validateContract(temporaryRoot);
  });

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /wordpress_target.*must NOT be valid/);
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
