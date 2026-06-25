<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Application;

use EDIS\EvidenceExporter\Admin\Settings\SettingsRepository;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\Diagnostic;
use EDIS\EvidenceExporter\Domain\CollectionResult;
use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Support\ArtifactStore;
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportFileStore;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportIntegrityException;
use EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;
use EDIS\EvidenceExporter\Infrastructure\Support\PreflightProof;
use EDIS\EvidenceExporter\Infrastructure\Support\DocumentIdentity;
use EDIS\EvidenceExporter\Infrastructure\Support\Uuid;

final class ExportJobService
{
    private const TERMINAL = ['completed', 'failed', 'cancelled'];
    private ?PreflightProof $preflightProof;
    /** @var array<string,array<string,array<string,mixed>>> */
    private array $committedArtifacts = [];

    public function __construct(
        private readonly CollectorRegistry $registry,
        private readonly ExportService $exporter,
        private readonly JobStore $jobs,
        private readonly ArtifactStore $artifacts,
        private readonly ExportFileStore $files,
        private readonly SettingsRepository $settings,
        private readonly InputSnapshotStore $inputs,
        private readonly ?PrivateStorage $privateStorage = null,
        ?PreflightProof $preflightProof = null,
    ) {
        $this->preflightProof = $preflightProof ?? PreflightProof::fromWordPress();
    }

    /** @param array<string, mixed> $request @return array<string, mixed> */
    public function create(int $ownerId, array $request): array
    {
        if ($ownerId <= 0) {
            throw new \InvalidArgumentException('An authenticated owner is required.');
        }
        if (function_exists('get_option') && !(bool) get_option('edis_evidence_accept_new_jobs', true)) {
            throw new \RuntimeException('EDIS is not accepting new export jobs during deactivation or maintenance.');
        }
        $normalized = $this->normalizeRequest($request);
        $token = is_string($request['preflight_token'] ?? null) ? trim($request['preflight_token']) : '';
        if ($token !== '') {
            $proof = $this->preflightProof?->verify($token, $ownerId, $normalized);
            if (!is_array($proof) || !$this->preflightProofStillValid($normalized, $proof)) {
                throw new \InvalidArgumentException('Export preflight proof is invalid, expired, or no longer matches saved source. Run preflight again.');
            }
            $preflight = ['state' => 'PASS', 'blockers' => []];
        } else {
            $preflight = $this->preflightNormalized($normalized, $ownerId);
        }
        if (($preflight['state'] ?? 'FAIL') !== 'PASS') {
            $codes = array_map(static fn (array $item): string => (string) ($item['code'] ?? 'EDIS_PREFLIGHT_FAILED'), (array) ($preflight['blockers'] ?? []));
            throw new \InvalidArgumentException('Export preflight failed: ' . implode(', ', $codes));
        }

        $privacyMode = $normalized['privacy_mode'];
        $selected = $normalized['collectors'];
        $documents = $normalized['document_ids'];
        $options = $normalized['options'];
        $includeOriginal = (bool) ($options['include_original_documents'] ?? $this->settings->includeOriginalDocuments());
        if ($privacyMode === 'Strict') {
            $includeOriginal = false;
        }
        $options['include_original_documents'] = $includeOriginal;
        $plan = $this->registry->executionPlan($selected, (string) $options['dependency_scope']);
        $now = time();
        $retention = $this->settings->retentionHours() * $this->hourSeconds();
        $expiresAt = $now + $retention;
        $jobId = Uuid::v4();

        try {
            $inputSnapshot = $this->inputs->capture($jobId, $documents, $expiresAt);
            $this->assertSnapshotSelection($jobId, $documents, $options);
            $options['input_snapshot_id'] = $jobId;
            $options['input_snapshot_sha256'] = (string) ($inputSnapshot['snapshot_sha256'] ?? '');
            $selectionSnapshot = $this->selectionSnapshot($documents, $options, $inputSnapshot);
            $capturedAt = (string) ($inputSnapshot['captured_at'] ?? gmdate('Y-m-d\TH:i:s\Z', $now));

            $job = $this->jobs->create([
                'job_id' => $jobId,
                'job_format_version' => '2.1.0',
                'implementation_version' => '3.7.11',
                'input_snapshot_format_version' => (string) ($inputSnapshot['snapshot_format_version'] ?? '2.0.0'),
                'input_snapshot_id' => $jobId,
                'input_snapshot_sha256' => (string) ($inputSnapshot['snapshot_sha256'] ?? ''),
                'owner_id' => $ownerId,
                'status' => 'queued',
                'phase' => 'initializing',
                'progress' => 0,
                'analysis_set_id' => Uuid::v4(),
                'wordpress_bundle_id' => Uuid::v4(),
                'created_at' => $now,
                'updated_at' => $now,
                'expires_at' => $expiresAt,
                'executor_mode' => 'HYBRID_REST_WITH_CRON_RECOVERY',
                'cursor' => 0,
                'current_component' => null,
                'last_heartbeat' => $now,
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
                'selected_components' => $plan,
                'selected_document_count' => count($documents),
                'export_scope' => (string) $options['export_scope'],
                'dependency_scope' => (string) $options['dependency_scope'],
                'selection_snapshot' => $selectionSnapshot,
                'completed_components' => [],
                'completed_step_records' => (object) [],
                'truth_summary' => ['VERIFIED' => 0, 'PARTIAL' => 0, 'UNKNOWN' => 0, 'UNSUPPORTED' => 0],
                'availability_summary' => ['AVAILABLE' => 0, 'PARTIAL' => 0, 'INSUFFICIENT' => 0, 'DISABLED' => 0, 'UNAVAILABLE' => 0, 'NOT_APPLICABLE' => 0, 'ERROR' => 0],
                'diagnostics' => [],
                'validation_state' => 'NOT_RUN',
                'config' => [
                    'privacy_mode' => $privacyMode,
                    'document_ids' => $documents,
                    'include_original_documents' => $includeOriginal,
                    'options' => $options,
                    'captured_at' => $capturedAt,
                    'selection_snapshot' => $selectionSnapshot,
                    'input_snapshot_id' => $jobId,
                    'input_snapshot_sha256' => (string) ($inputSnapshot['snapshot_sha256'] ?? ''),
                ],
            ]);
        } catch (\Throwable $exception) {
            $this->inputs->remove($jobId);
            throw $exception;
        }

        $this->scheduleRecovery($job);
        return $this->jobs->publicView($job);
    }

    /** @return array<string, mixed> */
    public function advance(string $jobId, int $ownerId, ?int $expectedRevision = null, int $budgetMs = 7000): array
    {
        $lock = $this->jobs->acquireLock($jobId, 1);
        if (!is_resource($lock)) {
            throw new \RuntimeException('The export job is currently being processed.');
        }
        try {
            return $this->advanceUnderLock($jobId, $ownerId, $expectedRevision, $budgetMs);
        } finally {
            $this->jobs->releaseLock($lock);
        }
    }

