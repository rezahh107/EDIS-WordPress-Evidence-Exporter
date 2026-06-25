<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;
use PHPUnit\Framework\TestCase;

final class LocalWpStorageRegressionTest extends TestCase
{
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
}
