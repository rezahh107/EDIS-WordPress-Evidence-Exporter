<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\InstallationIntegrity;
use PHPUnit\Framework\TestCase;

final class InstallationIntegrityTest extends TestCase
{
    private ?string $root = null;

    protected function tearDown(): void
    {
        if ($this->root !== null) {
            $this->remove($this->root);
        }
    }

    public function testCriticalFileManifestDetectsMixedInstallation(): void
    {
        $this->root = sys_get_temp_dir() . '/edis-integrity-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0750, true);
        mkdir($this->root . '/src', 0750, true);
        file_put_contents($this->root . '/src/Bootstrap.php', 'version-a');
        $manifest = [
            'format' => 'EDIS-INTEGRITY-1',
            'plugin_version' => '3.7.11',
            'files' => [
                'src/Bootstrap.php' => 'sha256:' . hash('sha256', 'version-a'),
            ],
        ];
        file_put_contents($this->root . '/config/critical-files.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        self::assertSame('PASS', InstallationIntegrity::verify($this->root . '/')['state']);

        file_put_contents($this->root . '/src/Bootstrap.php', 'version-b');
        $result = InstallationIntegrity::verify($this->root . '/');
        self::assertSame('FAIL', $result['state']);
        self::assertSame('EDIS_INSTALLATION_MIXED_VERSION', $result['code']);
        self::assertSame('src/Bootstrap.php', $result['failures'][0]['path']);
    }


    public function testBundledCriticalFileManifestPasses(): void
    {
        $root = dirname(__DIR__, 2) . '/';
        $result = InstallationIntegrity::verify($root);
        self::assertSame('PASS', $result['state']);
        self::assertSame('EDIS_INSTALLATION_INTEGRITY_PASS', $result['code']);
        self::assertSame('3.7.11', $result['version']);
    }

    private function remove(string $path): void
    {
        if (is_file($path) || is_link($path)) {
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
