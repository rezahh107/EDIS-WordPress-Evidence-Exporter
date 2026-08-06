<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/tools/ci/check-failure-conformance.php';

final class FailureSurfaceConformanceTest extends TestCase
{
    public function testStaticFailureSurfaceAuthorityIsStructurallyValid(): void
    {
        $errors = \edis_failure_conformance_validate(dirname(__DIR__, 2), false);
        self::assertSame([], $errors, implode(PHP_EOL, $errors));
    }

    public function testFinalModeRejectsLegacyOrPartialClaimsUntilRolloutCompletes(): void
    {
        $errors = \edis_failure_conformance_validate(dirname(__DIR__, 2), true);
        self::assertNotSame([], $errors);
        self::assertTrue(
            count(array_filter($errors, static fn (string $error): bool => str_contains($error, 'final conformance status'))) > 0,
            implode(PHP_EOL, $errors),
        );
    }

    public function testValidatorRejectsDuplicateIdsUnknownValuesAndMissingEvidence(): void
    {
        $root = sys_get_temp_dir() . '/edis-failure-surface-' . bin2hex(random_bytes(6));
        mkdir($root . '/config', 0777, true);
        mkdir($root . '/src', 0777, true);
        file_put_contents($root . '/config/failure-surfaces.json', json_encode([
            'schema_version' => '1.0.0',
            'architecture_id' => 'BOUNDED_HYBRID_SOURCE_FAILURE_CONTRACT_AND_OPERATION_BOUNDARY_POLICY',
            'runtime_dependency' => false,
            'families' => [
                [
                    'id' => 'REST_REQUEST_VALIDATION',
                    'classification_owner' => 'test',
                    'classification' => 'UNKNOWN_CLASSIFICATION',
                    'creation_policy' => 'UNKNOWN_POLICY',
                    'status' => 'UNKNOWN_STATUS',
                    'production_entrypoints' => ['missing.php'],
                    'required_tests' => ['missing-test.php'],
                ],
                [
                    'id' => 'REST_REQUEST_VALIDATION',
                    'classification_owner' => 'test',
                    'classification' => 'EXPECTED_REJECTION',
                    'creation_policy' => 'NONE',
                    'status' => 'CONFORMING',
                    'production_entrypoints' => ['missing.php'],
                    'required_tests' => ['missing-test.php'],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        try {
            $errors = \edis_failure_conformance_validate($root, true);
            self::assertTrue(count(array_filter($errors, static fn (string $error): bool => str_contains($error, 'Duplicate operation-family id'))) === 1);
            self::assertTrue(count(array_filter($errors, static fn (string $error): bool => str_contains($error, 'unknown status'))) === 1);
            self::assertTrue(count(array_filter($errors, static fn (string $error): bool => str_contains($error, 'unknown creation policy'))) === 1);
            self::assertTrue(count(array_filter($errors, static fn (string $error): bool => str_contains($error, 'unknown classification'))) === 1);
            self::assertTrue(count(array_filter($errors, static fn (string $error): bool => str_contains($error, 'Missing required operation family'))) > 0);
            self::assertTrue(count(array_filter($errors, static fn (string $error): bool => str_contains($error, 'missing evidence path'))) > 0);
        } finally {
            @unlink($root . '/config/failure-surfaces.json');
            @rmdir($root . '/config');
            @rmdir($root . '/src');
            @rmdir($root);
        }
    }
}
