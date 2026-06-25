<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Rest;

use EDIS\EvidenceExporter\Application\DocumentQueryService;

final class DocumentController
{
    public function __construct(
        private readonly DocumentQueryService $documents,
        private readonly string $capability = 'edis_export_evidence',
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route('edis-evidence-exporter/v3', '/documents', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'search'],
            'permission_callback' => [$this, 'permission'],
            'args' => [
                'search' => [
                    'type' => 'string',
                    'default' => '',
                    'maxLength' => 120,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 50,
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                ],
                'include' => [
                    'type' => 'string',
                    'default' => '',
                    'pattern' => '^[0-9,]*$',
                    'maxLength' => 1200,
                    'validate_callback' => static fn(mixed $value): bool => is_string($value) && preg_match('/\A[0-9,]*\z/D', $value) === 1,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    public function permission(): bool|\WP_Error
    {
        return current_user_can($this->capability)
            ? true
            : new \WP_Error('edis_documents_forbidden', __('You do not have permission to list exportable documents.', 'edis-evidence-exporter'), ['status' => 403]);
    }

    public function search(\WP_REST_Request $request): \WP_REST_Response
    {
        $include = [];
        foreach (explode(',', (string) $request->get_param('include')) as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $include[$id] = true;
            }
        }

        return new \WP_REST_Response($this->documents->query(
            (string) $request->get_param('search'),
            max(1, (int) $request->get_param('page')),
            min(50, max(1, (int) $request->get_param('per_page'))),
            array_keys($include),
        ), 200);
    }
}
