<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Application;

use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportIntegrityException;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;

/**
 * Narrow boundary around the existing authoritative Job-creation workflow.
 *
 * The existing ExportJobService remains the actual creator. This boundary
 * performs deterministic expected-rejection checks, preserves source-owned
 * reason codes, and proves whether a failure occurred before or after one
 * exact durable Job became resolvable.
 */
final class ExportCreateConformance
{
    public function __construct(
        private readonly ExportJobService $service,
        private readonly CollectorRegistry $registry,
        private readonly JobStore $jobs,
    ) {
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public function create(int $ownerId, array $request): array
    {
        $validated = $this->validateExpectedRequest($ownerId, $request);
        $this->validateExecutionPlan($validated['collectors'], $validated['dependency_scope']);

        $before = $this->jobIdsForOwner($ownerId);
        $startedAt = time();
        try {
            return $this->service->create($ownerId, $request);
        } catch (\Throwable $exception) {
            $durable = $this->resolveSingleNewDurableJob(
                $ownerId,
                $before,
                $startedAt,
                $validated['selected_document_count'],
            );
            if (is_array($durable)) {
                $reasonCode = $exception instanceof ExportIntegrityException
                    ? $exception->diagnosticCode
                    : 'EDIS_POST_CREATE_SCHEDULING_FAILED';
                $context = [
                    'schedule_state' => $this->safeIdentifier($durable['schedule_state'] ?? null, 'UNAVAILABLE'),
                    'schedule_error' => $this->safeIdentifier($durable['schedule_error'] ?? null, 'UNAVAILABLE'),
                ];
                $observation = new FailureObservation(
                    $reasonCode,
                    'post_create_scheduling',
                    'MATERIAL_JOB_INCIDENT',
                    'JOB_BOUND',
                    'RETRY_ALLOWED',
                    $context,
                );
                throw new DurableJobFailureException(
                    (string) $durable['job_id'],
                    $observation,
                    JobFailureCursor::capture($durable),
                    $exception,
                );
            }

            if ($exception instanceof ExportIntegrityException) {
                $stage = $this->stageForIntegrityFailure($exception);
                $context = $this->boundedIntegrityContext($exception);
                $observation = new FailureObservation(
                    $exception->diagnosticCode,
                    $stage,
                    'INTERNAL_EVIDENCE_FAILURE',
                    'PRE_JOB',
                    'NEW_JOB_REQUIRED',
                    $context,
                );
                throw new FailureObservationException($observation, $exception);
            }

            $observation = new FailureObservation(
                'EDIS_EXPORT_CREATE_STAGE_UNAVAILABLE',
                null,
                'UNEXPECTED_MATERIAL_FAILURE',
                'PRE_JOB',
                'NOT_PROVEN',
                ['exception_class' => $exception::class],
            );
            throw new FailureObservationException($observation, $exception);
        }
    }

    /**
     * @param array<string,mixed> $request
     * @return array{collectors:list<string>,dependency_scope:string,selected_document_count:int}
     */
    private function validateExpectedRequest(int $ownerId, array $request): array
    {
        if ($ownerId <= 0) {
            throw new ExpectedOperationRejection('edis_export_unauthenticated', 401);
        }
        $privacyMode = $request['privacy_mode'] ?? null;
        if (!is_string($privacyMode) || !in_array($privacyMode, CollectionContext::PRIVACY_MODES, true)) {
            throw new ExpectedOperationRejection('edis_invalid_privacy_mode', 400, ['validation_stage' => 'request_normalization']);
        }
        $options = is_array($request['options'] ?? null) ? $request['options'] : [];
        $scope = is_string($options['export_scope'] ?? null) ? $options['export_scope'] : 'MULTIPLE_DOCUMENTS';
        $dependencyScope = is_string($options['dependency_scope'] ?? null) ? $options['dependency_scope'] : 'REQUIRED_DEPENDENCIES';
        if (!in_array($scope, CollectionContext::EXPORT_SCOPES, true)) {
            throw new ExpectedOperationRejection('edis_invalid_export_scope', 400, ['validation_stage' => 'request_normalization']);
        }
        if (!in_array($dependencyScope, CollectionContext::DEPENDENCY_SCOPES, true)) {
            throw new ExpectedOperationRejection('edis_invalid_dependency_scope', 400, ['validation_stage' => 'request_normalization']);
        }

        $documents = [];
        foreach ((array) ($request['document_ids'] ?? []) as $value) {
            if (is_numeric($value) && (int) $value > 0) {
                $documents[(int) $value] = true;
            }
        }
        $documentIds = array_keys($documents);
        if ($scope === 'SINGLE_DOCUMENT' && count($documentIds) !== 1) {
            throw new ExpectedOperationRejection('edis_single_document_required', 400, ['validation_stage' => 'request_normalization']);
        }
        if ($scope === 'MULTIPLE_DOCUMENTS' && $documentIds === []) {
            throw new ExpectedOperationRejection('edis_document_selection_required', 400, ['validation_stage' => 'request_normalization']);
        }

        $collectorInput = $request['collectors'] ?? null;
        if (!is_array($collectorInput) || $collectorInput === [] || count($collectorInput) > 64) {
            throw new ExpectedOperationRejection('edis_collector_selection_required', 400, ['validation_stage' => 'collector_selection']);
        }
        $collectors = [];
        foreach ($collectorInput as $value) {
            if (!is_string($value) || preg_match('/\A[a-z0-9_:-]{1,128}\z/D', $value) !== 1) {
                throw new ExpectedOperationRejection('edis_collector_identifier_invalid', 400, ['validation_stage' => 'collector_selection']);
            }
            try {
                $definition = $this->registry->definition($value);
            } catch (\OutOfBoundsException) {
                throw new ExpectedOperationRejection('edis_collector_unknown', 400, ['validation_stage' => 'collector_selection']);
            }
            if (!$definition->selectable || !$this->registry->isExecutable($value)) {
                throw new ExpectedOperationRejection('edis_collector_not_executable', 400, ['validation_stage' => 'collector_selection']);
            }
            $collectors[$value] = true;
        }

        $documentStrings = array_map('strval', $documentIds);
        foreach ((array) ($options['element_selection'] ?? []) as $selection) {
            if (!is_array($selection) || !in_array((string) ($selection['document_id'] ?? ''), $documentStrings, true)) {
                throw new ExpectedOperationRejection('edis_element_selection_document_mismatch', 400, ['validation_stage' => 'request_normalization']);
            }
        }

        return [
            'collectors' => array_keys($collectors),
            'dependency_scope' => $dependencyScope,
            'selected_document_count' => $scope === 'METADATA_ONLY' ? 0 : count($documentIds),
        ];
    }

    /** @param list<string> $collectors */
    private function validateExecutionPlan(array $collectors, string $dependencyScope): void
    {
        try {
            $this->registry->executionPlan($collectors, $dependencyScope);
        } catch (\OutOfBoundsException $exception) {
            throw new FailureObservationException(new FailureObservation(
                'EDIS_EXECUTION_PLAN_COMPONENT_UNKNOWN',
                'execution_plan_construction',
                'INTERNAL_EVIDENCE_FAILURE',
                'PRE_JOB',
                'NEW_JOB_REQUIRED',
                ['validation_stage' => 'execution_plan_construction'],
            ), $exception);
        } catch (\InvalidArgumentException $exception) {
            throw new FailureObservationException(new FailureObservation(
                'EDIS_EXECUTION_PLAN_COMPONENT_NOT_EXECUTABLE',
                'execution_plan_construction',
                'INTERNAL_EVIDENCE_FAILURE',
                'PRE_JOB',
                'NEW_JOB_REQUIRED',
                ['validation_stage' => 'execution_plan_construction'],
            ), $exception);
        } catch (\LogicException $exception) {
            throw new FailureObservationException(new FailureObservation(
                'EDIS_EXECUTION_PLAN_DEPENDENCY_CYCLE',
                'execution_plan_construction',
                'PROGRAMMER_INVARIANT_FAILURE',
                'PRE_JOB',
                'NOT_RETRYABLE',
                ['validation_stage' => 'execution_plan_construction'],
            ), $exception);
        }
    }

    /** @return array<string,bool> */
    private function jobIdsForOwner(int $ownerId): array
    {
        $ids = [];
        foreach ($this->jobs->jobsForUser($ownerId) as $job) {
            $id = is_string($job['job_id'] ?? null) ? $job['job_id'] : '';
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        return $ids;
    }

    /** @param array<string,bool> $before @return array<string,mixed>|null */
    private function resolveSingleNewDurableJob(int $ownerId, array $before, int $startedAt, int $selectedDocumentCount): ?array
    {
        $candidates = [];
        foreach ($this->jobs->jobsForUser($ownerId) as $view) {
            $id = is_string($view['job_id'] ?? null) ? $view['job_id'] : '';
            if ($id === '' || isset($before[$id])) {
                continue;
            }
            $job = $this->jobs->get($id);
            if (!is_array($job)
                || (int) ($job['owner_id'] ?? 0) !== $ownerId
                || (int) ($job['created_at'] ?? 0) < $startedAt - 1
                || (int) ($job['selected_document_count'] ?? -1) !== $selectedDocumentCount
            ) {
                continue;
            }
            $candidates[] = $job;
        }
        return count($candidates) === 1 ? $candidates[0] : null;
    }

    private function stageForIntegrityFailure(ExportIntegrityException $exception): ?string
    {
        $contextStage = $exception->diagnosticContext['failure_phase'] ?? null;
        if (is_string($contextStage) && preg_match('/\A[a-z][a-z0-9_]{2,95}\z/D', $contextStage) === 1) {
            return $contextStage;
        }
        $code = $exception->diagnosticCode;
        if (str_starts_with($code, 'EDIS_PREFLIGHT_PROOF_')) {
            return 'preflight_proof_verification';
        }
        if (str_starts_with($code, 'EDIS_PREFLIGHT_SOURCE_')) {
            return 'preflight_source_revalidation';
        }
        if (str_starts_with($code, 'EDIS_INPUT_SNAPSHOT_') || $code === 'EDIS_SOURCE_CHANGED_DURING_SNAPSHOT') {
            return 'input_snapshot_capture';
        }
        if (str_starts_with($code, 'EDIS_INSPECTOR_')) {
            return 'snapshot_selection_validation';
        }
        return null;
    }

    /** @return array<string,scalar|list<scalar>|null> */
    private function boundedIntegrityContext(ExportIntegrityException $exception): array
    {
        $allowed = [
            'validation_stage', 'failed_checks', 'failure_phase', 'component_id', 'source_kind',
            'document_count', 'selected_document_count', 'filesystem_operation', 'path_role',
            'store_check', 'expected_revision', 'actual_revision', 'schedule_state', 'schedule_error',
        ];
        $context = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $exception->diagnosticContext)) {
                $context[$key] = $exception->diagnosticContext[$key];
            }
        }
        return $context;
    }

    private function safeIdentifier(mixed $value, string $fallback): string
    {
        if (!is_string($value) || preg_match('/\A[A-Z0-9_:-]{2,128}\z/D', strtoupper($value)) !== 1) {
            return $fallback;
        }
        return strtoupper($value);
    }
}
