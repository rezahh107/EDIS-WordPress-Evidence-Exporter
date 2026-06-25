import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';

const roots = process.argv.slice(2);
const files = [];
function walk(path) {
  const st = statSync(path);
  if (st.isDirectory()) {
    for (const entry of readdirSync(path).sort()) walk(join(path, entry));
    return;
  }
  if (path.endsWith('.js')) files.push(path);
}
for (const root of roots) walk(root);
let failed = false;
for (const file of files) {
  const text = readFileSync(file, 'utf8');
  const result = spawnSync(process.execPath, ['--check', file], { encoding: 'utf8' });
  if (result.status !== 0) {
    process.stderr.write(result.stderr || result.stdout);
    failed = true;
  }
  for (const pattern of [/eval\s*\(/, /new\s+Function\s*\(/]) {
    if (pattern.test(text)) {
      process.stderr.write(`${file}: forbidden JavaScript pattern ${pattern}\n`);
      failed = true;
    }
  }
}
console.log(JSON.stringify({ state: failed ? 'FAIL' : 'PASS', js_files: files.length }));
process.exit(failed ? 1 : 0);
