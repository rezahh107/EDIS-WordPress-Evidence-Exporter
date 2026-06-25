<?php
declare(strict_types=1);

use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicZipReader;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicZipWriter;
use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;

require dirname(__DIR__, 2) . '/autoload.php';

$mode = $argv[1] ?? 'zip';
if ($mode === 'zip') {
    $megabytes = max(1, min(512, (int) ($argv[2] ?? 32)));
    $path = sys_get_temp_dir() . '/edis-performance-' . bin2hex(random_bytes(6)) . '.zip';
    $payload = str_repeat('x', $megabytes * 1024 * 1024);
    $start = hrtime(true);
    $result = (new DeterministicZipWriter())->writeToFile($path, [
        'bridge/source-context.json' => '{"state":"READY"}',
        'sources/payload.bin' => $payload,
    ], new DeterministicFilesystem());
    $elapsed = (hrtime(true) - $start) / 1_000_000;
    unset($payload);
    $bridge = (new DeterministicZipReader())->readStoredEntry($path, 'bridge/source-context.json');
    echo json_encode([
        'mode' => 'zip',
        'payload_mib' => $megabytes,
        'archive_size' => $result['size'],
        'archive_sha256' => $result['sha256'],
        'bridge_bytes' => strlen((string) $bridge),
        'elapsed_ms' => round($elapsed, 3),
        'peak_memory_bytes' => memory_get_peak_usage(true),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    unlink($path);
    exit(0);
}

if ($mode === 'storage') {
    $root = sys_get_temp_dir() . '/edis-storage-benchmark-' . bin2hex(random_bytes(6));
    $first = new PrivateStorage($root);
    $start = hrtime(true);
    $live = $first->selfTest(true);
    $liveMs = (hrtime(true) - $start) / 1_000_000;
    $second = new PrivateStorage($root);
    $start = hrtime(true);
    $cached = $second->selfTest(false);
    $cachedMs = (hrtime(true) - $start) / 1_000_000;
    echo json_encode([
        'mode' => 'storage',
        'live_state' => $live['state'] ?? 'FAIL',
        'live_ms' => round($liveMs, 3),
        'cached_state' => $cached['state'] ?? 'FAIL',
        'cached_ms' => round($cachedMs, 3),
        'attestation_cache_hit' => $cached['attestation_cache_hit'] ?? false,
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $remove = function (string $path) use (&$remove): void {
        if (is_file($path) || is_link($path)) { unlink($path); return; }
        if (!is_dir($path)) { return; }
        foreach (scandir($path) ?: [] as $entry) { if ($entry !== '.' && $entry !== '..') { $remove($path . '/' . $entry); } }
        rmdir($path);
    };
    $remove($root);
    exit(0);
}

fwrite(STDERR, "Usage: php tools/benchmarks/performance-smoke.php [zip [MiB]|storage]\n");
exit(2);
