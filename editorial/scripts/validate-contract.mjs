#!/usr/bin/env node
import process from 'node:process';
import { validateContract } from './contract-lib.mjs';

const root = new URL('../../', import.meta.url);
const result = await validateContract(root);
if (!result.valid) {
  process.stderr.write(`${result.errors.join('\n')}\n`);
  process.exitCode = 1;
} else {
  process.stdout.write('Editorial Contract 1.0.0 is valid.\n');
}
