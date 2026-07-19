import { readdir, readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

import Ajv2020 from 'ajv/dist/2020.js';
import addFormats from 'ajv-formats';

const normalizeErrors = (errors = []) => errors.map(
  ({ instancePath, keyword, message }) => `${instancePath || '/'} ${keyword}: ${message}`
);

const artifactPath = (root, relativePath) => (
  root instanceof URL
    ? new URL(relativePath, root)
    : resolve(root, relativePath)
);

const requiredManifestKeys = [
  'editorial_contract',
  'mcpwp_profile',
  'requires_theme',
  'mcpwp_plugin_required',
  'tested_mcpwp_plugin',
  'schemas',
  'formats',
  'roles'
];

const requiredRuleFiles = [
  'authorship-disclosure.md',
  'freshness-corrections.md',
  'seo-geo.md',
  'sources.md',
  'wordpress-handoff.md'
];

const manifestSchema = {
  type: 'object',
  additionalProperties: false,
  required: requiredManifestKeys,
  properties: {
    editorial_contract: { type: 'string', pattern: '^\\d+\\.\\d+\\.\\d+$' },
    mcpwp_profile: { type: 'string', pattern: '^\\d+\\.\\d+\\.\\d+$' },
    requires_theme: { type: 'string', minLength: 1 },
    mcpwp_plugin_required: { type: 'boolean' },
    tested_mcpwp_plugin: { type: 'string', pattern: '^\\d+\\.\\d+\\.\\d+$' },
    schemas: {
      type: 'array',
      minItems: 1,
      uniqueItems: true,
      items: { type: 'string', pattern: '^[a-z0-9]+(?:-[a-z0-9]+)*$' }
    },
    formats: {
      type: 'array',
      minItems: 1,
      uniqueItems: true,
      items: { type: 'string', pattern: '^[a-z0-9]+(?:-[a-z0-9]+)*$' }
    },
    roles: {
      type: 'array',
      minItems: 1,
      uniqueItems: true,
      items: { type: 'string', pattern: '^[a-z0-9]+(?:-[a-z0-9]+)*$' }
    }
  }
};

const json = async (root, relativePath) => JSON.parse(
  await readFile(artifactPath(root, relativePath), 'utf8')
);

const validatorFor = (schema) => {
  const ajv = new Ajv2020({ allErrors: true });
  addFormats(ajv);
  return ajv.compile(schema);
};

const schemaErrors = (validate, label) => normalizeErrors(validate.errors ?? [])
  .map((error) => `${label}: ${error}`);

const validateManifestShape = (manifest, label) => {
  const validate = validatorFor(manifestSchema);
  return validate(manifest) ? [] : schemaErrors(validate, label);
};

const compareInventory = async (root, directory, expectedFiles, errors) => {
  const existingFiles = await readdir(artifactPath(root, `${directory}/`)).catch(() => []);
  const existingCounts = new Map();
  for (const file of existingFiles) {
    existingCounts.set(file, (existingCounts.get(file) ?? 0) + 1);
  }

  for (const file of expectedFiles) {
    if ((existingCounts.get(file) ?? 0) !== 1) {
      errors.push(`manifest item has no matching file: ${directory}/${file}`);
    }
  }

  for (const file of existingFiles) {
    if (!expectedFiles.includes(file)) {
      errors.push(`manifest inventory contains an undeclared file: ${directory}/${file}`);
    }
  }
};

const validateManifestInventory = async (root, manifest) => {
  const errors = [];
  if (!Array.isArray(manifest.schemas) || !Array.isArray(manifest.formats) || !Array.isArray(manifest.roles)) {
    return errors;
  }

  await compareInventory(
    root,
    'editorial/schemas',
    manifest.schemas.map((name) => `${name}.schema.json`),
    errors
  );
  await compareInventory(
    root,
    'editorial/templates',
    manifest.formats.map((name) => `${name}.md`),
    errors
  );
  await compareInventory(
    root,
    'editorial/agents',
    manifest.roles.map((name) => `${name}.md`),
    errors
  );
  await compareInventory(root, 'editorial/rules', requiredRuleFiles, errors);
  return errors;
};

const validateDocumentVersions = async (root, manifest) => {
  const errors = [];
  for (const directory of ['editorial/agents', 'editorial/templates', 'editorial/rules']) {
    const files = await readdir(artifactPath(root, `${directory}/`)).catch(() => []);
    for (const file of files.filter((name) => name.endsWith('.md'))) {
      const content = await readFile(artifactPath(root, `${directory}/${file}`), 'utf8');
      const versions = [...content.matchAll(/^Contract version: (.+)$/gm)].map((match) => match[1]);
      for (const version of versions) {
        if (version !== manifest.editorial_contract) {
          errors.push(
            `${directory}/${file}: Contract version ${version} does not equal manifest ${manifest.editorial_contract}`
          );
        }
      }
      if (directory === 'editorial/rules' && versions.length !== 1) {
        errors.push(`${directory}/${file}: expected exactly one Contract version declaration`);
      }
    }
  }
  return errors;
};

const roleTransitionText = (transition) => transition.from === null
  ? ['from: null', `to: ${transition.to}`]
  : [`\`${transition.from}\` to \`${transition.to}\``];

const documentedHumanTransitions = async (root) => {
  const content = await readFile(
    artifactPath(root, 'editorial/rules/freshness-corrections.md'),
    'utf8'
  );
  const clause = content.match(/Only `human-editor` may perform (.+)\./)?.[1];
  if (!clause) return null;

  const [directClause] = clause.split(', or any post-publication transition');
  const transitions = [...directClause.matchAll(/`([^`]+)` to `([^`]+)`/g)]
    .map(([, from, to]) => ({ from, to, actor: 'human-editor', human_only: true }));
  const postPublication = clause.match(
    /post-publication transition from `([^`]+)` to `([^`]+)`, `([^`]+)`, or `([^`]+)`/
  );
  if (!postPublication) return null;

  const [, from, ...destinations] = postPublication;
  transitions.push(...destinations.map((to) => ({
    from,
    to,
    actor: 'human-editor',
    human_only: true
  })));
  return transitions;
};

const transitionKey = ({ from, to, actor, human_only: humanOnly }) => (
  `${String(from)}:${to}:${actor}:${String(humanOnly)}`
);

const validateWorkflow = async (root, manifest, validationReportSchema, workflow, label) => {
  const errors = [];
  const approvedStates = validationReportSchema?.$defs?.state?.enum;
  if (!Array.isArray(approvedStates)) {
    return [`${label}: validation-report schema does not declare the approved state list`];
  }

  const workflowSchema = {
    type: 'object',
    additionalProperties: false,
    required: ['states', 'transitions'],
    properties: {
      states: {
        type: 'array',
        uniqueItems: true,
        items: { enum: approvedStates }
      },
      transitions: {
        type: 'array',
        minItems: 1,
        uniqueItems: true,
        items: {
          type: 'object',
          additionalProperties: false,
          required: ['from', 'to', 'actor', 'human_only'],
          properties: {
            from: { anyOf: [{ enum: approvedStates }, { type: 'null' }] },
            to: { enum: approvedStates },
            actor: { type: 'string', minLength: 1 },
            human_only: { type: 'boolean' }
          }
        }
      }
    }
  };
  const validate = validatorFor(workflowSchema);
  if (!validate(workflow)) {
    return schemaErrors(validate, label);
  }

  if (JSON.stringify(workflow.states) !== JSON.stringify(approvedStates)) {
    errors.push(`${label}: states do not match the approved state list`);
  }

  for (const state of approvedStates) {
    if (!workflow.transitions.some(({ to }) => to === state)) {
      errors.push(`${label}: complete transition graph does not reach ${state}`);
    }
  }

  for (const transition of workflow.transitions) {
    const actorIsRole = manifest.roles.includes(transition.actor);
    if (actorIsRole && transition.human_only) {
      errors.push(`${label}: ${transition.actor} transition cannot be human_only`);
    }
    if (!actorIsRole && (transition.actor !== 'human-editor' || !transition.human_only)) {
      errors.push(`${label}: transition actor ${transition.actor} is neither a declared role nor human-editor`);
    }
    if (transition.to === 'published' && transition.actor !== 'human-editor') {
      errors.push(`${label}: non-human transition to published is prohibited`);
    }
  }

  const documentedHuman = await documentedHumanTransitions(root);
  if (documentedHuman === null) {
    errors.push(`${label}: could not read the documented human transition graph`);
  } else {
    const actualHumanKeys = workflow.transitions
      .filter(({ actor }) => actor === 'human-editor')
      .map(transitionKey)
      .sort();
    const documentedHumanKeys = documentedHuman.map(transitionKey).sort();
    if (JSON.stringify(actualHumanKeys) !== JSON.stringify(documentedHumanKeys)) {
      errors.push(`${label}: human transition graph does not match documented state machine`);
    }
  }

  for (const role of manifest.roles) {
    const ownedTransitions = workflow.transitions.filter(({ actor }) => actor === role);
    if (ownedTransitions.length !== 1) {
      errors.push(`${label}: role ${role} must own exactly one transition`);
      continue;
    }

    const roleFile = `editorial/agents/${role}.md`;
    const content = await readFile(artifactPath(root, roleFile), 'utf8').catch(() => '');
    const allowedSection = content.split('## Allowed transition\n')[1]?.split('\n## Stop conditions')[0] ?? '';
    for (const expectedText of roleTransitionText(ownedTransitions[0])) {
      if (!allowedSection.includes(expectedText)) {
        errors.push(`${roleFile}: allowed transition does not match workflow assignment ${expectedText}`);
      }
    }
  }

  return errors;
};

const artifactCompatibilityErrors = (artifact, manifest, label) => {
  const errors = [];
  if (typeof artifact.contract_version === 'string') {
    const artifactMajor = artifact.contract_version.split('.')[0];
    const manifestMajor = manifest.editorial_contract.split('.')[0];
    if (artifactMajor !== manifestMajor) {
      errors.push(
        `${label}: incompatible contract major ${artifact.contract_version}; active contract is ${manifest.editorial_contract}`
      );
    }
  }
  return errors;
};

const researchEvidenceErrors = (artifact, label) => (artifact.claims ?? []).flatMap(
  (claim, index) => claim?.source_url || claim?.evidence_reference
    ? []
    : [`${label}: claims/${index} material claim requires a source URL or evidence reference`]
);

const workflowAttemptErrors = (artifact, workflow, approvedStates, label) => {
  const schema = {
    type: 'object',
    additionalProperties: false,
    required: [
      'kind',
      'actor',
      'from_state',
      'to_state',
      'wordpress_mutation',
      'validation_report_status'
    ],
    properties: {
      kind: { const: 'workflow-attempt' },
      actor: { type: 'string', minLength: 1 },
      from_state: { anyOf: [{ enum: approvedStates }, { type: 'null' }] },
      to_state: { enum: approvedStates },
      wordpress_mutation: { type: 'boolean' },
      validation_report_status: { enum: ['pass', 'fail'] }
    }
  };
  const validate = validatorFor(schema);
  const errors = validate(artifact) ? [] : schemaErrors(validate, label);
  if (errors.length > 0) {
    return errors;
  }

  if (artifact.to_state === 'published' && artifact.actor !== 'human-editor') {
    errors.push(`${label}: non-human transition to published is prohibited`);
  }

  if (artifact.wordpress_mutation) {
    if (artifact.validation_report_status !== 'pass') {
      errors.push(`${label}: WordPress mutation requires a passing validation report`);
    }
    const actorOwnsTransition = workflow.transitions.some((transition) => (
      transition.actor === artifact.actor
      && transition.from === artifact.from_state
      && transition.to === artifact.to_state
    ));
    if (!actorOwnsTransition) {
      errors.push(`${label}: WordPress mutation actor does not own the workflow transition`);
    }
  }

  return errors;
};

const validateSchemaArtifact = async (
  root,
  manifest,
  workflow,
  approvedStates,
  schemaName,
  artifact,
  label
) => {
  const errors = artifactCompatibilityErrors(artifact, manifest, label);
  if (schemaName === 'content-brief' && !manifest.formats.includes(artifact.content_format)) {
    errors.push(`${label}: unknown format ${String(artifact.content_format)}`);
  }
  if (schemaName === 'research-packet') {
    errors.push(...researchEvidenceErrors(artifact, label));
  }

  const result = await validateArtifact(root, schemaName, artifact);
  errors.push(...result.errors.map((error) => `${label}: ${error}`));

  if (schemaName === 'validation-report' && result.valid) {
    const transition = workflow.transitions.find(({ from, to }) => (
      from === artifact.state_transition.from && to === artifact.state_transition.to
    ));
    if (!transition || transition.actor !== artifact.role) {
      errors.push(`${label}: validation report role does not own its workflow transition`);
    }
    if (!approvedStates.includes(artifact.state_transition.to)) {
      errors.push(`${label}: validation report uses an unapproved state`);
    }
  }
  return errors;
};

const fixtureType = (file, artifact) => {
  if (file.startsWith('content-brief-')) return 'content-brief';
  if (file.startsWith('research-packet')) return 'research-packet';
  if (file.startsWith('validation-report')) return 'validation-report';
  if (file.startsWith('manifest-')) return 'manifest';
  if (artifact?.kind === 'workflow-attempt') return 'workflow-attempt';
  return null;
};

const validateFixtureArtifact = async (
  root,
  manifest,
  workflow,
  validationReportSchema,
  file,
  artifact,
  label
) => {
  const type = fixtureType(file, artifact);
  if (manifest.schemas.includes(type)) {
    return validateSchemaArtifact(
      root,
      manifest,
      workflow,
      validationReportSchema.$defs.state.enum,
      type,
      artifact,
      label
    );
  }
  if (type === 'workflow-attempt') {
    return workflowAttemptErrors(
      artifact,
      workflow,
      validationReportSchema.$defs.state.enum,
      label
    );
  }
  if (type === 'manifest') {
    return [
      ...validateManifestShape(artifact, label),
      ...await validateManifestInventory(root, artifact)
    ];
  }
  return [`${label}: unknown example artifact type`];
};

export const validateArtifact = async (root, schemaName, artifact) => {
  const manifest = await json(root, 'editorial/manifest.json');

  if (!manifest.schemas.includes(schemaName)) {
    throw new Error(`Schema "${schemaName}" is not declared in editorial/manifest.json`);
  }

  const schema = await json(root, `editorial/schemas/${schemaName}.schema.json`);
  const validate = validatorFor(schema);
  const valid = validate(artifact);

  return {
    valid,
    errors: normalizeErrors(validate.errors ?? [])
  };
};

export const validateContract = async (root) => {
  const errors = [];
  let manifest;
  try {
    manifest = await json(root, 'editorial/manifest.json');
  } catch (error) {
    return { valid: false, errors: [`editorial/manifest.json: ${error.message}`] };
  }

  errors.push(...validateManifestShape(manifest, 'editorial/manifest.json'));
  if (errors.length > 0) {
    return { valid: false, errors };
  }

  errors.push(...await validateManifestInventory(root, manifest));
  errors.push(...await validateDocumentVersions(root, manifest));

  const validationReportSchema = await json(
    root,
    'editorial/schemas/validation-report.schema.json'
  );
  const workflow = await json(root, 'editorial/workflow.json');
  errors.push(...await validateWorkflow(
    root,
    manifest,
    validationReportSchema,
    workflow,
    'editorial/workflow.json'
  ));

  const validDirectory = 'editorial/examples/valid';
  const validFiles = (await readdir(artifactPath(root, `${validDirectory}/`)).catch(() => []))
    .filter((file) => file.endsWith('.json'))
    .sort();
  const validBriefFormats = new Set();
  const briefSlugs = new Set();
  let researchSlug = null;

  for (const file of validFiles) {
    const label = `${validDirectory}/${file}`;
    const artifact = await json(root, label);
    const fixtureErrors = await validateFixtureArtifact(
      root,
      manifest,
      workflow,
      validationReportSchema,
      file,
      artifact,
      label
    );
    errors.push(...fixtureErrors);
    if (fixtureErrors.length === 0 && fixtureType(file, artifact) === 'content-brief') {
      validBriefFormats.add(artifact.content_format);
      briefSlugs.add(artifact.canonical_slug);
    }
    if (fixtureErrors.length === 0 && fixtureType(file, artifact) === 'research-packet') {
      researchSlug = artifact.canonical_slug;
      if (!briefSlugs.has(artifact.canonical_slug)) {
        errors.push(`${label}: canonical_slug has no matching valid content brief`);
      }
    }
    if (fixtureErrors.length === 0 && fixtureType(file, artifact) === 'validation-report') {
      if (!briefSlugs.has(artifact.canonical_slug) || artifact.canonical_slug !== researchSlug) {
        errors.push(`${label}: canonical_slug does not match a valid brief and research packet`);
      }
      if (artifact.overall_status !== 'pass') {
        errors.push(`${label}: repository validation report must pass`);
      }
    }
  }

  for (const format of manifest.formats) {
    if (!validBriefFormats.has(format)) {
      errors.push(`editorial/examples/valid: no valid brief fixture for format ${format}`);
    }
  }
  for (const requiredFile of ['research-packet.json', 'validation-report.json']) {
    if (!validFiles.includes(requiredFile)) {
      errors.push(`${validDirectory}: missing ${requiredFile}`);
    }
  }

  const invalidDirectory = 'editorial/examples/invalid';
  const invalidFiles = (await readdir(artifactPath(root, `${invalidDirectory}/`)).catch(() => []))
    .filter((file) => file.endsWith('.json'))
    .sort();
  for (const file of invalidFiles) {
    const label = `${invalidDirectory}/${file}`;
    const fixture = await json(root, label);
    const expectedErrors = fixture._expected_errors;
    const artifact = structuredClone(fixture);
    delete artifact._expected_errors;

    if (!Array.isArray(expectedErrors) || expectedErrors.length === 0
      || expectedErrors.some((fragment) => typeof fragment !== 'string' || fragment.length === 0)) {
      errors.push(`${label}: _expected_errors must be a non-empty array of strings`);
      continue;
    }

    const actualErrors = await validateFixtureArtifact(
      root,
      manifest,
      workflow,
      validationReportSchema,
      file,
      artifact,
      label
    );
    if (actualErrors.length === 0) {
      errors.push(`${label}: invalid fixture unexpectedly passed validation`);
      continue;
    }
    for (const expectedError of expectedErrors) {
      if (!actualErrors.some((actualError) => actualError.includes(expectedError))) {
        errors.push(`${label}: expected error fragment not found: ${expectedError}`);
      }
    }
  }

  return { valid: errors.length === 0, errors };
};
