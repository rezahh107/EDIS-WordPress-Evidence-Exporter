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
        self::assertSame('3.7.11', $plan['plugin_version'] ?? null);
        self::assertFalse($plan['runtime_feature_change'] ?? true);
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
}
