import { readFile } from 'node:fs/promises';
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

export const validateArtifact = async (root, schemaName, artifact) => {
  const manifest = JSON.parse(
    await readFile(artifactPath(root, 'editorial/manifest.json'), 'utf8')
  );

  if (!manifest.schemas.includes(schemaName)) {
    throw new Error(`Schema "${schemaName}" is not declared in editorial/manifest.json`);
  }

  const schema = JSON.parse(
    await readFile(
      artifactPath(root, `editorial/schemas/${schemaName}.schema.json`),
      'utf8'
    )
  );
  const ajv = new Ajv2020({ allErrors: true });
  addFormats(ajv);
  const validate = ajv.compile(schema);
  const valid = validate(artifact);

  return {
    valid,
    errors: normalizeErrors(validate.errors ?? [])
  };
};
