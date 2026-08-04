<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Application\DiagnosticRecordService;
use EDIS\EvidenceExporter\Application\DiagnosticsService;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\DiagnosticRecordStore;
use EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
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
        $root = sys_get_temp_dir() . '/edis-diagnostic-' . bin2hex(random_bytes(6));
        $this->cleanup[] = $root;
        $filesystem = new DeterministicFilesystem();
        $store = new DiagnosticRecordStore($root . '/diagnostics', $filesystem, 3600);
        $jobs = new JobStore($root . '/jobs', $filesystem);
        $service = new DiagnosticRecordService($store, $jobs, dirname(__DIR__, 2) . '/', $filesystem);
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
        self::assertStringNotContainsString('document 42', $resolved['bytes']);
        self::assertNull($service->resolveForOwner(8, (string) $result['diagnostic_id'], static fn (): bool => true));
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
