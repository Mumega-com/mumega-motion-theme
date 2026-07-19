import assert from 'node:assert/strict';
import test from 'node:test';

import { validateArtifact } from '../scripts/contract-lib.mjs';

const root = new URL('../../', import.meta.url);

const validBrief = {
  contract_version: '1.0.0',
  working_title: 'How AI agents can prepare safe WordPress drafts',
  canonical_slug: 'ai-agents-wordpress-drafts',
  content_format: 'practical-guide',
  primary_topic: 'build',
  primary_audience: 'builder',
  user_question: 'How can an AI agent prepare a WordPress draft safely?',
  direct_answer: 'Use bounded permissions, evidence gates, and human publication approval.',
  search_intent: 'procedural',
  unique_value_type: 'implementation-procedure',
  unique_value: 'A reproducible workflow with explicit validation and handoff boundaries.',
  public_entities: ['WordPress', 'AI agents'],
  canonical_page_supported: null,
  required_evidence: ['Current WordPress draft behavior', 'Agent permission boundaries'],
  primary_sources: ['https://developer.wordpress.org/rest-api/reference/posts/'],
  test_required: true,
  versions_and_date: 'WordPress 6.8, tested 2026-07-19',
  required_internal_links: ['/wordpress-ai/'],
  human_reviewer_role: 'editor',
  ai_disclosure: 'AI assisted with research and drafting; a human editor reviews publication.',
  freshness_class: 'version-sensitive',
  update_trigger: 'WordPress post status or permissions change.',
  commercial_relationship: 'none'
};

const validResearchPacket = {
  contract_version: '1.0.0',
  canonical_slug: 'ai-agents-wordpress-drafts',
  claims: [
    {
      claim: 'WordPress supports creating posts with draft status.',
      claim_type: 'sourced-fact',
      source_url: 'https://developer.wordpress.org/rest-api/reference/posts/',
      evidence_reference: null,
      publisher: 'WordPress.org',
      published_at: null,
      accessed_at: '2026-07-19',
      version_environment: 'WordPress 6.8',
      confidence: 'high',
      uncertainty: 'Plugin authorization can further restrict access.',
      stale_trigger: 'WordPress REST API post status behavior changes.',
      target_section: 'Prerequisites and permissions'
    }
  ],
  counterevidence: [],
  normalized_entities: ['WordPress'],
  forbidden_claims: ['The agent can publish without human approval.'],
  artifacts: ['https://example.com/artifacts/wordpress-draft-test.json']
};

const validValidationReport = {
  contract_version: '1.0.0',
  canonical_slug: 'ai-agents-wordpress-drafts',
  role: 'technical-verifier',
  state_transition: {
    from: 'drafting',
    to: 'technical_verification'
  },
  timestamp: '2026-07-19T16:30:00Z',
  artifact_hashes: {
    'content-brief.json': 'a'.repeat(64),
    'research-packet.json': 'b'.repeat(64)
  },
  gate_results: [
    {
      gate: 'schema',
      status: 'pass',
      details: 'All artifacts match contract version 1.0.0.'
    },
    {
      gate: 'evidence',
      status: 'pass',
      details: 'Every material claim has a primary source or test artifact.'
    }
  ],
  unresolved_risks: [],
  next_allowed_role: 'discovery-reviewer',
  overall_status: 'pass'
};

test('content brief accepts the complete controlled contract', async () => {
  const result = await validateArtifact(root, 'content-brief', validBrief);
  assert.equal(result.valid, true, result.errors.join('\n'));
});

test('all artifact schemas reject non-canonical slug syntax', async () => {
  const artifacts = {
    'content-brief': validBrief,
    'research-packet': validResearchPacket,
    'validation-report': validValidationReport
  };
  const invalidSlugs = [
    'ai agents',
    'https://example.com/ai-agents',
    'AI-agents',
    'ai--agents'
  ];
  const outcomes = [];

  for (const [schemaName, artifact] of Object.entries(artifacts)) {
    for (const canonicalSlug of invalidSlugs) {
      const result = await validateArtifact(root, schemaName, {
        ...artifact,
        canonical_slug: canonicalSlug
      });
      outcomes.push({ schemaName, canonicalSlug, result });
    }
  }

  assert.deepEqual(
    outcomes.filter(({ result }) => result.valid).map(
      ({ schemaName, canonicalSlug }) => `${schemaName}: ${canonicalSlug}`
    ),
    []
  );
  for (const { result } of outcomes) {
    assert.match(result.errors.join('\n'), /canonical_slug.*pattern/);
  }
});

