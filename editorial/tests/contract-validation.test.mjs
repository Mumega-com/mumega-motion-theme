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
  assert.match(result.errors.join('\n'), /WordPress mutation actor does not own the workflow transition/);
});

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
