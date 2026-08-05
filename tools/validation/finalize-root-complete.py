#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.write_text(content, encoding='utf-8', newline='\n')


def replace_once(path: str, old: str, new: str) -> None:
    content = read(path)
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f'{path}: expected one replacement, found {count}')
    write(path, content.replace(old, new, 1))


replace_once(
    'tests/Unit/CanonicalDiagnosticRecordTest.php',
    """        $javascript = (string) file_get_contents($root . 'assets/js/diagnostics.js');
        $template = (string) file_get_contents($root . 'templates/admin/diagnostics.php');
""",
    """        $javascript = (string) file_get_contents($root . 'assets/js/diagnostics.js');
        $adminBind = (string) file_get_contents($root . 'assets/js/admin-bind.js');
        $template = (string) file_get_contents($root . 'templates/admin/diagnostics.php');
""",
)
replace_once(
    'tests/Unit/CanonicalDiagnosticRecordTest.php',
    "        self::assertStringContainsString('copy-canonical-diagnostic', $javascript);",
    "        self::assertStringContainsString('copy-canonical-diagnostic', $adminBind);",
)

replace_once(
    'tests/Unit/DiagnosticFollowupRepairTest.php',
    """        $controller = (string) file_get_contents($root . 'src/Rest/DiagnosticsController.php');
        $exportController = (string) file_get_contents($root . 'src/Rest/DiagnosticExportJobController.php');
""",
    """        $controller = (string) file_get_contents($root . 'src/Rest/DiagnosticsController.php');
        $adapter = (string) file_get_contents($root . 'src/Rest/CanonicalDiagnosticResponseAdapter.php');
        $exportController = (string) file_get_contents($root . 'src/Rest/DiagnosticExportJobController.php');
""",
)
replace_once(
    'tests/Unit/DiagnosticFollowupRepairTest.php',
    "        self::assertStringContainsString('rest_pre_serve_request', $controller);",
    "        self::assertStringContainsString('rest_pre_serve_request', $adapter);",
)

worker_method = r'''    public function testAutomaticWorkerUsesCursorOccurrenceIdentityForDeduplicationAndRepetition(): void
    {
        $root = $this->tempRoot();
        [$worker, , $jobs, $inputs] = $this->service($root);
        $jobId = '12345678-1234-4234-8234-123456789abc';
        $this->persistJob($jobs, $inputs, $jobId, [
            'status' => 'queued',
            'phase' => 'collecting',
            'current_component' => 'environment',
            'next_retry_at' => time() + 3600,
        ]);
        $filesystem = new DeterministicFilesystem();
        $store = new DiagnosticRecordStore($root . '/diagnostics', $filesystem, 3600);
        $records = new DiagnosticRecordService($store, $jobs, $this->realPluginRoot(), $filesystem);
        $mode = 'initial';
        $errorCode = 'EDIS_EXPORT_ADVANCE_FAILED';
        $cycles = 0;
        $runner = new DiagnosticWorkerRunner(
            $worker,
            $jobs,
            $records,
            static function (string $id) use ($jobs, &$mode, &$errorCode, &$cycles): void {
                if ($mode === 'unchanged') {
                    return;
                }
                $job = $jobs->get($id);
                if (!is_array($job)) {
                    return;
                }
                $cycles++;
                $job['status'] = 'failed';
                $job['phase'] = 'failed';
                $job['current_component'] = 'environment';
                $job['last_error_code'] = $errorCode;
                $job['last_error_at'] = time() + $cycles;
                $job['diagnostics'][] = [
                    'code' => $errorCode,
                    'severity' => 'ERROR',
                    'scope' => 'OPERATIONAL',
                    'message_key' => 'diagnostic.export.advance_failed',
                    'context' => [
                        'failure_phase' => $mode === 'same-code-new-occurrence'
                            ? 'same_code_new_occurrence'
                            : 'failed',
                    ],
                ];
                $jobs->save($job);
            },
        );

        $runner->process($jobId);
        $mode = 'unchanged';
        $runner->process($jobId);
        $runner->process($jobId);
        $paths = glob($root . '/diagnostics/user-7/edis-diag-*.json') ?: [];
        self::assertCount(1, $paths);

        $mode = 'same-code-new-occurrence';
        $runner->process($jobId);
        $paths = glob($root . '/diagnostics/user-7/edis-diag-*.json') ?: [];
        self::assertCount(2, $paths);

        $mode = 'changed-code';
        $errorCode = 'EDIS_CHANGED_FAILURE_CODE';
        $runner->process($jobId);
        $paths = glob($root . '/diagnostics/user-7/edis-diag-*.json') ?: [];
        self::assertCount(3, $paths);

        $persisted = $jobs->get($jobId);
        self::assertIsArray($persisted);
        self::assertGreaterThan(time(), (int) $persisted['next_retry_at']);
        $codes = [];
        $phases = [];
        foreach ($paths as $path) {
            $record = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($jobId, $record['operation_identity']['job_id']);
            self::assertSame(
                'EDIS_RULE_CURRENT_OCCURRENCE_FROM_CHANGED_PERSISTED_TRANSITION',
                $record['plugin_classification'][0]['rule_id'],
            );
            $codes[] = $record['failure_classification']['internal_code'];
            $phases[] = $record['safe_context']['failure_phase'] ?? null;
        }
        sort($codes, SORT_STRING);
        self::assertSame(
            ['EDIS_CHANGED_FAILURE_CODE', 'EDIS_EXPORT_ADVANCE_FAILED', 'EDIS_EXPORT_ADVANCE_FAILED'],
            $codes,
        );
        self::assertContains('same_code_new_occurrence', $phases);
    }

'''
path = 'tests/Unit/RootCompleteRepairTest.php'
content = read(path)
pattern = r"    public function testEquivalentAutomaticWorkerFailuresEmitOnceAndChangedSignatureEmitsAgain\(\): void\n    \{.*?\n    \}\n\n(?=    public function testSafeWorkerPostCreateFailureBindsExactJobDespiteCompetingNewerJob)"
updated, count = re.subn(pattern, worker_method, content, count=1, flags=re.S)
if count != 1:
    raise RuntimeError(f'{path}: worker test replacement count={count}')
