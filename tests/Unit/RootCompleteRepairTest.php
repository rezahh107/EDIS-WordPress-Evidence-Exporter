<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Admin\Settings\SettingsRepository;
use EDIS\EvidenceExporter\Application\ExportJobService;
use EDIS\EvidenceExporter\Application\ExportService;
use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;
use EDIS\EvidenceExporter\Infrastructure\Bundle\BridgeContextProcessor;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Support\ArtifactStore;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportFileStore;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportIntegrityException;
use EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use PHPUnit\Framework\TestCase;

final class RootCompleteRepairTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $path) {
            $this->remove($path);
        }
    }

    /** T-G1-01 + T-G1-02 */
    public function testPackageContractFailureIsTypedBoundedNonRetryableAndAttributedToPackaging(): void
    {
        $root = $this->tempRoot();
        $emptyPluginRoot = $root . '/empty-plugin';
        mkdir($emptyPluginRoot . '/schemas', 0777, true);
        [$service, , $jobs, $inputs] = $this->service($root, $emptyPluginRoot);
        $job = $this->persistJob($jobs, $inputs, 'package-contract-fail', [
            'status' => 'queued',
            'phase' => 'collecting',
            'current_component' => 'environment',
        ]);

        try {
            $service->advance((string) $job['job_id'], 7, null, 5000);
            self::fail('Expected package contract validation to fail.');
        } catch (ExportIntegrityException $exception) {
            self::assertSame('EDIS_PACKAGE_CONTRACT_VALIDATION_FAILED', $exception->diagnosticCode);
            self::assertSame('package_contract_validation', $exception->diagnosticContext['failure_phase'] ?? null);
            self::assertTrue(array_key_exists('failed_checks', $exception->diagnosticContext));
            self::assertStringNotContainsString($root, json_encode($exception->diagnosticContext, JSON_THROW_ON_ERROR));
        }

        $failed = $jobs->get((string) $job['job_id']);
        self::assertIsArray($failed);
        self::assertSame('failed', $failed['status']);
        self::assertSame('failed', $failed['phase']);
        self::assertNull($failed['current_component']);
        self::assertSame('EDIS_PACKAGE_CONTRACT_VALIDATION_FAILED', $failed['last_error_code']);
        self::assertNull($failed['next_retry_at']);
        $diagnostic = $this->lastDiagnostic($failed);
        self::assertSame('packaging', $diagnostic['context']['failure_phase'] ?? null);
        self::assertSame(ExportIntegrityException::class, $diagnostic['context']['exception_class'] ?? null);
        self::assertFalse(array_key_exists('message', $diagnostic['context']));
    }

    /** T-G1-01 */
    public function testFinalPackageIntegrityFailureHasStableTypedCodeAndBoundedContext(): void
    {
        $root = $this->tempRoot();
        $pluginRoot = $root . '/plugin';
        $this->copyDirectory($this->realPluginRoot() . 'schemas', $pluginRoot . '/schemas');
        file_put_contents($pluginRoot . '/schemas/package-manifest.schema.json', "{\"type\":\"array\"}\n");
        [$service, $exporter, $jobs, $inputs] = $this->service($root, $pluginRoot);
        unset($service);
        $job = $this->persistJob($jobs, $inputs, 'package-final-integrity', [
            'status' => 'queued',
            'phase' => 'packaging',
        ]);
        $context = $this->context($job);

        try {
            $exporter->package(
                (string) $job['job_id'],
                $context,
                [],
                new ArtifactStore($root . '/artifacts-final'),
                new ExportFileStore(new SettingsRepository(), $root . '/bundles-final'),
                (int) $job['expires_at'],
            );
            self::fail('Expected final package integrity validation to fail.');
        } catch (ExportIntegrityException $exception) {
            self::assertSame('EDIS_PACKAGE_FINAL_INTEGRITY_FAILED', $exception->diagnosticCode);
            self::assertSame('package_final_integrity', $exception->diagnosticContext['failure_phase'] ?? null);
            self::assertContains(
                $exception->diagnosticContext['validation_stage'] ?? null,
                ['pre_validation_report_finalization', 'post_validation_report_finalization'],
            );
            self::assertStringNotContainsString($root, json_encode($exception->diagnosticContext, JSON_THROW_ON_ERROR));
        }
    }

    /** T-G1-03 */
    public function testGenericPackagingIoFailureRemainsRetryableWithBoundedPhaseAndClass(): void
    {
        $root = $this->tempRoot();
        $blocked = $root . '/blocked-root';
        file_put_contents($blocked, 'not-a-directory');
        [$service, , $jobs, $inputs] = $this->service($root, $this->realPluginRoot(), $blocked . '/bundles');
        $job = $this->persistJob($jobs, $inputs, 'package-io-fail', [
            'status' => 'queued',
            'phase' => 'packaging',
        ]);
        $before = time();

        try {
            $service->advance((string) $job['job_id'], 7, null, 5000);
            self::fail('Expected bundle storage failure.');
        } catch (\RuntimeException) {
        }

        $failed = $jobs->get((string) $job['job_id']);
        self::assertIsArray($failed);
        self::assertSame('EDIS_EXPORT_ADVANCE_FAILED', $failed['last_error_code']);
        self::assertGreaterThan($before, (int) $failed['next_retry_at']);
        $diagnostic = $this->lastDiagnostic($failed);
        self::assertSame('packaging', $diagnostic['context']['failure_phase'] ?? null);
        self::assertSame(\RuntimeException::class, $diagnostic['context']['exception_class'] ?? null);
        self::assertFalse(array_key_exists('message', $diagnostic['context']));
    }

    /** T-G2-01 */
    public function testDueFailedJobRecoversThroughResumeAndCompletes(): void
    {
        $root = $this->tempRoot();
        [$service, , $jobs, $inputs] = $this->service($root);
        $job = $this->persistJob($jobs, $inputs, 'due-retry', [
            'status' => 'failed',
            'phase' => 'failed',
            'next_retry_at' => time() - 1,
            'last_error_code' => 'EDIS_EXPORT_ADVANCE_FAILED',
        ]);

        $service->process((string) $job['job_id']);

        $completed = $jobs->get((string) $job['job_id']);
        self::assertIsArray($completed);
        self::assertSame('completed', $completed['status']);
        self::assertSame('completed', $completed['phase']);
        self::assertSame(100, $completed['progress']);
        self::assertSame('PASS', $completed['validation_state']);
        self::assertNull($completed['next_retry_at']);
        self::assertContains('EDIS_EXPORT_RESUMED', array_column((array) $completed['diagnostics'], 'code'));
    }

    /** T-G2-02 */
    public function testFutureRetryDoesNotAdvanceOrMutate(): void
    {
        $root = $this->tempRoot();
        [$service, , $jobs, $inputs] = $this->service($root);
        $job = $this->persistJob($jobs, $inputs, 'future-retry', [
            'status' => 'failed',
            'phase' => 'failed',
            'next_retry_at' => time() + 3600,
        ]);
        $before = $jobs->get((string) $job['job_id']);

        $service->process((string) $job['job_id']);

        self::assertSame($before, $jobs->get((string) $job['job_id']));
    }

    /** T-G2-03 */
    public function testNonRetryableFailedJobDoesNotAdvanceOrMutate(): void
    {
        $root = $this->tempRoot();
        [$service, , $jobs, $inputs] = $this->service($root);
        $job = $this->persistJob($jobs, $inputs, 'nonretryable-fail', [
            'status' => 'failed',
            'phase' => 'failed',
            'next_retry_at' => null,
            'last_error_code' => 'EDIS_PACKAGE_CONTRACT_VALIDATION_FAILED',
        ]);
        $before = $jobs->get((string) $job['job_id']);

        $service->process((string) $job['job_id']);

        self::assertSame($before, $jobs->get((string) $job['job_id']));
    }

    /** T-G2-04 */
    public function testQueuedAndStaleLeaseRecoveryRemainsValid(): void
    {
        $root = $this->tempRoot();
        $jobs = new JobStore($root . '/jobs-recovery');
        $now = time();
        $jobs->create([
            'job_id' => 'queued-job',
            'status' => 'queued',
            'expires_at' => $now + 3600,
            'lease_owner' => null,
            'lease_expires_at' => null,
        ]);
        $jobs->create([
            'job_id' => 'stale-running-job',
            'status' => 'running',
            'phase' => 'collecting',
            'expires_at' => $now + 3600,
            'lease_owner' => 'worker-old',
            'lease_acquired_at' => $now - 600,
            'lease_expires_at' => $now - 300,
            'diagnostics' => [],
        ]);

        $batch = $jobs->recoveryBatch();

        self::assertContains('queued-job', $batch['runnable']);
        self::assertContains('stale-running-job', $batch['repaired']);
        self::assertContains('stale-running-job', $batch['runnable']);
        $repaired = $jobs->get('stale-running-job');
        self::assertSame('queued', $repaired['status']);
        self::assertSame('REPAIRED_EXPIRED_LEASE', $repaired['schedule_state']);
    }

    /** T-G3-01 */
    public function testDefaultBundleProcessorsReceiveTransitiveRequiredDependencyClosure(): void
    {
        $registry = $this->registry();
        $plan = $registry->executionPlan([]);
        $positions = array_flip($plan);
        foreach ($registry->definitions() as $definition) {
            if ($definition->componentType !== ComponentType::BUNDLE_PROCESSOR || !$definition->defaultEnabled) {
                continue;
            }
            self::assertTrue(array_key_exists($definition->id, $positions));
            $this->assertRequiredDependenciesPrecede($registry, $definition->id, $positions, []);
        }
    }

    /** T-G3-02 */
    public function testBridgeMissingOrMalformedRequiredInputsFailClosed(): void
    {
        $processor = new BridgeContextProcessor();
        $context = new CollectionContext([], false, 'analysis', 'bundle', 'Strict', [
            'export_scope' => 'METADATA_ONLY',
            'dependency_scope' => 'SOURCE_ONLY',
        ]);
        $cases = [
            [],
            ['environment' => ['data' => []]],
            ['elementor_document_index' => ['data' => ['documents' => []]]],
            ['environment' => ['data' => 'malformed'], 'elementor_document_index' => ['data' => ['documents' => []]]],
            ['environment' => ['data' => []], 'elementor_document_index' => ['data' => ['documents' => 'malformed']]],
        ];
        foreach ($cases as $artifacts) {
            $result = $processor->collect($context, $artifacts);
            self::assertSame(TruthState::UNKNOWN, $result->truthState);
            self::assertSame(EvidenceAvailability::UNAVAILABLE, $result->availability);
            self::assertNull($result->data);
            self::assertSame('EDIS_BRIDGE_REQUIRED_INPUT_MISSING', $result->diagnostics[0]->code);
        }
    }

    /** T-G3-03 */
    public function testBridgeValidRequiredInputsPreserveProjectionSemantics(): void
    {
        $processor = new BridgeContextProcessor();
        $context = new CollectionContext([], false, 'analysis', 'bundle', 'Strict', [
            'export_scope' => 'METADATA_ONLY',
            'dependency_scope' => 'SOURCE_ONLY',
        ]);
        $result = $processor->collect($context, [
            'environment' => ['data' => ['multisite' => false, 'site_path_scope' => '/', 'site_locator_candidates' => []]],
            'elementor_document_index' => ['data' => ['documents' => []], 'semantic_payload_sha256' => 'sha256:' . str_repeat('0', 64)],
            'elementor_element_structure_index' => ['data' => ['elements' => []], 'semantic_payload_sha256' => 'sha256:' . str_repeat('1', 64)],
        ]);
        self::assertSame(TruthState::VERIFIED, $result->truthState);
        self::assertSame(EvidenceAvailability::AVAILABLE, $result->availability);
        self::assertIsArray($result->data);
        self::assertSame('READY', $result->data['bridge_readiness']);
        self::assertSame('bundle', $result->data['wordpress_bundle_id']);
    }

    /** T-G4-01 */
    public function testExplicitUnknownCollectorIsRejectedWithoutDefaultSubstitution(): void
    {
        [$service] = $this->service($this->tempRoot());
        $method = new \ReflectionMethod($service, 'normalizeRequest');
        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($service, $this->metadataRequest(['collector_that_does_not_exist']));
    }

    /** T-G4-02 */
    public function testMixedValidAndInvalidCollectorSelectionRejectsWholeRequest(): void
    {
        [$service] = $this->service($this->tempRoot());
        $method = new \ReflectionMethod($service, 'normalizeRequest');
        try {
            $method->invoke($service, $this->metadataRequest(['environment', 'collector_that_does_not_exist']));
            self::fail('Expected mixed collector request to be rejected.');
        } catch (\Throwable $exception) {
            self::assertInstanceOf(\InvalidArgumentException::class, $exception->getPrevious() ?? $exception);
        }

        $registry = $this->registry();
        foreach ($registry->definitions() as $definition) {
            if ($definition->selectable && $registry->isExecutable($definition->id)) {
                continue;
            }
            try {
                $method->invoke($service, $this->metadataRequest(['environment', $definition->id]));
                self::fail('Expected non-selectable/non-executable collector request to be rejected.');
            } catch (\Throwable $exception) {
                self::assertInstanceOf(\InvalidArgumentException::class, $exception->getPrevious() ?? $exception);
            }
            break;
        }
    }

    /** T-G4-03 */
    public function testTrueOmissionUsesDefaultsAndValidExplicitSelectionRemainsAccepted(): void
    {
        [$service] = $this->service($this->tempRoot());
        $method = new \ReflectionMethod($service, 'normalizeRequest');
        $omitted = $this->metadataRequest(null);
        unset($omitted['collectors']);
        $normalizedDefault = $method->invoke($service, $omitted);
        self::assertTrue($normalizedDefault['collectors'] !== []);

        $normalizedExplicit = $method->invoke($service, $this->metadataRequest(['environment']));
        self::assertContains('environment', $normalizedExplicit['collectors']);
    }

    /** C-10 */
    public function testPersisted3711JobIsRejectedBy372CompatibilityBoundary(): void
    {
        [$service] = $this->service($this->tempRoot());
        $method = new \ReflectionMethod($service, 'assertJobCompatible');
        try {
            $method->invoke($service, [
                'job_format_version' => '2.1.0',
                'input_snapshot_format_version' => '2.0.0',
                'implementation_version' => '3.7.11',
            ]);
            self::fail('Expected 3.7.11 persisted job to be rejected.');
        } catch (\Throwable $exception) {
            $actual = $exception->getPrevious() ?? $exception;
            self::assertInstanceOf(ExportIntegrityException::class, $actual);
            self::assertSame('EDIS_JOB_FORMAT_INCOMPATIBLE', $actual->diagnosticCode);
            self::assertStringContainsString('Create a new export job', $actual->getMessage());
        }
    }

    /** @return array{ExportJobService,ExportService,JobStore,InputSnapshotStore,CollectorRegistry} */
    private function service(string $root, ?string $exporterPluginRoot = null, ?string $bundleRoot = null): array
    {
        $registry = $this->registry();
        $settings = new SettingsRepository();
        $jobs = new JobStore($root . '/jobs');
        $artifacts = new ArtifactStore($root . '/artifacts');
        $inputs = new InputSnapshotStore($root . '/inputs', static fn (int $id): ?array => null);
        $exporter = new ExportService($registry, $exporterPluginRoot ?? $this->realPluginRoot());
        $service = new ExportJobService(
            $registry,
            $exporter,
            $jobs,
            $artifacts,
            new ExportFileStore($settings, $bundleRoot ?? $root . '/bundles'),
            $settings,
            $inputs,
        );
        return [$service, $exporter, $jobs, $inputs, $registry];
    }

    /** @param array<string,mixed> $overrides @return array<string,mixed> */
    private function persistJob(JobStore $jobs, InputSnapshotStore $inputs, string $jobId, array $overrides = []): array
    {
        $expiresAt = time() + 3600;
        $snapshot = $inputs->capture($jobId, [], $expiresAt);
        $job = [
            'job_id' => $jobId,
            'job_format_version' => '2.1.0',
            'implementation_version' => '3.7.12',
            'input_snapshot_format_version' => '2.0.0',
            'input_snapshot_id' => $jobId,
            'input_snapshot_sha256' => $snapshot['snapshot_sha256'],
            'owner_id' => 7,
            'status' => 'queued',
            'phase' => 'packaging',
            'progress' => 88,
            'analysis_set_id' => 'analysis-' . $jobId,
            'wordpress_bundle_id' => 'bundle-' . $jobId,
            'created_at' => time(),
            'expires_at' => $expiresAt,
            'cursor' => 0,
            'current_component' => null,
            'last_heartbeat' => time(),
            'last_successful_step_at' => null,
            'attempt_count' => 0,
            'last_error_code' => null,
            'last_error_at' => null,
            'next_retry_at' => null,
            'stale_after' => 120,
            'lease_owner' => null,
            'lease_acquired_at' => null,
            'lease_expires_at' => null,
            'schedule_state' => 'NOT_SCHEDULED',
            'schedule_error' => null,
            'selected_components' => [],
            'completed_components' => [],
            'completed_step_records' => [],
            'diagnostics' => [],
            'validation_state' => 'NOT_RUN',
            'config' => [
                'privacy_mode' => 'Strict',
                'document_ids' => [],
                'include_original_documents' => false,
                'options' => ['export_scope' => 'METADATA_ONLY', 'dependency_scope' => 'SOURCE_ONLY'],
                'captured_at' => '2026-07-30T00:00:00Z',
            ],
        ];
        return $jobs->create(array_replace($job, $overrides));
    }

    /** @param array<string,mixed> $job */
    private function context(array $job): CollectionContext
    {
        $config = (array) $job['config'];
        return new CollectionContext(
            [],
            false,
            (string) $job['analysis_set_id'],
            (string) $job['wordpress_bundle_id'],
            'Strict',
            (array) $config['options'],
            (string) $config['captured_at'],
        );
    }

    /** @return array<string,mixed> */
    private function metadataRequest(?array $collectors): array
    {
        $request = [
            'privacy_mode' => 'Strict',
            'document_ids' => [],
            'options' => ['export_scope' => 'METADATA_ONLY', 'dependency_scope' => 'SOURCE_ONLY'],
        ];
        if ($collectors !== null) {
            $request['collectors'] = $collectors;
        }
        return $request;
    }

    private function registry(): CollectorRegistry
    {
        /** @var list<\EDIS\EvidenceExporter\Infrastructure\Collector\CollectorDefinition> $definitions */
        $definitions = require $this->realPluginRoot() . 'config/collectors.php';
        return CollectorRegistry::fromDefinitions($definitions);
    }

    /** @param array<string,int> $positions @param array<string,bool> $seen */
    private function assertRequiredDependenciesPrecede(CollectorRegistry $registry, string $componentId, array $positions, array $seen): void
    {
        if (isset($seen[$componentId])) {
            return;
        }
        $seen[$componentId] = true;
        foreach ($registry->definition($componentId)->dependencies as $dependency) {
            if (($dependency['kind'] ?? null) !== 'REQUIRED') {
                continue;
            }
            $dependencyId = (string) $dependency['id'];
            self::assertTrue(array_key_exists($dependencyId, $positions));
            self::assertLessThan($positions[$componentId], $positions[$dependencyId]);
            $this->assertRequiredDependenciesPrecede($registry, $dependencyId, $positions, $seen);
        }
    }

    /** @param array<string,mixed> $job @return array<string,mixed> */
    private function lastDiagnostic(array $job): array
    {
        $diagnostics = (array) ($job['diagnostics'] ?? []);
        $last = end($diagnostics);
        self::assertIsArray($last);
        return $last;
    }

    private function realPluginRoot(): string
    {
        return dirname(__DIR__, 2) . '/';
    }

    private function tempRoot(): string
    {
        $root = sys_get_temp_dir() . '/edis-root-repair-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $this->cleanup[] = $root;
        return $root;
    }

    private function copyDirectory(string $source, string $target): void
    {
        if (!is_dir($target)) {
            mkdir($target, 0777, true);
        }
        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $sourcePath = $source . '/' . $entry;
            $targetPath = $target . '/' . $entry;
            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $targetPath);
            } else {
                copy($sourcePath, $targetPath);
            }
        }
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
