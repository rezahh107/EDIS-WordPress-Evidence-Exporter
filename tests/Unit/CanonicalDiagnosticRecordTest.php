<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Application\DiagnosticRecordService;
use EDIS\EvidenceExporter\Application\DiagnosticsService;
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\DiagnosticRecordStore;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportIntegrityException;
use EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use EDIS\EvidenceExporter\Infrastructure\Support\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

final class CanonicalDiagnosticRecordTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $path) {
            $this->remove($path);
        }
    }

    public function testPreJobInputSnapshotFailureProducesAuthorizedResolvableCanonicalArtifact(): void
    {
        [$service, , , $filesystem, $root] = $this->services();
        $inputs = new InputSnapshotStore(
            $root . '/inputs',
            static fn (int $documentId): ?array => null,
            $filesystem,
        );

        try {
            $inputs->capture('snapshot-pre-job-failure', [42], time() + 3600);
            self::fail('Expected the immutable input snapshot capture to fail.');
        } catch (\Throwable $exception) {
            $result = $service->capturePreJobFailure(
                7,
                'EXPORT_CREATE',
                '/edis-evidence-exporter/v3/export-jobs',
                [
                    'privacy_mode' => 'Strict',
                    'collectors' => ['environment'],
                    'document_ids' => [42],
                    'options' => ['export_scope' => 'SINGLE_DOCUMENT'],
                ],
                $exception,
                [
                    'lifecycle_stage' => 'input_snapshot_capture',
                    'subsystem' => 'InputSnapshotStore',
                    'operation_immediately_attempted' => 'capture immutable saved-source snapshot',
                    'last_successful_state' => 'PREFLIGHT_PASSED',
                ],
            );
        }

        self::assertTrue($result['diagnostic_available']);
        self::assertMatchesRegularExpression('/\Aedis-diag-[a-f0-9]{32}\z/D', (string) $result['diagnostic_id']);

        $resolved = $service->resolveForOwner(7, (string) $result['diagnostic_id'], static fn (int $documentId): bool => $documentId === 42);
        self::assertIsArray($resolved);
        self::assertSame('EDIS-DIAGNOSTIC-1', $resolved['record']['schema']['format']);
        self::assertSame('PRE_JOB_FAILURE', $resolved['record']['diagnostic_identity']['record_type']);
        self::assertSame($result['diagnostic_id'], $resolved['record']['diagnostic_identity']['diagnostic_id']);
        self::assertSame('CONFIRMED', $resolved['record']['recorded_facts'][0]['status']);
        self::assertArrayHasKey('unresolved_questions', $resolved['record']);
        self::assertArrayHasKey('model_analysis_not_included', $resolved['record']);
        self::assertSame(CanonicalJson::encode($resolved['record']), $resolved['bytes']);
        self::assertStringNotContainsString('document 42', $resolved['bytes']);
        self::assertNull($service->resolveForOwner(8, (string) $result['diagnostic_id'], static fn (): bool => true));
    }

    public function testSchemaRejectsMissingFactBoundaryAndUnknownRootProperty(): void
    {
        [$service, , , $filesystem] = $this->services();
        $result = $service->capturePreJobFailure(
            7,
            'EXPORT_CREATE',
            '/edis-evidence-exporter/v3/export-jobs',
            ['privacy_mode' => 'Strict', 'collectors' => ['environment'], 'document_ids' => [], 'options' => ['export_scope' => 'METADATA_ONLY']],
            new \RuntimeException('not exported'),
        );
        $resolved = $service->resolveForOwner(7, (string) $result['diagnostic_id']);
        self::assertIsArray($resolved);
        $validator = new JsonSchemaValidator(dirname(__DIR__, 2) . '/', $filesystem);

        $valid = json_decode($resolved['bytes'], false, 512, JSON_THROW_ON_ERROR);
        self::assertSame([], $validator->validate($valid, 'schemas/diagnostic-record.schema.json'));

        $missingBoundary = $resolved['record'];
        unset($missingBoundary['recorded_facts']);
        $missingObject = json_decode(CanonicalJson::encode($missingBoundary), false, 512, JSON_THROW_ON_ERROR);
        self::assertNotSame([], $validator->validate($missingObject, 'schemas/diagnostic-record.schema.json'));

        $extraProperty = $resolved['record'];
        $extraProperty['raw_exception_message'] = '/Users/alice/site token=secret';
        $extraObject = json_decode(CanonicalJson::encode($extraProperty), false, 512, JSON_THROW_ON_ERROR);
        self::assertNotSame([], $validator->validate($extraObject, 'schemas/diagnostic-record.schema.json'));
    }

    public function testPrivacyAllowlistExcludesSecretsPathsMessagesAndSourceContent(): void
    {
        [$service] = $this->services();
        $exception = new ExportIntegrityException(
            'EDIS_INPUT_SNAPSHOT_CAPTURE_FAILED',
            'token=super-secret /Users/alice/Local Sites/private/app/public source=<h1>customer text</h1>',
            null,
            [
                'failure_phase' => 'input_snapshot_capture',
                'path' => '/Users/alice/Local Sites/private/app/public',
                'token' => 'super-secret',
                'source_content' => '<h1>customer text</h1>',
                'component_id' => 'elementor_document_source',
                'path_role' => 'input_snapshot_root',
            ],
        );
        $result = $service->capturePreJobFailure(
            7,
            'EXPORT_CREATE',
            '/edis-evidence-exporter/v3/export-jobs',
            [
                'privacy_mode' => 'Diagnostic',
                'collectors' => ['elementor_document_source'],
                'document_ids' => [42],
                'preflight_token' => 'request-token-that-must-not-appear',
                'options' => ['export_scope' => 'SINGLE_DOCUMENT', 'raw_source' => '<p>private source</p>'],
            ],
            $exception,
            ['lifecycle_stage' => 'input_snapshot_capture', 'subsystem' => 'InputSnapshotStore'],
        );
        $resolved = $service->resolveForOwner(7, (string) $result['diagnostic_id']);
        self::assertIsArray($resolved);
        foreach (['super-secret', '/Users/alice', 'customer text', 'request-token-that-must-not-appear', 'private source'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $resolved['bytes']);
        }
        self::assertSame('input_snapshot_root', $resolved['record']['safe_context']['path_role']);
        self::assertSame('elementor_document_source', $resolved['record']['safe_context']['component_id']);
        self::assertSame(ExportIntegrityException::class, $resolved['record']['failure_classification']['exception_class']);
    }

    public function testSelectedComponentOverflowIsBoundedAndTruncationIsVisible(): void
    {
        [$service] = $this->services();
        $collectors = [];
        for ($index = 0; $index < 80; $index++) {
            $collectors[] = sprintf('collector_%03d', $index);
        }
        $result = $service->capturePreJobFailure(
            7,
            'EXPORT_CREATE',
            '/edis-evidence-exporter/v3/export-jobs',
            ['privacy_mode' => 'Strict', 'collectors' => $collectors, 'document_ids' => [], 'options' => ['export_scope' => 'METADATA_ONLY']],
            new \RuntimeException('not exported'),
        );
        $resolved = $service->resolveForOwner(7, (string) $result['diagnostic_id']);
        self::assertIsArray($resolved);
        self::assertCount(64, $resolved['record']['operation_identity']['selected_components']);
        self::assertTrue($resolved['record']['bounds']['truncated']);
        self::assertSame('operation_identity.selected_components', $resolved['record']['bounds']['truncated_fields'][0]['path']);
        self::assertSame(80, $resolved['record']['bounds']['truncated_fields'][0]['original_count']);
        self::assertSame(64, $resolved['record']['bounds']['truncated_fields'][0]['retained_count']);
        self::assertLessThanOrEqual(DiagnosticRecordStore::MAX_BYTES, strlen($resolved['bytes']));
    }

    public function testJobBoundFailureResolvesExactAuthorizedJobAndHonorsRevokedObjectPermission(): void
    {
        [$service, , $jobs] = $this->services();
        $jobId = '11111111-2222-4333-8444-555555555555';
        $jobs->create([
            'job_id' => $jobId,
            'owner_id' => 7,
            'status' => 'failed',
            'phase' => 'failed',
            'created_at' => time() - 60,
            'expires_at' => time() + 7200,
            'last_error_at' => time(),
            'last_error_code' => 'EDIS_PACKAGE_CONTRACT_VALIDATION_FAILED',
            'next_retry_at' => null,
            'schedule_state' => 'NOT_SCHEDULED',
            'schedule_error' => 'NON_RETRYABLE_INTEGRITY_FAILURE',
            'selected_components' => ['environment'],
            'selected_document_count' => 1,
            'config' => ['privacy_mode' => 'Strict', 'document_ids' => [42], 'options' => ['export_scope' => 'SINGLE_DOCUMENT']],
            'diagnostics' => [[
                'code' => 'EDIS_PACKAGE_CONTRACT_VALIDATION_FAILED',
                'severity' => 'ERROR',
                'scope' => 'SEMANTIC',
                'message_key' => 'diagnostic.export.advance_failed',
                'context' => ['failure_phase' => 'packaging', 'failed_checks' => ['manifest_contract']],
            ]],
        ]);
        $result = $service->captureJobFailure(
            7,
            $jobId,
            'EXPORT_ADVANCE',
            '/edis-evidence-exporter/v3/export-jobs/{job_id}/advance',
            new ExportIntegrityException('EDIS_PACKAGE_CONTRACT_VALIDATION_FAILED', 'raw failure text must not appear'),
            'edis_export_advance_failed',
        );
        self::assertTrue($result['diagnostic_available']);

        $resolved = $service->resolveForOwner(7, (string) $result['diagnostic_id'], static fn (int $documentId): bool => $documentId === 42);
        self::assertIsArray($resolved);
        self::assertSame($jobId, $resolved['record']['operation_identity']['job_id']);
        self::assertSame('JOB_FAILURE', $resolved['record']['diagnostic_identity']['record_type']);
        self::assertSame('NOT_RETRYABLE', $resolved['record']['failure_classification']['retryability']);
        self::assertSame('FAILED', $resolved['record']['failure_classification']['terminal_state']);
        self::assertNull($service->resolveForOwner(8, (string) $result['diagnostic_id'], static fn (): bool => true));
        self::assertNull($service->resolveForOwner(7, (string) $result['diagnostic_id'], static fn (): bool => false));
    }

    public function testOperationalJobFailureUsesPersistedRetryState(): void
    {
        [$service, , $jobs] = $this->services();
        $jobId = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
        $jobs->create([
            'job_id' => $jobId,
            'owner_id' => 7,
            'status' => 'failed',
            'phase' => 'failed',
            'created_at' => time() - 60,
            'expires_at' => time() + 7200,
            'last_error_at' => time(),
            'last_error_code' => 'EDIS_EXPORT_ADVANCE_FAILED',
            'next_retry_at' => time() + 30,
            'schedule_state' => 'SCHEDULED',
            'schedule_error' => null,
            'selected_components' => ['environment'],
            'selected_document_count' => 0,
            'config' => ['privacy_mode' => 'Strict', 'document_ids' => [], 'options' => ['export_scope' => 'METADATA_ONLY']],
            'diagnostics' => [[
                'code' => 'EDIS_EXPORT_ADVANCE_FAILED',
                'severity' => 'ERROR',
                'scope' => 'OPERATIONAL',
                'message_key' => 'diagnostic.export.advance_failed',
                'context' => ['failure_phase' => 'packaging', 'filesystem_operation' => 'write'],
            ]],
        ]);
        $result = $service->captureJobFailure(7, $jobId, 'EXPORT_ADVANCE', null, new \RuntimeException('not exported'));
        $resolved = $service->resolveForOwner(7, (string) $result['diagnostic_id']);
        self::assertIsArray($resolved);
        self::assertSame('OPERATIONAL', $resolved['record']['failure_classification']['scope']);
        self::assertSame('RETRY_SAFE', $resolved['record']['failure_classification']['retryability']);
        self::assertSame('SCHEDULED', $resolved['record']['safe_context']['schedule_state']);
    }

    public function testDiagnosticPersistenceFailureReturnsTruthfulUnavailableResultWithoutRecursion(): void
    {
        $root = $this->tempRoot();
        file_put_contents($root . '/blocked', 'not a directory');
        $filesystem = new DeterministicFilesystem();
        $jobs = new JobStore($root . '/jobs', $filesystem);
        $store = new DiagnosticRecordStore($root . '/blocked/diagnostics', $filesystem, 3600);
        $service = new DiagnosticRecordService($store, $jobs, dirname(__DIR__, 2) . '/', $filesystem);

        $result = $service->capturePreJobFailure(
            7,
            'EXPORT_CREATE',
            '/edis-evidence-exporter/v3/export-jobs',
            ['privacy_mode' => 'Strict', 'collectors' => ['environment'], 'document_ids' => []],
            new \RuntimeException('not exported'),
        );

        self::assertFalse($result['diagnostic_available']);
        self::assertNull($result['diagnostic_id']);
        self::assertSame('EDIS_DIAGNOSTIC_PERSISTENCE_FAILED', $result['diagnostic_persistence_code']);
    }

    public function testExpiredRecordIsUnavailableAndCleanupDoesNotTouchUnrelatedFiles(): void
    {
        [$service, , , $filesystem, $root] = $this->services();
        $result = $service->capturePreJobFailure(
            7,
            'EXPORT_CREATE',
            null,
            ['privacy_mode' => 'Strict', 'collectors' => ['environment'], 'document_ids' => []],
            new \RuntimeException('not exported'),
        );
        $diagnosticId = (string) $result['diagnostic_id'];
        $resolved = $service->resolveForOwner(7, $diagnosticId);
        self::assertIsArray($resolved);
        $expired = $resolved['record'];
        $expired['diagnostic_identity']['expires_at'] = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
        $path = $root . '/diagnostics/user-7/' . $diagnosticId . '.json';
        $filesystem->writeAtomically($path, CanonicalJson::encode($expired));
        $unrelated = $root . '/diagnostics/unrelated.txt';
        file_put_contents($unrelated, 'preserve');

        self::assertNull($service->resolveForOwner(7, $diagnosticId));
        $service->cleanupExpired();
        self::assertFileExists($unrelated);
        self::assertSame('preserve', file_get_contents($unrelated));
    }

    public function testAdminAndBootContractsPreserveCanonicalMetadataAndTruthfulFallbacks(): void
    {
        $root = dirname(__DIR__, 2) . '/';
        $javascript = (string) file_get_contents($root . 'assets/js/diagnostics.js');
        $template = (string) file_get_contents($root . 'templates/admin/diagnostics.php');
        $plugin = (string) file_get_contents($root . 'edis-evidence-exporter.php');
        $degraded = (string) file_get_contents($root . 'src/WordPress/DegradedModeIntegration.php');

        self::assertStringContainsString('diagnosticAvailable', $javascript);
        self::assertStringContainsString('diagnosticId', $javascript);
        self::assertStringContainsString('safeResponseMetadata', $javascript);
        self::assertStringContainsString('copy-canonical-diagnostic', $javascript);
        self::assertStringContainsString('edis-canonical-diagnostic-json', $template);
        self::assertStringContainsString('Current environment health', $template);
        self::assertStringContainsString('EDIS_UNSUPPORTED_RUNTIME', $plugin);
        self::assertStringContainsString('EDIS_BOOTSTRAP_FAILED', $plugin);
        self::assertStringContainsString('diagnostic_available=false', $plugin);
        self::assertStringContainsString('diagnostic_available=false', $degraded);
        self::assertStringNotContainsString('new JobStore', $degraded);
        self::assertStringNotContainsString('new DiagnosticRecordStore', $degraded);
    }

    public function testRunningWorkerJobMapsToInProgressInsteadOfFail(): void
    {
        self::assertSame('PASS', DiagnosticsService::workerTestState('completed'));
        self::assertSame('IN_PROGRESS', DiagnosticsService::workerTestState('queued'));
        self::assertSame('IN_PROGRESS', DiagnosticsService::workerTestState('running'));
        self::assertSame('FAIL', DiagnosticsService::workerTestState('failed'));
        self::assertSame('ABORTED', DiagnosticsService::workerTestState('cancelled'));
        self::assertSame('UNKNOWN', DiagnosticsService::workerTestState('unexpected'));
    }

    /** @return array{DiagnosticRecordService,DiagnosticRecordStore,JobStore,DeterministicFilesystem,string} */
    private function services(): array
    {
        $root = $this->tempRoot();
        $filesystem = new DeterministicFilesystem();
        $store = new DiagnosticRecordStore($root . '/diagnostics', $filesystem, 3600);
        $jobs = new JobStore($root . '/jobs', $filesystem);
        $service = new DiagnosticRecordService($store, $jobs, dirname(__DIR__, 2) . '/', $filesystem);
        return [$service, $store, $jobs, $filesystem, $root];
    }

    private function tempRoot(): string
    {
        $root = sys_get_temp_dir() . '/edis-diagnostic-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $this->cleanup[] = $root;
        return $root;
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
            $this->remove($path . DIRECTORY_SEPARATOR . $entry);
        }
        rmdir($path);
    }
}
