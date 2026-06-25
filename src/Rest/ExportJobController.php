<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Rest;

use EDIS\EvidenceExporter\Application\ExportJobService;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportFileStore;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;

final class ExportJobController
{
    public function __construct(
        private readonly ExportJobService $service,
        private readonly JobStore $jobs,
        private readonly ExportFileStore $files,
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
            if (!$this->isUuid($jobId)) {
                return new \WP_Error('edis_export_job_not_found', __('Export job not found.', 'edis-evidence-exporter'), ['status' => 404]);
            }
            $job = $this->jobs->get($jobId);
            if (!is_array($job) || (int) ($job['owner_id'] ?? 0) !== get_current_user_id()) {
                return new \WP_Error('edis_export_job_not_found', __('Export job not found.', 'edis-evidence-exporter'), ['status' => 404]);
            }
            if (!$this->jobDocumentsEditable($job)) {
                return new \WP_Error('edis_export_document_forbidden', __('You no longer have permission to access one of the exported documents.', 'edis-evidence-exporter'), ['status' => 403]);
            }
            return true;
        }

        $documentIds = $request->get_param('document_ids');
        if (is_array($documentIds)) {
            foreach ($documentIds as $value) {
                $documentId = (int) $value;
                if ($documentId <= 0 || !current_user_can('edit_post', $documentId)) {
                    return new \WP_Error('edis_export_document_forbidden', __('You do not have permission to export one of the requested documents.', 'edis-evidence-exporter'), ['status' => 403]);
                }
            }
        }
        return true;
    }

    public function preflight(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            return new \WP_REST_Response($this->service->preflight(get_current_user_id(), $this->request($request)), 200);
        } catch (\Throwable $exception) {
            return $this->error('edis_invalid_export_preflight', $exception, 400);
        }
    }

    public function create(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            return new \WP_REST_Response($this->service->create(get_current_user_id(), $this->request($request)), 202);
        } catch (\Throwable $exception) {
            return $this->error('edis_invalid_export_request', $exception, 400);
        }
    }

    public function status(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $job = $this->owned((string) $request->get_param('job_id'));
        return $job instanceof \WP_Error ? $job : new \WP_REST_Response($this->jobs->publicView($job), 200);
    }

    public function advance(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $revision = $request->get_param('revision');
            return new \WP_REST_Response($this->service->advance(
                (string) $request->get_param('job_id'),
                get_current_user_id(),
                is_numeric($revision) ? (int) $revision : null,
            ), 200);
        } catch (\Throwable $exception) {
            return $this->error('edis_export_advance_failed', $exception, 409);
        }
    }

    public function resume(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->action($request, 'resume');
    }

    public function retry(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->action($request, 'retry');
    }

    public function cancel(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->action($request, 'cancel');
    }

    public function download(): void
    {
        if (!current_user_can($this->capability)) {
            wp_die(esc_html__('You do not have permission to download this export.', 'edis-evidence-exporter'), '', ['response' => 403]);
        }
        $jobId = isset($_GET['job_id']) ? sanitize_text_field(wp_unslash($_GET['job_id'])) : '';
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        check_admin_referer('edis_download_export');
        $job = $this->jobs->get($jobId);
        if (!$this->isUuid($jobId) || !is_array($job) || (int) ($job['owner_id'] ?? 0) !== get_current_user_id() || ($job['status'] ?? '') !== 'completed') {
            wp_die(esc_html__('The export is unavailable.', 'edis-evidence-exporter'), '', ['response' => 404]);
        }
        $this->assertJobDocumentsEditableOrDie($job);
        $path = $this->files->authorize($jobId, $token);
        if ($path === null) {
            wp_die(esc_html__('The download token is invalid or expired.', 'edis-evidence-exporter'), '', ['response' => 403]);
        }
        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="edis-source-evidence-' . $jobId . '.zip"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Authorized immutable bundle bytes must be streamed unchanged.
        readfile($path);
        exit;
    }

    public function downloadBridgeContext(): void
    {
        if (!current_user_can($this->capability)) {
            wp_die(esc_html__('You do not have permission to download this export.', 'edis-evidence-exporter'), '', ['response' => 403]);
        }
        $jobId = isset($_GET['job_id']) ? sanitize_text_field(wp_unslash($_GET['job_id'])) : '';
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        check_admin_referer('edis_download_bridge_context');
        $job = $this->jobs->get($jobId);
        if (!$this->isUuid($jobId) || !is_array($job) || (int) ($job['owner_id'] ?? 0) !== get_current_user_id() || ($job['status'] ?? '') !== 'completed') {
            wp_die(esc_html__('The export is unavailable.', 'edis-evidence-exporter'), '', ['response' => 404]);
        }
        $this->assertJobDocumentsEditableOrDie($job);
        $path = $this->files->authorize($jobId, $token);
        if ($path === null) {
            wp_die(esc_html__('The download token is invalid or expired.', 'edis-evidence-exporter'), '', ['response' => 403]);
        }
        try {
            $bytes = $this->files->readStoredEntry($path, 'bridge/source-context.json');
        } catch (\Throwable) {
            wp_die(esc_html__('The source bundle failed deterministic ZIP integrity validation.', 'edis-evidence-exporter'), '', ['response' => 500]);
        }
        if (!is_string($bytes)) {
            wp_die(esc_html__('This export does not contain a Browser Bridge Context.', 'edis-evidence-exporter'), '', ['response' => 404]);
        }
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="edis-browser-bridge-context-' . $jobId . '.json"');
        header('Content-Length: ' . (string) strlen($bytes));
        header('X-Content-Type-Options: nosniff');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Exact validated JSON artifact bytes must not be mutated.
        echo $bytes;
        exit;
    }

    /** @return array<string,mixed> */
    private function createArgs(): array
    {
        return [
            'privacy_mode' => [
                'type' => 'string',
                'enum' => ['Strict', 'Standard', 'Diagnostic'],
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'collectors' => [
                'type' => 'array',
                'required' => true,
                'minItems' => 1,
                'maxItems' => 64,
                'items' => ['type' => 'string', 'pattern' => '^[a-z0-9_:-]{1,128}$'],
                'validate_callback' => [$this, 'validateCollectors'],
                'sanitize_callback' => [$this, 'sanitizeStringList'],
            ],
            'document_ids' => [
                'type' => 'array',
                'items' => ['type' => 'integer', 'minimum' => 1],
                'default' => [],
                'maxItems' => 250,
                'validate_callback' => [$this, 'validatePositiveIntegerList'],
                'sanitize_callback' => [$this, 'sanitizeIntegerList'],
            ],
            'options' => ['type' => 'object', 'default' => []],
            'preflight_token' => [
                'type' => 'string',
                'required' => false,
                'maxLength' => 65536,
                'pattern' => '^[A-Za-z0-9._-]+$',
                'validate_callback' => static fn(mixed $value): bool => $value === null || (is_string($value) && strlen($value) <= 65536 && preg_match('/\A[A-Za-z0-9._-]+\z/D', $value) === 1),
                'sanitize_callback' => static fn(mixed $value): string => is_string($value) ? trim($value) : '',
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function jobArgs(): array
    {
        return [
            'job_id' => [
                'type' => 'string',
                'required' => true,
                'pattern' => '^[a-f0-9-]{36}$',
                'validate_callback' => fn(mixed $value): bool => is_string($value) && $this->isUuid($value),
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function advanceArgs(): array
    {
        return array_merge($this->jobArgs(), [
            'revision' => [
                'type' => 'integer',
                'required' => false,
                'minimum' => 0,
                'validate_callback' => static fn(mixed $value): bool => $value === null || (is_numeric($value) && (int) $value >= 0),
                'sanitize_callback' => 'absint',
            ],
        ]);
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
        if (!$this->jobDocumentsEditable($job)) {
            return new \WP_Error('edis_export_document_forbidden', __('You no longer have permission to access one of the exported documents.', 'edis-evidence-exporter'), ['status' => 403]);
        }
        return $job;
    }

    /** @param array<string,mixed> $job */
    private function jobDocumentsEditable(array $job): bool
    {
        foreach ((array) ($job['document_ids'] ?? $job['config']['document_ids'] ?? []) as $value) {
            $documentId = (int) $value;
            if ($documentId <= 0 || !current_user_can('edit_post', $documentId)) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,mixed> $job */
    private function assertJobDocumentsEditableOrDie(array $job): void
    {
        if (!$this->jobDocumentsEditable($job)) {
            wp_die(esc_html__('You no longer have permission to access one of the exported documents.', 'edis-evidence-exporter'), '', ['response' => 403]);
        }
    }

    private function action(\WP_REST_Request $request, string $method): \WP_REST_Response|\WP_Error
    {
        try {
            return new \WP_REST_Response($this->service->{$method}((string) $request->get_param('job_id'), get_current_user_id()), 200);
        } catch (\Throwable $exception) {
            return $this->error('edis_export_action_failed', $exception, 409);
        }
    }

    private function error(string $code, \Throwable $exception, int $status): \WP_Error
    {
        return new \WP_Error($code, __('The export request could not be completed. Review EDIS diagnostics for details.', 'edis-evidence-exporter'), [
            'status' => $status,
            'diagnostic_id' => $this->diagnosticId($code, $exception),
        ]);
    }

    private function diagnosticId(string $code, \Throwable $exception): string
    {
        return 'edis-' . substr(hash('sha256', $code . '|' . $exception::class . '|' . $exception->getMessage()), 0, 16);
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/D', $value) === 1;
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
}
