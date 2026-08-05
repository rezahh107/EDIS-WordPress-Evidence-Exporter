<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Rest;

use EDIS\EvidenceExporter\Application\DiagnosticRecordService;
use EDIS\EvidenceExporter\Application\DiagnosticsService;

final class CanonicalDiagnosticResponse extends \WP_REST_Response
{
    public function __construct(public readonly string $canonicalBytes)
    {
        parent::__construct(null, 200);
        $this->header('Content-Type', DiagnosticRecordService::MEDIA_TYPE . '; charset=utf-8');
        $this->header('Content-Length', (string) strlen($canonicalBytes));
        $this->header('Cache-Control', 'no-store');
        $this->header('X-Content-Type-Options', 'nosniff');
    }
}

final class CanonicalDiagnosticResponseAdapter
{
    private const ROUTE_PATTERN = '#\A/edis-evidence-exporter/v3/diagnostics/edis-diag-[a-f0-9]{32}\z#D';

    public static function register(): void
    {
        add_filter('rest_pre_serve_request', [self::class, 'serve'], 10, 4);
    }

    public static function serve(bool $served, mixed $result, mixed $request, mixed $server): bool
    {
        if ($served || !$result instanceof CanonicalDiagnosticResponse || !$request instanceof \WP_REST_Request) {
            return $served;
        }
        $method = strtoupper($request->get_method());
        if (!in_array($method, ['GET', 'HEAD'], true)
            || preg_match(self::ROUTE_PATTERN, $request->get_route()) !== 1) {
            return false;
        }
        if ($method === 'GET') {
            echo $result->canonicalBytes;
        }
        return true;
    }
}

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
        register_rest_route('edis-evidence-exporter/v3', '/diagnostics/(?P<diagnostic_id>edis-diag-[a-f0-9]{32})', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'resolve'],
            'permission_callback' => [$this, 'permission'],
            'args' => [
                'diagnostic_id' => [
                    'type' => 'string',
                    'required' => true,
                    'pattern' => '^edis-diag-[a-f0-9]{32}$',
                    'validate_callback' => static fn (mixed $value): bool => is_string($value) && preg_match('/\Aedis-diag-[a-f0-9]{32}\z/D', $value) === 1,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
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
        $response = new \WP_REST_Response($this->diagnostics->report(), 200);
        $response->header('Cache-Control', 'no-store');
        return $response;
    }

    public function resolve(\WP_REST_Request $request): CanonicalDiagnosticResponse|\WP_Error
    {
        $diagnosticId = (string) $request->get_param('diagnostic_id');
        $resolved = $this->diagnostics->resolveDiagnostic(get_current_user_id(), $diagnosticId);
        if (!is_array($resolved)) {
            return new \WP_Error('edis_diagnostic_not_found', __('Diagnostic artifact not found.', 'edis-evidence-exporter'), ['status' => 404]);
        }
        return new CanonicalDiagnosticResponse($resolved['bytes']);
    }

    public function workerTest(): \WP_REST_Response
    {
        $response = new \WP_REST_Response($this->diagnostics->workerTest(get_current_user_id()), 200);
        $response->header('Cache-Control', 'no-store');
        return $response;
    }
}
