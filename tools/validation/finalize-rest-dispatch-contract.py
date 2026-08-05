#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding='utf-8', newline='\n')


def replace_once(path: str, old: str, new: str) -> None:
    content = read(path)
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f'{path}: expected one replacement, found {count}')
    write(path, content.replace(old, new, 1))


integration = 'tests/integration/canonical-diagnostic-rest.php'
replace_once(
    integration,
    """function edis_rest_serve(\\WP_REST_Server $server, string $method, string $route, array $query = []): string
{""",
    """/** @param \\WP_REST_Response|\\WP_Error $response */
function edis_rest_response_status($response): int
{
    if ($response instanceof \\WP_Error) {
        $data = $response->get_error_data();
        return is_array($data) ? (int) ($data['status'] ?? 0) : 0;
    }
    return $response instanceof \\WP_REST_Response ? $response->get_status() : 0;
}

/** @param \\WP_REST_Response|\\WP_Error $response */
function edis_rest_response_code($response): string
{
    if ($response instanceof \\WP_Error) {
        return (string) $response->get_error_code();
    }
    if (!$response instanceof \\WP_REST_Response) {
        return '';
    }
    $data = $response->get_data();
    return is_array($data) && is_string($data['code'] ?? null) ? $data['code'] : '';
}

function edis_rest_serve(\\WP_REST_Server $server, string $method, string $route, array $query = []): string
{""",
)

replace_once(
    integration,
    """edis_rest_assert($envelope instanceof \\WP_Error && $envelope->get_error_code() === 'edis_diagnostic_envelope_unsupported' && (int) ($envelope->get_error_data()['status'] ?? 0) === 406, 'Authorized canonical _envelope request was not rejected deterministically.', 18);""",
    """edis_rest_assert(edis_rest_response_code($envelope) === 'edis_diagnostic_envelope_unsupported' && edis_rest_response_status($envelope) === 406, 'Authorized canonical _envelope request was not rejected deterministically.', 18);""",
)
replace_once(
    integration,
    """edis_rest_assert($forbidden instanceof \\WP_Error && (int) ($forbidden->get_error_data()['status'] ?? 0) === 403, 'Capability denial did not precede canonical envelope handling.', 23);""",
    """edis_rest_assert(edis_rest_response_status($forbidden) === 403, 'Capability denial did not precede canonical envelope handling.', 23);""",
)
replace_once(
    integration,
    """edis_rest_assert($wrongOwner instanceof \\WP_Error && (int) ($wrongOwner->get_error_data()['status'] ?? 0) === 404, 'Wrong owner was not hidden by 404 before envelope handling.', 24);""",
    """edis_rest_assert(edis_rest_response_status($wrongOwner) === 404, 'Wrong owner was not hidden by 404 before envelope handling.', 24);""",
)
replace_once(
    integration,
    """edis_rest_assert($missing instanceof \\WP_Error && (int) ($missing->get_error_data()['status'] ?? 0) === 404, 'Nonexistent diagnostic did not retain bounded 404.', 25);""",
    """edis_rest_assert(edis_rest_response_status($missing) === 404, 'Nonexistent diagnostic did not retain bounded 404.', 25);""",
)
replace_once(
    integration,
    """edis_rest_assert($expired instanceof \\WP_Error && (int) ($expired->get_error_data()['status'] ?? 0) === 404, 'Expired diagnostic did not retain bounded 404.', 26);""",
    """edis_rest_assert(edis_rest_response_status($expired) === 404, 'Expired diagnostic did not retain bounded 404.', 26);""",
)
replace_once(
    integration,
    """edis_rest_assert($revoked instanceof \\WP_Error && (int) ($revoked->get_error_data()['status'] ?? 0) === 404, 'Revoked document access did not retain bounded 404.', 29);""",
    """edis_rest_assert(edis_rest_response_status($revoked) === 404, 'Revoked document access did not retain bounded 404.', 29);""",
)

manifest_path = ROOT / 'plugin.manifest.json'
manifest = json.loads(manifest_path.read_text(encoding='utf-8'))
context_path = 'tests/integration/rest-request-context.php'
manifest['files'] = [
    entry for entry in manifest.get('files', [])
    if not (isinstance(entry, dict) and entry.get('path') == context_path)
]
manifest['files'] = sorted(manifest['files'], key=lambda entry: str(entry.get('path', '')))
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
    '.github/workflows/finalize-rest-dispatch-contract.yml',
    'tools/validation/finalize-rest-dispatch-contract.py',
]:
    target = ROOT / temporary
    if target.exists():
        target.unlink()

print('EDIS REST dispatch contract finalization: APPLIED')
