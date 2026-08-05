<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Rest;

use EDIS\EvidenceExporter\Application\DiagnosticRecordService;
use EDIS\EvidenceExporter\Application\DiagnosticsService;

final class DiagnosticsController
{
    public function __construct(
        private readonly DiagnosticsService $service,
        private readonly string $capability = 'edis_export_evidence',
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route('edis-evidence-exporter/v3', '/diagnostics', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => fn (): \WP_REST_Response => new \WP_REST_Response($this->service->report(), 200),
            'permission_callback' => [$this, 'permission'],
        ]);
        register_rest_route('edis-evidence-exporter/v3', '/diagnostics/worker-test', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => fn (): \WP_REST_Response => new \WP_REST_Response($this->service->workerTest(get_current_user_id()), 200),
            'permission_callback' => [$this, 'permission'],
        ]);
        register_rest_route('edis-evidence-exporter/v3', '/diagnostics/(?P<diagnostic_id>edis-diag-[a-f0-9]{32})', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'resolve'],
            'permission_callback' => [$this, 'permission'],
            'args' => [
                'diagnostic_id' => [
                    'type' => 'string',
                    'required' => true,
                    'pattern' => '^edis-diag-[a-f0-9]{32}$',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    public function permission(\WP_REST_Request $request): bool|\WP_Error
    {
        return current_user_can($this->capability)
            ? true
            : new \WP_Error(
                'edis_diagnostics_forbidden',
                __('You do not have permission to view EDIS diagnostics.', 'edis-evidence-exporter'),
                ['status' => 403],
            );
    }

    public function resolve(\WP_REST_Request $request): CanonicalDiagnosticResponse|\WP_Error
    {
        $diagnosticId = (string) $request->get_param('diagnostic_id');
        if (preg_match('/\Aedis-diag-[a-f0-9]{32}\z/D', $diagnosticId) !== 1) {
            return $this->notFound();
        }

        $resolved = $this->service->resolveDiagnostic(get_current_user_id(), $diagnosticId);
        if (!is_array($resolved) || !is_string($resolved['bytes'] ?? null)) {
            return $this->notFound();
        }

        if ($request->get_param('_envelope') !== null) {
            return new \WP_Error(
                'edis_diagnostic_envelope_unsupported',
                __('Canonical EDIS diagnostic responses do not support the REST envelope parameter.', 'edis-evidence-exporter'),
                ['status' => 406],
            );
        }

        return new CanonicalDiagnosticResponse($resolved['bytes']);
    }

    private function notFound(): \WP_Error
    {
        return new \WP_Error(
            'edis_diagnostic_not_found',
            __('Diagnostic record not found.', 'edis-evidence-exporter'),
            ['status' => 404],
        );
    }
}
