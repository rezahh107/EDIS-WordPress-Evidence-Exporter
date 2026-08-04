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

    public function testOwnerCapacityPreservesAllLiveRecordsAndReopensAfterExpiry(): void
    {
        [$service, , , $filesystem, $root] = $this->services();
        $ids = [];
        for ($index = 0; $index < DiagnosticRecordStore::MAX_LIVE_RECORDS_PER_OWNER; $index++) {
            $result = $service->capturePreJobFailure(
                7,
                'EXPORT_CREATE',
                null,
                ['privacy_mode' => 'Strict', 'collectors' => ['environment'], 'document_ids' => []],
                new \RuntimeException('not exported'),
            );
            self::assertTrue($result['diagnostic_available']);
            $ids[] = (string) $result['diagnostic_id'];
        }
        $directory = $root . '/diagnostics/user-7';
        $before = [];
        foreach ($ids as $id) {
            $path = $directory . '/' . $id . '.json';
            $before[$id] = hash_file('sha256', $path);
        }

        $full = $service->capturePreJobFailure(
            7,
            'EXPORT_CREATE',
            null,
            ['privacy_mode' => 'Strict', 'collectors' => ['environment'], 'document_ids' => []],
            new \RuntimeException('not exported'),
        );
        self::assertFalse($full['diagnostic_available']);
        self::assertNull($full['diagnostic_id']);
        self::assertSame('EDIS_DIAGNOSTIC_CAPACITY_REACHED', $full['diagnostic_persistence_code']);
        self::assertCount(DiagnosticRecordStore::MAX_LIVE_RECORDS_PER_OWNER, glob($directory . '/edis-diag-*.json') ?: []);
        foreach ($before as $id => $digest) {
            self::assertSame($digest, hash_file('sha256', $directory . '/' . $id . '.json'));
            self::assertIsArray($service->resolveForOwner(7, $id));
        }

        $expiredId = $ids[0];
        $expired = $service->resolveForOwner(7, $expiredId);
        self::assertIsArray($expired);
        $expired['record']['diagnostic_identity']['expires_at'] = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
        $filesystem->writeAtomically($directory . '/' . $expiredId . '.json', CanonicalJson::encode($expired['record']));
        $reopened = $service->capturePreJobFailure(
            7,
            'EXPORT_CREATE',
            null,
            ['privacy_mode' => 'Strict', 'collectors' => ['environment'], 'document_ids' => []],
            new \RuntimeException('not exported'),
        );
        self::assertTrue($reopened['diagnostic_available']);
        self::assertCount(DiagnosticRecordStore::MAX_LIVE_RECORDS_PER_OWNER, glob($directory . '/edis-diag-*.json') ?: []);
        self::assertFileDoesNotExist($directory . '/' . $expiredId . '.json');
    }

    public function testConcurrentOwnerCreatesCannotExceedCapacity(): void
    {
        if (DIRECTORY_SEPARATOR !== '/' || !function_exists('proc_open')) {
            self::markTestSkipped('Independent PHP process execution is unavailable.');
        }
        [$service, , , , $root] = $this->services();
        $template = null;
        for ($index = 0; $index < 124; $index++) {
            $result = $service->capturePreJobFailure(
                7,
                'EXPORT_CREATE',
                null,
                ['privacy_mode' => 'Strict', 'collectors' => ['environment'], 'document_ids' => []],
                new \RuntimeException('not exported'),
            );
            self::assertTrue($result['diagnostic_available']);
            if ($template === null) {
                $resolved = $service->resolveForOwner(7, (string) $result['diagnostic_id']);
                self::assertIsArray($resolved);
                $template = $resolved['record'];
            }
        }
        self::assertIsArray($template);
        $templatePath = $root . '/template.json';
        file_put_contents($templatePath, CanonicalJson::encode($template));
        $scriptPath = $root . '/concurrent-create.php';
        file_put_contents($scriptPath, <<<'PHP'
<?php
declare(strict_types=1);
require $argv[1] . '/autoload.php';
$record = json_decode((string) file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
$record['diagnostic_identity']['diagnostic_id'] = 'edis-diag-' . hash('md5', 'capacity-child-' . $argv[4]);
$record['diagnostic_identity']['created_at'] = gmdate('Y-m-d\TH:i:s\Z');
$record['diagnostic_identity']['expires_at'] = gmdate('Y-m-d\TH:i:s\Z', time() + 3600);
$store = new EDIS\EvidenceExporter\Infrastructure\Support\DiagnosticRecordStore(
    $argv[3],
    new EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem(),
    3600,
);
try {
    $store->create(7, $record);
    echo 'OK';
} catch (EDIS\EvidenceExporter\Infrastructure\Support\DiagnosticCapacityReachedException) {
    echo 'CAPACITY';
}
PHP
        );

        $processes = [];
        $repositoryRoot = dirname(__DIR__, 2);
        for ($index = 0; $index < 12; $index++) {
            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, $scriptPath, $repositoryRoot, $templatePath, $root . '/diagnostics', (string) $index],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
            self::assertIsResource($process);
            fclose($pipes[0]);
            $processes[] = [$process, $pipes[1], $pipes[2]];
        }
        $outcomes = [];
        foreach ($processes as [$process, $stdout, $stderr]) {
            $output = trim((string) stream_get_contents($stdout));
            $error = trim((string) stream_get_contents($stderr));
            fclose($stdout);
            fclose($stderr);
            self::assertSame(0, proc_close($process), $error);
            $outcomes[] = $output;
        }
        self::assertSame(4, count(array_filter($outcomes, static fn (string $value): bool => $value === 'OK')));
        self::assertSame(8, count(array_filter($outcomes, static fn (string $value): bool => $value === 'CAPACITY')));
        $paths = glob($root . '/diagnostics/user-7/edis-diag-*.json') ?: [];
        self::assertCount(DiagnosticRecordStore::MAX_LIVE_RECORDS_PER_OWNER, $paths);
        foreach ($paths as $path) {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(basename($path, '.json'), $decoded['diagnostic_identity']['diagnostic_id']);
            self::assertSame(CanonicalJson::encode($decoded), file_get_contents($path));
        }
    }

    public function testConfirmedSystemIdentityUsesOnlyDirectMatchingEvidence(): void
    {
        $GLOBALS['wp_version'] = '6.7.4';
        if (!defined('ELEMENTOR_VERSION')) {
            define('ELEMENTOR_VERSION', '4.1.3');
        }
        [$service] = $this->services();
        $result = $service->capturePreJobFailure(
            7,
            'EXPORT_CREATE',
            null,
            ['privacy_mode' => 'Strict', 'collectors' => ['environment'], 'document_ids' => []],
            new \RuntimeException('not exported'),
        );
        $resolved = $service->resolveForOwner(7, (string) $result['diagnostic_id']);
        self::assertIsArray($resolved);
        $evidence = [];
        foreach ($resolved['record']['evidence_and_provenance'] as $row) {
            $evidence[$row['evidence_id']] = $row;
        }
        $expected = [
            'plugin_version' => ['RUNTIME_CONSTANT', 'EDIS_EVIDENCE_EXPORTER_VERSION'],
            'worker_implementation_version' => ['RUNTIME_CLASS_CONSTANT', 'ExportJobService::IMPLEMENTATION_VERSION'],
            'wordpress_version' => ['WORDPRESS_RUNTIME_GLOBAL', 'global:wp_version'],
            'php_version' => ['PHP_RUNTIME_VERSION', 'PHP_VERSION'],
            'elementor_version' => ['ELEMENTOR_RUNTIME_CONSTANT', 'ELEMENTOR_VERSION'],
        ];
        foreach ($expected as $field => [$sourceType, $locator]) {
            $observation = $resolved['record']['system_identity'][$field];
            self::assertSame('CONFIRMED', $observation['status']);
            self::assertCount(1, $observation['evidence_ids']);
            $row = $evidence[$observation['evidence_ids'][0]] ?? null;
            self::assertIsArray($row);
            self::assertSame($sourceType, $row['source_type']);
            self::assertSame($locator, $row['source_locator']);
        }
        $build = $resolved['record']['system_identity']['build_identity'];
        self::assertSame('CONFIRMED', $build['status']);
        self::assertCount(2, $build['evidence_ids']);
        $buildLocators = array_map(static fn (string $id): string => (string) $evidence[$id]['source_locator'], $build['evidence_ids']);
        self::assertStringStartsWith('plugin.manifest.json#sha256:', $buildLocators[0]);
        self::assertStringStartsWith('config/critical-files.json#sha256:', $buildLocators[1]);
        foreach (array_merge(...array_map(static fn (array $item): array => $item['evidence_ids'], array_values($resolved['record']['system_identity']))) as $id) {
            self::assertNotContains($evidence[$id]['source_type'], ['RUNTIME_EXCEPTION_CLASS', 'PERSISTED_JOB_RECORD']);
        }
    }

    public function testUnavailableBuildIdentityHasNullValueAndNoUnrelatedEvidence(): void
    {
        $root = $this->tempRoot();
        mkdir($root . '/plugin/schemas', 0777, true);
        copy(dirname(__DIR__, 2) . '/schemas/diagnostic-record.schema.json', $root . '/plugin/schemas/diagnostic-record.schema.json');
        $filesystem = new DeterministicFilesystem();
        $jobs = new JobStore($root . '/jobs', $filesystem);
        $store = new DiagnosticRecordStore($root . '/diagnostics', $filesystem, 3600);
        $service = new DiagnosticRecordService($store, $jobs, $root . '/plugin/', $filesystem);
        $result = $service->capturePreJobFailure(
            7,
            'EXPORT_CREATE',
            null,
            ['privacy_mode' => 'Strict', 'collectors' => ['environment'], 'document_ids' => []],
            new \RuntimeException('not exported'),
        );
        $resolved = $service->resolveForOwner(7, (string) $result['diagnostic_id']);
        self::assertIsArray($resolved);
        self::assertSame('UNAVAILABLE', $resolved['record']['system_identity']['build_identity']['status']);
        self::assertNull($resolved['record']['system_identity']['build_identity']['value']);
        self::assertSame([], $resolved['record']['system_identity']['build_identity']['evidence_ids']);
        $validator = new JsonSchemaValidator($root . '/plugin/', $filesystem);
        self::assertSame([], $validator->validate(json_decode($resolved['bytes']), 'schemas/diagnostic-record.schema.json'));
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
