import { createHash } from 'node:crypto';
import { readdir, readFile, realpath } from 'node:fs/promises';
import { isAbsolute, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

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

const filesystemRoot = (root) => (
  root instanceof URL ? fileURLToPath(root) : resolve(root)
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

const sourceDirectories = [
  'editorial/schemas',
  'editorial/templates',
  'editorial/agents',
  'editorial/rules'
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

const listContractSourceFiles = async (root) => {
  const files = ['editorial/manifest.json', 'editorial/workflow.json'];
  for (const directory of sourceDirectories) {
    const names = await readdir(artifactPath(root, `${directory}/`));
    files.push(...names.sort().map((name) => `${directory}/${name}`));
  }
  return files.sort();
};

const validatorFor = (schema) => {
  const ajv = new Ajv2020({ allErrors: true });
  addFormats(ajv);
  return ajv.compile(schema);
};

export const loadManifest = async (root) => json(root, 'editorial/manifest.json');

export const createValidators = async (root) => {
  const manifest = await loadManifest(root);
  const validators = new Map();
  for (const schemaName of manifest.schemas) {
    const schema = await json(root, `editorial/schemas/${schemaName}.schema.json`);
    validators.set(schemaName, validatorFor(schema));
  }
  return validators;
};

export const contractSourceHash = async (root) => {
  const hash = createHash('sha256');
  for (const file of await listContractSourceFiles(root)) {
    hash.update(file);
    hash.update('\0');
    hash.update(await readFile(artifactPath(root, file)));
    hash.update('\0');
  }
  return hash.digest('hex');
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

const markdownSection = (content, heading, nextHeading = null) => {
  const start = content.indexOf(`${heading}\n`);
  if (start === -1) return '';
  const bodyStart = start + heading.length + 1;
  const end = nextHeading ? content.indexOf(`\n${nextHeading}\n`, bodyStart) : content.length;
  return content.slice(bodyStart, end === -1 ? content.length : end).trim();
};

const authorityDeclarations = [
  {
    file: 'editorial/rules/wordpress-handoff.md',
    nextSection: '## Fail closed',
    declaration: '`human-editor only: publication, scheduling, redirects, deletions, canonical changes, exceptions`'
  },
  {
    file: 'editorial/rules/freshness-corrections.md',
    nextSection: '## Fail closed',
    declaration: '`human-editor only: redirects, deletions, canonical changes, retirement, public correction decisions`'
  },
  {
    file: 'editorial/rules/authorship-disclosure.md',
    declaration: '`human-editor only: commercial conclusions`'
  }
];

const validateHumanAuthorityDeclarations = async (root) => {
  const errors = [];
  for (const { file, nextSection, declaration } of authorityDeclarations) {
    const content = await readFile(artifactPath(root, file), 'utf8');
    const body = markdownSection(content, '## Human-only authority', nextSection);
    if (body !== declaration) {
      errors.push(`${file}: Human-only authority section must be one canonical line`);
    }
  }
  return errors;
};

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
  const approvedRoles = validationReportSchema?.$defs?.role?.enum;
  const approvedGates = validationReportSchema?.properties?.gate_results
    ?.items?.properties?.gate?.enum;
  if (!Array.isArray(approvedStates)) {
    return [`${label}: validation-report schema does not declare the approved state list`];
  }
  if (!Array.isArray(approvedRoles) || !Array.isArray(approvedGates)) {
    return [`${label}: validation-report schema does not declare approved roles and gates`];
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
          required: [
            'from',
            'to',
            'actor',
            'human_only',
            'required_gates',
            'next_allowed_role'
          ],
          properties: {
            from: { anyOf: [{ enum: approvedStates }, { type: 'null' }] },
            to: { enum: approvedStates },
            actor: { enum: approvedRoles },
            human_only: { type: 'boolean' },
            required_gates: {
              type: 'array',
              minItems: 1,
              uniqueItems: true,
              items: { enum: approvedGates }
            },
            next_allowed_role: { enum: approvedRoles }
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

  const transitionEdges = new Set();
  for (const transition of workflow.transitions) {
    const edge = `${String(transition.from)}:${transition.to}`;
    if (transitionEdges.has(edge)) {
      errors.push(`${label}: workflow edge ${edge} must be unique`);
    }
    transitionEdges.add(edge);

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
    if (transition.human_only && !transition.required_gates.includes('human')) {
      errors.push(`${label}: human-only transition ${edge} requires the human gate`);
    }
    if (!transition.human_only && transition.required_gates.includes('human')) {
      errors.push(`${label}: small-agent transition ${edge} cannot require the human gate`);
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
    const allowedSection = markdownSection(
      content,
      '## Allowed transition',
      '## Stop conditions'
    );
    const declaration = allowedSection.match(
      /^`transition: (null|[a-z_]+) -> ([a-z_]+)`$/
    );
    if (!declaration) {
      errors.push(`${roleFile}: Allowed transition section must be one canonical line`);
      continue;
    }

    const assignedTransition = ownedTransitions[0];
    const declaredFrom = declaration[1] === 'null' ? null : declaration[1];
    const declaredTo = declaration[2];
    if (declaredFrom !== assignedTransition.from || declaredTo !== assignedTransition.to) {
      errors.push(`${roleFile}: allowed transition does not exactly match workflow assignment`);
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

const authorizedWordPressDraftFields = [
  'title',
  'content',
  'excerpt',
  'featured_media',
  'categories',
  'tags'
];

const validationReportReferenceSchema = {
  type: 'object',
  additionalProperties: false,
  required: ['path', 'sha256'],
  properties: {
    path: {
      type: 'string',
      pattern: '^editorial/(?:[a-z0-9][a-z0-9._-]*/)*validation-report(?:-[a-z0-9][a-z0-9-]*)?\\.json$'
    },
    sha256: {
      type: 'string',
      pattern: '^[a-f0-9]{64}$'
    }
  }
};

const workflowAttemptErrors = (artifact, manifest, workflow, approvedStates, label) => {
  const schema = {
    type: 'object',
    additionalProperties: false,
    required: [
      'kind',
      'actor',
      'from_state',
      'to_state',
      'wordpress_operation',
      'validation_report_ref'
    ],
    properties: {
      kind: { const: 'workflow-attempt' },
      actor: { type: 'string', minLength: 1 },
      from_state: { anyOf: [{ enum: approvedStates }, { type: 'null' }] },
      to_state: { enum: approvedStates },
      wordpress_operation: { enum: ['none', 'create-draft', 'update-draft'] },
      wordpress_target: {
        type: 'object',
        additionalProperties: false,
        required: ['canonical_slug', 'authorized_fields'],
        properties: {
          canonical_slug: {
            type: 'string',
            minLength: 1,
            pattern: '^[a-z0-9]+(?:-[a-z0-9]+)*$'
          },
          authorized_fields: {
            type: 'array',
            minItems: 1,
            uniqueItems: true,
            items: { enum: authorizedWordPressDraftFields }
          }
        }
      },
      validation_report_ref: validationReportReferenceSchema
    },
    allOf: [
      {
        if: {
          required: ['wordpress_operation'],
          properties: { wordpress_operation: { const: 'none' } }
        },
        then: { not: { required: ['wordpress_target'] } },
        else: { required: ['wordpress_target'] }
      }
    ]
  };
  const validate = validatorFor(schema);
  const errors = validate(artifact) ? [] : schemaErrors(validate, label);
  if (artifact.wordpress_operation === 'none' && artifact.wordpress_target !== undefined) {
    errors.push(`${label}: wordpress_target must NOT be valid when wordpress_operation is none`);
  }
  if (errors.length > 0) {
    return errors;
  }

  const transitions = Array.isArray(workflow?.transitions) ? workflow.transitions : [];
  const transition = transitions.find((candidate) => (
    candidate?.from === artifact.from_state && candidate?.to === artifact.to_state
  ));
  if (transition?.human_only) {
    errors.push(`${label}: workflow attempts cannot represent human-only transitions`);
  }
  if (!Array.isArray(manifest?.roles)
    || !manifest.roles.includes(artifact.actor)
    || !transition
    || transition.actor !== artifact.actor) {
    errors.push(`${label}: workflow attempt actor does not own the workflow transition`);
  }

  if (artifact.wordpress_operation !== 'none'
    && (
      artifact.actor !== 'writer'
      || artifact.from_state !== 'research_accepted'
      || artifact.to_state !== 'drafting'
    )) {
    errors.push(
      `${label}: only writer may perform a WordPress draft operation on research_accepted -> drafting`
    );
  }

  return errors;
};

const validationReportWorkflowErrors = (artifact, workflow, approvedStates, label) => {
  const errors = [];
  const transitions = Array.isArray(workflow?.transitions) ? workflow.transitions : [];
  const transition = transitions.find((candidate) => (
    candidate?.from === artifact.state_transition.from
      && candidate?.to === artifact.state_transition.to
  ));

  if (!transition || transition.actor !== artifact.role) {
    errors.push(`${label}: validation report role does not own its exact workflow transition`);
  }
  if (!approvedStates.includes(artifact.state_transition.to)) {
    errors.push(`${label}: validation report uses an unapproved state`);
  }

  const gateCounts = new Map();
  for (const { gate } of artifact.gate_results) {
    gateCounts.set(gate, (gateCounts.get(gate) ?? 0) + 1);
  }
  for (const [gate, count] of gateCounts) {
    if (count > 1) {
      errors.push(`${label}: duplicate gate identifier ${gate}`);
    }
  }

  if (!transition || !Array.isArray(transition.required_gates)) {
    return errors;
  }

  for (const requiredGate of transition.required_gates) {
    const result = artifact.gate_results.find(({ gate }) => gate === requiredGate);
    if (!result) {
      errors.push(`${label}: missing required gate ${requiredGate}`);
    } else if (result.status !== 'pass') {
      errors.push(`${label}: required gate ${requiredGate} must pass`);
    }
  }

  for (const { gate } of artifact.gate_results) {
    if (!transition.required_gates.includes(gate)) {
      errors.push(`${label}: unexpected gate ${gate} is not required by the workflow transition`);
    }
  }

  if (artifact.next_allowed_role !== transition.next_allowed_role) {
    errors.push(`${label}: next_allowed_role does not match workflow transition`);
  }

  return errors;
};

const referencedReportPath = (root, referencePath) => {
  const rootPath = filesystemRoot(root);
  const candidate = resolve(rootPath, referencePath);
  const relativePath = relative(rootPath, candidate);
  if (relativePath === '..'
    || relativePath.startsWith(`..${sep}`)
    || isAbsolute(relativePath)) {
    throw new Error('validation report path must remain inside the contract root');
  }
  return candidate;
};

const pathEscapesRoot = (rootPath, candidate) => {
  const relativePath = relative(rootPath, candidate);
  return relativePath === '..'
    || relativePath.startsWith(`..${sep}`)
    || isAbsolute(relativePath);
};

const validateWorkflowAttemptBinding = async (
  root,
  artifact,
  manifest,
  workflow,
  validationReportSchema,
  label
) => {
  const approvedStates = validationReportSchema?.$defs?.state?.enum ?? [];
  const errors = workflowAttemptErrors(
    artifact,
    manifest,
    workflow,
    approvedStates,
    label
  );
  if (errors.length > 0) {
    return errors;
  }

  let reportFile;
  try {
    reportFile = referencedReportPath(root, artifact.validation_report_ref.path);
  } catch (error) {
    return [`${label}: invalid validation_report_ref path: ${error.message}`];
  }

  let canonicalRoot;
  try {
    [canonicalRoot, reportFile] = await Promise.all([
      realpath(filesystemRoot(root)),
      realpath(reportFile)
    ]);
  } catch (error) {
    return [`${label}: could not read referenced validation report: ${error.message}`];
  }
  if (pathEscapesRoot(canonicalRoot, reportFile)) {
    return [`${label}: validation report symlink resolves outside the contract root`];
  }

  let reportBytes;
  try {
    reportBytes = await readFile(reportFile);
  } catch (error) {
    return [`${label}: could not read referenced validation report: ${error.message}`];
  }

  const actualHash = createHash('sha256').update(reportBytes).digest('hex');
  if (actualHash !== artifact.validation_report_ref.sha256) {
    return [`${label}: validation report SHA-256 does not match exact file bytes`];
  }

  let report;
  try {
    report = JSON.parse(reportBytes.toString('utf8'));
  } catch (error) {
    return [`${label}: referenced validation report is not valid JSON: ${error.message}`];
  }

  const validateReport = validatorFor(validationReportSchema);
  if (!validateReport(report)) {
    return schemaErrors(validateReport, `${label}: referenced validation report`);
  }

  errors.push(...validationReportWorkflowErrors(
    report,
    workflow,
    approvedStates,
    `${label}: referenced validation report`
  ));
  if (report.contract_version !== manifest.editorial_contract) {
    errors.push(`${label}: bound validation report contract_version does not match active contract`);
  }
  if (report.role !== artifact.actor) {
    errors.push(`${label}: bound validation report role does not match workflow attempt actor`);
  }
  if (report.state_transition.from !== artifact.from_state
    || report.state_transition.to !== artifact.to_state) {
    errors.push(`${label}: bound validation report transition does not match workflow attempt edge`);
  }
  if (artifact.wordpress_target
    && report.canonical_slug !== artifact.wordpress_target.canonical_slug) {
    errors.push(`${label}: bound validation report canonical_slug does not match WordPress target`);
  }
  if (report.overall_status !== 'pass') {
    errors.push(`${label}: bound validation report overall_status must be pass`);
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
    errors.push(...validationReportWorkflowErrors(artifact, workflow, approvedStates, label));
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
    return validateWorkflowAttemptBinding(
      root,
      artifact,
      manifest,
      workflow,
      validationReportSchema,
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
  const manifest = await loadManifest(root);

  if (!manifest.schemas.includes(schemaName)) {
    throw new Error(`Schema "${schemaName}" is not declared in editorial/manifest.json`);
  }

  const validators = await createValidators(root);
  const validate = validators.get(schemaName);
  const valid = validate(artifact);

  return {
    valid,
    errors: normalizeErrors(validate.errors ?? [])
  };
};

export const validateWorkflowAttempt = async (root, artifact) => {
  const label = 'workflow-attempt';
  try {
    const [manifest, workflow, validationReportSchema] = await Promise.all([
      loadManifest(root),
      json(root, 'editorial/workflow.json'),
      json(root, 'editorial/schemas/validation-report.schema.json')
    ]);
    const errors = await validateWorkflowAttemptBinding(
      root,
      artifact,
      manifest,
      workflow,
      validationReportSchema,
      label
    );
    return { valid: errors.length === 0, errors };
  } catch (error) {
    return { valid: false, errors: [`${label}: ${error.message}`] };
  }
};

export const validateContract = async (root) => {
  const errors = [];
  let manifest;
  try {
    manifest = await loadManifest(root);
  } catch (error) {
    return { valid: false, errors: [`editorial/manifest.json: ${error.message}`] };
  }

  errors.push(...validateManifestShape(manifest, 'editorial/manifest.json'));
  if (errors.length > 0) {
    return { valid: false, errors };
  }

  errors.push(...await validateManifestInventory(root, manifest));
  errors.push(...await validateDocumentVersions(root, manifest));
  errors.push(...await validateHumanAuthorityDeclarations(root));

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
  let reportSlug = null;

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
      reportSlug = artifact.canonical_slug;
      if (!briefSlugs.has(artifact.canonical_slug) || artifact.canonical_slug !== researchSlug) {
        errors.push(`${label}: canonical_slug does not match a valid brief and research packet`);
      }
      if (artifact.overall_status !== 'pass') {
        errors.push(`${label}: repository validation report must pass`);
      }
    }
    if (fixtureErrors.length === 0
      && fixtureType(file, artifact) === 'workflow-attempt'
      && artifact.wordpress_operation !== 'none') {
      const targetSlug = artifact.wordpress_target.canonical_slug;
      if (!briefSlugs.has(targetSlug)
        || targetSlug !== researchSlug
        || targetSlug !== reportSlug) {
        errors.push(
          `${label}: wordpress_target canonical_slug does not match a valid brief, research packet, and validation report`
        );
      }
    }
  }

  for (const format of manifest.formats) {
    if (!validBriefFormats.has(format)) {
      errors.push(`editorial/examples/valid: no valid brief fixture for format ${format}`);
    }
  }
  for (const requiredFile of [
    'research-packet.json',
    'validation-report.json',
    'workflow-attempt.json'
  ]) {
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
