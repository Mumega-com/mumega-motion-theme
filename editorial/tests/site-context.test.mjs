import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { promisify } from 'node:util';
import test from 'node:test';

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

  assert.doesNotMatch(committedOutput, /^[A-Z][A-Z0-9_]*=/m);
  assert.doesNotMatch(committedOutput, /(?:api[_-]?key|token|secret)\s*[:=]\s*['\"][A-Za-z0-9_-]{16,}/i);
  assert.doesNotMatch(committedOutput, /publish automatically/i);

  const source = await readFile(generator, 'utf8');
  assert.doesNotMatch(source, /process\.env/);
  assert.doesNotMatch(source, /git\s+(?:log|rev-parse|show)/i);
});