    /**
     * Advance a job while the caller owns the stable per-job lock.
     *
     * @return array<string,mixed>
     */
    private function advanceUnderLock(string $jobId, int $ownerId, ?int $expectedRevision, int $budgetMs, bool $stateAlreadyVerified = false): array
    {
        try {
            $job = $this->requireOwnedJob($jobId, $ownerId);
            if (!$stateAlreadyVerified) {
                $this->assertJobCompatible($job);
                $this->assertCurrentResumeState($job);
            }
            if (in_array((string) ($job['status'] ?? ''), self::TERMINAL, true)) {
                return $this->jobs->publicView($job);
            }
            $this->primeCommittedArtifacts($job);
            $leaseOwner = Uuid::v4();
            $now = time();
            $existingLeaseExpiry = (int) ($job['lease_expires_at'] ?? 0);
            if ($existingLeaseExpiry > $now && is_string($job['lease_owner'] ?? null) && $job['lease_owner'] !== '') {
                throw new \RuntimeException('The export job has an active worker lease.');
            }
            $job['lease_owner'] = $leaseOwner;
            $job['lease_acquired_at'] = $now;
            $job['lease_expires_at'] = $now + max(30, (int) ($job['stale_after'] ?? 120));
            if ($expectedRevision !== null && (int) ($job['revision'] ?? 0) !== $expectedRevision) {
                throw new \RuntimeException('The export job changed; refresh its state before advancing it.');
            }
            $job['status'] = 'running';
            $job['attempt_count'] = (int) ($job['attempt_count'] ?? 0) + 1;
            $job['last_heartbeat'] = time();
            $job['schedule_state'] = 'REST_ADVANCE_ACTIVE';
            $deadline = microtime(true) + max(500, min(15000, $budgetMs)) / 1000;
            do {
                $done = $this->performOneStep($job);
                $job['last_heartbeat'] = time();
                $job['lease_expires_at'] = $job['last_heartbeat'] + max(30, (int) ($job['stale_after'] ?? 120));
                $this->jobs->save($job);
                if ($done || in_array((string) ($job['status'] ?? ''), self::TERMINAL, true)) {
                    break;
                }
            } while (microtime(true) < $deadline);
            if (!in_array((string) ($job['status'] ?? ''), self::TERMINAL, true)) {
                $this->scheduleRecovery($job);
            } else {
                $this->clearRecoverySchedule((string) $job['job_id']);
            }
            $job['lease_owner'] = null;
            $job['lease_acquired_at'] = null;
            $job['lease_expires_at'] = null;
            $this->jobs->save($job);
            return $this->jobs->publicView($job);
        } catch (\Throwable $exception) {
            $job = $this->jobs->get($jobId);
            if (is_array($job) && !in_array((string) ($job['status'] ?? ''), ['cancelled', 'completed'], true)) {
                $errorCode = $exception instanceof ExportIntegrityException
                    ? $exception->diagnosticCode
                    : 'EDIS_EXPORT_ADVANCE_FAILED';
                $job['status'] = 'failed';
                $job['phase'] = 'failed';
                $job['last_error_code'] = $errorCode;
                $job['last_error_at'] = time();
                $job['next_retry_at'] = $exception instanceof ExportIntegrityException ? null : time() + 5;
                $job['lease_owner'] = null;
                $job['lease_acquired_at'] = null;
                $job['lease_expires_at'] = null;
                $job['diagnostics'][] = ['code' => $errorCode, 'severity' => 'ERROR', 'scope' => 'OPERATIONAL', 'message_key' => 'diagnostic.export.advance_failed', 'context' => ['exception_class' => get_class($exception)]];
                $this->jobs->save($job);
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function resume(string $jobId, int $ownerId): array
    {
        $lock = $this->jobs->acquireLock($jobId, 1);
        if (!is_resource($lock)) {
            throw new \RuntimeException('The export job is currently being processed.');
        }
        try {
            $job = $this->requireOwnedJob($jobId, $ownerId);
            $this->assertJobCompatible($job);
            $this->assertCurrentResumeState($job);
            if (($job['status'] ?? '') === 'cancelled' || ($job['status'] ?? '') === 'completed') {
                return $this->jobs->publicView($job);
            }
            $job['status'] = 'queued';
            if (($job['phase'] ?? '') === 'failed') {
                $job['phase'] = (int) ($job['cursor'] ?? 0) < count((array) ($job['selected_components'] ?? [])) ? 'collecting' : 'packaging';
            }
            $job['last_error_code'] = null;
            $job['next_retry_at'] = null;
            $job['diagnostics'][] = ['code' => 'EDIS_EXPORT_RESUMED', 'severity' => 'INFO', 'scope' => 'OPERATIONAL', 'message_key' => 'diagnostic.export.resumed', 'context' => []];
            $this->jobs->save($job);
            return $this->advanceUnderLock($jobId, $ownerId, null, 5000, true);
        } finally {
            $this->jobs->releaseLock($lock);
        }
    }

    /** @return array<string, mixed> */
    public function retry(string $jobId, int $ownerId): array
    {
        return $this->resume($jobId, $ownerId);
    }

    /** @return array<string, mixed> */
    public function cancel(string $jobId, int $ownerId): array
    {
        $lock = $this->jobs->acquireLock($jobId, 1);
        if (!is_resource($lock)) {
            throw new \RuntimeException('The export job is currently locked.');
        }
        try {
            $job = $this->requireOwnedJob($jobId, $ownerId);
            if (($job['status'] ?? '') !== 'completed') {
                $job['status'] = 'cancelled';
                $job['phase'] = 'cancelled';
                $job['diagnostics'][] = ['code' => 'EDIS_EXPORT_CANCELLED', 'severity' => 'INFO', 'scope' => 'OPERATIONAL', 'message_key' => 'diagnostic.export.cancelled', 'context' => []];
                $this->jobs->save($job);
                $this->clearRecoverySchedule($jobId);
            }
            return $this->jobs->publicView($job);
        } finally {
            $this->jobs->releaseLock($lock);
        }
    }

    public function process(string $jobId): void
    {
        $job = $this->jobs->get($jobId);
        if (!is_array($job) || in_array((string) ($job['status'] ?? ''), self::TERMINAL, true)) {
            return;
        }
        try {
            $this->advance($jobId, (int) ($job['owner_id'] ?? 0), null, 8000);
        } catch (\Throwable) {
            // Failure is persisted by advance(). Cron remains a recovery path only.
        }
    }

    /** @return array<string, mixed> */
    public function safeWorkerTest(int $ownerId): array
    {
        $job = $this->create($ownerId, ['privacy_mode' => 'Strict', 'collectors' => ['environment'], 'document_ids' => [], 'options' => ['include_original_documents' => false, 'worker_test' => true, 'export_scope' => 'METADATA_ONLY', 'dependency_scope' => 'SOURCE_ONLY']]);
        $jobId = (string) $job['job_id'];
        $result = $this->advance($jobId, $ownerId, null, 10000);
        return ['test_job_id' => $jobId, 'state' => ($result['status'] ?? '') === 'completed' ? 'PASS' : 'FAIL', 'job' => $result];
    }

    /** @param array<string, mixed> $job */
    private function performOneStep(array &$job): bool
    {
        $phase = (string) ($job['phase'] ?? 'initializing');
        if ($phase === 'initializing') {
            $job['phase'] = 'collecting';
            $job['progress'] = 1;
            $job['last_successful_step_at'] = time();
            return false;
        }
        if ($phase === 'collecting') {
            $plan = array_values(array_filter((array) ($job['selected_components'] ?? []), 'is_string'));
            $cursor = (int) ($job['cursor'] ?? 0);
            if ($cursor >= count($plan)) {
                $job['phase'] = 'packaging';
                $job['progress'] = 88;
                return false;
            }
            $componentId = $plan[$cursor];
            $job['current_component'] = $componentId;
            $context = $this->context($job);
            $availableArtifacts = $this->committedArtifacts[(string) $job['job_id']] ?? [];
            $committed = $this->componentInputs($componentId, $availableArtifacts);
            $records = is_array($job['completed_step_records'] ?? null) ? $job['completed_step_records'] : [];
            $stepInputSha256 = $this->stepInputSha256($componentId, $job, $records);
            try {
                $result = $this->registry->execute($componentId, $context, $committed);
            } catch (\Throwable $exception) {
                $definition = $this->registry->definition($componentId);
                $result = new CollectionResult(
                    $componentId,
                    TruthState::UNKNOWN,
                    EvidenceAvailability::ERROR,
                    $definition->componentType,
                    null,
                    [new Diagnostic('EDIS_COMPONENT_RUNTIME_FAILURE', 'ERROR', 'SEMANTIC', 'diagnostic.component.runtime_failure', ['component_id' => $componentId, 'exception_class' => get_class($exception)])],
                );
            }
            $artifact = $result->jsonSerialize();
            $this->artifacts->put((string) $job['job_id'], $componentId, $artifact);
            $this->committedArtifacts[(string) $job['job_id']][$componentId] = $artifact;
            $artifactSha256 = $this->artifacts->fileSha256((string) $job['job_id'], $componentId);
            if (!is_string($artifactSha256)) {
                throw new ExportIntegrityException('EDIS_ARTIFACT_COMMIT_FAILED', 'A completed component artifact could not be verified after commit.');
            }
            $definition = $this->registry->definition($componentId);
            $records[$componentId] = [
                'component_id' => $componentId,
                'component_schema_version' => $definition->schemaVersion,
                'implementation_version' => '3.7.11',
                'input_snapshot_sha256' => (string) ($job['input_snapshot_sha256'] ?? ''),
                'step_input_sha256' => $stepInputSha256,
                'artifact_file_sha256' => $artifactSha256,
            ];
            ksort($records, SORT_STRING);
            $job['completed_step_records'] = $records;
            $job['cursor'] = $cursor + 1;
            $job['completed_components'][] = $componentId;
            $truth = (string) ($artifact['source_truth_state'] ?? 'UNKNOWN');
            $availability = (string) ($artifact['source_availability'] ?? 'ERROR');
            $job['truth_summary'][$truth] = (int) ($job['truth_summary'][$truth] ?? 0) + 1;
            $job['availability_summary'][$availability] = (int) ($job['availability_summary'][$availability] ?? 0) + 1;
            foreach (($artifact['diagnostics'] ?? []) as $diagnostic) {
                if ($diagnostic instanceof \JsonSerializable) {
                    $diagnostic = $diagnostic->jsonSerialize();
                }
                if (is_array($diagnostic)) {
                    $diagnostic['component_id'] = $componentId;
                    $job['diagnostics'][] = $diagnostic;
                }
            }
            $job['last_successful_step_at'] = time();
            $job['progress'] = min(87, 3 + (int) floor((($cursor + 1) / max(1, count($plan))) * 84));
            return false;
        }
        if ($phase === 'packaging') {
            $plan = array_values(array_filter((array) ($job['selected_components'] ?? []), 'is_string'));
            $context = $this->context($job);
            $bundle = $this->exporter->package((string) $job['job_id'], $context, $plan, $this->artifacts, $this->files, (int) $job['expires_at']);
            $job['bundle_sha256'] = $bundle['sha256'];
            $job['bundle_size'] = $bundle['size'];
            $job['download_token'] = $bundle['token'];
            $job['download_expires_at'] = $bundle['expires_at'];
            $job['validation_state'] = $bundle['validation_state'];
            $job['source_export_root_sha256'] = $bundle['source_export_root_sha256'];
            $job['phase'] = 'completed';
            $job['status'] = 'completed';
            $job['progress'] = 100;
            $job['current_component'] = null;
            $job['last_successful_step_at'] = time();
            $job['schedule_state'] = 'COMPLETE';
            $this->markDocumentExports($job);
            return true;
        }
        throw new \LogicException('Unknown export phase: ' . $phase);
    }

    /** @param array<string,mixed> $job */
    private function assertJobCompatible(array $job): void
    {
        if (($job['job_format_version'] ?? null) !== '2.1.0'
            || ($job['input_snapshot_format_version'] ?? null) !== '2.0.0'
            || ($job['implementation_version'] ?? null) !== '3.7.11') {
            throw new ExportIntegrityException('EDIS_JOB_FORMAT_INCOMPATIBLE', 'This job was created by an older worker contract and cannot be resumed. Create a new export job.');
        }
        $snapshotId = is_string($job['input_snapshot_id'] ?? null) ? $job['input_snapshot_id'] : '';
        $snapshotSha256 = is_string($job['input_snapshot_sha256'] ?? null) ? $job['input_snapshot_sha256'] : '';
        $snapshotValid = false;
        if ($snapshotId !== '' && $snapshotSha256 !== '') {
            try {
                $snapshotValid = $this->inputs->verify($snapshotId, $snapshotSha256);
            } catch (\Throwable) {
                $snapshotValid = false;
            }
        }
        if (!$snapshotValid) {
            throw new ExportIntegrityException('EDIS_RESUME_INPUT_MISMATCH', 'The immutable input snapshot is missing or no longer matches the job contract.');
        }
    }

    /** @param array<string,mixed> $job */
    private function assertCurrentResumeState(array $job): void
    {
        $plan = array_values(array_filter((array) ($job['selected_components'] ?? []), 'is_string'));
        $cursor = (int) ($job['cursor'] ?? 0);
        $this->assertResumeState($job, $plan, $cursor);
    }

    /** @param array<string,array<string,mixed>> $available @return array<string,array<string,mixed>> */
    private function componentInputs(string $componentId, array $available): array
    {
        $inputs = [];
        foreach ($this->registry->definition($componentId)->dependencies as $dependency) {
            $dependencyId = (string) ($dependency['id'] ?? '');
            if ($dependencyId !== '' && isset($available[$dependencyId])) {
                $inputs[$dependencyId] = $available[$dependencyId];
            }
        }
        ksort($inputs, SORT_STRING);
        return $inputs;
    }

    /** @param array<string,mixed> $job */
    private function primeCommittedArtifacts(array $job): void
    {
        $jobId = (string) ($job['job_id'] ?? '');
        if ($jobId === '' || isset($this->committedArtifacts[$jobId])) {
            return;
        }
        $plan = array_values(array_filter((array) ($job['selected_components'] ?? []), 'is_string'));
        $cursor = (int) ($job['cursor'] ?? 0);
        $this->committedArtifacts[$jobId] = $this->artifacts->all($jobId, array_slice($plan, 0, $cursor));
    }

    /** @param array<string,mixed> $job @param list<string> $plan */
    private function assertResumeState(array $job, array $plan, int $cursor): void
    {
        if ($cursor < 0 || $cursor > count($plan)) {
            throw new ExportIntegrityException('EDIS_RESUME_STATE_INVALID', 'The job cursor is outside the execution plan.');
        }
        $expectedComponents = array_slice($plan, 0, $cursor);
        $completed = array_values(array_filter((array) ($job['completed_components'] ?? []), 'is_string'));
        if ($completed !== $expectedComponents) {
            throw new ExportIntegrityException('EDIS_RESUME_STATE_INVALID', 'Completed components do not match the execution-plan prefix.');
        }
        $records = is_array($job['completed_step_records'] ?? null) ? $job['completed_step_records'] : [];
        if (count($records) !== $cursor) {
            throw new ExportIntegrityException('EDIS_RESUME_ARTIFACT_MISMATCH', 'Completed step records do not match the job cursor.');
        }
        $verifiedRecords = [];
        foreach ($expectedComponents as $componentId) {
            $record = is_array($records[$componentId] ?? null) ? $records[$componentId] : null;
            if ($record === null) {
                throw new ExportIntegrityException('EDIS_RESUME_ARTIFACT_MISMATCH', 'A completed step record is missing.');
            }
            $definition = $this->registry->definition($componentId);
            $artifactSha256 = is_string($record['artifact_file_sha256'] ?? null) ? $record['artifact_file_sha256'] : '';
            $valid = ($record['component_id'] ?? null) === $componentId
                && ($record['component_schema_version'] ?? null) === $definition->schemaVersion
                && ($record['implementation_version'] ?? null) === '3.7.11'
                && ($record['input_snapshot_sha256'] ?? null) === ($job['input_snapshot_sha256'] ?? null)
                && $this->artifacts->verifyFileSha256((string) $job['job_id'], $componentId, $artifactSha256)
                && ($record['step_input_sha256'] ?? null) === $this->stepInputSha256($componentId, $job, $verifiedRecords);
            if (!$valid) {
                throw new ExportIntegrityException('EDIS_RESUME_ARTIFACT_MISMATCH', 'A completed artifact or its deterministic step contract no longer matches the job.');
            }
            $verifiedRecords[$componentId] = $record;
        }
    }

    /** @param array<string,mixed> $job @param array<string,array<string,mixed>> $priorRecords */
    private function stepInputSha256(string $componentId, array $job, array $priorRecords): string
    {
        $artifactHashes = [];
        foreach ($priorRecords as $priorId => $record) {
            if (is_array($record) && is_string($record['artifact_file_sha256'] ?? null)) {
                $artifactHashes[(string) $priorId] = $record['artifact_file_sha256'];
            }
        }
        ksort($artifactHashes, SORT_STRING);
        $definition = $this->registry->definition($componentId);
        return 'sha256:' . hash('sha256', CanonicalJson::encode([
            'job_format_version' => '2.1.0',
            'component_id' => $componentId,
            'component_schema_version' => $definition->schemaVersion,
            'implementation_version' => '3.7.11',
            'analysis_set_id' => (string) ($job['analysis_set_id'] ?? ''),
            'wordpress_bundle_id' => (string) ($job['wordpress_bundle_id'] ?? ''),
            'input_snapshot_sha256' => (string) ($job['input_snapshot_sha256'] ?? ''),
            'prior_artifact_hashes' => (object) $artifactHashes,
        ]));
    }

    /** @param list<int> $documents @param array<string,mixed> $options */
    private function assertSnapshotSelection(string $snapshotId, array $documents, array $options): void
    {
        $documentStrings = array_map('strval', $documents);
        foreach ($documents as $documentId) {
            $captured = $this->inputs->document($snapshotId, $documentId);
            if (!is_array($captured) || !is_string($captured['raw_source'] ?? null)) {
                throw new ExportIntegrityException('EDIS_INPUT_SNAPSHOT_INTEGRITY_FAILED', 'A selected document could not be read from the immutable input snapshot.');
            }
            $decoded = DocumentIdentity::decodeAssociative($captured['raw_source']);
            if (!is_array($decoded)) {
                throw new ExportIntegrityException('EDIS_INPUT_SNAPSHOT_SOURCE_INVALID', 'A selected document snapshot is not valid Elementor JSON.');
            }
            $occurrences = $this->elementIdOccurrences($decoded);
            foreach ((array) ($options['element_selection'] ?? []) as $selection) {
                if (!is_array($selection) || (string) ($selection['document_id'] ?? '') !== (string) $documentId) {
                    continue;
                }
                $elementId = (string) ($selection['elementor_element_id'] ?? '');
                $count = (int) ($occurrences[$elementId] ?? 0);
                if ($count === 0) {
                    throw new ExportIntegrityException('EDIS_INSPECTOR_ELEMENT_NOT_FOUND', 'A selected Elementor element is not present in the captured saved source.');
                }
                if ($count > 1) {
                    throw new ExportIntegrityException('EDIS_INSPECTOR_ELEMENT_ID_AMBIGUOUS', 'A selected Elementor element ID is duplicated in the captured saved source.');
                }
            }
        }
        foreach ((array) ($options['element_selection'] ?? []) as $selection) {
            if (is_array($selection) && !in_array((string) ($selection['document_id'] ?? ''), $documentStrings, true)) {
                throw new ExportIntegrityException('EDIS_INSPECTOR_DOCUMENT_MISMATCH', 'An element selection does not belong to the captured document set.');
            }
        }
    }

    /** @param array<string, mixed> $job */
    private function context(array $job): CollectionContext
    {
        $config = is_array($job['config'] ?? null) ? $job['config'] : [];
        return new CollectionContext(
            array_values(array_filter((array) ($config['document_ids'] ?? []), 'is_int')),
            (bool) ($config['include_original_documents'] ?? false),
            (string) $job['analysis_set_id'],
            (string) $job['wordpress_bundle_id'],
            (string) ($config['privacy_mode'] ?? 'Standard'),
            is_array($config['options'] ?? null) ? $config['options'] : [],
            (string) ($config['captured_at'] ?? gmdate('Y-m-d\TH:i:s\Z')),
        );
    }

    /** @param array<string, mixed> $job */
    private function scheduleRecovery(array &$job): void
    {
        if (!function_exists('wp_schedule_single_event')) {
            $job['schedule_state'] = 'UNAVAILABLE';
            $job['schedule_error'] = 'WP_CRON_API_UNAVAILABLE';
            $this->jobs->save($job);
            return;
        }
        $args = [(string) $job['job_id']];
        if (function_exists('wp_next_scheduled') && wp_next_scheduled('edis_process_export_job', $args) !== false) {
            $job['schedule_state'] = 'SCHEDULED_RECOVERY';
            $job['schedule_error'] = null;
            $this->jobs->save($job);
            return;
        }
        $result = wp_schedule_single_event(time() + 15, 'edis_process_export_job', $args, true);
        if (function_exists('is_wp_error') && is_wp_error($result)) {
            $job['schedule_state'] = 'ERROR';
            $job['schedule_error'] = method_exists($result, 'get_error_code') ? (string) $result->get_error_code() : 'WP_CRON_SCHEDULE_ERROR';
        } elseif ($result === false) {
            $job['schedule_state'] = 'NOT_SCHEDULED';
            $job['schedule_error'] = 'WP_CRON_SCHEDULE_REJECTED';
        } else {
            $job['schedule_state'] = 'SCHEDULED_RECOVERY';
            $job['schedule_error'] = null;
        }
        $this->jobs->save($job);
    }


    private function clearRecoverySchedule(string $jobId): void
    {
        if ($jobId !== '' && function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook('edis_process_export_job', [$jobId]);
        }
    }

    /** @return array<string, mixed> */
    private function requireOwnedJob(string $jobId, int $ownerId): array
    {
        $job = $this->jobs->get($jobId);
        if (!is_array($job) || (int) ($job['owner_id'] ?? 0) !== $ownerId) {
            throw new \OutOfBoundsException('Export job not found.');
        }
        return $job;
    }

    private function privacyMode(mixed $value): string
    {
        $mode = is_string($value) ? $value : '';
        if (!in_array($mode, CollectionContext::PRIVACY_MODES, true)) {
            throw new \InvalidArgumentException('Invalid privacy mode.');
        }
        return $mode;
    }

    /** @return list<string> */
    private function componentIds(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Components must be an array.');
        }
        $ids = [];
        foreach ($value as $id) {
            if (!is_string($id) || !$this->registry->isExecutable($id) || !$this->registry->definition($id)->selectable) {
                continue;
            }
            $ids[$id] = true;
        }
        if ($ids === []) {
            $ids = array_fill_keys($this->registry->defaultSelectableIds(), true);
        }
        return array_keys($ids);
    }

    /** @return list<int> */
    private function documentIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $ids = [];
        foreach ($value as $id) {
            $id = (int) $id;
            if ($id > 0 && function_exists('current_user_can') && current_user_can('edit_post', $id)) {
                $ids[$id] = true;
            }
        }
        $result = array_keys($ids);
        sort($result, SORT_NUMERIC);
        return $result;
    }

    /** @return array<string, mixed> */
    private function options(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $elementSelection=[];
        foreach(array_slice(is_array($value['element_selection']??null)?$value['element_selection']:[],0,50) as $item){
            if(!is_array($item)){continue;}
            $documentId=is_scalar($item['document_id']??null)?(string)$item['document_id']:'';
            $elementId=is_string($item['elementor_element_id']??null)?$item['elementor_element_id']:'';
            if($documentId===''||!(strlen($elementId)<=128&&$elementId!==''&&ctype_alnum(str_replace(['-','_'],'',$elementId)))){continue;}
            $elementSelection[$documentId.':'.$elementId]=[
                'document_id'=>$documentId,
                'elementor_element_id'=>$elementId,
                'include_descendants'=>!empty($item['include_descendants']),
                'selection_reason'=>'USER_SELECTED',
            ];
        }
        $elementSelection=array_values($elementSelection);usort($elementSelection,static fn(array $a,array $b):int=>[$a['document_id'],$a['elementor_element_id'],(int)$a['include_descendants']]<=>[$b['document_id'],$b['elementor_element_id'],(int)$b['include_descendants']]);
        $selectionScope='DOCUMENT';
        if(count($elementSelection)===1){$selectionScope=!empty($elementSelection[0]['include_descendants'])?'SUBTREE':'ELEMENT';}
        elseif(count($elementSelection)>1){$selectionScope='MULTI_SUBTREE';}
        $editorUnsavedState = strtoupper((string) ($value['editor_unsaved_changes_state'] ?? 'UNAVAILABLE'));
        if (!in_array($editorUnsavedState, ['TRUE','FALSE','UNAVAILABLE','ERROR'], true)) { $editorUnsavedState = 'UNAVAILABLE'; }
        return [
            'include_original_documents' => !empty($value['include_original_documents']),
            'include_diagnostic_metadata' => !empty($value['include_diagnostic_metadata']),
            'document_inventory_limit' => max(1, min(2000, (int) ($value['document_inventory_limit'] ?? 500))),
            'worker_test' => !empty($value['worker_test']),
            'fixture_mode' => !empty($value['fixture_mode']),
            'compare_previous_export' => !array_key_exists('compare_previous_export', $value) || !empty($value['compare_previous_export']),
            'export_scope' => is_string($value['export_scope'] ?? null) ? (string) $value['export_scope'] : 'MULTIPLE_DOCUMENTS',
            'dependency_scope' => is_string($value['dependency_scope'] ?? null) ? (string) $value['dependency_scope'] : 'REQUIRED_DEPENDENCIES',
            'element_selection'=>$elementSelection,
            'element_selection_scope'=>$selectionScope,
            'editor_unsaved_changes_state'=>$editorUnsavedState,
            'editor_unsaved_changes_detected'=>strtoupper((string)($value['editor_unsaved_changes_state']??''))==='TRUE'||!empty($value['editor_unsaved_changes_detected']),
            'editor_selection_source'=>is_string($value['editor_selection_source']??null)?(string)$value['editor_selection_source']:'NONE',
        ];
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public function preflight(int $ownerId,array $request):array
    {
        if($ownerId<=0){throw new \InvalidArgumentException('An authenticated owner is required.');}
        return $this->preflightNormalized($this->normalizeRequest($request), $ownerId);
    }

    /** @param array{privacy_mode:string,collectors:list<string>,document_ids:list<int>,options:array<string,bool|int|string>} $normalized @return array<string,mixed> */
    private function preflightNormalized(array $normalized, int $ownerId = 0):array
    {
        $documents=$normalized['document_ids'];$options=$normalized['options'];$blockers=[];$warnings=[];$sourceAvailable=true;
        if (!CanonicalJson::environmentReady()) {
            $blockers[] = ['code' => 'EDIS_UNSUPPORTED_DETERMINISTIC_RUNTIME', 'message' => 'EDIS requires a verified 64-bit PHP 8.2–8.5 runtime with fsync and deterministic serialize_precision support.'];
        }
        if ($this->privateStorage instanceof PrivateStorage) {
            $storageTest = $this->privateStorage->selfTest(true);
            if (!$this->privateStorage->acceptsSelfTestResult($storageTest)) {
                $blockers[] = [
                    'code' => 'EDIS_PRIVATE_STORAGE_LOCK_PROBE_FAILED',
                    'message' => 'Active private storage did not prove durable writes, atomic replacement, local lock exclusion, and separate-process lock exclusion.',
                ];
            }
        }
        $estimatedRawBytes=0;$estimatedElements=0;$selectedRecords=[];$sourceHashes=[];
        foreach($documents as $id){
            $rawBytes=$this->currentRawSourceBytes($id);
            if($rawBytes===null){$sourceAvailable=false;$blockers[]=['code'=>'EDIS_PREFLIGHT_SOURCE_MISSING','message'=>'Saved Elementor source is missing for document '.$id.'.'];continue;}
            $inspection=DocumentIdentity::inspectSource($rawBytes);$stats=$this->elementSourceStats($inspection['processing_value']);$elementCount=$stats['count'];$occurrences=$stats['occurrences'];foreach((array)($options['element_selection']??[]) as $selection){if((string)($selection['document_id']??'')!==(string)$id){continue;}$elementId=(string)($selection['elementor_element_id']??'');$count=(int)($occurrences[$elementId]??0);if($count===0){$blockers[]=['code'=>'EDIS_INSPECTOR_ELEMENT_NOT_FOUND','message'=>'The selected Elementor element '.$elementId.' is not present in the last saved source for document '.$id.'.'];}elseif($count>1){$blockers[]=['code'=>'EDIS_INSPECTOR_ELEMENT_ID_AMBIGUOUS','message'=>'The selected Elementor element '.$elementId.' is duplicated in document '.$id.'.'];}}
            $estimatedRawBytes+=strlen($rawBytes);$estimatedElements+=$elementCount;$sourceHashes[(string)$id]='sha256:'.hash('sha256',$rawBytes);
            $selectedRecords[]=['document_id'=>(string)$id,'title'=>function_exists('get_the_title')?(string)get_the_title($id):'','raw_source_bytes'=>strlen($rawBytes),'estimated_element_count'=>$elementCount];
        }
        $scope=(string)$options['export_scope'];if($scope==='METADATA_ONLY'){$sourceAvailable=true;}if($scope==='ENTIRE_SITE'&&$documents===[]){$blockers[]=['code'=>'EDIS_PREFLIGHT_NO_SITE_DOCUMENTS','message'=>'No editable Elementor documents were found for Entire Site scope.'];}
        $inventory=is_array($normalized['inventory']??null)?$normalized['inventory']:[];if($scope==='ENTIRE_SITE'&&!empty($inventory['truncated'])){$blockers[]=['code'=>'EDIS_ENTIRE_SITE_SCOPE_TRUNCATED','message'=>'Entire Site scope exceeds the configured document inventory limit. Increase the explicit limit or choose a bounded scope.'];}
        if((string)$options['dependency_scope']==='SOURCE_ONLY'){$warnings[]=['code'=>'EDIS_PREFLIGHT_SOURCE_ONLY','message'=>'Source-only mode can leave Variables, Global Classes, Kit or breakpoint references unresolved until a later export provides them.'];}
        if(($options['editor_unsaved_changes_state']??'UNAVAILABLE')==='TRUE'){$warnings[]=['code'=>'EDIS_EDITOR_UNSAVED_CHANGES_LAST_SAVED_ONLY','message'=>'Unsaved Elementor editor changes were reported by the client. EDIS will export the last saved WordPress source only.'];}
        $includeOriginal=!empty($options['include_original_documents'])&&$normalized['privacy_mode']!=='Strict';
        if($normalized['privacy_mode']==='Diagnostic'){$warnings[]=['code'=>'EDIS_PREFLIGHT_DIAGNOSTIC_DISCLOSURE','message'=>'Diagnostic mode may include document titles, environment versions, plugin/theme metadata and source content selected by the active options.'];}
        $kitAvailable=function_exists('get_option')&&(int)get_option('elementor_active_kit',0)>0;$bridgeReady=$scope==='METADATA_ONLY'||($documents!==[]&&$sourceAvailable);
        $result = [
            'state'=>$blockers===[]?'PASS':'FAIL','export_scope'=>$scope,'dependency_scope'=>(string)$options['dependency_scope'],
            'selected_document_count'=>count($documents),'selected_document_ids'=>array_map('strval',$documents),'selected_documents'=>$selectedRecords,
            'saved_source_available'=>$sourceAvailable,'active_kit_available'=>$kitAvailable,'bridge_context_ready'=>$bridgeReady,
            'estimated_raw_source_bytes'=>$estimatedRawBytes,'estimated_element_count'=>$estimatedElements,
            'other_document_titles_included'=>in_array($scope,['SINGLE_DOCUMENT','MULTIPLE_DOCUMENTS'],true)&&(string)$options['dependency_scope']!=='FULL_SITE_CONTEXT'?0:null,
            'original_text_content_included'=>$includeOriginal,'media_urls_may_be_included'=>$includeOriginal,
            'fixture_mode'=>!empty($options['fixture_mode']),'compare_previous_export'=>!empty($options['compare_previous_export']),'selected_element_count'=>count((array)($options['element_selection']??[])),'selection_scope'=>(string)($options['element_selection_scope']??'DOCUMENT'),'editor_unsaved_changes_detected'=>!empty($options['editor_unsaved_changes_detected']),'editor_unsaved_changes_state'=>(string)($options['editor_unsaved_changes_state']??'UNAVAILABLE'),
            'entire_site_inventory_limit'=>(int)($inventory['limit']??0),'entire_site_eligible_count_lower_bound'=>(int)($inventory['eligible_count_lower_bound']??count($documents)),'entire_site_truncated'=>!empty($inventory['truncated']),
            'blockers'=>$blockers,'warnings'=>$warnings
        ];
        if($result['state']==='PASS'&&$ownerId>0&&$this->preflightProof instanceof PreflightProof){$result['preflight_token']=$this->preflightProof->issue($ownerId,$normalized,$sourceHashes);}
        return $result;
    }

    /** @param array<string,mixed> $normalized @param array{source_raw_sha256:array<string,string>,expires_at:int} $proof */
    private function preflightProofStillValid(array $normalized,array $proof):bool
    {
        if($this->privateStorage instanceof PrivateStorage&&!$this->privateStorage->acceptsSelfTestResult($this->privateStorage->selfTest(false))){return false;}
        $expected=$proof['source_raw_sha256'];$documents=array_map('strval',$normalized['document_ids']);sort($documents,SORT_STRING);$proofIds=array_keys($expected);sort($proofIds,SORT_STRING);if($documents!==$proofIds){return false;}
        foreach($expected as $documentId=>$hash){$raw=$this->currentRawSourceBytes((int)$documentId);if($raw===null||!hash_equals($hash,'sha256:'.hash('sha256',$raw))){return false;}}
        return true;
    }

    private function currentRawSourceBytes(int $documentId):?string
    {
        if(!function_exists('get_post_meta')){return null;}$raw=get_post_meta($documentId,'_elementor_data',true);if(is_string($raw)){return $raw!==''?$raw:null;}if(is_array($raw)||$raw instanceof \stdClass){return CanonicalJson::encode($raw);}return null;
    }

    /** @param array<string,mixed> $request @return array{privacy_mode:string,collectors:list<string>,document_ids:list<int>,options:array<string,mixed>,inventory:array<string,mixed>} */
    private function normalizeRequest(array $request):array
    {
        $privacy=$this->privacyMode($request['privacy_mode']??$this->settings->defaultPrivacyMode());$options=$this->options($request['options']??[]);$scope=(string)$options['export_scope'];$dependency=(string)$options['dependency_scope'];
        if(!in_array($scope,CollectionContext::EXPORT_SCOPES,true)){throw new \InvalidArgumentException('Invalid export scope.');}if(!in_array($dependency,CollectionContext::DEPENDENCY_SCOPES,true)){throw new \InvalidArgumentException('Invalid dependency scope.');}
        $inventory=['limit'=>(int)$options['document_inventory_limit'],'eligible_count_lower_bound'=>0,'truncated'=>false];$documents=$this->documentIds($request['document_ids']??[]);if($scope==='ENTIRE_SITE'){$inventory=$this->editableElementorDocumentInventory((int)$options['document_inventory_limit']);$documents=$inventory['document_ids'];}if($scope==='METADATA_ONLY'){$documents=[];}
        if($scope==='SINGLE_DOCUMENT'&&count($documents)!==1){throw new \InvalidArgumentException('Single Document scope requires exactly one selected document.');}if($scope==='MULTIPLE_DOCUMENTS'&&$documents===[]){throw new \InvalidArgumentException('Multiple Documents scope requires at least one selected document.');}
        $documentStrings=array_map('strval',$documents);foreach((array)($options['element_selection']??[]) as $selection){if(!in_array((string)($selection['document_id']??''),$documentStrings,true)){throw new \InvalidArgumentException('Element selections must belong to a selected document.');}}
        if(($options['element_selection']??[])!==[]&&!in_array($scope,['SINGLE_DOCUMENT','MULTIPLE_DOCUMENTS'],true)){throw new \InvalidArgumentException('Element selection requires Single or Multiple Documents scope.');}
        $collectors=$this->componentIds($request['collectors']??$this->settings->defaultCollectors());$collectors=$this->applyDependencyScope($collectors,$scope,$dependency);
        return ['privacy_mode'=>$privacy,'collectors'=>$collectors,'document_ids'=>$documents,'options'=>$options,'inventory'=>$inventory];
    }

    /** @return array{document_ids:list<int>,limit:int,eligible_count_lower_bound:int,truncated:bool} */
    private function editableElementorDocumentInventory(int $limit):array
    {
        $limit=max(1,min(2000,$limit));if(!class_exists('WP_Query')){return ['document_ids'=>[],'limit'=>$limit,'eligible_count_lower_bound'=>0,'truncated'=>false];}
        $ids=[];$page=1;$batch=min(250,$limit+1);$exhausted=false;
        while(count($ids)<=$limit&&!$exhausted){$query=new \WP_Query(['post_type'=>'any','post_status'=>['publish','draft','pending','private','future'],'posts_per_page'=>$batch,'paged'=>$page,'orderby'=>'ID','order'=>'ASC','fields'=>'ids','no_found_rows'=>true,'meta_query'=>[['key'=>'_elementor_data','compare'=>'EXISTS']]]);$posts=(array)$query->posts;$exhausted=count($posts)<$batch;foreach($posts as $value){$id=(int)$value;if($id>0&&current_user_can('edit_post',$id)){$ids[$id]=true;if(count($ids)>$limit){break;}}}$page++;if($posts===[]){break;}}
        $all=array_keys($ids);sort($all,SORT_NUMERIC);$truncated=count($all)>$limit;return ['document_ids'=>array_slice($all,0,$limit),'limit'=>$limit,'eligible_count_lower_bound'=>count($all),'truncated'=>$truncated];
    }

    /** @param list<int> $documents @param array<string,mixed> $options @param array<string,mixed> $inputSnapshot @return array<string,mixed> */
    private function selectionSnapshot(array $documents, array $options, array $inputSnapshot): array
    {
        $records = $inputSnapshot['documents'] ?? [];
        if ($records instanceof \stdClass) {
            $records = get_object_vars($records);
        }
        $records = is_array($records) ? $records : [];
        $hashes = [];
        $rawHashes = [];
        foreach ($documents as $id) {
            $record = is_array($records[(string) $id] ?? null) ? $records[(string) $id] : [];
            $hash = $record['canonical_saved_source_sha256'] ?? null;
            if (!is_string($hash) || $hash === '') {
                throw new ExportIntegrityException('EDIS_INPUT_SNAPSHOT_INTEGRITY_FAILED', 'A selected-source hash is missing from the immutable input snapshot.');
            }
            $hashes[(string) $id] = $hash;
            $rawHash = $record['raw_storage_bytes_sha256'] ?? null;
            if (is_string($rawHash) && $rawHash !== '') { $rawHashes[(string) $id] = $rawHash; }
        }
        ksort($hashes, SORT_STRING);
        ksort($rawHashes, SORT_STRING);
        return [
            'selection_revision' => 4,
            'selected_document_ids' => array_map('strval', $documents),
            'selected_at' => (string) ($inputSnapshot['captured_at'] ?? gmdate('Y-m-d\TH:i:s\Z')),
            'selected_source_hashes' => $hashes,
            'selected_source_raw_hashes' => $rawHashes === [] ? (object) [] : $rawHashes,
            'hash_kind' => 'CANONICAL_SAVED_SOURCE',
            'selection_scope' => (string) ($options['element_selection_scope'] ?? 'DOCUMENT'),
            'selected_elements' => array_values((array) ($options['element_selection'] ?? [])),
            'editor_unsaved_changes_detected' => !empty($options['editor_unsaved_changes_detected']),
            'editor_unsaved_changes_state' => (string) ($options['editor_unsaved_changes_state'] ?? 'UNAVAILABLE'),
            'exported_state' => 'LAST_SAVED_SOURCE',
        ];
    }

    /** @return array{count:int,occurrences:array<string,int>} */
    private function elementSourceStats(mixed $value): array
    {
        if (!is_array($value)) {
            return ['count' => 0, 'occurrences' => []];
        }
        $count = 0;
        $occurrences = [];
        $stack = [$value];
        while ($stack !== []) {
            $node = array_pop($stack);
            if (!is_array($node)) {
                continue;
            }
            if (isset($node['id']) && is_scalar($node['id']) && (isset($node['elType']) || isset($node['widgetType']))) {
                $count++;
                $id = (string) $node['id'];
                if ($id !== '') {
                    $occurrences[$id] = ($occurrences[$id] ?? 0) + 1;
                }
            }
            foreach ($node as $child) {
                if (is_array($child)) {
                    $stack[] = $child;
                }
            }
        }
        ksort($occurrences, SORT_STRING);
        return ['count' => $count, 'occurrences' => $occurrences];
    }

    /** @return array<string,int> */
    private function elementIdOccurrences(mixed $value):array
    {
        if(!is_array($value)){return [];}$counts=[];$stack=[$value];while($stack!==[]){$node=array_pop($stack);if(!is_array($node)){continue;}if(isset($node['id'])&&is_scalar($node['id'])&&(isset($node['elType'])||isset($node['widgetType']))){$id=(string)$node['id'];if($id!==''){$counts[$id]=($counts[$id]??0)+1;}}foreach($node as $child){if(is_array($child)){$stack[]=$child;}}}ksort($counts,SORT_STRING);return $counts;
    }

    private function countElementRecords(mixed $value):int
    {
        if(!is_array($value)){return 0;}$count=0;$stack=[$value];while($stack!==[]){$node=array_pop($stack);if(!is_array($node)){continue;}if(isset($node['id'])&&(isset($node['elType'])||isset($node['widgetType']))){$count++;}foreach($node as $child){if(is_array($child)){$stack[]=$child;}}}return $count;
    }


    /** @param list<string> $collectors @return list<string> */
    private function applyDependencyScope(array $collectors,string $exportScope,string $dependencyScope):array
    {
        $metadataOnly=['environment','plugin','theme','elementor_installation','elementor_feature_flags','elementor_breakpoints','elementor_kit_metadata','elementor_kit_settings','elementor_variables_registry','elementor_global_classes_registry','elementor_global_classes_order','elementor_legacy_global_styles','elementor_registered_widgets','elementor_registered_document_types','elementor_document_inventory','elementor_performance_configuration','elementor_site_settings_index','elementor_capability_evidence'];
        $sourceOnly=['environment','elementor_installation','elementor_document_inventory','elementor_document_source','elementor_document_index','elementor_element_structure_index','elementor_responsive_declaration_index','elementor_dynamic_references','elementor_reference_index','elementor_architecture_index','elementor_usage_summary'];
        $requiredContext=array_values(array_unique(array_merge($sourceOnly,['elementor_breakpoints','elementor_kit_metadata','elementor_kit_settings','elementor_variables_registry','elementor_global_classes_registry','elementor_global_classes_order','elementor_legacy_global_styles','elementor_registered_widgets','elementor_registered_document_types','elementor_site_settings_index','elementor_capability_evidence'])));
        if($exportScope==='METADATA_ONLY'){$allowed=array_fill_keys($metadataOnly,true);}
        elseif($dependencyScope==='SOURCE_ONLY'){$allowed=array_fill_keys($sourceOnly,true);}
        elseif($dependencyScope==='REQUIRED_DEPENDENCIES'){$allowed=array_fill_keys($requiredContext,true);}
        else{return array_values(array_unique($collectors));}
        $filtered=[];foreach($collectors as $id){if(isset($allowed[$id])){$filtered[$id]=true;}}
        foreach(array_keys($allowed) as $id){if($this->registry->isExecutable($id)&&$this->registry->definition($id)->defaultEnabled){$filtered[$id]=true;}}
        $result=array_keys($filtered);sort($result,SORT_STRING);return $result;
    }

    /** @param array<string,mixed> $job */
    private function markDocumentExports(array $job):void
    {
        if(!function_exists('update_post_meta')){return;}
        $snapshot=is_array($job['selection_snapshot']??null)?$job['selection_snapshot']:[];$hashes=is_array($snapshot['selected_source_hashes']??null)?$snapshot['selected_source_hashes']:[];$rawHashes=is_array($snapshot['selected_source_raw_hashes']??null)?$snapshot['selected_source_raw_hashes']:[];
        $summaryByDocument=$this->completedSourceSummaries((string)($job['job_id']??''));
        foreach((array)($snapshot['selected_document_ids']??[]) as $value){$id=(int)$value;if($id<=0){continue;}update_post_meta($id,'_edis_last_evidence_export',[
            'exported_at'=>gmdate('Y-m-d\TH:i:s\Z'),'bundle_sha256'=>$job['bundle_sha256']??null,'validation_state'=>$job['validation_state']??'NOT_RUN',
            'canonical_saved_source_sha256'=>$hashes[(string)$id]??null,'saved_source_sha256'=>$hashes[(string)$id]??null,'raw_storage_bytes_sha256'=>$rawHashes[(string)$id]??null,
            'source_summary'=>$summaryByDocument[(string)$id]??$this->emptySourceSummary(),'analysis_set_id'=>$job['analysis_set_id']??null,
            'wordpress_bundle_id'=>$job['wordpress_bundle_id']??null,'producer_version'=>'3.7.11']);}
    }

    /** @return array<string,array<string,int>> */ private function completedSourceSummaries(string $jobId):array
    {
        $summaries=[];$structure=$this->artifacts->get($jobId,'elementor_element_structure_index');
        foreach((array)($structure['data']['elements']??[]) as $record){if(!is_array($record)){continue;}$id=(string)($record['document_id']??'');if($id===''){continue;}$summaries[$id]??=$this->emptySourceSummary();$summaries[$id]['element_count']++;}
        $responsive=$this->artifacts->get($jobId,'elementor_responsive_declaration_index');foreach((array)($responsive['data']['declarations']??[]) as $record){if(!is_array($record)){continue;}$id=(string)($record['document_id']??'');if($id===''){continue;}$summaries[$id]??=$this->emptySourceSummary();$summaries[$id]['responsive_declaration_count']++;}
        $references=$this->artifacts->get($jobId,'elementor_dynamic_references');foreach((array)($references['data']['references']??[]) as $record){if(!is_array($record)){continue;}$id=(string)($record['document_id']??'');if($id===''){continue;}$summaries[$id]??=$this->emptySourceSummary();$summaries[$id]['reference_count']++;}
        ksort($summaries,SORT_STRING);return $summaries;
    }
    /** @return array{element_count:int,responsive_declaration_count:int,reference_count:int} */ private function emptySourceSummary():array{return ['element_count'=>0,'responsive_declaration_count'=>0,'reference_count'=>0];}

    private function hourSeconds(): int
    {
        return defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600;
    }
}
