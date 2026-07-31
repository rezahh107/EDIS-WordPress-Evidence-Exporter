<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EnvironmentObservation314Test extends TestCase
{
    /** T09 */
    public function testUrlNormalizationFailurePreservesUsableEnvironmentAsPartial(): void
    {
        $root = dirname(__DIR__, 2);
        $script = <<<'PHP'
<?php
declare(strict_types=1);

$GLOBALS['wp_version'] = '6.8-test';
function get_site_url(): string { return 'https://example.test/%ZZ'; }
function get_home_url(): string { return 'https://example.test/'; }
function wp_parse_url(string $url, int $component = -1): mixed
{
    return $component === -1 ? parse_url($url) : parse_url($url, $component);
}
function is_multisite(): bool { return false; }
function get_locale(): string { return 'en_US'; }
function wp_timezone_string(): string { return 'UTC'; }
function wp_convert_hr_to_bytes(string $value): int { return 134217728; }

require $argv[1] . '/autoload.php';

$context = new EDIS\EvidenceExporter\Domain\Contracts\CollectionContext(
    [],
    false,
    'analysis-test',
    'bundle-test',
    'Strict',
    ['export_scope' => 'METADATA_ONLY', 'dependency_scope' => 'SOURCE_ONLY'],
    '2026-07-30T00:00:00Z',
);
$result = (new EDIS\EvidenceExporter\Infrastructure\WordPress\Collectors\EnvironmentCollector())->collect($context);
$serialized = json_decode(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
$codes = [];
foreach ((array) ($serialized['diagnostics'] ?? []) as $diagnostic) {
    if (is_array($diagnostic) && is_string($diagnostic['code'] ?? null)) {
        $codes[] = $diagnostic['code'];
    }
}
if (($serialized['source_truth_state'] ?? null) !== 'PARTIAL'
    || ($serialized['source_availability'] ?? null) !== 'PARTIAL'
    || !in_array('EDIS_URL_NORMALIZATION_FAILED', $codes, true)
    || !is_array($serialized['data'] ?? null)
    || ($serialized['data']['wordpress_version'] ?? null) !== '6.8-test'
    || count((array) ($serialized['data']['site_locator_candidates'] ?? [])) !== 1) {
    fwrite(STDERR, json_encode($serialized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    exit(1);
}
foreach ((array) ($serialized['diagnostics'] ?? []) as $diagnostic) {
    if (!is_array($diagnostic)) { continue; }
    $encoded = json_encode($diagnostic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (is_string($encoded) && str_contains($encoded, '%ZZ')) {
        fwrite(STDERR, "failed URL leaked into bounded diagnostic\n");
        exit(1);
    }
}
fwrite(STDOUT, "EDIS_T09_PARTIAL_PASS\n");
PHP;

        $temporary = tempnam(sys_get_temp_dir(), 'edis-t09-');
        self::assertIsString($temporary);
        file_put_contents($temporary, $script);
        try {
            $command = [PHP_BINARY, $temporary, $root];
            $descriptor = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open($command, $descriptor, $pipes, $root, ['EDIS_TEST' => '1']);
            self::assertIsResource($process);
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            self::assertSame(0, $exit, is_string($stderr) ? $stderr : 'T09 subprocess failed');
            self::assertStringContainsString('EDIS_T09_PARTIAL_PASS', (string) $stdout);
        } finally {
            @unlink($temporary);
        }
    }
}
