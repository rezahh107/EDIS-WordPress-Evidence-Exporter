import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const args = process.argv.slice(2);
const root = args[0] && !args[0].startsWith('--') ? args[0] : '.github/workflows';
const policyIndex = args.indexOf('--policy');
const policyPath = policyIndex >= 0 ? args[policyIndex + 1] : 'tools/ci/github-actions-policy.json';
const strict = args.includes('--strict-sha');
const files = [];
function walk(path) {
  const st = statSync(path);
  if (st.isDirectory()) {
    for (const entry of readdirSync(path).sort()) walk(join(path, entry));
    return;
  }
  if (path.endsWith('.yml') || path.endsWith('.yaml')) files.push(path);
}
walk(root);

let policy = { rolling_refs: [] };
if (!strict && existsSync(policyPath)) {
  policy = JSON.parse(readFileSync(policyPath, 'utf8'));
}
const accepted = new Map();
for (const item of policy.rolling_refs ?? []) {
  if (item && item.action && item.ref && item.state === 'documented_rolling_major_ref' && item.immutable === false && item.evidence && item.reason && item.review_cadence) {
    accepted.set(`${item.action}@${item.ref}`, item);
  }
}

let failed = false;
let shaPinned = 0;
let documentedRolling = 0;
const actionRef = /uses:\s*([A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+)@([^\s#]+)/g;
const forbiddenMutable = new Set(['main', 'master', 'latest', 'HEAD']);
for (const file of files) {
  const text = readFileSync(file, 'utf8');
  for (const match of text.matchAll(actionRef)) {
    const action = match[1];
    const ref = match[2].replace(/["']/g, '');
    if (/^[a-f0-9]{40}$/.test(ref)) {
      shaPinned++;
      continue;
    }
    if (forbiddenMutable.has(ref)) {
      process.stderr.write(`${file}: action ${action} uses forbidden mutable ref ${ref}.\n`);
      failed = true;
      continue;
    }
    const key = `${action}@${ref}`;
    if (!strict && accepted.has(key)) {
      documentedRolling++;
      continue;
    }
    process.stderr.write(`${file}: action ${action} is not pinned to a full 40-character commit SHA and has no documented rolling-ref policy (${ref}).\n`);
    failed = true;
  }
}
const state = failed ? 'FAIL' : (documentedRolling > 0 ? 'PASS_WITH_DOCUMENTED_ROLLING_REFS' : 'PASS');
console.log(JSON.stringify({ state, workflow_files: files.length, sha_pinned: shaPinned, documented_rolling_refs: documentedRolling, strict_sha: strict }));
process.exit(failed ? 1 : 0);
