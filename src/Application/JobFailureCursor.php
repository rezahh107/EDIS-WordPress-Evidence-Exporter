<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Application;

use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportIntegrityException;

final class JobFailureCursor
{
    /**
     * @param array<string,mixed> $job
     * @return array{revision:int,signature:string,state:array<string,mixed>}
     */
    public static function capture(array $job): array
    {
        $diagnostics = array_values(array_filter((array) ($job['diagnostics'] ?? []), 'is_array'));
        $latest = $diagnostics === [] ? [] : (array) $diagnostics[array_key_last($diagnostics)];
        $context = is_array($latest['context'] ?? null) ? $latest['context'] : [];
        $state = [
            'status' => self::scalarString($job['status'] ?? null),
            'phase' => self::scalarString($job['phase'] ?? null),
            'current_component' => self::scalarString($job['current_component'] ?? null),
            'last_error_code' => self::scalarString($job['last_error_code'] ?? null),
            'last_error_at' => is_numeric($job['last_error_at'] ?? null) ? (int) $job['last_error_at'] : null,
            'diagnostic_code' => self::scalarString($latest['code'] ?? null),
            'diagnostic_scope' => self::scalarString($latest['scope'] ?? null),
            'diagnostic_context' => self::boundedContext($context),
        ];
        return [
            'revision' => max(0, (int) ($job['revision'] ?? 0)),
            'signature' => 'sha256:' . hash('sha256', CanonicalJson::encode($state)),
            'state' => $state,
        ];
    }

    /** @param array<string,mixed>|null $before @param array<string,mixed> $after */
    public static function changed(?array $before, array $after): bool
    {
        if (!is_array($before)
            || !is_int($before['revision'] ?? null)
            || !is_string($before['signature'] ?? null)) {
            return true;
        }
        $current = self::capture($after);
        return $current['revision'] !== $before['revision']
            || !hash_equals($current['signature'], $before['signature']);
    }

    private static function scalarString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : substr($value, 0, 128);
    }

    /** @param array<string,mixed> $context @return array<string,string|int|bool|null> */
    private static function boundedContext(array $context): array
    {
        $safe = [];
        foreach (array_slice($context, 0, 16, true) as $key => $value) {
            if (!is_string($key) || preg_match('/\A[a-zA-Z0-9_.:-]{1,64}\z/D', $key) !== 1) {
                continue;
            }
            if (is_bool($value) || is_int($value) || $value === null) {
                $safe[$key] = $value;
            } elseif (is_string($value)) {
                $safe[$key] = substr($value, 0, 128);
            }
        }
        ksort($safe, SORT_STRING);
        return $safe;
    }
}

/** Immutable bounded observation projected only by DiagnosticRecordService. */
final readonly class FailureObservation
{
    private const CLASSIFICATIONS = [
        'MATERIAL_PRE_JOB_INCIDENT', 'MATERIAL_JOB_INCIDENT', 'MATERIAL_WORKER_INCIDENT',
        'INTERNAL_EVIDENCE_FAILURE', 'AUTHORITY_UNAVAILABLE', 'UNEXPECTED_MATERIAL_FAILURE',
        'PROGRAMMER_INVARIANT_FAILURE',
    ];
    private const POLICIES = ['PRE_JOB', 'JOB_BOUND', 'WORKER_BOUND', 'UNAVAILABLE_ONLY'];
    private const RETRYABILITY = ['NEW_JOB_REQUIRED', 'RETRY_ALLOWED', 'NOT_RETRYABLE', 'NOT_PROVEN'];
    private const CONTEXT_KEYS = [
        'validation_stage', 'failed_checks', 'failure_phase', 'component_id', 'source_kind',
        'document_count', 'selected_document_count', 'filesystem_operation', 'path_role',
        'store_check', 'expected_revision', 'actual_revision', 'schedule_state', 'schedule_error',
        'exception_class',
    ];

    /** @param array<string,scalar|list<scalar>|null> $safeContext */
    public function __construct(
        public string $reasonCode,
        public ?string $lifecycleStage,
        public string $classification,
        public string $creationPolicy,
        public string $retryability,
        public array $safeContext = [],
    ) {
        if (preg_match('/\AEDIS_[A-Z0-9_]{3,120}\z/D', $reasonCode) !== 1) {
            throw new \InvalidArgumentException('Invalid stable failure reason code.');
        }
        if ($lifecycleStage !== null && preg_match('/\A[a-z][a-z0-9_]{2,95}\z/D', $lifecycleStage) !== 1) {
            throw new \InvalidArgumentException('Invalid lifecycle stage.');
        }
        if (!in_array($classification, self::CLASSIFICATIONS, true)
            || !in_array($creationPolicy, self::POLICIES, true)
            || !in_array($retryability, self::RETRYABILITY, true)) {
            throw new \InvalidArgumentException('Invalid failure observation contract.');
        }
        if (count($safeContext) > 16) {
            throw new \InvalidArgumentException('Failure context exceeds the bounded property limit.');
        }
        foreach ($safeContext as $key => $value) {
            if (!is_string($key) || !in_array($key, self::CONTEXT_KEYS, true)) {
                throw new \InvalidArgumentException('Failure context contains a non-allowlisted key.');
            }
            self::assertBoundedValue($value);
        }
    }

    public function asIntegrityException(?\Throwable $previous = null): ExportIntegrityException
    {
        $context = $this->safeContext;
        if ($this->lifecycleStage !== null) {
            $context['failure_phase'] = $this->lifecycleStage;
        }
        return new ExportIntegrityException(
            $this->reasonCode,
            'EDIS observed a bounded operation failure.',
            $previous,
            $context,
        );
    }

    private static function assertBoundedValue(mixed $value): void
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return;
        }
        if (is_string($value)) {
            if (strlen($value) > 256 || str_contains($value, "\0")) {
                throw new \InvalidArgumentException('Failure context string exceeds the bounded limit.');
            }
            return;
        }
        if (is_array($value) && array_is_list($value) && count($value) <= 16) {
            foreach ($value as $item) {
                if (!is_scalar($item) || (is_string($item) && strlen($item) > 128)) {
                    throw new \InvalidArgumentException('Failure context array contains an unsafe value.');
                }
            }
            return;
        }
        throw new \InvalidArgumentException('Failure context contains an unsupported value.');
    }
}

