<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Admin\Settings\SettingsRepository;
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportFileStore;
use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;
use PHPUnit\Framework\TestCase;

final class LocalWpStorageRegressionTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $path) {
            $this->remove($path);
        }
        $this->cleanup = [];
    }

    public function testDetectsDocumentedLocalWpAppPublicLayout(): void
    {
        self::assertSame(
            'C:/Users/Nestech/Local Sites/nurro',
            PrivateStorage::detectLocalWpSiteRoot('C:/Users/Nestech/Local Sites/nurro/app/public/')
        );
        self::assertSame(
            '/Users/example/Local Sites/demo',
            PrivateStorage::detectLocalWpSiteRoot('/Users/example/Local Sites/demo/app/public')
        );
    }

    public function testRejectsNonLocalWpLayouts(): void
    {
        self::assertNull(PrivateStorage::detectLocalWpSiteRoot('C:/inetpub/wwwroot'));
        self::assertNull(PrivateStorage::detectLocalWpSiteRoot('/srv/wordpress/public'));
    }

    public function testStorageActivationFailureRemainsDegradedInsteadOfAbortingActivation(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/WordPress/LifecycleManager.php');
        self::assertIsString($source);
        self::assertStringContainsString('Storage failure is always fail-closed for exports', $source);
        self::assertStringNotContainsString("throw new \\RuntimeException( 'EDIS private storage preflight failed.'", $source);
    }

    public function testDegradedModeExposesStorageCliAndAdminRetest(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/WordPress/DegradedModeIntegration.php');
        self::assertIsString($source);
        self::assertStringContainsString("'edis storage paths'", $source);
        self::assertStringContainsString("'edis storage self-test'", $source);
        self::assertStringContainsString("'admin_post_edis_storage_retest'", $source);
    }

    public function testWindowsCgiProbePrefersSiblingCliBinary(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/Infrastructure/Support/DeterministicFilesystem.php');
        self::assertIsString($source);
        self::assertStringContainsString("\$basename === 'php-cgi.exe'", $source);
        self::assertStringContainsString("'php.exe'", $source);
        self::assertStringContainsString('$observedExit', $source);
    }

    public function testBundleAuthorizationAcceptsEquivalentCanonicalPathRepresentation(): void
    {
        $base = $this->temporaryDirectory();
        $root = str_replace('\\', '/', $base) . '/bundles';
        $store = new ExportFileStore(new SettingsRepository(), $root);
        $bundle = $store->createBundle('path-equivalence', ['proof.txt' => 'EDIS'], time() + 3600);

        $metadata = $store->metadata('path-equivalence');
        self::assertIsArray($metadata);
        self::assertSame($bundle['path'], $metadata['path']);
        $real = realpath($bundle['path']);
        $canonicalRoot = realpath($root);
        self::assertIsString($real);
        self::assertIsString($canonicalRoot);
        self::assertTrue(is_file($real));
        self::assertFalse(is_link($real));
        $samePath = new \ReflectionMethod($store, 'samePath');
        $withinRoot = new \ReflectionMethod($store, 'isWithinRoot');
        self::assertTrue($samePath->invoke($store, $real, $bundle['path']));
        self::assertTrue($withinRoot->invoke($store, $real, $canonicalRoot));
        self::assertSame((int) $metadata['size'], (int) filesize($real));
        $hash = hash_file('sha256', $real);
        self::assertIsString($hash);
        self::assertSame((string) $metadata['sha256'], 'sha256:' . $hash);

        $authorized = $store->authorize('path-equivalence', $bundle['token']);

        self::assertIsString($authorized);
        self::assertSame($real, $authorized);
        self::assertFileExists($authorized);
    }

    public function testInvalidTokenIsRejected(): void
    {
        [$store, $bundle] = $this->bundleFixture('invalid-token');
        self::assertNull($store->authorize('invalid-token', 'definitely-not-the-token'));
        self::assertNotSame('definitely-not-the-token', $bundle['token']);
    }

    public function testExpiredMetadataIsRejected(): void
    {
        [$store, $bundle] = $this->bundleFixture('expired');
        $this->mutateMetadata($bundle['path'], static function (array &$metadata): void {
            $metadata['expires_at'] = time() - 1;
        });
        self::assertNull($store->authorize('expired', $bundle['token']));
    }

    public function testInvalidZipProfileAndCompressionAreRejected(): void
    {
        [$profileStore, $profileBundle] = $this->bundleFixture('profile');
        $this->mutateMetadata($profileBundle['path'], static function (array &$metadata): void {
            $metadata['zip_profile'] = 'UNTRUSTED';
        });
        self::assertNull($profileStore->authorize('profile', $profileBundle['token']));

        [$compressionStore, $compressionBundle] = $this->bundleFixture('compression');
        $this->mutateMetadata($compressionBundle['path'], static function (array &$metadata): void {
            $metadata['compression_method'] = 'DEFLATE';
        });
        self::assertNull($compressionStore->authorize('compression', $compressionBundle['token']));
    }

    public function testSizeMismatchIsRejected(): void
    {
        [$store, $bundle] = $this->bundleFixture('size-mismatch');
        file_put_contents($bundle['path'], 'x', FILE_APPEND);
        self::assertNull($store->authorize('size-mismatch', $bundle['token']));
    }

    public function testShaMismatchWithSameSizeIsRejected(): void
    {
        [$store, $bundle] = $this->bundleFixture('sha-mismatch');
        $bytes = file_get_contents($bundle['path']);
        self::assertIsString($bytes);
        self::assertNotSame('', $bytes);
        $bytes[0] = chr(ord($bytes[0]) ^ 0x01);
        file_put_contents($bundle['path'], $bytes);
        self::assertNull($store->authorize('sha-mismatch', $bundle['token']));
    }

    public function testMissingBundleIsRejected(): void
    {
        [$store, $bundle] = $this->bundleFixture('missing');
        unlink($bundle['path']);
        self::assertNull($store->authorize('missing', $bundle['token']));
    }

    public function testSymlinkBundleEscapeIsRejectedWhenSupported(): void
    {
        [$store, $bundle] = $this->bundleFixture('symlink');
        $outside = dirname(dirname($bundle['path'])) . DIRECTORY_SEPARATOR . 'outside-bundle.zip';
        if (!rename($bundle['path'], $outside)) {
            self::markTestSkipped('Could not move bundle for symlink fixture.');
        }
        if (!@symlink($outside, $bundle['path'])) {
            rename($outside, $bundle['path']);
            self::markTestSkipped('Symlink creation is unavailable on this platform.');
        }
        self::assertTrue(is_link($bundle['path']));
        self::assertNull($store->authorize('symlink', $bundle['token']));
    }

    public function testParentTraversalRootCannotAuthorizePrefixCollisionTarget(): void
    {
        $base = $this->temporaryDirectory();
        $authorizedRoot = $base . DIRECTORY_SEPARATOR . 'bundles';
        $evilRoot = $base . DIRECTORY_SEPARATOR . 'bundles-evil';
        mkdir($authorizedRoot, 0750, true);
        mkdir($evilRoot, 0750, true);
        $lexicalRoot = $authorizedRoot . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bundles-evil';
        $store = new ExportFileStore(new SettingsRepository(), $lexicalRoot);
        $bundle = $store->createBundle('prefix-collision', ['proof.txt' => 'EDIS'], time() + 3600);

        self::assertNull($store->authorize('prefix-collision', $bundle['token']));
    }

    public function testWindowsPathComparisonHandlesSeparatorsDriveCaseAndPrefixBoundaries(): void
    {
        $store = new ExportFileStore(new SettingsRepository(), sys_get_temp_dir());
        $samePath = new \ReflectionMethod($store, 'samePath');
        $withinRoot = new \ReflectionMethod($store, 'isWithinRoot');

        self::assertTrue($samePath->invoke($store, 'C:\\Users\\Example\\EDIS\\bundle.zip', 'c:/users/example/edis/bundle.zip'));
        self::assertTrue($withinRoot->invoke($store, 'C:\\EDIS\\bundles\\bundle.zip', 'c:/edis/bundles'));
        self::assertFalse($withinRoot->invoke($store, 'C:\\EDIS\\bundles-evil\\bundle.zip', 'c:/edis/bundles'));
        self::assertFalse($samePath->invoke($store, 'C:/edis/bundles/../outside.zip', 'C:/edis/outside.zip'));
    }

    public function testPosixComparisonRemainsCaseSensitive(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX case-sensitivity assertion is not applicable on Windows.');
        }
        $store = new ExportFileStore(new SettingsRepository(), sys_get_temp_dir());
        $samePath = new \ReflectionMethod($store, 'samePath');
        self::assertFalse($samePath->invoke($store, '/tmp/EDIS/Bundle.zip', '/tmp/edis/Bundle.zip'));
    }

    /** @return array{ExportFileStore,array{path:string,sha256:string,size:int,token:string,expires_at:int}} */
    private function bundleFixture(string $jobId): array
    {
        $base = $this->temporaryDirectory();
        $root = $base . DIRECTORY_SEPARATOR . 'bundles';
        $store = new ExportFileStore(new SettingsRepository(), $root);
        $bundle = $store->createBundle($jobId, ['proof.txt' => 'EDIS-' . $jobId], time() + 3600);
        return [$store, $bundle];
    }

    private function mutateMetadata(string $bundlePath, callable $mutator): void
    {
        $metadataPath = $bundlePath . '.json';
        $metadata = json_decode((string) file_get_contents($metadataPath), true, 32, JSON_THROW_ON_ERROR);
        self::assertIsArray($metadata);
        $mutator($metadata);
        file_put_contents($metadataPath, CanonicalJson::encode($metadata));
    }

    private function temporaryDirectory(): string
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'edis-localwp-download-' . bin2hex(random_bytes(8));
        mkdir($base, 0750, true);
        $this->cleanup[] = $base;
        return $base;
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . DIRECTORY_SEPARATOR . $entry);
            }
        }
        @rmdir($path);
    }
}
