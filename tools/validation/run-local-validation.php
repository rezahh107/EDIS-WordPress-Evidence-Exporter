<?php
declare(strict_types=1);

/**
 * Execute repository-owned validation gates and emit evidence without promoting
 * skipped local gates or unexecuted external gates to PASS.
 */
require_once __DIR__ . '/ValidationSupport.php';

final class EdisValidationRunner
{
    private string $root;
    private bool $skipNpm;
    private bool $skipBuild;
    /** @var array<string,array<string,mixed>> */
    private array $gates = [];

    public function __construct(string $root, bool $skipNpm, bool $skipBuild)
    {
        $this->root = $root;
        $this->skipNpm = $skipNpm;
        $this->skipBuild = $skipBuild;
    }

    /** @return array<string,mixed> */
    public function run(): array
    {
        $this->commandGate('php_runtime', ['php', '-v']);

        if ($this->skipBuild) {
            $this->notRun('deterministic_release_build', 'skipped_by_command_line');
        } elseif ($this->commandExists('python3')) {
            $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'edis-validation-' . bin2hex(random_bytes(8));
            if (!mkdir($tmp, 0700, true) && !is_dir($tmp)) {
                $this->gates['deterministic_release_build'] = [
                    'state' => 'FAIL',
                    'reason' => 'temporary_output_directory_creation_failed',
                ];
            } else {
                try {
                    $this->commandGate(
                        'deterministic_release_build',
                        ['python3', 'tools/release/build-release.py', '--root', $this->root, '--output', $tmp],
                    );
                } finally {
                    $this->removeTree($tmp);
                }
            }
        } else {
            $this->notRun('deterministic_release_build', 'python3_unavailable');
        }

        $this->lintPhp();
        $this->commandGate('local_test_harness', ['php', 'tests/run-local.php']);
        $this->commandGate('runtime_smoke', ['php', '-d', 'error_reporting=E_ALL', '-d', 'display_errors=1', 'tests/runtime-smoke.php']);

        if ($this->skipNpm) {
            $this->notRun('npm_ci', 'skipped_by_command_line');
            $this->notRun('asset_quality', 'skipped_by_command_line');
        } elseif ($this->commandExists('npm')) {
            $this->commandGate('npm_ci', ['npm', 'ci', '--ignore-scripts']);
            if (($this->gates['npm_ci']['state'] ?? null) === 'PASS') {
                $this->commandGate('asset_quality', ['npm', 'run', 'quality:assets']);
            } else {
                $this->notRun('asset_quality', 'npm_ci_failed');
            }
        } else {
            $this->notRun('npm_ci', 'npm_unavailable');
            $this->notRun('asset_quality', 'npm_unavailable');
        }

        $lockPath = $this->root . DIRECTORY_SEPARATOR . 'composer.lock';
        if (!is_file($lockPath)) {
            $this->gates['composer_lock'] = [
                'state' => 'BLOCKED_EXTERNAL',
                'reason' => 'composer_lock_missing',
                'required_command' => 'composer update --with-all-dependencies',
            ];
            foreach (['composer_validate', 'composer_install', 'composer_audit', 'official_phpunit', 'phpcs'] as $gate) {
                $this->notRun($gate, 'composer_lock_missing');
            }
        } elseif (!$this->commandExists('composer')) {
            $this->gates['composer_lock'] = [
                'state' => 'PASS',
                'sha256' => hash_file('sha256', $lockPath),
            ];
            foreach (['composer_validate', 'composer_install', 'composer_audit', 'official_phpunit', 'phpcs'] as $gate) {
                $this->notRun($gate, 'composer_unavailable');
            }
        } else {
            $this->gates['composer_lock'] = [
                'state' => 'PASS',
                'sha256' => hash_file('sha256', $lockPath),
            ];
            $this->commandGate('composer_validate', ['composer', 'validate', '--strict', '--no-interaction']);
            $this->commandGate('composer_install', ['composer', 'install', '--no-interaction', '--prefer-dist', '--no-progress']);
            if (($this->gates['composer_install']['state'] ?? null) === 'PASS') {
                $this->commandGate('composer_audit', ['composer', 'audit', '--locked', '--no-interaction']);
                $this->commandGate('official_phpunit', ['vendor/bin/phpunit', '--configuration', 'phpunit.xml.dist']);
                $this->commandGate('phpcs', ['vendor/bin/phpcs', '--standard=phpcs.xml.dist']);
            } else {
                foreach (['composer_audit', 'official_phpunit', 'phpcs'] as $gate) {
                    $this->notRun($gate, 'composer_install_failed');
                }
            }
        }

        foreach (
            [
                'wordpress_single_site' => 'requires_real_wordpress_runtime',
                'wordpress_multisite' => 'requires_real_wordpress_multisite_runtime',
                'plugin_check' => 'requires_wordpress_and_plugin_check_runtime',
                'elementor_real_fixtures' => 'requires_controlled_real_elementor_exports',
                'windows_localwp' => 'requires_real_windows_localwp_environment',
                'cross_product_ingestion' => 'requires_python_consumer_fixture_execution',
            ] as $gate => $reason
        ) {
            $this->notRun($gate, $reason);
        }

        $result = [
            'schema_version' => 'EDIS-VALIDATION-EVIDENCE-2',
            'plugin_version' => $this->pluginVersion(),
            'generated_at_utc' => gmdate('Y-m-d\\TH:i:s\\Z'),
            'environment' => [
                'os_family' => PHP_OS_FAMILY,
                'php_version' => PHP_VERSION,
                'php_int_size' => PHP_INT_SIZE,
            ],
            'gates' => $this->gates,
            'summary' => EdisValidationSummary::summarize($this->gates),
        ];
        return $this->sortRecursive($result);
    }

