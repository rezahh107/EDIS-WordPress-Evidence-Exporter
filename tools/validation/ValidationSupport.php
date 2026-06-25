<?php
declare(strict_types=1);

/** Source-only helpers for deterministic validation evidence. */
final class EdisValidationSummary
{
    /** @var list<string> */
    public const LOCAL_REQUIRED = [
        'php_runtime',
        'php_lint',
        'local_test_harness',
        'runtime_smoke',
        'npm_ci',
        'asset_quality',
        'deterministic_release_build',
    ];

    /** @var list<string> */
    public const EXTERNAL_REQUIRED = [
        'composer_lock',
        'composer_validate',
        'composer_install',
        'composer_audit',
        'official_phpunit',
        'phpcs',
        'wordpress_single_site',
        'wordpress_multisite',
        'plugin_check',
        'elementor_real_fixtures',
        'windows_localwp',
        'cross_product_ingestion',
    ];

    /** @param array<string,array<string,mixed>> $gates @return array<string,mixed> */
    public static function summarize(array $gates): array
    {
        $failed = [];
        foreach ($gates as $id => $gate) {
            if (($gate['state'] ?? null) === 'FAIL') {
                $failed[] = $id;
            }
        }

        $incompleteLocal = self::notPassed(self::LOCAL_REQUIRED, $gates);
        $unresolvedExternal = self::notPassed(self::EXTERNAL_REQUIRED, $gates);
        $localFailures = array_values(array_intersect(self::LOCAL_REQUIRED, $failed));
        $externalFailures = array_values(array_intersect(self::EXTERNAL_REQUIRED, $failed));

        $localState = $localFailures !== []
            ? 'FAIL'
            : ($incompleteLocal === [] ? 'PASS' : 'INCOMPLETE');

        if ($externalFailures !== []) {
            $externalState = 'FAIL';
        } elseif ($unresolvedExternal === []) {
            $externalState = 'PASS';
        } else {
            $hasBlocked = false;
            foreach ($unresolvedExternal as $id) {
                if (($gates[$id]['state'] ?? null) === 'BLOCKED_EXTERNAL') {
                    $hasBlocked = true;
                    break;
                }
            }
            $externalState = $hasBlocked ? 'BLOCKED_EXTERNAL' : 'NOT_RUN';
        }

        $notRunOrBlocked = [];
        foreach ($gates as $id => $gate) {
            if (in_array(($gate['state'] ?? null), ['BLOCKED_EXTERNAL', 'NOT_RUN'], true)) {
                $notRunOrBlocked[] = $id;
            }
        }

        foreach ([$failed, $incompleteLocal, $unresolvedExternal, $notRunOrBlocked] as &$ids) {
            sort($ids, SORT_STRING);
        }
        unset($ids);

        return [
            'local_state' => $localState,
            'external_state' => $externalState,
            'production_ready_verified' => $localState === 'PASS' && $externalState === 'PASS',
            'failed_gates' => $failed,
            'incomplete_local_gates' => $incompleteLocal,
            'unresolved_external_gates' => $unresolvedExternal,
            'not_run_or_blocked_gates' => $notRunOrBlocked,
        ];
    }

    /** @param list<string> $ids @param array<string,array<string,mixed>> $gates @return list<string> */
    private static function notPassed(array $ids, array $gates): array
    {
        $result = [];
        foreach ($ids as $id) {
            if (($gates[$id]['state'] ?? null) !== 'PASS') {
                $result[] = $id;
            }
        }
        return $result;
    }
}

final class EdisValidationProcess
{
    private const TAIL_BYTES = 65536;
    private const TAIL_LINES = 20;

