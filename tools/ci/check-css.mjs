import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
const roots = process.argv.slice(2);
const files = [];
function walk(path) {
  const st = statSync(path);
  if (st.isDirectory()) {
    for (const entry of readdirSync(path).sort()) walk(join(path, entry));
    return;
  }
  if (path.endsWith('.css')) files.push(path);
}
for (const root of roots) walk(root);
let failed = false;
for (const file of files) {
  const text = readFileSync(file, 'utf8');
  let balance = 0;
  for (const ch of text) {
    if (ch === '{') balance++;
    if (ch === '}') balance--;
    if (balance < 0) failed = true;
  }
  if (balance !== 0) {
    process.stderr.write(`${file}: unbalanced CSS braces\n`);
    failed = true;
  }
  if (/expression\s*\(/i.test(text) || /url\s*\(\s*javascript:/i.test(text)) {
    process.stderr.write(`${file}: forbidden CSS executable pattern\n`);
    failed = true;
  }
}
console.log(JSON.stringify({ state: failed ? 'FAIL' : 'PASS', css_files: files.length }));
process.exit(failed ? 1 : 0);
