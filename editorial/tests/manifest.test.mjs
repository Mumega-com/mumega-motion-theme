import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const root = new URL('../../', import.meta.url);

test('manifest declares the approved independent release trains', async () => {
  const manifest = JSON.parse(await readFile(new URL('editorial/manifest.json', root), 'utf8'));
  assert.deepEqual(manifest, {
    editorial_contract: '1.0.0',
    mcpwp_profile: '1.0.0',
    requires_theme: '>=0.2.0 <0.3.0',
    mcpwp_plugin_required: false,
    tested_mcpwp_plugin: '3.6.1',
    schemas: ['content-brief', 'research-packet', 'validation-report'],
    formats: ['explainer', 'practical-guide', 'test-report', 'comparison-review', 'news-briefing', 'analysis-opinion'],
    roles: ['scout', 'brief-creator', 'researcher', 'writer', 'technical-verifier', 'discovery-reviewer', 'editor-handoff']
  });
});
