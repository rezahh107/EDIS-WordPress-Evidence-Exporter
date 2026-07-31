#!/usr/bin/env python3
from pathlib import Path
import re

VERSIONS = {
    'elementor_document_source': '1.1.0',
    'elementor_kit_settings': '1.1.0',
    'elementor_site_settings_index': '1.1.0',
    'selection_snapshot': '1.2.0',
}


def patch_doc(path: str, id_label: str, version_word: str) -> None:
    file = Path(path)
    text = file.read_text(encoding='utf-8')
    text = text.replace('3.7.11', '3.7.14')
    for component_id, schema_version in VERSIONS.items():
        pattern = re.compile(
            rf'(- \*\*{re.escape(id_label)}:\*\* `{re.escape(component_id)}`.*?- \*\*Schema:\*\* `[^`]+` {re.escape(version_word)} `)([^`]+)(`)',
            re.S,
        )
        text, count = pattern.subn(rf'\g<1>{schema_version}\g<3>', text, count=1)
        if count != 1:
            raise SystemExit(f'{path}: expected one schema declaration for {component_id}, found {count}')
    file.write_text(text, encoding='utf-8')


patch_doc('docs/collector-encyclopedia.md', 'Technical ID', 'version')
patch_doc('docs/collector-encyclopedia-fa.md', 'شناسه فنی', 'نسخه')

runner = Path('tools/validation/run-local-validation.php')
text = runner.read_text(encoding='utf-8')
old = "        $this->commandGate('runtime_smoke', ['php', '-d', 'error_reporting=E_ALL', '-d', 'display_errors=1', 'tests/runtime-smoke.php']);\n"
new = old + "        $this->collectorDocumentationSync();\n"
if text.count(old) != 1:
    raise SystemExit('validation runner: runtime_smoke anchor mismatch')
text = text.replace(old, new, 1)
anchor = "    private function commandExists(string $name): bool\n    {\n"
method = r'''    private function collectorDocumentationSync(): void
    {
        $manifestPath = $this->root . DIRECTORY_SEPARATOR . 'plugin.manifest.json';
        try {
            $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $this->gates['collector_documentation_sync'] = [
                'state' => 'FAIL',
                'reason' => 'manifest_unreadable',
                'exception_class' => get_class($exception),
            ];
            return;
        }
        $expected = [];
        foreach ((array) ($manifest['collectors'] ?? []) as $collector) {
            if (!is_array($collector) || !is_string($collector['technical_id'] ?? null) || !is_string($collector['schema_version'] ?? null)) {
                continue;
            }
            $expected[$collector['technical_id']] = $collector['schema_version'];
        }
        ksort($expected, SORT_STRING);
        if ($expected === []) {
            $this->gates['collector_documentation_sync'] = ['state' => 'FAIL', 'reason' => 'manifest_collector_authority_empty'];
            return;
        }

        $documents = [
            'docs/collector-encyclopedia.md' => '/- \*\*Technical ID:\*\* `([^`]+)`.*?- \*\*Schema:\*\* `[^`]+` version `([^`]+)`/s',
            'docs/collector-encyclopedia-fa.md' => '/- \*\*شناسه فنی:\*\* `([^`]+)`.*?- \*\*Schema:\*\* `[^`]+` نسخه `([^`]+)`/su',
        ];
        $failures = [];
        foreach ($documents as $relative => $pattern) {
            $bytes = @file_get_contents($this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
            if (!is_string($bytes) || preg_match_all($pattern, $bytes, $matches, PREG_SET_ORDER) === false) {
                $failures[$relative] = ['document_unreadable_or_unparseable'];
                continue;
            }
            $actual = [];
            foreach ($matches as $match) {
                $actual[(string) $match[1]] = (string) $match[2];
            }
            ksort($actual, SORT_STRING);
            $documentFailures = [];
            foreach ($expected as $technicalId => $schemaVersion) {
                if (!array_key_exists($technicalId, $actual)) {
                    $documentFailures[] = $technicalId . ':MISSING';
                } elseif ($actual[$technicalId] !== $schemaVersion) {
                    $documentFailures[] = $technicalId . ':EXPECTED_' . $schemaVersion . '_FOUND_' . $actual[$technicalId];
                }
            }
            foreach (array_keys($actual) as $technicalId) {
                if (!array_key_exists($technicalId, $expected)) {
                    $documentFailures[] = $technicalId . ':UNDECLARED';
                }
            }
            if ($documentFailures !== []) {
                sort($documentFailures, SORT_STRING);
                $failures[$relative] = array_slice($documentFailures, 0, 64);
            }
        }
        ksort($failures, SORT_STRING);
        $this->gates['collector_documentation_sync'] = [
            'state' => $failures === [] ? 'PASS' : 'FAIL',
            'expected_component_count' => count($expected),
            'documents_checked' => array_keys($documents),
            'failures' => $failures,
        ];
    }

'''
if text.count(anchor) != 1:
    raise SystemExit('validation runner: method insertion anchor mismatch')
text = text.replace(anchor, method + anchor, 1)
runner.write_text(text, encoding='utf-8')
