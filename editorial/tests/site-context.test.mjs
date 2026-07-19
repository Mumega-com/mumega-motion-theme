import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { cp, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { promisify } from 'node:util';
import test from 'node:test';

import { generateSiteContext } from '../scripts/generate-site-context.mjs';

const execFileAsync = promisify(execFile);
const root = new URL('../../', import.meta.url);
const generator = new URL('editorial/scripts/generate-site-context.mjs', root);
const generatedContext = new URL('editorial/generated/mcpwp-site-context.md', root);

const formats = [
  'analysis-opinion',
  'comparison-review',
  'explainer',
  'news-briefing',
  'practical-guide',
  'test-report'
];
const roles = [
  'brief-creator',
  'discovery-reviewer',
  'editor-handoff',
  'researcher',
  'scout',
  'technical-verifier',
  'writer'
];

const generate = async () => {
  try {
    await execFileAsync(process.execPath, [generator.pathname], { cwd: root.pathname });
  } catch (error) {
    assert.fail(`site-context generator must run successfully: ${error.stderr || error.message}`);
  }
  return readFile(generatedContext, 'utf8');
};

const withTemporaryContract = async (callback) => {
  const temporaryRoot = await mkdtemp(join(tmpdir(), 'editorial-context-'));
  await cp(new URL('editorial/', root), join(temporaryRoot, 'editorial'), { recursive: true });

  try {
    return await callback(temporaryRoot);
  } finally {
    await rm(temporaryRoot, { recursive: true, force: true });
  }
};

test('site context is deterministic, complete, and safe for MCPWP handoff', async () => {
  const committedOutput = await readFile(generatedContext, 'utf8');
  const firstGeneration = await generate();
  const secondGeneration = await generate();

  assert.equal(committedOutput, firstGeneration);
  assert.equal(firstGeneration, secondGeneration);
  assert.match(committedOutput, /^Editorial Contract: 1\.0\.0$/m);
  assert.match(committedOutput, /^Contract SHA-256: [a-f0-9]{64}$/m);
  assert.match(committedOutput, /^Draft-only: true$/m);
  assert.match(committedOutput, /^Human publication required: true$/m);

  for (const format of formats) assert.match(committedOutput, new RegExp('`' + format + '`'));
  for (const role of roles) assert.match(committedOutput, new RegExp('`' + role + '`'));

  assert.match(
    committedOutput,
    /`wordpress_operation: none \| create-draft \| update-draft`/
  );
  assert.match(committedOutput, /unique `authorized_fields`/);

  assert.doesNotMatch(committedOutput, /^[A-Z][A-Z0-9_]*=/m);
  assert.doesNotMatch(committedOutput, /(?:api[_-]?key|token|secret)\s*[:=]\s*['\"][A-Za-z0-9_-]{16,}/i);
  assert.doesNotMatch(committedOutput, /publish automatically/i);

  const source = await readFile(generator, 'utf8');
  assert.doesNotMatch(source, /process\.env/);
  assert.doesNotMatch(source, /git\s+(?:log|rev-parse|show)/i);
});

test('pure site-context generation semantically follows role, format, rule, and workflow sources', async () => {
  await withTemporaryContract(async (temporaryRoot) => {
    const baseline = await generateSiteContext(temporaryRoot);

    const writerFile = join(temporaryRoot, 'editorial/agents/writer.md');
    const writer = await readFile(writerFile, 'utf8');
    await writeFile(
      writerFile,
      writer.replace(
        'Synthesize, organize, and explain supported material',
        'Synthesize source-backed material under the changed role policy'
      )
    );

    const formatFile = join(temporaryRoot, 'editorial/templates/explainer.md');
    const format = await readFile(formatFile, 'utf8');
    await writeFile(
      formatFile,
      format.replace(
        'Every material factual claim must map to a current primary source',
        'Every changed explainer claim must map to a named primary source'
      )
    );

    const ruleFile = join(temporaryRoot, 'editorial/rules/wordpress-handoff.md');
    const rule = await readFile(ruleFile, 'utf8');
    await writeFile(
      ruleFile,
      rule.replace(
        'The writer changes only the authorized post and fields.',
        'The changed handoff limits the writer to a named draft target and fields.'
      )
    );

    const workflowFile = join(temporaryRoot, 'editorial/workflow.json');
    const workflow = JSON.parse(await readFile(workflowFile, 'utf8'));
    workflow.transitions[0].next_allowed_role = 'researcher';
    await writeFile(workflowFile, `${JSON.stringify(workflow, null, 2)}\n`);

    const changed = await generateSiteContext(temporaryRoot);

    assert.notEqual(changed, baseline);
    assert.match(changed, /changed role policy/);
    assert.match(changed, /changed explainer claim/);
    assert.match(changed, /changed handoff limits/);
    assert.match(
      changed,
      /`null` → `idea` — actor `scout` — gates `schema`, `scope-duplication` — next `researcher`/
    );
  });
});
