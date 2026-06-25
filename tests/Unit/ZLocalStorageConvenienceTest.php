<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;
use PHPUnit\Framework\TestCase;

final class ZLocalStorageConvenienceTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $path) {
            $this->remove($path);
        }
    }

    public function testLocalModeProposesSiblingStorageOutsidePublicRoot(): void
    {
        if (!defined('WP_ENVIRONMENT_TYPE')) {
            define('WP_ENVIRONMENT_TYPE', 'local');
        }
        $storage = new PrivateStorage();
        $candidates = $storage->candidateRoots();
        self::assertTrue($candidates !== []);
        self::assertStringContainsString('/edis-private-storage', str_replace('\\', '/', $candidates[0]));
        self::assertFalse(str_contains(str_replace('\\', '/', $candidates[0]), '/public/'));
    }

    public function testUnsafeExplicitPublicPathCanFallBackOnlyToSafeLocalSibling(): void
    {
        if (!defined('WP_ENVIRONMENT_TYPE')) {
            define('WP_ENVIRONMENT_TYPE', 'local');
        }
        $public = rtrim(ABSPATH, '/\\');
        $unsafe = $public . '/wp-content/edis-public-storage';
        $this->cleanup[] = dirname($public) . '/edis-private-storage';
        $storage = new PrivateStorage($unsafe);
        $candidates = array_map(static fn (string $path): string => str_replace('\\', '/', $path), $storage->candidateRoots());
        self::assertContains(str_replace('\\', '/', dirname($public) . '/edis-private-storage'), $candidates);
        self::assertFalse($storage->ensure() && str_starts_with(str_replace('\\', '/', $storage->root()), str_replace('\\', '/', $public . '/')));
    }

    public function testLocalModeStillRequiresIndependentProcessProof(): void
    {
        if (!defined('WP_ENVIRONMENT_TYPE')) {
            define('WP_ENVIRONMENT_TYPE', 'local');
        }
        $storage = new PrivateStorage();
        self::assertTrue($storage->acceptsSelfTestResult(['state' => 'PASS', 'multiprocess_lock_exclusion' => 'PASS']));
        self::assertFalse($storage->acceptsSelfTestResult([
            'state' => 'PASS_LOCAL_SINGLE_PROCESS',
            'multiprocess_lock_exclusion' => 'LOCAL_UNAVAILABLE_ACCEPTED',
        ]));
        self::assertFalse($storage->acceptsSelfTestResult([
            'state' => 'FAIL',
            'multiprocess_lock_exclusion' => 'FAIL',
        ]));
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->remove($path . '/' . $entry);
        }
        rmdir($path);
    }
}
