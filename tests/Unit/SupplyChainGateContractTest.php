<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SupplyChainGateContractTest extends TestCase
{
    public function testWorkflowPolicyRequiresImmutableActionReferencesAndLockedComposerDependencies(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertFileExists($root . '/tools/ci/check-github-actions-pinning.mjs');
        self::assertFileExists($root . '/tools/ci/github-actions-policy.json');
        $policy = json_decode((string) file_get_contents($root . '/tools/ci/github-actions-policy.json'), true);
        self::assertIsArray($policy);
        self::assertSame('EDIS-GITHUB-ACTIONS-POLICY-1', $policy['schema_version'] ?? null);
        self::assertSame([], $policy['rolling_refs'] ?? null);

        $workflow = (string) file_get_contents($root . '/.github/workflows/quality.yml');
        self::assertStringContainsString('actions/checkout@34e114876b0b11c390a56381ad16ebd13914f8d5', $workflow);
        self::assertStringContainsString('shivammathur/setup-php@f3e473d116dcccaddc5834248c87452386958240', $workflow);
        self::assertStringContainsString('test -f composer.lock', $workflow);
        self::assertStringContainsString('composer audit --locked', $workflow);
        self::assertStringNotContainsString("if: hashFiles('composer.lock') != ''", $workflow);
        self::assertStringContainsString('EDIS_EVIDENCE_EXPORTER_VERSION', $workflow);
        self::assertStringNotContainsString("grep '^3.7.7$'", $workflow);
        self::assertStringContainsString('wp-cli-2.12.0.phar', $workflow);
        self::assertStringContainsString('sha512sum --check --strict', $workflow);
    }

    public function testPackageScriptsRunLocalQualityGates(): void
    {
        $package = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/package.json'), true);
        self::assertSame('3.7.11', $package['version'] ?? null);
        self::assertStringContainsString('check-js.mjs', $package['scripts']['lint:js'] ?? '');
        self::assertStringContainsString('check-css.mjs', $package['scripts']['lint:css'] ?? '');
        self::assertStringContainsString('github-actions-policy.json', $package['scripts']['lint:workflows'] ?? '');
        self::assertStringContainsString('--strict-sha', $package['scripts']['lint:workflows:strict'] ?? '');
    }

    public function testPluginManifestListsEveryRepositoryFile(): void
    {
        $root = dirname(__DIR__, 2);
        $manifest = json_decode((string) file_get_contents($root . '/plugin.manifest.json'), true);
        self::assertIsArray($manifest);
        $listed = [];
        foreach ((array) ($manifest['files'] ?? []) as $entry) {
            if (is_array($entry) && is_string($entry['path'] ?? null)) {
                $listed[$entry['path']] = true;
            }
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        $missing = [];
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->isLink()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if (str_starts_with($relative, 'vendor/')
                || str_starts_with($relative, 'node_modules/')
                || str_starts_with($relative, '.git/')) {
                continue;
            }
            if (!isset($listed[$relative])) {
                $missing[] = $relative;
            }
        }
        sort($missing, SORT_STRING);
        self::assertSame([], $missing, 'plugin.manifest.json omits repository files.');
    }

}