final class FailureObservationException extends \RuntimeException
{
    public function __construct(
        public readonly FailureObservation $observation,
        ?\Throwable $previous = null,
    ) {
        parent::__construct('EDIS operation failure observation.', 0, $previous);
    }
}

final class ExpectedOperationRejection extends \RuntimeException
{
    /** @param array<string,scalar|null> $publicData */
    public function __construct(
        public readonly string $publicCode,
        public readonly int $httpStatus,
        public readonly array $publicData = [],
    ) {
        if (preg_match('/\Aedis_[a-z0-9_]{3,120}\z/D', $publicCode) !== 1
            || $httpStatus < 400 || $httpStatus > 499) {
            throw new \InvalidArgumentException('Invalid expected-operation rejection contract.');
        }
        parent::__construct('EDIS expected operation rejection.');
    }
}

final class DurableJobFailureException extends \RuntimeException
{
    /** @param array{revision:int,signature:string,state:array<string,mixed>}|null $failureCursor */
    public function __construct(
        public readonly string $jobId,
        public readonly FailureObservation $observation,
        public readonly ?array $failureCursor,
        ?\Throwable $previous = null,
    ) {
        if (preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/D', $jobId) !== 1
            || $observation->creationPolicy !== 'JOB_BOUND') {
            throw new \InvalidArgumentException('Durable Job failure requires exact JOB_BOUND authority.');
        }
        parent::__construct('EDIS failure after durable Job confirmation.', 0, $previous);
    }
}

/** Narrow conformance boundary around the existing authoritative Job creator. */
final class ExportCreateConformance
{
    public function __construct(
        private readonly ExportJobService $service,
        private readonly CollectorRegistry $registry,
    ) {
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public function create(int $ownerId, array $request): array
    {
        $validated = $this->validateExpectedRequest($ownerId, $request);
        $this->validateExecutionPlan($validated['collectors'], $validated['dependency_scope']);
        try {
            return $this->service->create($ownerId, $request);
        } catch (ExpectedOperationRejection $exception) {
            throw $exception;
        } catch (DurableJobFailureException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            if ($exception instanceof ExportIntegrityException) {
                throw new FailureObservationException(new FailureObservation(
                    $exception->diagnosticCode,
                    $this->stageForIntegrityFailure($exception),
                    'INTERNAL_EVIDENCE_FAILURE',
                    'PRE_JOB',
                    'NEW_JOB_REQUIRED',
                    $this->boundedIntegrityContext($exception),
                ), $exception);
            }
            throw new FailureObservationException(new FailureObservation(
                'EDIS_EXPORT_CREATE_STAGE_UNAVAILABLE',
                null,
                'UNEXPECTED_MATERIAL_FAILURE',
                'PRE_JOB',
                'NOT_PROVEN',
                ['exception_class' => $exception::class],
            ), $exception);
        }
    }

    /**
     * @param array<string,mixed> $request
     * @return array{collectors:list<string>,dependency_scope:string}
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
        ];
    }

    /** @param list<string> $collectors */
    private function validateExecutionPlan(array $collectors, string $dependencyScope): void
    {
        try {
            $this->registry->executionPlan($collectors, $dependencyScope);
        } catch (\OutOfBoundsException $exception) {
            throw new FailureObservationException(new FailureObservation(
                'EDIS_EXECUTION_PLAN_COMPONENT_UNKNOWN', 'execution_plan_construction',
                'INTERNAL_EVIDENCE_FAILURE', 'PRE_JOB', 'NEW_JOB_REQUIRED',
                ['validation_stage' => 'execution_plan_construction'],
            ), $exception);
        } catch (\InvalidArgumentException $exception) {
            throw new FailureObservationException(new FailureObservation(
                'EDIS_EXECUTION_PLAN_COMPONENT_NOT_EXECUTABLE', 'execution_plan_construction',
                'INTERNAL_EVIDENCE_FAILURE', 'PRE_JOB', 'NEW_JOB_REQUIRED',
                ['validation_stage' => 'execution_plan_construction'],
            ), $exception);
        } catch (\LogicException $exception) {
            throw new FailureObservationException(new FailureObservation(
                'EDIS_EXECUTION_PLAN_DEPENDENCY_CYCLE', 'execution_plan_construction',
                'PROGRAMMER_INVARIANT_FAILURE', 'PRE_JOB', 'NOT_RETRYABLE',
                ['validation_stage' => 'execution_plan_construction'],
            ), $exception);
        }
    }

    private function stageForIntegrityFailure(ExportIntegrityException $exception): ?string
    {
        $stage = $exception->diagnosticContext['failure_phase'] ?? null;
        if (is_string($stage) && preg_match('/\A[a-z][a-z0-9_]{2,95}\z/D', $stage) === 1) {
            return $stage;
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
}
