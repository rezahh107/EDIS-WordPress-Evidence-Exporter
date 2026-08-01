<?php
declare(strict_types=1);

namespace PHPUnit\Framework {
    final class SkippedTestError extends \RuntimeException {}

    class TestCase
    {
        private ?string $expectedException = null;
        protected function setUp(): void {}
        protected function tearDown(): void {}
        public function expectException(string $class): void { $this->expectedException = $class; }
        public function consumeExpectedException(): ?string { $expected = $this->expectedException; $this->expectedException = null; return $expected; }
        public static function fail(string $message = 'Test failed.'): never { throw new \AssertionError($message); }
        public static function markTestSkipped(string $message = 'Skipped.'): never { throw new SkippedTestError($message); }
        public static function assertTrue(mixed $actual, string $message = ''): void { if ($actual !== true) self::fail($message ?: 'Expected true.'); }
        public static function assertFalse(mixed $actual, string $message = ''): void { if ($actual !== false) self::fail($message ?: 'Expected false.'); }
        public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void { if ($expected !== $actual) self::fail($message ?: 'Values are not identical. Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)); }
        public static function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void { if ($expected === $actual) self::fail($message ?: 'Values are identical.'); }
        public static function assertNull(mixed $actual, string $message = ''): void { if ($actual !== null) self::fail($message ?: 'Expected null.'); }
        public static function assertIsArray(mixed $actual, string $message = ''): void { if (!is_array($actual)) self::fail($message ?: 'Expected array.'); }
        public static function assertIsString(mixed $actual, string $message = ''): void { if (!is_string($actual)) self::fail($message ?: 'Expected string.'); }
        public static function assertIsResource(mixed $actual, string $message = ''): void { if (!is_resource($actual)) self::fail($message ?: 'Expected resource.'); }
        public static function assertArrayNotHasKey(int|string $key, array $array, string $message = ''): void { if (array_key_exists($key, $array)) self::fail($message ?: 'Array unexpectedly contains key: ' . (string)$key); }
        public static function assertInstanceOf(string $expected, mixed $actual, string $message = ''): void { if (!$actual instanceof $expected) self::fail($message ?: 'Object is not an instance of ' . $expected); }
        public static function assertContains(mixed $needle, iterable $haystack, string $message = ''): void { foreach ($haystack as $value) if ($value === $needle) return; self::fail($message ?: 'Value was not found in iterable.'); }
        public static function assertCount(int $expectedCount, \Countable|array $haystack, string $message = ''): void { if (count($haystack) !== $expectedCount) self::fail($message ?: 'Unexpected count.'); }
        public static function assertGreaterThan(int|float $expected, int|float $actual, string $message = ''): void { if (!($actual > $expected)) self::fail($message ?: 'Value is not greater than expected.'); }
        public static function assertGreaterThanOrEqual(int|float $expected, int|float $actual, string $message = ''): void { if (!($actual >= $expected)) self::fail($message ?: 'Value is less than expected.'); }
        public static function assertLessThan(int|float $expected, int|float $actual, string $message = ''): void { if (!($actual < $expected)) self::fail($message ?: 'Value is not less than expected.'); }
        public static function assertFileExists(string $filename, string $message = ''): void { if (!is_file($filename)) self::fail($message ?: 'File does not exist: ' . $filename); }
        public static function assertStringContainsString(string $needle, string $haystack, string $message = ''): void { if (!str_contains($haystack, $needle)) self::fail($message ?: 'String does not contain expected fragment: ' . $needle); }
        public static function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void { if (str_contains($haystack, $needle)) self::fail($message ?: 'String contains forbidden fragment: ' . $needle); }
        public static function assertStringStartsWith(string $prefix, string $actual, string $message = ''): void { if (!str_starts_with($actual, $prefix)) self::fail($message ?: 'String does not start with expected prefix.'); }
    }
}

namespace {
    function edis_local_remove_tree(string $path): void
    {
        if (is_file($path) || is_link($path)) { @unlink($path); return; }
        if (!is_dir($path)) return;
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            edis_local_remove_tree($path . DIRECTORY_SEPARATOR . $entry);
        }
        @rmdir($path);
    }

