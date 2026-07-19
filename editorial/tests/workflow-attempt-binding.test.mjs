import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { cp, mkdtemp, readFile, rm, symlink, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

import * as contractLib from '../scripts/contract-lib.mjs';

const root = new URL('../../', import.meta.url);
const attemptPath = 'editorial/examples/valid/workflow-attempt.json';
const reportPath = 'editorial/examples/valid/validation-report.json';

const loadJson = async (base, relativePath) => JSON.parse(
  await readFile(base instanceof URL ? new URL(relativePath, base) : join(base, relativePath), 'utf8')
);

const withTemporaryContract = async (callback) => {
  const temporaryRoot = await mkdtemp(join(tmpdir(), 'editorial-attempt-binding-'));
  await cp(new URL('editorial/', root), join(temporaryRoot, 'editorial'), { recursive: true });

  try {
    return await callback(temporaryRoot);
  } finally {
    await rm(temporaryRoot, { recursive: true, force: true });
  }
};

const sha256 = (bytes) => createHash('sha256').update(bytes).digest('hex');

const validateAttempt = async (base, attempt) => {
  assert.equal(
    typeof contractLib.validateWorkflowAttempt,
    'function',
    'validateWorkflowAttempt(root, attempt) must be exported'
  );
  return contractLib.validateWorkflowAttempt(base, attempt);
};

const validateWithModifiedReport = async (mutate) => withTemporaryContract(async (temporaryRoot) => {
  const attempt = await loadJson(temporaryRoot, attemptPath);
  const report = await loadJson(temporaryRoot, reportPath);
  mutate(report);
  const reportBytes = Buffer.from(`${JSON.stringify(report, null, 2)}\n`);
  await writeFile(join(temporaryRoot, reportPath), reportBytes);
  attempt.validation_report_ref.sha256 = sha256(reportBytes);
  return validateAttempt(temporaryRoot, attempt);
});

test('a workflow attempt is authorized by its exact immutable validation report', async () => {
  const attempt = await loadJson(root, attemptPath);
  const result = await validateAttempt(root, attempt);

  assert.deepEqual(result, { valid: true, errors: [] });
});

test('workflow attempt rejects legacy self-attested pass without a report reference', async () => {
  const attempt = await loadJson(root, attemptPath);
  delete attempt.validation_report_ref;
  const legacyStatusField = ['validation', 'report', 'status'].join('_');
  attempt[legacyStatusField] = 'pass';

  const result = await validateAttempt(root, attempt);

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /validation_report_ref/);
  assert.match(result.errors.join('\n'), /additionalProperties/);
});

test('workflow attempt rejects a mismatched raw report hash', async () => {
  const attempt = await loadJson(root, attemptPath);
  attempt.validation_report_ref.sha256 = '0'.repeat(64);

  const result = await validateAttempt(root, attempt);

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /validation report SHA-256 does not match exact file bytes/);
});

for (const { name, mutate, expected } of [
  {
    name: 'a missing report reference',
    mutate: (attempt) => delete attempt.validation_report_ref,
    expected: /required.*validation_report_ref/
  },
  {
    name: 'path traversal',
    mutate: (attempt) => { attempt.validation_report_ref.path = '../validation-report.json'; },
    expected: /validation_report_ref\/path.*pattern/
  },
  {
    name: 'an absolute report path',
    mutate: (attempt) => { attempt.validation_report_ref.path = '/tmp/validation-report.json'; },
    expected: /validation_report_ref\/path.*pattern/
  },
  {
    name: 'unknown reference properties',
    mutate: (attempt) => { attempt.validation_report_ref.mutable = true; },
    expected: /validation_report_ref.*additionalProperties/
  }
]) {
  test(`workflow attempt rejects ${name}`, async () => {
    const attempt = await loadJson(root, attemptPath);
    mutate(attempt);

    const result = await validateAttempt(root, attempt);

    assert.equal(result.valid, false);
    assert.match(result.errors.join('\n'), expected);
  });
}

test('workflow attempt rejects a missing referenced report file', async () => {
  const attempt = await loadJson(root, attemptPath);
  attempt.validation_report_ref.path = 'editorial/examples/valid/validation-report-missing.json';

  const result = await validateAttempt(root, attempt);

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /could not read referenced validation report/);
});

test('workflow attempt rejects a JSON file outside the validation-report artifact namespace', async () => {
  const attempt = await loadJson(root, attemptPath);
  attempt.validation_report_ref.path = 'editorial/examples/valid/content-brief-explainer.json';
  attempt.validation_report_ref.sha256 = sha256(
    await readFile(new URL('editorial/examples/valid/content-brief-explainer.json', root))
  );

  const result = await validateAttempt(root, attempt);

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /validation_report_ref\/path.*pattern/);
});

test('workflow attempt rejects a validation-report symlink that escapes the contract root', async () => {
  const result = await withTemporaryContract(async (temporaryRoot) => {
    const attempt = await loadJson(temporaryRoot, attemptPath);
    const linkedPath = 'editorial/examples/valid/validation-report-external.json';
    await symlink(new URL(reportPath, root), join(temporaryRoot, linkedPath));
    attempt.validation_report_ref.path = linkedPath;
    return validateAttempt(temporaryRoot, attempt);
  });

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /validation report symlink resolves outside the contract root/);
});

for (const { name, mutate, expected } of [
  {
    name: 'report actor mismatch',
    mutate: (report) => { report.role = 'researcher'; },
    expected: /bound validation report role does not match workflow attempt actor/
  },
  {
    name: 'report edge mismatch',
    mutate: (report) => {
      report.role = 'technical-verifier';
      report.state_transition = { from: 'drafting', to: 'technical_verification' };
      report.gate_results = [
        { gate: 'schema', status: 'pass', details: 'Schema passed.' },
        { gate: 'evidence', status: 'pass', details: 'Evidence passed.' },
        { gate: 'template', status: 'pass', details: 'Template passed.' },
        { gate: 'wordpress', status: 'pass', details: 'Post-draft WordPress validation passed.' }
      ];
      report.next_allowed_role = 'discovery-reviewer';
    },
    expected: /bound validation report transition does not match workflow attempt edge/
  },
  {
    name: 'report canonical slug mismatch',
    mutate: (report) => { report.canonical_slug = 'another-canonical-draft'; },
    expected: /bound validation report canonical_slug does not match WordPress target/
  },
  {
    name: 'failing report status',
    mutate: (report) => { report.overall_status = 'fail'; },
    expected: /bound validation report overall_status must be pass/
  },
  {
    name: 'missing required preflight gate',
    mutate: (report) => {
      report.gate_results = report.gate_results.filter(({ gate }) => gate !== 'template');
    },
    expected: /missing required gate template/
  },
  {
    name: 'wrong next role',
    mutate: (report) => { report.next_allowed_role = 'writer'; },
    expected: /next_allowed_role does not match workflow transition/
  }
]) {
  test(`workflow attempt rejects ${name}`, async () => {
    const result = await validateWithModifiedReport(mutate);

    assert.equal(result.valid, false);
    assert.match(result.errors.join('\n'), expected);
  });
}
