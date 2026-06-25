<?php
declare(strict_types=1);

namespace PHPUnit\Framework {
    final class SkippedTestError extends \RuntimeException {}

    class TestCase
    {
        private ?string $expectedException = null;

        protected function setUp(): void {}
        protected function tearDown(): void {}

        public function expectException(string $class): void
        {
            $this->expectedException = $class;
        }

        public function consumeExpectedException(): ?string
        {
            $expected = $this->expectedException;
            $this->expectedException = null;
            return $expected;
        }

        public static function fail(string $message = 'Test failed.'): never
        {
            throw new \AssertionError($message);
        }

        public static function markTestSkipped(string $message = 'Skipped.'): never
        {
            throw new SkippedTestError($message);
        }

        public static function assertTrue(mixed $actual, string $message = ''): void
        {
            if ($actual !== true) { self::fail($message !== '' ? $message : 'Expected true.'); }
        }

        public static function assertFalse(mixed $actual, string $message = ''): void
        {
            if ($actual !== false) { self::fail($message !== '' ? $message : 'Expected false.'); }
        }

        public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
        {
            if ($expected !== $actual) {
                self::fail($message !== '' ? $message : 'Values are not identical. Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
            }
        }

        public static function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
        {
            if ($expected === $actual) { self::fail($message !== '' ? $message : 'Values are identical.'); }
        }

        public static function assertNull(mixed $actual, string $message = ''): void
        {
            if ($actual !== null) { self::fail($message !== '' ? $message : 'Expected null.'); }
        }

        public static function assertIsArray(mixed $actual, string $message = ''): void
        {
            if (!is_array($actual)) { self::fail($message !== '' ? $message : 'Expected array.'); }
        }

        public static function assertIsString(mixed $actual, string $message = ''): void
        {
            if (!is_string($actual)) { self::fail($message !== '' ? $message : 'Expected string.'); }
        }

        public static function assertInstanceOf(string $expected, mixed $actual, string $message = ''): void
        {
            if (!$actual instanceof $expected) { self::fail($message !== '' ? $message : 'Object is not an instance of ' . $expected); }
        }

        public static function assertContains(mixed $needle, iterable $haystack, string $message = ''): void
        {
            foreach ($haystack as $value) {
                if ($value === $needle) { return; }
            }
            self::fail($message !== '' ? $message : 'Value was not found in iterable.');
        }

        public static function assertCount(int $expectedCount, \Countable|array $haystack, string $message = ''): void
        {
            if (count($haystack) !== $expectedCount) { self::fail($message !== '' ? $message : 'Unexpected count.'); }
        }

        public static function assertGreaterThan(int|float $expected, int|float $actual, string $message = ''): void
        {
            if (!($actual > $expected)) { self::fail($message !== '' ? $message : 'Value is not greater than expected.'); }
        }

        public static function assertLessThan(int|float $expected, int|float $actual, string $message = ''): void
        {
            if (!($actual < $expected)) { self::fail($message !== '' ? $message : 'Value is not less than expected.'); }
        }

        public static function assertFileExists(string $filename, string $message = ''): void
        {
            if (!is_file($filename)) { self::fail($message !== '' ? $message : 'File does not exist: ' . $filename); }
        }

        public static function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
        {
            if (!str_contains($haystack, $needle)) { self::fail($message !== '' ? $message : 'String does not contain expected fragment: ' . $needle); }
        }

        public static function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
        {
            if (str_contains($haystack, $needle)) { self::fail($message !== '' ? $message : 'String contains forbidden fragment: ' . $needle); }
        }

        public static function assertStringStartsWith(string $prefix, string $actual, string $message = ''): void
        {
            if (!str_starts_with($actual, $prefix)) { self::fail($message !== '' ? $message : 'String does not start with expected prefix.'); }
        }
    }
}

namespace {
    if (!defined('ABSPATH')) { define('ABSPATH', sys_get_temp_dir() . '/edis-test-wordpress/'); }
    require dirname(__DIR__) . '/autoload.php';

    $files = glob(__DIR__ . '/Unit/*Test.php') ?: [];
    sort($files, SORT_STRING);
    foreach ($files as $file) {
        require_once $file;
    }

    $testFiles = array_fill_keys(array_map('realpath', $files), false);
    $classes = [];
    foreach (get_declared_classes() as $class) {
        if (!str_ends_with($class, 'Test') || !is_subclass_of($class, \PHPUnit\Framework\TestCase::class)) {
            continue;
        }
        $reflection = new \ReflectionClass($class);
        $fileName = $reflection->getFileName();
        $realFile = is_string($fileName) ? realpath($fileName) : false;
        if (!is_string($realFile) || !array_key_exists($realFile, $testFiles)) {
            continue;
        }
        $testFiles[$realFile] = true;
        $classes[] = $class;
    }
    $undiscovered = array_keys(array_filter($testFiles, static fn (bool $discovered): bool => !$discovered));
    if ($undiscovered !== []) {
        fwrite(STDERR, "Undiscovered test files:
" . implode("
", $undiscovered) . "
");
        exit(1);
    }
    sort($classes, SORT_STRING);

    $passed = 0;
    $failed = 0;
    $skipped = 0;
    $failures = [];

    foreach ($classes as $class) {
        $reflection = new \ReflectionClass($class);
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!str_starts_with($method->getName(), 'test') || $method->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            $test = $reflection->newInstance();
            $label = $class . '::' . $method->getName();
            $setup = $reflection->getMethod('setUp');
            $teardown = $reflection->getMethod('tearDown');
            $setup->setAccessible(true);
            $teardown->setAccessible(true);
            $thrown = null;
            try {
                $setup->invoke($test);
                try {
                    $method->invoke($test);
                } catch (\ReflectionException $exception) {
                    throw $exception;
                } catch (\Throwable $exception) {
                    $thrown = $exception instanceof \ReflectionException && $exception->getPrevious() instanceof \Throwable
                        ? $exception->getPrevious()
                        : ($exception->getPrevious() ?? $exception);
                }

                $expected = $test->consumeExpectedException();
                if ($expected !== null) {
                    if (!$thrown instanceof $expected) {
                        $actual = $thrown instanceof \Throwable ? get_class($thrown) : 'none';
                        throw new \AssertionError('Expected exception ' . $expected . ', got ' . $actual . '.');
                    }
                    $thrown = null;
                }
                if ($thrown instanceof \Throwable) {
                    throw $thrown;
                }
                ++$passed;
                echo "PASS {$label}\n";
            } catch (\PHPUnit\Framework\SkippedTestError $exception) {
                ++$skipped;
                echo "SKIP {$label}: {$exception->getMessage()}\n";
            } catch (\Throwable $exception) {
                ++$failed;
                $failures[] = $label . ': ' . get_class($exception) . ': ' . $exception->getMessage();
                echo "FAIL {$label}: {$exception->getMessage()}\n";
            } finally {
                try { $teardown->invoke($test); } catch (\Throwable $exception) {
                    ++$failed;
                    $failures[] = $label . ' tearDown: ' . $exception->getMessage();
                }
            }
        }
    }

    echo "\nRESULT passed={$passed} failed={$failed} skipped={$skipped}\n";
    if ($failures !== []) {
        fwrite(STDERR, implode("\n", $failures) . "\n");
        exit(1);
    }
}