    /** @param list<string> $command */
    private function commandGate(string $id, array $command): void
    {
        fwrite(STDERR, '[EDIS validation] START ' . $id . PHP_EOL);
        $evidence = EdisValidationProcess::run($this->root, $command);
        if (($evidence['started'] ?? false) !== true) {
            $this->gates[$id] = [
                'state' => 'FAIL',
                'command' => $command,
                'reason' => $evidence['reason'] ?? 'process_start_failed',
            ];
        } else {
            $evidence['state'] = ($evidence['exit_code'] ?? 1) === 0 ? 'PASS' : 'FAIL';
            unset($evidence['started']);
            $this->gates[$id] = $evidence;
        }
        fwrite(STDERR, '[EDIS validation] END ' . $id . ' state=' . $this->gates[$id]['state'] . PHP_EOL);
    }

    private function lintPhp(): void
    {
        fwrite(STDERR, '[EDIS validation] START php_lint' . PHP_EOL);
        $temporary = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'edis-php-lint-' . bin2hex(random_bytes(8)) . '.json';
        try {
            $execution = EdisValidationProcess::run(
                $this->root,
                ['php', 'tools/validation/lint-php.php', '--report=' . $temporary],
            );
            $report = null;
            if (is_file($temporary)) {
                try {
                    $decoded = json_decode((string) file_get_contents($temporary), true, 64, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        $report = $decoded;
                    }
                } catch (Throwable) {
                    $report = null;
                }
            }
            if (($execution['started'] ?? false) !== true || !is_array($report)) {
                $this->gates['php_lint'] = [
                    'state' => 'FAIL',
                    'reason' => $execution['reason'] ?? 'lint_report_unavailable',
                    'execution' => $execution,
                ];
            } else {
                $this->gates['php_lint'] = [
                    'state' => ($report['state'] ?? null) === 'PASS' && ($execution['exit_code'] ?? 1) === 0 ? 'PASS' : 'FAIL',
                    'file_count' => $report['file_count'] ?? null,
                    'duration_ms' => $report['duration_ms'] ?? null,
                    'failures' => $report['failures'] ?? [],
                    'execution' => $execution,
                ];
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
        fwrite(STDERR, '[EDIS validation] END php_lint state=' . $this->gates['php_lint']['state'] . PHP_EOL);
    }

    private function commandExists(string $name): bool
    {
        $path = getenv('PATH');
        if (!is_string($path) || $path === '') {
            return false;
        }
        $extensions = PHP_OS_FAMILY === 'Windows' ? ['.exe', '.bat', '.cmd', ''] : [''];
        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            foreach ($extensions as $extension) {
                $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name . $extension;
                if (is_file($candidate) && (PHP_OS_FAMILY === 'Windows' || is_executable($candidate))) {
                    return true;
                }
            }
        }
        return false;
    }

    private function notRun(string $id, string $reason): void
    {
        $this->gates[$id] = ['state' => 'NOT_RUN', 'reason' => $reason];
    }

    private function pluginVersion(): string
    {
        $text = (string) file_get_contents($this->root . DIRECTORY_SEPARATOR . 'edis-evidence-exporter.php');
        if (preg_match('/Version:\\s*([0-9]+\\.[0-9]+\\.[0-9]+)/', $text, $match) === 1) {
            return $match[1];
        }
        return 'unknown';
    }

    /** @param mixed $value @return mixed */
    private function sortRecursive(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortRecursive($item), $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursive($item);
        }
        return $value;
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item instanceof SplFileInfo) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}

$options = getopt('', ['report:', 'skip-npm', 'skip-build', 'strict-external']);
$root = dirname(__DIR__, 2);
$runner = new EdisValidationRunner(
    $root,
    array_key_exists('skip-npm', $options),
    array_key_exists('skip-build', $options),
);
$result = $runner->run();
$json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
$report = $options['report'] ?? null;
if (is_string($report) && $report !== '') {
    $target = str_starts_with($report, DIRECTORY_SEPARATOR) ? $report : $root . DIRECTORY_SEPARATOR . $report;
    try {
        EdisValidationEvidenceWriter::write($target, $json);
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Unable to commit validation report: ' . $exception->getMessage() . PHP_EOL);
        exit(2);
    }
}
fwrite(STDOUT, $json);
$localState = $result['summary']['local_state'] ?? 'FAIL';
$externalState = $result['summary']['external_state'] ?? 'FAIL';
$strictExternal = array_key_exists('strict-external', $options);
exit($localState !== 'PASS' || ($strictExternal && $externalState !== 'PASS') ? 1 : 0);