test('content brief rejects unknown format, missing reviewer, duplicate sources, and extra fields', async () => {
  const invalidBrief = {
    ...validBrief,
    content_format: 'listicle',
    human_reviewer_role: '',
    primary_sources: [validBrief.primary_sources[0], validBrief.primary_sources[0]],
    publish_now: true
  };

  const result = await validateArtifact(root, 'content-brief', invalidBrief);
  assert.equal(result.valid, false);
  const errors = result.errors.join('\n');
  assert.match(errors, /content_format/);
  assert.match(errors, /human_reviewer_role/);
  assert.match(errors, /primary_sources/);
  assert.match(errors, /additionalProperties/);
});

test('content brief requires versions and date when testing is required', async () => {
  const result = await validateArtifact(root, 'content-brief', {
    ...validBrief,
    versions_and_date: null
  });

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /versions_and_date/);
});

test('research packet accepts claims backed by a public source', async () => {
  const result = await validateArtifact(root, 'research-packet', validResearchPacket);
  assert.equal(result.valid, true, result.errors.join('\n'));
});

test('research packet rejects a claim without a source or evidence reference', async () => {
  const invalidPacket = structuredClone(validResearchPacket);
  invalidPacket.claims[0].source_url = null;
  invalidPacket.claims[0].evidence_reference = null;

  const result = await validateArtifact(root, 'research-packet', invalidPacket);
  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /claims\/0.*anyOf/);
});

test('research packet accepts claims backed by an evidence reference', async () => {
  const packet = structuredClone(validResearchPacket);
  packet.claims[0].source_url = null;
  packet.claims[0].evidence_reference = 'test-artifacts/wordpress-draft-run.json';

  const result = await validateArtifact(root, 'research-packet', packet);
  assert.equal(result.valid, true, result.errors.join('\n'));
});

test('validation report accepts controlled fields and lowercase SHA-256 hashes', async () => {
  const result = await validateArtifact(root, 'validation-report', validValidationReport);
  assert.equal(result.valid, true, result.errors.join('\n'));
});

test('validation report cannot pass when any gate fails', async () => {
  const invalidReport = structuredClone(validValidationReport);
  invalidReport.gate_results[1].status = 'fail';
  invalidReport.overall_status = 'pass';

  const result = await validateArtifact(root, 'validation-report', invalidReport);
  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /overall_status.*const/);
});

test('validation report rejects an invalid role', async () => {
  const result = await validateArtifact(root, 'validation-report', {
    ...validValidationReport,
    role: 'publisher'
  });

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /role.*enum/);
});

test('validation report rejects an invalid artifact hash', async () => {
  const report = structuredClone(validValidationReport);
  report.artifact_hashes['content-brief.json'] = 'ABC123';

  const result = await validateArtifact(root, 'validation-report', report);
  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /artifact_hashes\/content-brief\.json.*pattern/);
});

test('validation report rejects an invalid timestamp', async () => {
  const result = await validateArtifact(root, 'validation-report', {
    ...validValidationReport,
    timestamp: '2026-07-19'
  });

  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /timestamp.*format/);
});

test('validation report rejects unknown nested fields', async () => {
  const report = structuredClone(validValidationReport);
  report.state_transition.actor = 'technical-verifier';

  const result = await validateArtifact(root, 'validation-report', report);
  assert.equal(result.valid, false);
  assert.match(result.errors.join('\n'), /state_transition.*additionalProperties/);
});

test('validation report permits scout creation with a null from state', async () => {
  const report = structuredClone(validValidationReport);
  report.role = 'scout';
  report.state_transition = { from: null, to: 'idea' };
  report.next_allowed_role = 'brief-creator';

  const result = await validateArtifact(root, 'validation-report', report);
  assert.equal(result.valid, true, result.errors.join('\n'));
});

test('validation does not mutate input artifacts', async () => {
  const artifact = structuredClone(validBrief);
  const before = structuredClone(artifact);

  await validateArtifact(root, 'content-brief', artifact);

  assert.deepEqual(artifact, before);
});

test('validation rejects schema names not declared by the manifest', async () => {
  await assert.rejects(
    validateArtifact(root, 'workflow-attempt', {}),
    /Schema "workflow-attempt" is not declared in editorial\/manifest\.json/
  );
});
