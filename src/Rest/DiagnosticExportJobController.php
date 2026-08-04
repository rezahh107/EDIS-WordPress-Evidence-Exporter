<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Rest;

use EDIS\EvidenceExporter\Application\DiagnosticRecordService;
use EDIS\EvidenceExporter\Application\ExportJobService;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;

final class DiagnosticExportJobController
{
    public function __construct(
        private readonly ExportJobService $service,
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
        } catch (\Throwable $exception) {
            return $this->preJobError(
                'edis_invalid_export_preflight',
                'EXPORT_PREFLIGHT',
                '/edis-evidence-exporter/v3/export-preflight',
                $request,
                $exception,
                400,
                [
                    'lifecycle_stage' => 'preflight_execution',
                    'subsystem' => 'ExportJobService',
                    'operation_immediately_attempted' => 'execute the bounded export preflight',
                    'last_successful_state' => 'REQUEST_AUTHORIZED',
                ],
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
                        __('Preflight found blocking issues. Correct the listed blockers and run preflight again.', 'edis-evidence-exporter'),
                        [
                            'status' => 400,
                            'blockers' => array_slice((array) ($preflight['blockers'] ?? []), 0, 32),
                            'warnings' => array_slice((array) ($preflight['warnings'] ?? []), 0, 32),
                            'diagnostic_available' => false,
                            'diagnostic_id' => null,
                        ],
                    );
                }
            }
            return new \WP_REST_Response($this->service->create(get_current_user_id(), $requestData), 202);
        } catch (\Throwable $exception) {
            return $this->preJobError(
                'edis_invalid_export_request',
                'EXPORT_CREATE',
                '/edis-evidence-exporter/v3/export-jobs',
                $request,
                $exception,
                400,
                [
                    'lifecycle_stage' => null,
                    'subsystem' => 'ExportJobService',
                    'operation_immediately_attempted' => 'validate, snapshot, and persist a new export Job',
                    'last_successful_state' => 'REQUEST_AUTHORIZED',
                ],
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
        $jobId = (string) $request->get_param('job_id');
        try {
            $revision = $request->get_param('revision');
            return new \WP_REST_Response($this->service->advance(
                $jobId,
                get_current_user_id(),
                is_numeric($revision) ? (int) $revision : null,
            ), 200);
        } catch (\Throwable $exception) {
            return $this->jobError('edis_export_advance_failed', 'EXPORT_ADVANCE', '/edis-evidence-exporter/v3/export-jobs/{job_id}/advance', $jobId, $exception, 409);
        }
    }

    public function resume(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->action($request, 'resume', 'EXPORT_RESUME');
    }

    public function retry(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->action($request, 'retry', 'EXPORT_RETRY');
    }

    public function cancel(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->action($request, 'cancel', 'EXPORT_CANCEL');
    }

    private function action(\WP_REST_Request $request, string $method, string $operation): \WP_REST_Response|\WP_Error
    {
        $jobId = (string) $request->get_param('job_id');
        try {
            return new \WP_REST_Response($this->service->{$method}($jobId, get_current_user_id()), 200);
        } catch (\Throwable $exception) {
            return $this->jobError('edis_export_action_failed', $operation, '/edis-evidence-exporter/v3/export-jobs/{job_id}/' . $method, $jobId, $exception, 409);
        }
    }

    /** @param array<string,mixed> $boundary */
    private function preJobError(
        string $code,
        string $operation,
        string $route,
        \WP_REST_Request $request,
        \Throwable $exception,
        int $status,
        array $boundary,
    ): \WP_Error {
        $diagnostic = $this->diagnostics->capturePreJobFailure(
            get_current_user_id(),
            $operation,
            $route,
            $this->request($request),
            $exception,
            $boundary,
            $code,
        );
        return $this->error($code, $status, $diagnostic);
    }

    private function jobError(
        string $code,
        string $operation,
        string $route,
        string $jobId,
        \Throwable $exception,
        int $status,
    ): \WP_Error {
        $diagnostic = $this->diagnostics->captureJobFailure(
            get_current_user_id(),
            $jobId,
            $operation,
            $route,
            $exception,
            $code,
        );
        return $this->error($code, $status, $diagnostic);
    }

    /** @param array{diagnostic_available:bool,diagnostic_id:?string,diagnostic_persistence_code:?string} $diagnostic */
    private function error(string $code, int $status, array $diagnostic): \WP_Error
    {
        $available = $diagnostic['diagnostic_available'] === true && is_string($diagnostic['diagnostic_id']);
        $diagnosticId = $available ? $diagnostic['diagnostic_id'] : null;
        $url = $available && function_exists('admin_url')
            ? add_query_arg('diagnostic_id', $diagnosticId, admin_url('admin.php?page=edis-evidence-diagnostics'))
            : null;
        return new \WP_Error(
            $code,
            $available
                ? __('The export request could not be completed. Open EDIS Diagnostics with the returned diagnostic ID.', 'edis-evidence-exporter')
                : __('The export request failed. EDIS could not persist a diagnostic artifact.', 'edis-evidence-exporter'),
            [
                'status' => $status,
                'diagnostic_available' => $available,
                'diagnostic_id' => $diagnosticId,
                'diagnostics_page' => 'edis-evidence-diagnostics',
                'diagnostics_url' => $url,
                'diagnostic_persistence_code' => $available ? null : 'EDIS_DIAGNOSTIC_PERSISTENCE_FAILED',
            ],
        );
    }

    /** @return array<string,mixed>|\WP_Error */
    private function owned(string $jobId): array|\WP_Error
    {
        if (!$this->isUuid($jobId)) {
            return new \WP_Error('edis_export_job_not_found', __('Export job not found.', 'edis-evidence-exporter'), ['status' => 404]);
        }
        $job = $this->jobs->get($jobId);
        if (!is_array($job) || (int) ($job['owner_id'] ?? 0) !== get_current_user_id()) {
            return new \WP_Error('edis_export_job_not_found', __('Export job not found.', 'edis-evidence-exporter'), ['status' => 404]);
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

    private function isUuid(string $value): bool
    {
        return preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/D', $value) === 1;
    }
}
