<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\ArtifactStore;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore;
use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;
use EDIS\EvidenceExporter\Infrastructure\Support\SelectionTokenStore;
use PHPUnit\Framework\TestCase;

final class PrivateStorageTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $path) {
            $this->remove($path);
        }
    }

    public function testCreatesProtectionFilesAndPassesAtomicSelfTest(): void
    {
        $root = $this->temporaryRoot();
        $storage = new PrivateStorage($root);
        self::assertTrue($storage->ensure());
        self::assertFileExists($root . '/index.php');
        self::assertFileExists($root . '/.htaccess');
        self::assertFileExists($root . '/web.config');
        $result = $storage->selfTest();
        self::assertTrue($result['atomic_replace']);
        self::assertSame('PASS', $result['multiprocess_lock_exclusion']);
        self::assertSame('PASS', $result['state']);
    }


    public function testSuccessfulForcedProofIsReusedByASecondStorageInstance(): void
    {
        $root = $this->temporaryRoot();
        $first = new PrivateStorage($root);
        $live = $first->selfTest(true);
        if (!$first->acceptsSelfTestResult($live)) {
            self::markTestSkipped('The current runtime cannot execute the independent-process lock proof.');
        }
        $second = new PrivateStorage($root);
        $cached = $second->selfTest(false);
        self::assertSame('PASS', $cached['state']);
        self::assertSame('PASS', $cached['multiprocess_lock_exclusion']);
        self::assertTrue($cached['attestation_cache_hit']);
    }

    public function testConfiguredPathInsideWebRootIsNotMisreportedAsPrivate(): void
    {
        $root = rtrim(ABSPATH, '/\\') . '/wp-content/edis-private-inside-' . bin2hex(random_bytes(4));
        $this->cleanup[] = $root;
        $storage = new PrivateStorage($root);
        self::assertFalse($storage->ensure());
        self::assertSame('ERROR', $storage->securityState());
        self::assertSame('FAIL', $storage->selfTest()['state']);
    }


    public function testRejectsLogicalPathInsideWebRootThroughAncestorSymlink(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('Symlinks are unavailable.');
        }
        $outside = $this->temporaryRoot();
        mkdir($outside, 0750, true);
        $web = rtrim(ABSPATH, '/\\');
        if (!is_dir($web)) {
            mkdir($web, 0750, true);
            $this->cleanup[] = $web;
        }
        $link = $web . '/edis-private-alias-' . bin2hex(random_bytes(4));
        $this->cleanup[] = $link;
        if (!$this->createSymlink($outside, $link)) {
            self::markTestSkipped('The current filesystem does not permit symlinks.');
        }
        $storage = new PrivateStorage($link . '/private');
        self::assertFalse($storage->ensure());
        self::assertSame('ERROR', $storage->securityState());
    }

    public function testRejectsSymlinkStorageRoot(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('Symlinks are unavailable.');
        }
        $target = $this->temporaryRoot();
        mkdir($target, 0750, true);
        $link = $target . '-link';
        $this->cleanup[] = $link;
        if (!$this->createSymlink($target, $link)) {
            self::markTestSkipped('The current filesystem does not permit symlinks.');
        }
        self::assertFalse((new PrivateStorage($link))->ensure());
    }

    public function testPrivateSubStoresRejectSymlinkRoots(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('Symlinks are unavailable.');
        }
        $target = $this->temporaryRoot();
        mkdir($target, 0750, true);
        $link = $target . '-store-link';
        $this->cleanup[] = $link;
        if (!$this->createSymlink($target, $link)) {
            self::markTestSkipped('The current filesystem does not permit symlinks.');
        }
        self::assertFalse((new JobStore($link))->rootWritable());
        self::assertFalse((new ArtifactStore($link))->rootWritable());
        self::assertFalse((new InputSnapshotStore($link, static fn (int $id): ?array => null))->rootWritable());
        $failedClosed = false;
        try {
            (new SelectionTokenStore($link))->issue(1, 1, [], 'FALSE');
        } catch (\RuntimeException) {
            $failedClosed = true;
        }
        self::assertTrue($failedClosed);
    }

    public function testSelectionTokenIsOwnerBoundAndConsumedOnce(): void
    {
        $root = $this->temporaryRoot();
        $store = new SelectionTokenStore($root, 600);
        $issued = $store->issue(10, 42, [['elementor_element_id' => 'abc']], 'FALSE');
        self::assertNull($store->consume($issued['token'], 11));
        $payload = $store->consume($issued['token'], 10);
        self::assertIsArray($payload);
        self::assertSame(42, $payload['document_id']);
        self::assertNull($store->consume($issued['token'], 10));
    }

    private function createSymlink(string $target, string $link): bool
    {
        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;
            return true;
        });
        try {
            $created = symlink($target, $link);
        } finally {
            restore_error_handler();
        }
        return $warning === null && $created;
    }

    private function temporaryRoot(): string
    {
        $root = sys_get_temp_dir() . '/edis-private-test-' . bin2hex(random_bytes(6));
        $this->cleanup[] = $root;
        return $root;
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
