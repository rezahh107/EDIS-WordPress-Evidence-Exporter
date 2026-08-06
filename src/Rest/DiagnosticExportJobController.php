<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Rest;

use EDIS\EvidenceExporter\Application\DiagnosticRecordService;
use EDIS\EvidenceExporter\Application\DurableJobFailureException;
use EDIS\EvidenceExporter\Application\ExpectedOperationRejection;
use EDIS\EvidenceExporter\Application\ExportCreateConformance;
use EDIS\EvidenceExporter\Application\ExportJobService;
use EDIS\EvidenceExporter\Application\FailureObservation;
use EDIS\EvidenceExporter\Application\FailureObservationException;
use EDIS\EvidenceExporter\Application\JobFailureCursor;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportIntegrityException;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;

final class DiagnosticExportJobController
{
    public function __construct(
        private readonly ExportJobService $service,
        private readonly ExportCreateConformance $createBoundary,
        private readonly JobStore $jobs,
        private readonly DiagnosticRecordService $diagnostics,
        private readonly string $capability = 'edis_export_evidence',
    ) {
    }

    public function registerRoutes(): void
    {
        $namespace = 'edis-evidence-exporter/v3';
        register_rest_route($namespace, '/export-preflight', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'preflight'],
            'permission_callback' => [$this, 'permission'],
            'args' => $this->createArgs(),
        ]);
        register_rest_route($namespace, '/export-jobs', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'create'],
            'permission_callback' => [$this, 'permission'],
            'args' => $this->createArgs(),
        ]);
        register_rest_route($namespace, '/export-jobs/(?P<job_id>[a-f0-9-]{36})', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'status'],
            'permission_callback' => [$this, 'permission'],
            'args' => $this->jobArgs(),
        ]);
        register_rest_route($namespace, '/export-jobs/(?P<job_id>[a-f0-9-]{36})/advance', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'advance'],
            'permission_callback' => [$this, 'permission'],
            'args' => $this->advanceArgs(),
        ]);
        foreach (['resume', 'retry', 'cancel'] as $action) {
            register_rest_route($namespace, '/export-jobs/(?P<job_id>[a-f0-9-]{36})/' . $action, [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, $action],
                'permission_callback' => [$this, 'permission'],
                'args' => $this->jobArgs(),
            ]);
        }
    }

    public function permission(\WP_REST_Request $request): bool|\WP_Error
    {
        if (!current_user_can($this->capability)) {
            return new \WP_Error('edis_export_forbidden', __('You do not have permission to export evidence.', 'edis-evidence-exporter'), ['status' => 403]);
        }
        $jobId = (string) $request->get_param('job_id');
        if ($jobId !== '') {
            $job = $this->owned($jobId);
            return $job instanceof \WP_Error ? $job : true;
        }
        foreach ((array) $request->get_param('document_ids') as $value) {
            $documentId = (int) $value;
            if ($documentId <= 0 || !current_user_can('edit_post', $documentId)) {
                return new \WP_Error('edis_export_document_forbidden', __('You do not have permission to export one of the requested documents.', 'edis-evidence-exporter'), ['status' => 403]);
            }
        }
        return true;
    }

    public function preflight(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            return new \WP_REST_Response($this->service->preflight(get_current_user_id(), $this->request($request)), 200);
        } catch (\InvalidArgumentException) {
            return $this->expectedError(
                'edis_invalid_export_preflight',
                400,
                __('The export Preflight request is invalid. Correct the request and run Preflight again.', 'edis-evidence-exporter'),
                ['reason_code' => 'EDIS_PREFLIGHT_REQUEST_REJECTED', 'lifecycle_stage' => 'preflight_execution'],
            );
        } catch (ExportIntegrityException $exception) {
            return $this->preJobObservation(
                'edis_invalid_export_preflight',
                'EXPORT_PREFLIGHT',
                '/edis-evidence-exporter/v3/export-preflight',
                $request,
                new FailureObservation(
                    $exception->diagnosticCode,
                    $this->exceptionStage($exception, 'preflight_execution'),
                    'INTERNAL_EVIDENCE_FAILURE',
                    'PRE_JOB',
                    'NEW_JOB_REQUIRED',
                    $this->boundedContext($exception),
                ),
                $exception,
                400,
            );
        } catch (\Throwable $exception) {
            return $this->preJobObservation(
                'edis_invalid_export_preflight',
                'EXPORT_PREFLIGHT',
                '/edis-evidence-exporter/v3/export-preflight',
                $request,
                new FailureObservation(
                    'EDIS_PREFLIGHT_EXECUTION_FAILED',
                    'preflight_execution',
                    'UNEXPECTED_MATERIAL_FAILURE',
                    'PRE_JOB',
                    'NOT_PROVEN',
                    ['exception_class' => $exception::class],
                ),
                $exception,
                400,
            );
        }
    }

    public function create(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $requestData = $this->request($request);
        try {
            $token = is_string($requestData['preflight_token'] ?? null) ? trim($requestData['preflight_token']) : '';
            if ($token === '') {
                $preflight = $this->service->preflight(get_current_user_id(), $requestData);
                if (($preflight['state'] ?? 'FAIL') !== 'PASS') {
                    return new \WP_Error(
                        'edis_export_preflight_blocked',
                        __('Preflight found blocking issues. Correct the listed blockers and run Preflight again.', 'edis-evidence-exporter'),
                        [
                            'status' => 400,
                            'diagnostic_available' => false,
                            'diagnostic_id' => null,
                            'diagnostic_persistence_code' => null,
                            'blockers' => array_slice((array) ($preflight['blockers'] ?? []), 0, 32),
                            'warnings' => array_slice((array) ($preflight['warnings'] ?? []), 0, 32),
                        ],
                    );
                }
            }
            return new \WP_REST_Response($this->createBoundary->create(get_current_user_id(), $requestData), 202);
        } catch (ExpectedOperationRejection $rejection) {
            return $this->expectedError(
                $rejection->publicCode,
                $rejection->httpStatus,
                __('The export request was rejected. Correct the bounded request condition and run Preflight again.', 'edis-evidence-exporter'),
                $rejection->publicData,
            );
        } catch (DurableJobFailureException $exception) {
            return $this->jobObservation(
                'edis_export_post_create_failed',
                'EXPORT_CREATE',
                '/edis-evidence-exporter/v3/export-jobs',
                $exception->jobId,
                $exception->observation,
                $exception,
                500,
                $exception->failureCursor,
            );
        } catch (FailureObservationException $exception) {
            return $this->preJobObservation(
                'edis_invalid_export_request',
                'EXPORT_CREATE',
                '/edis-evidence-exporter/v3/export-jobs',
                $request,
                $exception->observation,
                $exception,
                400,
            );
        } catch (\InvalidArgumentException) {
            return $this->expectedError(
                'edis_invalid_export_request',
                400,
                __('The export request is invalid. Correct the request and run Preflight again.', 'edis-evidence-exporter'),
                ['reason_code' => 'EDIS_EXPORT_REQUEST_REJECTED', 'lifecycle_stage' => 'request_normalization'],
            );
        } catch (\Throwable $exception) {
            return $this->preJobObservation(
                'edis_invalid_export_request',
                'EXPORT_CREATE',
                '/edis-evidence-exporter/v3/export-jobs',
                $request,
                new FailureObservation(
                    'EDIS_EXPORT_CREATE_STAGE_UNAVAILABLE',
                    null,
                    'UNEXPECTED_MATERIAL_FAILURE',
                    'PRE_JOB',
                    'NOT_PROVEN',
                    ['exception_class' => $exception::class],
                ),
                $exception,
                500,
            );
        }
    }

    public function status(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $job = $this->owned((string) $request->get_param('job_id'));
        return $job instanceof \WP_Error ? $job : new \WP_REST_Response($this->jobs->publicView($job), 200);
    }

    public function advance(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->runAction($request, 'advance', 'EXPORT_ADVANCE', true);
    }

    public function resume(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->runAction($request, 'resume', 'EXPORT_RESUME', false);
    }

    public function retry(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->runAction($request, 'retry', 'EXPORT_RETRY', false);
    }

    public function cancel(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->runAction($request, 'cancel', 'EXPORT_CANCEL', false);
    }

    private function runAction(\WP_REST_Request $request, string $method, string $operation, bool $withRevision): \WP_REST_Response|\WP_Error
    {
        $jobId = (string) $request->get_param('job_id');
        $owned = $this->owned($jobId);
        if ($owned instanceof \WP_Error) {
            return $owned;
        }
        $expected = $this->expectedActionResult($owned, $method, $withRevision ? $request->get_param('revision') : null);
        if ($expected instanceof \WP_REST_Response || $expected instanceof \WP_Error) {
            return $expected;
        }

        $failureCursor = JobFailureCursor::capture($owned);
        try {
            $result = $method === 'advance'
                ? $this->service->advance(
                    $jobId,
                    get_current_user_id(),
                    is_numeric($request->get_param('revision')) ? (int) $request->get_param('revision') : null,
                )
                : $this->service->{$method}($jobId, get_current_user_id());
            return new \WP_REST_Response($result, 200);
        } catch (\Throwable $exception) {
            $after = $this->jobs->get($jobId);
            if (is_array($after)
                && (int) ($after['owner_id'] ?? 0) === get_current_user_id()
                && (string) ($after['status'] ?? '') !== 'failed'
                && !JobFailureCursor::changed($failureCursor, $after)
            ) {
                return $this->expectedError(
                    'edis_export_operation_conflict',
                    409,
                    __('The export Job changed or is already being processed. Refresh the Job state before retrying.', 'edis-evidence-exporter'),
                    [
                        'reason_code' => 'EDIS_EXPECTED_OPERATION_CONFLICT',
                        'actual_revision' => (int) ($after['revision'] ?? 0),
                        'schedule_state' => is_string($after['schedule_state'] ?? null) ? $after['schedule_state'] : null,
                    ],
                );
            }
            $stage = match ($method) {
                'advance' => 'job_advance',
                'resume' => 'job_resume',
                'retry' => 'job_retry',
                'cancel' => 'job_cancel',
                default => 'job_action',
            };
            $observation = new FailureObservation(
                $exception instanceof ExportIntegrityException ? $exception->diagnosticCode : 'EDIS_' . $operation . '_FAILED',
                $stage,
                'MATERIAL_JOB_INCIDENT',
                'JOB_BOUND',
                $exception instanceof ExportIntegrityException ? 'NOT_RETRYABLE' : 'NOT_PROVEN',
                $exception instanceof ExportIntegrityException ? $this->boundedContext($exception) : ['exception_class' => $exception::class],
            );
            return $this->jobObservation(
                'edis_export_action_failed',
                $operation,
                '/edis-evidence-exporter/v3/export-jobs/{job_id}/' . $method,
                $jobId,
                $observation,
                $exception,
                409,
                $failureCursor,
            );
        }
    }

    /** @param array<string,mixed> $job */
    private function expectedActionResult(array $job, string $method, mixed $requestedRevision): \WP_REST_Response|\WP_Error|null
    {
        $now = time();
        $status = (string) ($job['status'] ?? '');
        $leaseActive = (int) ($job['lease_expires_at'] ?? 0) > $now
            && is_string($job['lease_owner'] ?? null)
            && $job['lease_owner'] !== '';
        if ($leaseActive) {
            return $this->expectedError(
                'edis_export_job_busy',
                409,
                __('The export Job already has an active Worker lease. Refresh its state after the current Worker finishes.', 'edis-evidence-exporter'),
                ['reason_code' => 'EDIS_ACTIVE_WORKER_LEASE', 'schedule_state' => is_string($job['schedule_state'] ?? null) ? $job['schedule_state'] : null],
            );
        }
        if ($method === 'advance' && is_numeric($requestedRevision) && (int) $requestedRevision !== (int) ($job['revision'] ?? 0)) {
            return $this->expectedError(
                'edis_export_revision_conflict',
                409,
                __('The export Job revision is stale. Refresh the Job state before advancing it.', 'edis-evidence-exporter'),
                [
                    'reason_code' => 'EDIS_STALE_JOB_REVISION',
                    'expected_revision' => (int) $requestedRevision,
                    'actual_revision' => (int) ($job['revision'] ?? 0),
                ],
            );
        }
        if (in_array($status, ['completed', 'cancelled'], true)) {
            if (in_array($method, ['advance', 'cancel'], true)) {
                return new \WP_REST_Response($this->jobs->publicView($job), 200);
            }
            return $this->expectedError(
                'edis_export_action_not_allowed',
                409,
                __('This terminal export Job does not allow the requested action.', 'edis-evidence-exporter'),
                ['reason_code' => 'EDIS_TERMINAL_JOB_ACTION_REJECTED'],
            );
        }
        if (in_array($method, ['resume', 'retry'], true) && !in_array($status, ['failed', 'queued'], true)) {
            return $this->expectedError(
                'edis_export_action_not_allowed',
                409,
                __('The requested recovery action is not valid for the current Job state.', 'edis-evidence-exporter'),
                ['reason_code' => 'EDIS_JOB_ACTION_STATE_REJECTED'],
            );
        }
        return null;
    }

    private function preJobObservation(
        string $publicCode,
        string $operation,
        string $route,
        \WP_REST_Request $request,
        FailureObservation $observation,
        \Throwable $exception,
        int $status,
    ): \WP_Error {
        $diagnostic = $this->diagnostics->capturePreJobFailure(
            get_current_user_id(),
            $operation,
            $route,
            $this->request($request),
            $observation->asIntegrityException($exception),
            [
                'lifecycle_stage' => $observation->lifecycleStage,
                'subsystem' => 'ExportJobService',
                'operation_immediately_attempted' => 'complete the bounded ' . strtolower($operation) . ' operation',
                'last_successful_state' => 'REQUEST_AUTHORIZED',
            ],
            $publicCode,
        );
        return $this->diagnosticError($publicCode, $status, $diagnostic);
    }

    /** @param array{revision:int,signature:string,state:array<string,mixed>}|null $failureCursor */
    private function jobObservation(
        string $publicCode,
        string $operation,
        string $route,
        string $jobId,
        FailureObservation $observation,
        \Throwable $exception,
        int $status,
        ?array $failureCursor,
    ): \WP_Error {
        $diagnostic = $this->diagnostics->captureJobFailure(
            get_current_user_id(),
            $jobId,
            $operation,
            $route,
            $observation->asIntegrityException($exception),
            $publicCode,
            'JOB_FAILURE',
            $failureCursor,
        );
        return $this->diagnosticError($publicCode, $status, $diagnostic);
    }

    /** @param array<string,scalar|null> $data */
    private function expectedError(string $code, int $status, string $message, array $data = []): \WP_Error
    {
        return new \WP_Error($code, $message, [
            'status' => $status,
            'diagnostic_available' => false,
            'diagnostic_id' => null,
            'diagnostics_url' => null,
            'diagnostic_persistence_code' => null,
        ] + $data);
    }

    /** @param array{diagnostic_available:bool,diagnostic_id:?string,diagnostic_persistence_code:?string} $diagnostic */
    private function diagnosticError(string $code, int $status, array $diagnostic): \WP_Error
    {
        $available = $diagnostic['diagnostic_available'] === true
            && is_string($diagnostic['diagnostic_id'])
            && preg_match('/\Aedis-diag-[a-f0-9]{32}\z/D', $diagnostic['diagnostic_id']) === 1;
        $diagnosticId = $available ? $diagnostic['diagnostic_id'] : null;
        $persistenceCode = !$available && is_string($diagnostic['diagnostic_persistence_code'] ?? null)
            ? $diagnostic['diagnostic_persistence_code']
            : null;
        $url = $available && function_exists('admin_url')
            ? add_query_arg('diagnostic_id', $diagnosticId, admin_url('admin.php?page=edis-evidence-diagnostics'))
            : null;
        $message = $available
            ? __('The export operation failed. Open EDIS Diagnostics with the returned diagnostic ID.', 'edis-evidence-exporter')
            : ($persistenceCode === 'EDIS_DIAGNOSTIC_CAPACITY_REACHED'
                ? __('The export operation failed and live Diagnostic capacity is full. Existing records were preserved.', 'edis-evidence-exporter')
                : __('The export operation failed. EDIS could not persist a Diagnostic artifact.', 'edis-evidence-exporter'));
        return new \WP_Error($code, $message, [
            'status' => $status,
            'diagnostic_available' => $available,
            'diagnostic_id' => $diagnosticId,
            'diagnostics_page' => 'edis-evidence-diagnostics',
            'diagnostics_url' => $url,
            'diagnostic_persistence_code' => $available ? null : ($persistenceCode ?? 'EDIS_DIAGNOSTIC_PERSISTENCE_FAILED'),
        ]);
    }

    /** @return array<string,mixed>|\WP_Error */
    private function owned(string $jobId): array|\WP_Error
    {
        if (!$this->isUuid($jobId)) {
            return new \WP_Error('edis_export_job_not_found', __('Export Job not found.', 'edis-evidence-exporter'), ['status' => 404]);
        }
        $job = $this->jobs->get($jobId);
        if (!is_array($job) || (int) ($job['owner_id'] ?? 0) !== get_current_user_id()) {
            return new \WP_Error('edis_export_job_not_found', __('Export Job not found.', 'edis-evidence-exporter'), ['status' => 404]);
        }
        foreach ((array) ($job['document_ids'] ?? $job['config']['document_ids'] ?? []) as $value) {
            $documentId = (int) $value;
            if ($documentId <= 0 || !current_user_can('edit_post', $documentId)) {
                return new \WP_Error('edis_export_document_forbidden', __('You no longer have permission to access one of the exported documents.', 'edis-evidence-exporter'), ['status' => 403]);
            }
        }
        return $job;
    }

    /** @return array<string,mixed> */
    private function request(\WP_REST_Request $request): array
    {
        return [
            'privacy_mode' => $request->get_param('privacy_mode'),
            'collectors' => $request->get_param('collectors'),
            'document_ids' => $request->get_param('document_ids'),
            'options' => $request->get_param('options'),
            'preflight_token' => $request->get_param('preflight_token'),
        ];
    }

    /** @return array<string,mixed> */
    private function createArgs(): array
    {
        return [
            'privacy_mode' => ['type' => 'string', 'enum' => ['Strict', 'Standard', 'Diagnostic'], 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'collectors' => [
                'type' => 'array', 'required' => true, 'minItems' => 1, 'maxItems' => 64,
                'items' => ['type' => 'string', 'pattern' => '^[a-z0-9_:-]{1,128}$'],
                'validate_callback' => [$this, 'validateCollectors'], 'sanitize_callback' => [$this, 'sanitizeStringList'],
            ],
            'document_ids' => [
                'type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'default' => [], 'maxItems' => 250,
                'validate_callback' => [$this, 'validatePositiveIntegerList'], 'sanitize_callback' => [$this, 'sanitizeIntegerList'],
            ],
            'options' => ['type' => 'object', 'default' => []],
            'preflight_token' => [
                'type' => 'string', 'required' => false, 'maxLength' => 65536, 'pattern' => '^[A-Za-z0-9._-]+$',
                'validate_callback' => static fn (mixed $value): bool => $value === null || (is_string($value) && strlen($value) <= 65536 && preg_match('/\A[A-Za-z0-9._-]+\z/D', $value) === 1),
                'sanitize_callback' => static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function jobArgs(): array
    {
        return [
            'job_id' => [
                'type' => 'string', 'required' => true, 'pattern' => '^[a-f0-9-]{36}$',
                'validate_callback' => fn (mixed $value): bool => is_string($value) && $this->isUuid($value),
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function advanceArgs(): array
    {
        return array_merge($this->jobArgs(), [
            'revision' => [
                'type' => 'integer', 'required' => false, 'minimum' => 0,
                'validate_callback' => static fn (mixed $value): bool => $value === null || (is_numeric($value) && (int) $value >= 0),
                'sanitize_callback' => 'absint',
            ],
        ]);
    }

    public function validateCollectors(mixed $value): bool
    {
        if (!is_array($value) || $value === [] || count($value) > 64) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_string($item) || preg_match('/\A[a-z0-9_:-]{1,128}\z/D', $item) !== 1) {
                return false;
            }
        }
        return true;
    }

    /** @return list<string> */
    public function sanitizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $clean = sanitize_key((string) $item);
            if ($clean !== '') {
                $out[] = $clean;
            }
        }
        return array_values(array_unique($out));
    }

    public function validatePositiveIntegerList(mixed $value): bool
    {
        if (!is_array($value) || count($value) > 250) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_numeric($item) || (int) $item <= 0) {
                return false;
            }
        }
        return true;
    }

    /** @return list<int> */
    public function sanitizeIntegerList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $id = (int) $item;
            if ($id > 0) {
                $out[] = $id;
            }
        }
        return array_values(array_unique($out));
    }

    /** @return array<string,scalar|list<scalar>|null> */
    private function boundedContext(ExportIntegrityException $exception): array
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

    private function exceptionStage(ExportIntegrityException $exception, ?string $fallback): ?string
    {
        $stage = $exception->diagnosticContext['failure_phase'] ?? null;
        return is_string($stage) && preg_match('/\A[a-z][a-z0-9_]{2,95}\z/D', $stage) === 1 ? $stage : $fallback;
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/D', $value) === 1;
    }
}
