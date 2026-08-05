#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

manifest_path = ROOT / 'plugin.manifest.json'
manifest = json.loads(manifest_path.read_text(encoding='utf-8'))
files = manifest.get('files', [])
if not isinstance(files, list):
    raise RuntimeError('plugin.manifest.json files must be a list')
context_path = 'tests/integration/rest-request-context.php'
by_path = {
    entry.get('path'): entry
    for entry in files
    if isinstance(entry, dict) and isinstance(entry.get('path'), str)
}
if context_path not in by_path:
    files.append({
        'installable': False,
        'mode': '0644',
        'path': context_path,
        'required': True,
        'template': '',
        'type': 'php',
    })
else:
    entry = by_path[context_path]
    entry.update({
        'installable': False,
        'mode': '0644',
        'required': True,
        'template': '',
        'type': 'php',
    })
manifest['files'] = sorted(files, key=lambda entry: str(entry.get('path', '')))
manifest_path.write_text(
    json.dumps(manifest, ensure_ascii=False, sort_keys=True, separators=(',', ':')),
    encoding='utf-8',
    newline='\n',
)

critical_path = ROOT / 'config/critical-files.json'
critical = json.loads(critical_path.read_text(encoding='utf-8'))
for relative in sorted(critical.get('files', {})):
    file_path = ROOT / relative
    if not file_path.is_file() or file_path.is_symlink():
        raise RuntimeError(f'Critical authority path is missing or redirected: {relative}')
    critical['files'][relative] = 'sha256:' + hashlib.sha256(file_path.read_bytes()).hexdigest()
critical_path.write_text(
    json.dumps(critical, ensure_ascii=False, sort_keys=True, separators=(',', ':')),
    encoding='utf-8',
    newline='\n',
)

for temporary in [
    '.github/workflows/finalize-rest-context-inventory.yml',
    'tools/validation/finalize-rest-context-inventory.py',
]:
    target = ROOT / temporary
    if target.exists():
        target.unlink()

print('EDIS REST context inventory finalization: APPLIED')
