<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Application\DiagnosticRecordService;
use EDIS\EvidenceExporter\Application\JobFailureCursor;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\DiagnosticRecordStore;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportIntegrityException;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use PHPUnit\Framework\TestCase;

final class DiagnosticFollowupRepairTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $path) {
            $this->remove($path);
        }
    }

    public function testFailureCursorDetectsRepeatedSameCodeOccurrenceByRevisionAndObservedTime(): void
    {
        $job = [
            'revision' => 8,
            'status' => 'failed',
            'phase' => 'failed',
            'current_component' => 'environment',
            'last_error_code' => 'EDIS_EXPORT_ADVANCE_FAILED',
            'last_error_at' => 100,
            'diagnostics' => [[
                'code' => 'EDIS_EXPORT_ADVANCE_FAILED',
                'scope' => 'OPERATIONAL',
                'context' => ['failure_phase' => 'collecting'],
            ]],
        ];
        $before = JobFailureCursor::capture($job);
        self::assertFalse(JobFailureCursor::changed($before, $job));

        $job['revision'] = 9;
        $job['last_error_at'] = 101;
        self::assertTrue(JobFailureCursor::changed($before, $job));
        self::assertSame(
            $before,
            JobFailureCursor::capture(array_replace($job, ['revision' => 8, 'last_error_at' => 100])),
        );
    }

    public function testUnchangedHistoricalFailureCannotReplaceCurrentResumeException(): void
    {
        [$service, $jobs] = $this->services();
        $job = $jobs->create($this->failedJob('occurrence-current', [
            'last_error_code' => 'OLD_FAILURE_CODE',
            'last_error_at' => time() - 3600,
            'diagnostics' => [[
                'code' => 'OLD_FAILURE_CODE',
                'severity' => 'ERROR',
                'scope' => 'OPERATIONAL',
                'message_key' => 'diagnostic.old_failure',
                'context' => ['failure_phase' => 'old_context'],
            ]],
        ]));
        $cursor = JobFailureCursor::capture($job);
        $captureStarted = time();

        $result = $service->captureJobFailure(
            7,
            (string) $job['job_id'],
            'EXPORT_RESUME',
            '/edis-evidence-exporter/v3/export-jobs/{job_id}/resume',
            new ExportIntegrityException(
                'EDIS_JOB_FORMAT_INCOMPATIBLE',
                'not exported',
                null,
                ['failure_phase' => 'resume_compatibility'],
            ),
            'edis_export_action_failed',
            'JOB_FAILURE',
            $cursor,
        );

        self::assertTrue($result['diagnostic_available']);
        $resolved = $service->resolveForOwner(7, (string) $result['diagnostic_id']);
        self::assertIsArray($resolved);
        $record = $resolved['record'];
        self::assertSame('EDIS_JOB_FORMAT_INCOMPATIBLE', $record['failure_classification']['internal_code']);
        self::assertSame('SEMANTIC', $record['failure_classification']['scope']);
        self::assertSame('resume_compatibility', $record['safe_context']['failure_phase']);
        $observedTimes = array_map(
            static fn (array $entry): int => (int) strtotime((string) ($entry['observed_at'] ?? '')),
            $record['causal_timeline'],
        );
        self::assertNotSame([], $observedTimes);
        self::assertGreaterThanOrEqual($captureStarted, max($observedTimes));
        self::assertContains(
            'PRIOR_PERSISTED_JOB_FAILURE',
            array_column($record['evidence_and_provenance'], 'source_type'),
        );
        self::assertStringContainsString(
            'Prior persisted state contained failure code OLD_FAILURE_CODE',
            implode(' ', array_column($record['recorded_facts'], 'statement')),
        );
        self::assertSame(
            'EDIS_RULE_CURRENT_OCCURRENCE_FROM_CAUGHT_OPERATION',
            $record['plugin_classification'][0]['rule_id'],
        );
    }

    public function testChangedPersistedTransitionIsCurrentEvenWhenCodeRepeats(): void
    {
        [$service, $jobs] = $this->services();
        $job = $jobs->create($this->failedJob('occurrence-transition'));
        $cursor = JobFailureCursor::capture($job);
        $job['last_error_at'] = time();
        $job['diagnostics'][] = [
            'code' => 'EDIS_EXPORT_ADVANCE_FAILED',
            'severity' => 'ERROR',
            'scope' => 'OPERATIONAL',
            'message_key' => 'diagnostic.export.advance_failed',
            'context' => ['failure_phase' => 'new_persisted_occurrence'],
        ];
        $jobs->save($job, (int) $job['revision']);

        $result = $service->captureJobFailure(
            7,
            (string) $job['job_id'],
            'EXPORT_ADVANCE',
            '/edis-evidence-exporter/v3/export-jobs/{job_id}/advance',
            new \RuntimeException('not exported'),
            'edis_export_advance_failed',
            'JOB_FAILURE',
            $cursor,
        );

        self::assertTrue($result['diagnostic_available']);
        $resolved = $service->resolveForOwner(7, (string) $result['diagnostic_id']);
        self::assertIsArray($resolved);
        $record = $resolved['record'];
        self::assertSame('EDIS_EXPORT_ADVANCE_FAILED', $record['failure_classification']['internal_code']);
        self::assertSame('new_persisted_occurrence', $record['safe_context']['failure_phase']);
        self::assertContains(
            'PERSISTED_JOB_TRANSITION',
            array_column($record['evidence_and_provenance'], 'source_type'),
        );
        self::assertNotContains(
            'PRIOR_PERSISTED_JOB_FAILURE',
            array_column($record['evidence_and_provenance'], 'source_type'),
        );
        self::assertSame(
            'EDIS_RULE_CURRENT_OCCURRENCE_FROM_CHANGED_PERSISTED_TRANSITION',
            $record['plugin_classification'][0]['rule_id'],
        );
    }

    public function testJobDiagnosticExpiryClampsToSmallerFutureAuthorityAndRejectsNoOverlap(): void
    {
        $root = $this->tempRoot('edis-life-');
        $store = new DiagnosticRecordStore($root . '/diagnostics', new DeterministicFilesystem(), 7200);
        $near = time() + 120;
        $clamped = $store->expirationForJob($near);
        self::assertIsInt($clamped);
        self::assertLessThanOrEqual($near, $clamped);
        self::assertGreaterThan(time(), $clamped);
        self::assertNull($store->expirationForJob(time()));
        self::assertGreaterThan(time() + 3500, $store->defaultExpiration());
    }

    public function testJobBoundRecordAdvertisesNoLifetimeBeyondJobAndNoOverlapCreatesNothing(): void
    {
        [$service, $jobs, $root] = $this->services();
        $job = $jobs->create($this->failedJob('lifetime-short', ['expires_at' => time() + 90]));
        $result = $service->captureJobFailure(
            7,
            (string) $job['job_id'],
            'EXPORT_ADVANCE',
            null,
            new \RuntimeException('not exported'),
        );
        self::assertTrue($result['diagnostic_available']);
        $resolved = $service->resolveForOwner(7, (string) $result['diagnostic_id']);
        self::assertIsArray($resolved);
        self::assertLessThanOrEqual(
            (int) $job['expires_at'],
            strtotime((string) $resolved['record']['diagnostic_identity']['expires_at']),
        );

        $expiredJob = $jobs->create($this->failedJob('lifetime-ended', ['expires_at' => time()]));
        $unavailable = $service->captureJobFailure(
            7,
            (string) $expiredJob['job_id'],
            'EXPORT_ADVANCE',
            null,
            new \RuntimeException('not exported'),
        );
        self::assertFalse($unavailable['diagnostic_available']);
        self::assertNull($unavailable['diagnostic_id']);
        self::assertSame('EDIS_DIAGNOSTIC_AUTHORITY_EXPIRED', $unavailable['diagnostic_persistence_code']);
        self::assertCount(1, glob($root . '/diagnostics/user-7/edis-diag-*.json') ?: []);
    }

    public function testLockedSourceContractsRemainExplicit(): void
    {
        $root = dirname(__DIR__, 2) . '/';
        $controller = (string) file_get_contents($root . 'src/Rest/DiagnosticsController.php');
        $adapter = (string) file_get_contents($root . 'src/Rest/CanonicalDiagnosticResponseAdapter.php');
        $exportController = (string) file_get_contents($root . 'src/Rest/DiagnosticExportJobController.php');
        $service = (string) file_get_contents($root . 'src/Application/DiagnosticRecordService.php');
        $builder = (string) file_get_contents($root . 'tools/release/build-release.py');
        self::assertStringContainsString('rest_pre_serve_request', $adapter);
        self::assertStringContainsString("\$resolved['bytes']", $controller);
        self::assertStringNotContainsString("\$resolved['record']", $controller);
        self::assertStringContainsString('JobFailureCursor::capture', $exportController);
        self::assertStringContainsString('failureCursor', $service);
        self::assertStringContainsString('expirationForJob', $service);
        self::assertStringNotContainsString('worker_version != plugin_version', $builder);
        self::assertStringNotContainsString(
            "'diagnostic_available' => false,\n                            'diagnostic_id' => null",
            $exportController,
        );
    }

    /** @return array{DiagnosticRecordService,JobStore,string} */
    private function services(): array
    {
        $root = $this->tempRoot('edis-followup-');
        $filesystem = new DeterministicFilesystem();
        $jobs = new JobStore($root . '/jobs', $filesystem);
        $store = new DiagnosticRecordStore($root . '/diagnostics', $filesystem, 3600);
        return [
            new DiagnosticRecordService($store, $jobs, dirname(__DIR__, 2) . '/', $filesystem),
            $jobs,
            $root,
        ];
    }

    /** @param array<string,mixed> $overrides @return array<string,mixed> */
    private function failedJob(string $jobId, array $overrides = []): array
    {
        return array_replace([
            'job_id' => $jobId,
            'owner_id' => 7,
            'status' => 'failed',
            'phase' => 'failed',
            'current_component' => 'environment',
            'created_at' => time() - 60,
            'expires_at' => time() + 3600,
            'last_error_at' => time() - 30,
            'last_error_code' => 'EDIS_EXPORT_ADVANCE_FAILED',
            'next_retry_at' => null,
            'schedule_state' => 'NOT_SCHEDULED',
            'schedule_error' => null,
            'selected_components' => ['environment'],
            'selected_document_count' => 0,
            'config' => [
                'privacy_mode' => 'Strict',
                'document_ids' => [],
                'options' => ['export_scope' => 'METADATA_ONLY'],
            ],
            'diagnostics' => [[
                'code' => 'EDIS_EXPORT_ADVANCE_FAILED',
                'severity' => 'ERROR',
                'scope' => 'OPERATIONAL',
                'message_key' => 'diagnostic.export.advance_failed',
                'context' => ['failure_phase' => 'collecting'],
            ]],
        ], $overrides);
    }

    private function tempRoot(string $prefix): string
    {
        $root = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(5));
        mkdir($root, 0777, true);
        $this->cleanup[] = $root;
        return $root;
    }

    private function remove(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
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
        @rmdir($path);
    }
}
