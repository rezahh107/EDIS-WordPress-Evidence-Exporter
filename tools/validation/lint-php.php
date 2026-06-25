<?php
declare(strict_types=1);

require_once __DIR__ . '/ValidationSupport.php';

$root = dirname(__DIR__, 2);
$options = getopt('', ['report:']);
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || $file->isLink()) {
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (str_starts_with($relative, 'vendor/') || str_starts_with($relative, 'node_modules/')) {
        continue;
    }
    if (str_ends_with($relative, '.php')) {
        $files[] = $relative;
    }
}
sort($files, SORT_STRING);

$started = hrtime(true);
$failures = [];
foreach ($files as $file) {
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open(['php', '-l', $file], $descriptors, $pipes, $root);
    if (!is_resource($process)) {
        $failures[] = ['path' => $file, 'reason' => 'process_start_failed'];
        continue;
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        $failures[] = [
            'path' => $file,
            'exit_code' => $exitCode,
            'stdout_tail' => is_string($stdout) ? trim($stdout) : '',
            'stderr_tail' => is_string($stderr) ? trim($stderr) : '',
        ];
    }
}

$result = [
    'schema_version' => 'EDIS-PHP-LINT-EVIDENCE-1',
    'state' => $failures === [] ? 'PASS' : 'FAIL',
    'file_count' => count($files),
    'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
    'failures' => $failures,
];
$json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
$report = $options['report'] ?? null;
if (is_string($report) && $report !== '') {
    $target = str_starts_with($report, DIRECTORY_SEPARATOR) ? $report : $root . DIRECTORY_SEPARATOR . $report;
    EdisValidationEvidenceWriter::write($target, $json);
}
fwrite(STDOUT, $json);
exit($failures === [] ? 0 : 1);
