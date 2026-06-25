<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\ExportIntegrityException;
use EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore;
use PHPUnit\Framework\TestCase;

final class InputSnapshotStoreTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $path) {
            $this->remove($path);
        }
    }

    public function testCapturedSourceRemainsImmutableAfterLiveReaderChanges(): void
    {
        $root = $this->temporaryRoot();
        $current = $this->record('alpha');
        $store = new InputSnapshotStore($root, static function (int $id) use (&$current): array {
            return $current;
        });
        $manifest = $store->capture('job-one', [42], time() + 3600);
        self::assertTrue($store->verify('job-one', (string) $manifest['snapshot_sha256']));

        $current = $this->record('beta');
        $captured = $store->document('job-one', 42);
        self::assertIsArray($captured);
        self::assertStringContainsString('alpha', (string) $captured['raw_source']);
        self::assertStringNotContainsString('beta', (string) $captured['raw_source']);
    }

    public function testDetectsSourceDriftDuringCapture(): void
    {
        $root = $this->temporaryRoot();
        $reads = 0;
        $store = new InputSnapshotStore($root, function (int $id) use (&$reads): array {
            $reads++;
            return $this->record($reads === 1 ? 'first' : 'second');
        });
        try {
            $store->capture('job-drift', [42], time() + 3600);
            self::fail('Expected source drift to fail closed.');
        } catch (ExportIntegrityException $exception) {
            self::assertSame('EDIS_SOURCE_CHANGED_DURING_SNAPSHOT', $exception->diagnosticCode);
        }
        self::assertNull($store->manifest('job-drift'));
    }

    public function testTamperedSnapshotFailsVerification(): void
    {
        $root = $this->temporaryRoot();
        $store = new InputSnapshotStore($root, fn (int $id): array => $this->record('stable'));
        $manifest = $store->capture('job-tamper', [42], time() + 3600);
        file_put_contents($root . '/job-tamper/documents/42.source.json', '[{"id":"tampered"}]');
        self::assertFalse($store->verify('job-tamper', (string) $manifest['snapshot_sha256']));
        self::assertNull($store->document('job-tamper', 42));
    }


    public function testRehashedUnsupportedSnapshotFormatStillFailsVerification(): void
    {
        $root = $this->temporaryRoot();
        $store = new InputSnapshotStore($root, fn (int $id): array => $this->record('stable'));
        $manifest = $store->capture('job-format', [42], time() + 3600);
        $manifest['snapshot_format_version'] = '0.9.0';
        $hashMethod = new \ReflectionMethod($store, 'semanticManifestHash');
        $manifest['snapshot_sha256'] = $hashMethod->invoke($store, $manifest);
        file_put_contents(
            $root . '/job-format/input-manifest.json',
            \EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson::encode($manifest),
        );
        self::assertFalse($store->verify('job-format', (string) $manifest['snapshot_sha256']));
    }


    public function testRehashedUnsafeManifestPathFailsClosedWithoutTraversal(): void
    {
        $root = $this->temporaryRoot();
        $store = new InputSnapshotStore($root, fn (int $id): array => $this->record('stable'));
        $manifest = $store->capture('job-path', [42], time() + 3600);
        $manifest['documents']['42']['source_path'] = '../outside.json';
        $hashMethod = new \ReflectionMethod($store, 'semanticManifestHash');
        $manifest['snapshot_sha256'] = $hashMethod->invoke($store, $manifest);
        file_put_contents(
            $root . '/job-path/input-manifest.json',
            \EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson::encode($manifest),
        );
        self::assertFalse($store->verify('job-path', (string) $manifest['snapshot_sha256']));
        self::assertNull($store->document('job-path', 42));
    }

    /** @return array<string,mixed> */
    private function record(string $label): array
    {
        return [
            'raw_source' => '[{"id":"abc","elType":"container","settings":{"label":"' . $label . '"},"elements":[]}]',
            'raw_source_representation' => 'WORDPRESS_META_STRING',
            'document_type' => 'page',
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_modified_gmt' => '2026-06-15 00:00:00',
            'page_settings' => [],
            'elementor_edit_mode' => 'builder',
            'elementor_version' => '4.1.3',
        ];
    }

    private function temporaryRoot(): string
    {
        $root = sys_get_temp_dir() . '/edis-input-snapshot-test-' . bin2hex(random_bytes(6));
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
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . '/' . $entry);
            }
        }
        rmdir($path);
    }
}