    function edis_local_run_file(string $file): int
    {
        if (!defined('ABSPATH')) define('ABSPATH', sys_get_temp_dir() . '/wordpress/');
        if (basename($file) === 'RootCompleteRepairTest.php' && !function_exists('get_option')) {
            function get_option(string $option, mixed $default = false): mixed { return $default; }
        }
        require dirname(__DIR__) . '/autoload.php';
        $before = get_declared_classes();
        require_once $file;
        $after = array_values(array_diff(get_declared_classes(), $before));
        $classes = [];
        foreach ($after as $class) {
            if (!str_ends_with($class, 'Test') || !is_subclass_of($class, \PHPUnit\Framework\TestCase::class)) continue;
            $reflection = new \ReflectionClass($class);
            $declaredFile = $reflection->getFileName();
            if (is_string($declaredFile) && realpath($declaredFile) === realpath($file)) $classes[] = $class;
        }
        sort($classes, SORT_STRING);
        if ($classes === []) { fwrite(STDERR, "No test class discovered in {$file}\n"); return 1; }

        $passed = 0; $failed = 0; $skipped = 0; $failures = [];
        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            $methods = array_values(array_filter($reflection->getMethods(\ReflectionMethod::IS_PUBLIC), static fn(\ReflectionMethod $method): bool => str_starts_with($method->getName(), 'test') && $method->getDeclaringClass()->getName() === $class));
            usort($methods, static fn(\ReflectionMethod $a, \ReflectionMethod $b): int => strcmp($a->getName(), $b->getName()));
            foreach ($methods as $method) {
                $test = $reflection->newInstance();
                $label = $class . '::' . $method->getName();
                $setup = $reflection->getMethod('setUp'); $setup->setAccessible(true);
                $teardown = $reflection->getMethod('tearDown'); $teardown->setAccessible(true);
                $thrown = null;
                try {
                    $setup->invoke($test);
                    try { $method->invoke($test); } catch (\Throwable $exception) { $thrown = $exception; }
                    $expected = $test->consumeExpectedException();
                    if ($expected !== null) {
                        if (!$thrown instanceof $expected) {
                            $actual = $thrown instanceof \Throwable ? get_class($thrown) : 'none';
                            throw new \AssertionError('Expected exception ' . $expected . ', got ' . $actual . '.');
                        }
                        $thrown = null;
                    }
                    if ($thrown instanceof \Throwable) throw $thrown;
                    ++$passed; echo "PASS {$label}\n";
                } catch (\PHPUnit\Framework\SkippedTestError $exception) {
                    ++$skipped; echo "SKIP {$label}: {$exception->getMessage()}\n";
                } catch (\Throwable $exception) {
                    ++$failed; $failures[] = $label . ': ' . get_class($exception) . ': ' . $exception->getMessage(); echo "FAIL {$label}: {$exception->getMessage()}\n";
                } finally {
                    try { $teardown->invoke($test); } catch (\Throwable $exception) { ++$failed; $failures[] = $label . ' tearDown: ' . get_class($exception) . ': ' . $exception->getMessage(); }
                }
            }
        }
        echo "RESULT file=" . basename($file) . " passed={$passed} failed={$failed} skipped={$skipped}\n";
        if ($failures !== []) { fwrite(STDERR, implode("\n", $failures) . "\n"); return 1; }
        return 0;
    }

    $childIndex = array_search('--child-file', $argv, true);
    if (is_int($childIndex)) {
        $file = $argv[$childIndex + 1] ?? '';
        if (!is_string($file) || !is_file($file)) { fwrite(STDERR, "Invalid child test file.\n"); exit(2); }
        exit(edis_local_run_file($file));
    }

    $files = glob(__DIR__ . '/Unit/*Test.php') ?: [];
    sort($files, SORT_STRING);
    if ($files === []) { fwrite(STDERR, "No unit tests discovered.\n"); exit(1); }
    $failedFiles = [];
    foreach ($files as $file) {
        $temporary = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'edis-local-harness-' . bin2hex(random_bytes(8));
        if (!mkdir($temporary, 0700, true) && !is_dir($temporary)) { fwrite(STDERR, "Unable to create isolated temp root for {$file}.\n"); $failedFiles[] = $file; continue; }
        $stdoutPath = $temporary . DIRECTORY_SEPARATOR . 'stdout.log';
        $stderrPath = $temporary . DIRECTORY_SEPARATOR . 'stderr.log';
        $descriptors = [0 => ['pipe', 'r'], 1 => ['file', $stdoutPath, 'wb'], 2 => ['file', $stderrPath, 'wb']];
        $environment = getenv(); if (!is_array($environment)) $environment = [];
        $environment['TMPDIR'] = $temporary; $environment['TMP'] = $temporary; $environment['TEMP'] = $temporary;
        try {
            $process = proc_open([PHP_BINARY, __FILE__, '--child-file', $file], $descriptors, $pipes, dirname(__DIR__), $environment);
            if (!is_resource($process)) { fwrite(STDERR, "Could not start isolated test process for {$file}.\n"); $failedFiles[] = $file; continue; }
            fclose($pipes[0]);
            $exit = proc_close($process);
            $stdout = is_file($stdoutPath) ? file_get_contents($stdoutPath) : '';
            $stderr = is_file($stderrPath) ? file_get_contents($stderrPath) : '';
            if (is_string($stdout) && $stdout !== '') fwrite(STDOUT, $stdout);
            if (is_string($stderr) && $stderr !== '') fwrite(STDERR, $stderr);
            if ($exit !== 0) $failedFiles[] = $file;
        } finally { edis_local_remove_tree($temporary); }
    }
    echo "\nRESULT files=" . count($files) . " failed_files=" . count($failedFiles) . "\n";
    if ($failedFiles !== []) { fwrite(STDERR, "Failed test files:\n" . implode("\n", $failedFiles) . "\n"); exit(1); }
    exit(0);
}