    /** @param list<string> $command @return array<string,mixed> */
    public static function run(string $workingDirectory, array $command): array
    {
        $temporary = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'edis-validation-process-' . bin2hex(random_bytes(8));
        if (!mkdir($temporary, 0700, true) && !is_dir($temporary)) {
            return [
                'started' => false,
                'reason' => 'temporary_output_directory_creation_failed',
                'command' => $command,
            ];
        }

        $stdoutPath = $temporary . DIRECTORY_SEPARATOR . 'stdout.log';
        $stderrPath = $temporary . DIRECTORY_SEPARATOR . 'stderr.log';
        $started = hrtime(true);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $stdoutPath, 'wb'],
            2 => ['file', $stderrPath, 'wb'],
        ];

        try {
            $process = proc_open($command, $descriptors, $pipes, $workingDirectory);
            if (!is_resource($process)) {
                return [
                    'started' => false,
                    'reason' => 'process_start_failed',
                    'command' => $command,
                ];
            }
            fclose($pipes[0]);
            $exitCode = proc_close($process);
            $durationMs = (int) round((hrtime(true) - $started) / 1_000_000);
            $stdout = self::fileEvidence($stdoutPath);
            $stderr = self::fileEvidence($stderrPath);

            return [
                'started' => true,
                'command' => $command,
                'exit_code' => $exitCode,
                'duration_ms' => $durationMs,
                'stdout_sha256' => $stdout['sha256'],
                'stderr_sha256' => $stderr['sha256'],
                'stdout_bytes' => $stdout['bytes'],
                'stderr_bytes' => $stderr['bytes'],
                'stdout_tail' => $stdout['tail'],
                'stderr_tail' => $stderr['tail'],
            ];
        } finally {
            self::removeTree($temporary);
        }
    }

    /** @return array{sha256:string,bytes:int,tail:string} */
    private static function fileEvidence(string $path): array
    {
        if (!is_file($path)) {
            return ['sha256' => hash('sha256', ''), 'bytes' => 0, 'tail' => ''];
        }
        $size = filesize($path);
        $bytes = is_int($size) ? $size : 0;
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            $hash = hash('sha256', '');
        }
        $handle = fopen($path, 'rb');
        if (!is_resource($handle)) {
            return ['sha256' => $hash, 'bytes' => $bytes, 'tail' => ''];
        }
        try {
            if ($bytes > self::TAIL_BYTES) {
                fseek($handle, -self::TAIL_BYTES, SEEK_END);
            }
            $tailBytes = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }
        $tail = self::tail(is_string($tailBytes) ? $tailBytes : '');
        return ['sha256' => $hash, 'bytes' => $bytes, 'tail' => $tail];
    }

    private static function tail(string $value): string
    {
        $value = str_replace("\r\n", "\n", $value);
        $lines = explode("\n", trim($value));
        return implode("\n", array_slice($lines, -self::TAIL_LINES));
    }

    private static function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $candidate = $path . DIRECTORY_SEPARATOR . $entry;
            is_dir($candidate) && !is_link($candidate) ? self::removeTree($candidate) : @unlink($candidate);
        }
        @rmdir($path);
    }
}

final class EdisValidationEvidenceWriter
{
    public static function write(string $target, string $contents): void
    {
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('report_directory_creation_failed');
        }
        $temporary = $directory . DIRECTORY_SEPARATOR . '.' . basename($target) . '.tmp-' . bin2hex(random_bytes(8));
        $handle = fopen($temporary, 'xb');
        if (!is_resource($handle)) {
            throw new RuntimeException('report_temporary_file_creation_failed');
        }
        @chmod($temporary, 0600);
        try {
            try {
                $offset = 0;
                $length = strlen($contents);
                while ($offset < $length) {
                    $written = fwrite($handle, substr($contents, $offset, 1048576));
                    if (!is_int($written) || $written <= 0) {
                        throw new RuntimeException('report_write_failed');
                    }
                    $offset += $written;
                }
                if (!fflush($handle)) {
                    throw new RuntimeException('report_flush_failed');
                }
                if (function_exists('fsync') && !fsync($handle)) {
                    throw new RuntimeException('report_fsync_failed');
                }
            } finally {
                fclose($handle);
            }
        } catch (Throwable $exception) {
            @unlink($temporary);
            throw $exception;
        }

        if (!@rename($temporary, $target)) {
            if (PHP_OS_FAMILY === 'Windows' && is_file($target) && @unlink($target) && @rename($temporary, $target)) {
                // Windows replacement fallback. The write remains fail-closed on any error.
            } else {
                @unlink($temporary);
                throw new RuntimeException('report_commit_failed');
            }
        }
        @chmod($target, 0600);
        $actual = hash_file('sha256', $target);
        if (!is_string($actual) || !hash_equals(hash('sha256', $contents), $actual)) {
            throw new RuntimeException('report_verification_failed');
        }
    }
}
