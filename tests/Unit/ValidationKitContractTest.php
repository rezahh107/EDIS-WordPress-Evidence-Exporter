<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ValidationKitContractTest extends TestCase
{
    public function testValidationPlanDoesNotClaimUnexecutedExternalGates(): void
    {
        $root = dirname(__DIR__, 2);
        $plan = json_decode((string) file_get_contents($root . '/validation/validation-plan.json'), true);
        self::assertIsArray($plan);
        self::assertSame('EDIS-VALIDATION-PLAN-2', $plan['schema_version'] ?? null);
        self::assertSame('EDIS-VALIDATION-EVIDENCE-2', $plan['evidence_schema_version'] ?? null);
        self::assertSame('3.7.15', $plan['plugin_version'] ?? null);
        self::assertSame('payload_shape_closure_v3_7_15', $plan['scope'] ?? null);
        self::assertTrue($plan['runtime_feature_change'] ?? false);
        self::assertFalse($plan['frozen_contract_change'] ?? true);
        self::assertSame('all_required_local_gates_must_pass', $plan['local_completion_policy'] ?? null);
        self::assertSame('all_required_external_gates_must_pass', $plan['strict_external_policy'] ?? null);

        $states = [];
        foreach ((array) ($plan['gates'] ?? []) as $gate) {
            if (is_array($gate) && is_string($gate['id'] ?? null)) {
                $states[$gate['id']] = $gate['state'] ?? null;
            }
        }
        self::assertSame('blocked_external', $states['composer_lock'] ?? null);
        self::assertSame('configured_not_run', $states['plugin_check'] ?? null);
        self::assertSame('insufficient_evidence', $states['elementor_real_fixtures'] ?? null);
        self::assertSame('not_run', $states['windows_localwp'] ?? null);
    }

    public function testRealFixtureRegistryStartsFailClosedWithoutSyntheticClaims(): void
    {
        $root = dirname(__DIR__, 2);
        $manifest = json_decode((string) file_get_contents($root . '/tests/fixtures/elementor-real/fixtures-manifest.json'), true);
        self::assertIsArray($manifest);
        self::assertSame('insufficient_evidence', $manifest['verification_state'] ?? null);
        self::assertSame([], $manifest['fixtures'] ?? null);
        $readme = (string) file_get_contents($root . '/tests/fixtures/elementor-real/README.md');
        self::assertStringContainsString('no synthetic file presented as a real Elementor export', $readme);
    }

    public function testValidationKitIsExcludedFromInstallProfile(): void
    {
        $root = dirname(__DIR__, 2);
        $distIgnore = (string) file_get_contents($root . '/.distignore');
        self::assertStringContainsString('/validation', $distIgnore);
        self::assertStringContainsString('/tools', $distIgnore);
        self::assertStringContainsString('/tests', $distIgnore);
        self::assertFileExists($root . '/tools/validation/run-local-validation.php');
        self::assertFileExists($root . '/tools/validation/run-local-validation.ps1');
        self::assertFileExists($root . '/tools/validation/ValidationSupport.php');
    }

    public function testReleaseDocumentationBoundsRecoveryToBootstrapDegradedCauses(): void
    {
        $root = dirname(__DIR__, 2);
        $readme = (string) file_get_contents($root . '/README.md');
        $wordpressReadme = (string) file_get_contents($root . '/readme.txt');

        foreach ([$readme, $wordpressReadme] as $document) {
            self::assertStringContainsString('edis_evidence_exporter_runtime_notice', $document);
            self::assertStringContainsString('before `Bootstrap`', $document);
            self::assertStringContainsString('installation-integrity failure', $document);
            self::assertStringContainsString('invalid configuration', $document);
            self::assertStringContainsString('private-storage failure', $document);
            self::assertStringContainsString('DegradedModeIntegration', $document);
        }

        self::assertStringNotContainsString(
            'When runtime, installation-integrity, configuration, or private-storage gates fail closed',
            $wordpressReadme
        );
        self::assertStringNotContainsString(
            'remain unavailable until the existing runtime, integrity, configuration and private-storage gates pass',
            $readme
        );

        self::assertStringContainsString('Create Export, job operations, downloads, worker tests', $readme);
        self::assertStringContainsString('operational REST controllers', $readme);
        self::assertStringContainsString('export, worker, download, and operational REST controls remain unavailable', $wordpressReadme);
    }

    public function testRecoveryCatalogsTrackPluginVersionAndCurrentGettextLiterals(): void
    {
        $root = dirname(__DIR__, 2);
        $entrypoint = (string) file_get_contents($root . '/edis-evidence-exporter.php');
        self::assertSame(1, preg_match('/^\s*\* Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$/m', $entrypoint, $versionMatch));
        $version = $versionMatch[1];

        $source = (string) file_get_contents($root . '/src/WordPress/DegradedModeIntegration.php');
        $messages = $this->extractRecoveryMessages($source);
        self::assertNotSame([], $messages);

        foreach (['edis-evidence-exporter.pot', 'edis-evidence-exporter-fa_IR.po'] as $catalogName) {
            $catalog = (string) file_get_contents($root . '/languages/' . $catalogName);
            self::assertStringContainsString(
                'Project-Id-Version: EDIS WordPress Evidence Exporter ' . $version . '\\n',
                $catalog,
                $catalogName . ' version header drifted from the plugin entrypoint.'
            );
            $msgids = $this->extractCatalogMsgids($catalog);
            foreach ($messages as $message) {
                self::assertContains($message, $msgids, $catalogName . ' is missing recovery msgid: ' . $message);
            }
        }
    }

    /** @return list<string> */
    private function extractRecoveryMessages(string $source): array
    {
        $pattern = "/(?:__|esc_html__|esc_attr__|_e|esc_html_e|esc_attr_e)\\(\\s*'((?:\\\\'|[^'])*)'\\s*,\\s*'edis-evidence-exporter'\\s*\\)/";
        preg_match_all($pattern, $source, $matches);
        $messages = [];
        foreach ($matches[1] ?? [] as $message) {
            if (! is_string($message)) {
                continue;
            }
            $message = str_replace(["\\\\", "\\'"], ["\\", "'"], $message);
            if (! in_array($message, $messages, true)) {
                $messages[] = $message;
            }
        }
        return $messages;
    }

    /** @return list<string> */
    private function extractCatalogMsgids(string $catalog): array
    {
        preg_match_all('/^msgid "((?:[^"\\\\]|\\\\.)*)"$/m', $catalog, $matches);
        $msgids = [];
        foreach ($matches[1] ?? [] as $encoded) {
            if (! is_string($encoded)) {
                continue;
            }
            $msgids[] = stripcslashes($encoded);
        }
        return $msgids;
    }
}