write(path, updated)

manifest_path = ROOT / 'plugin.manifest.json'
manifest = json.loads(manifest_path.read_text(encoding='utf-8'))
manifest['admin']['assets'] = [
    {'dependencies': [], 'handle': 'edis-evidence-admin', 'path': 'assets/css/admin.css', 'type': 'css'},
    {'dependencies': [], 'handle': 'edis-evidence-diagnostics', 'path': 'assets/js/diagnostics.js', 'type': 'js'},
    {'dependencies': ['edis-evidence-diagnostics', 'wp-api-fetch', 'wp-i18n'], 'handle': 'edis-evidence-admin-core', 'path': 'assets/js/admin.js', 'type': 'js'},
    {'dependencies': ['edis-evidence-admin-core'], 'handle': 'edis-evidence-admin-options', 'path': 'assets/js/admin-options.js', 'type': 'js'},
    {'dependencies': ['edis-evidence-admin-options'], 'handle': 'edis-evidence-admin-document-list', 'path': 'assets/js/admin-document-list.js', 'type': 'js'},
    {'dependencies': ['edis-evidence-admin-document-list'], 'handle': 'edis-evidence-admin-preflight', 'path': 'assets/js/admin-preflight.js', 'type': 'js'},
    {'dependencies': ['edis-evidence-admin-preflight'], 'handle': 'edis-evidence-admin-jobs', 'path': 'assets/js/admin-jobs.js', 'type': 'js'},
    {'dependencies': ['edis-evidence-admin-jobs'], 'handle': 'edis-evidence-admin', 'path': 'assets/js/admin-bind.js', 'type': 'js'},
]
files = manifest.get('files', [])
by_path = {entry.get('path'): entry for entry in files if isinstance(entry, dict)}
for relative in [
    'assets/js/admin-bind.js',
    'assets/js/admin-document-list.js',
    'assets/js/admin-jobs.js',
    'assets/js/admin-options.js',
    'assets/js/admin-preflight.js',
    'src/Rest/CanonicalDiagnosticResponse.php',
    'src/Rest/CanonicalDiagnosticResponseAdapter.php',
]:
    if relative not in by_path:
        suffix = Path(relative).suffix.lstrip('.')
        files.append({
            'installable': True,
            'mode': '0644',
            'path': relative,
            'required': True,
            'template': '',
            'type': suffix,
        })
manifest['files'] = sorted(files, key=lambda entry: str(entry.get('path', '')))
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, sort_keys=True, separators=(',', ':')), encoding='utf-8', newline='\n')

critical_path = ROOT / 'config/critical-files.json'
critical = json.loads(critical_path.read_text(encoding='utf-8'))
for relative in sorted(critical.get('files', {})):
    file_path = ROOT / relative
    if not file_path.is_file() or file_path.is_symlink():
        raise RuntimeError(f'Critical authority path is missing or redirected: {relative}')
    critical['files'][relative] = 'sha256:' + hashlib.sha256(file_path.read_bytes()).hexdigest()
critical_path.write_text(json.dumps(critical, ensure_ascii=False, sort_keys=True, separators=(',', ':')), encoding='utf-8', newline='\n')

for temporary in [
    '.github/workflows/finalize-root-complete.yml',
    'tools/validation/finalize-root-complete.py',
]:
    target = ROOT / temporary
    if target.exists():
        target.unlink()

print('EDIS bounded finalization patch: APPLIED')
