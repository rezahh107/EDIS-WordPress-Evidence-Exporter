<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Rest;

use EDIS\EvidenceExporter\Application\DiagnosticsService;

final class DiagnosticsController
{
    public function __construct(private readonly DiagnosticsService $diagnostics) {}

    public function registerRoutes(): void
    {
        register_rest_route('edis-evidence-exporter/v3', '/diagnostics', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'report'],
            'permission_callback' => [$this, 'permission'],
            'args' => [],
        ]);
        register_rest_route('edis-evidence-exporter/v3', '/diagnostics/worker-test', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'workerTest'],
            'permission_callback' => [$this, 'permission'],
            'args' => [],
        ]);
    }

    public function permission(): bool|\WP_Error
    {
        return current_user_can('edis_export_evidence')
            ? true
            : new \WP_Error('edis_diagnostics_forbidden', __('You do not have permission to view EDIS diagnostics.', 'edis-evidence-exporter'), ['status' => 403]);
    }

    public function report(): \WP_REST_Response
    {
        return new \WP_REST_Response($this->diagnostics->report(), 200);
    }

    public function workerTest(): \WP_REST_Response|\WP_Error
    {
        try {
            return new \WP_REST_Response($this->diagnostics->workerTest(get_current_user_id()), 200);
        } catch (\Throwable $exception) {
            return new \WP_Error('edis_worker_test_failed', __('The worker self-test could not be completed. Review private diagnostics for details.', 'edis-evidence-exporter'), [
                'status' => 500,
                'diagnostic_id' => 'edis-' . substr(hash('sha256', 'edis_worker_test_failed|' . $exception::class . '|' . $exception->getMessage()), 0, 16),
            ]);
        }
    }
}
