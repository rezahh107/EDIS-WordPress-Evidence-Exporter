<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FailureConformanceRootCompleteTest extends TestCase
{
    /** T-REJ-001 */
    public function testMaintenanceRejectionRemainsExpectedAcrossCreateBoundary(): void
    {
        $result = $this->fixture('maintenance_rejection');
        self::assertSame('edis_export_jobs_unavailable', $result['error']['code']);
        self::assertSame(409, $result['error']['data']['status']);
        self::assertSame('MAINTENANCE', $result['error']['data']['schedule_state']);
        $this->assertNoDiagnosticEnvelope($result['error']['data']);
        self::assertSame(0, $result['diagnostic_count']);
        self::assertSame(0, $result['job_count']);
    }

    /** T-REJ-002 */
    public function testFailedJobLockRejectionStaysExpectedThroughController(): void
    {
        $result = $this->fixture('failed_job_lock_rejection');
        self::assertSame('edis_export_job_busy', $result['error']['code']);
        self::assertSame(409, $result['error']['data']['status']);
        self::assertSame('LOCKED', $result['error']['data']['schedule_state']);
        $this->assertNoDiagnosticEnvelope($result['error']['data']);
        self::assertSame(0, $result['diagnostic_count']);
        self::assertTrue($result['job_unchanged']);
        self::assertTrue($result['diagnostics_unchanged']);
        self::assertTrue($result['last_error_code_unchanged']);
    }

    /** T-REJ-003 */
    public function testActiveLeaseAndStaleRevisionPreserveExactExpectedSemantics(): void
    {
        $lease = $this->fixture('active_lease_rejection');
        self::assertSame('edis_export_job_busy', $lease['error']['code']);
        self::assertSame(409, $lease['error']['data']['status']);
        self::assertSame('EDIS_ACTIVE_WORKER_LEASE', $lease['error']['data']['reason_code']);
        self::assertSame('SCHEDULED_RECOVERY', $lease['error']['data']['schedule_state']);
        $this->assertNoDiagnosticEnvelope($lease['error']['data']);
        self::assertSame(0, $lease['diagnostic_count']);
        self::assertTrue($lease['job_unchanged']);

        $revision = $this->fixture('stale_revision_rejection');
        self::assertSame('edis_export_revision_conflict', $revision['error']['code']);
        self::assertSame(409, $revision['error']['data']['status']);
        self::assertSame('EDIS_STALE_JOB_REVISION', $revision['error']['data']['reason_code']);
        self::assertSame($revision['requested_revision'], $revision['error']['data']['expected_revision']);
        self::assertIsInt($revision['error']['data']['actual_revision']);
        $this->assertNoDiagnosticEnvelope($revision['error']['data']);
        self::assertSame(0, $revision['diagnostic_count']);
        self::assertTrue($revision['job_unchanged']);
    }

    /** T-DUR-001 */
    public function testPostPersistenceSchedulingFailureCarriesExactDurableJob(): void
    {
        $result = $this->fixture('durable_exact');
        self::assertSame('EDIS\\EvidenceExporter\\Application\\DurableJobFailureException', $result['exception_class']);
        self::assertMatchesRegularExpression('/\A[a-f0-9-]{36}\z/D', $result['job_id']);
        self::assertTrue($result['job_exists']);
        self::assertTrue($result['cursor_matches']);
        self::assertSame('JOB_BOUND', $result['creation_policy']);
        self::assertSame('post_create_scheduling', $result['stage']);
        self::assertSame('EDIS_POST_CREATE_SCHEDULING_FAILED', $result['reason_code']);
        self::assertSame('RuntimeException', $result['previous_class']);
        self::assertSame(1, $result['job_count']);
    }

    /** T-DUR-002 */
    public function testEntireSiteAttributionUsesNormalizedPersistedJobNotRawDocumentCount(): void
    {
        $result = $this->fixture('durable_entire_site');
        self::assertTrue($result['job_exists']);
        self::assertSame('JOB_BOUND', $result['creation_policy']);
        self::assertTrue($result['cursor_matches']);
        self::assertSame(1, $result['raw_request_document_count']);
        self::assertSame(2, $result['persisted_selected_document_count']);
        self::assertSame([101, 102], $result['persisted_documents']);
    }

    /** T-DUR-003 */
    public function testOverlappingSameOwnerCreateCannotAmbiguateExactJobAttribution(): void
    {
        $result = $this->fixture('durable_overlap');
        self::assertTrue($result['job_exists']);
        self::assertTrue($result['decoy_exists']);
        self::assertTrue($result['not_decoy']);
        self::assertSame(2, $result['owner_job_count']);
        self::assertTrue($result['cursor_matches']);
        self::assertSame('JOB_BOUND', $result['creation_policy']);
    }

    /** T-DUR-004 */
    public function testMaterialFailureBeforePersistenceRemainsPreJobWithoutIdentity(): void
    {
        $result = $this->fixture('prejob_before_persistence');
        self::assertSame('EDIS\\EvidenceExporter\\Application\\FailureObservationException', $result['exception_class']);
        self::assertSame('PRE_JOB', $result['creation_policy']);
        self::assertSame('preflight_proof_verification', $result['stage']);
        self::assertSame('EDIS_PREFLIGHT_PROOF_AUTHORITY_UNAVAILABLE', $result['reason_code']);
        self::assertSame(0, $result['job_count']);
        self::assertFalse($result['has_job_id_property']);
    }

    /** T-POS-001 */
    public function testValidCreateStillPersistsAndSchedulesExistingRecovery(): void
    {
        $result = $this->fixture('positive_create');
        self::assertMatchesRegularExpression('/\A[a-f0-9-]{36}\z/D', $result['job_id']);
        self::assertSame('queued', $result['status']);
        self::assertTrue($result['job_exists']);
        self::assertSame('SCHEDULED_RECOVERY', $result['schedule_state']);
        self::assertSame(0, $result['diagnostic_count']);
        self::assertSame(1, $result['job_count']);
    }

    public function testSelectedMethodDeviationGuardsRejectHeuristicOrUntypedRegression(): void
    {
        $root = dirname(__DIR__, 2);
        $boundarySource = (string) file_get_contents($root . '/src/Application/JobFailureCursor.php');
        self::assertStringNotContainsString('resolveSingleNewDurableJob', $boundarySource);
        self::assertStringNotContainsString('jobIdsForOwner', $boundarySource);
        self::assertStringNotContainsString('jobsForUser(', $boundarySource);
        self::assertStringNotContainsString('private readonly JobStore $jobs', $boundarySource);

        $boundary = substr($boundarySource, (int) strpos($boundarySource, 'final class ExportCreateConformance'));
        $expectedCatch = strpos($boundary, 'catch (ExpectedOperationRejection $exception)');
        $durableCatch = strpos($boundary, 'catch (DurableJobFailureException $exception)');
        $genericCatch = strpos($boundary, 'catch (\\Throwable $exception)');
        self::assertIsInt($expectedCatch);
        self::assertIsInt($durableCatch);
        self::assertIsInt($genericCatch);
        self::assertLessThan($genericCatch, $expectedCatch);
        self::assertLessThan($genericCatch, $durableCatch);

        $service = (string) file_get_contents($root . '/src/Application/ExportJobService.php');
        self::assertStringContainsString("'job_persistence'", $service);
        self::assertStringContainsString("'post_create_scheduling'", $service);
        self::assertStringContainsString('JobFailureCursor::capture($job)', $service);
        self::assertStringContainsString('throw new DurableJobFailureException(', $service);

        $controllerSource = (string) file_get_contents($root . '/src/Rest/DiagnosticExportJobController.php');
        $runStart = (int) strpos($controllerSource, 'private function runAction');
        $runEnd = (int) strpos($controllerSource, 'private function expectedActionResult');
        $runAction = substr($controllerSource, $runStart, $runEnd - $runStart);
        $typed = strpos($runAction, 'catch (ExpectedOperationRejection $rejection)');
        $generic = strpos($runAction, 'catch (\\Throwable $exception)');
        self::assertIsInt($typed);
        self::assertIsInt($generic);
        self::assertLessThan($generic, $typed);

        $bootstrap = (string) file_get_contents($root . '/src/Bootstrap.php');
        self::assertStringContainsString('new ExportCreateConformance($jobService, $registry)', $bootstrap);
        self::assertStringNotContainsString('new ExportCreateConformance($jobService, $registry, $jobStore)', $bootstrap);
    }

    /** @param array<string,mixed> $data */
    private function assertNoDiagnosticEnvelope(array $data): void
    {
        self::assertFalse($data['diagnostic_available']);
        self::assertNull($data['diagnostic_id']);
        self::assertNull($data['diagnostics_url']);
        self::assertNull($data['diagnostic_persistence_code']);
    }

    /** @return array<string,mixed> */
    private function fixture(string $scenario): array
    {
        $root = dirname(__DIR__, 2);
        $command = [PHP_BINARY, $root . '/tests/fixtures/failure-conformance-root-complete.php', $scenario];
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        self::assertIsResource($process, 'Could not start failure-conformance fixture.');
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        self::assertSame(0, $exit, $scenario . ' failed: ' . trim((string) $stderr));
        $decoded = json_decode(trim((string) $stdout), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame($scenario, $decoded['scenario'] ?? null);
        return $decoded;
    }
}
