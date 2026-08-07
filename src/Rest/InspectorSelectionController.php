<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Rest;

use EDIS\EvidenceExporter\Application\DiagnosticRecordService;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportIntegrityException;
use EDIS\EvidenceExporter\Infrastructure\Support\SelectionTokenStore;

final class InspectorSelectionController
{
    public function __construct(
        private readonly SelectionTokenStore $tokens,
        private readonly DiagnosticRecordService $diagnostics,
        private readonly string $capability = 'edis_export_evidence',
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('edis-evidence-exporter/v3', '/inspector-selections', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'create'],
            'permission_callback' => [$this, 'permission'],
            'args' => [
                'document_id' => [
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'selection' => [
                    'type' => 'array',
                    'required' => true,
                    'minItems' => 1,
                    'maxItems' => 50,
                    'validate_callback' => [$this, 'validateSelection'],
                ],
                'editor_unsaved_changes_state' => [
                    'type' => 'string',
                    'required' => false,
                    'enum' => ['TRUE', 'FALSE', 'UNAVAILABLE', 'ERROR', 'true', 'false', 'unavailable', 'error'],
                    'default' => 'UNAVAILABLE',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    public function permission(): bool|\WP_Error
    {
        return current_user_can($this->capability)
            ? true
            : new \WP_Error('edis_inspector_forbidden', __('You do not have permission to export Inspector selections.', 'edis-evidence-exporter'), ['status' => 403]);
    }

    public function create(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $documentId = (int) $request->get_param('document_id');
        if ($documentId <= 0 || !current_user_can('edit_post', $documentId)) {
            return $this->expectedError(
                'edis_inspector_forbidden',
                403,
                __('You cannot export evidence for this document.', 'edis-evidence-exporter'),
                'EDIS_INSPECTOR_DOCUMENT_AUTHORIZATION_DENIED',
            );
        }

        $raw = $request->get_param('selection');
        if (!$this->validateSelection($raw)) {
            return $this->expectedError(
                'edis_invalid_inspector_selection',
                400,
                __('The Inspector selection shape is invalid.', 'edis-evidence-exporter'),
                'EDIS_INSPECTOR_SELECTION_SHAPE_REJECTED',
            );
        }

        $selection = [];
        foreach ($raw as $item) {
            $itemDocument = (string) ($item['document_id'] ?? '');
            $elementId = (string) ($item['elementor_element_id'] ?? '');
            if ($itemDocument !== (string) $documentId || !$this->validElementId($elementId)) {
                return $this->expectedError(
                    'edis_invalid_inspector_selection',
                    400,
                    __('The Inspector selection does not match the authorized document.', 'edis-evidence-exporter'),
                    'EDIS_INSPECTOR_SELECTION_IDENTITY_REJECTED',
                );
            }
            $selection[$elementId] = [
                'document_id' => (string) $documentId,
                'elementor_element_id' => $elementId,
                'include_descendants' => !empty($item['include_descendants']),
                'selection_reason' => 'USER_SELECTED',
                'element_type' => sanitize_key((string) ($item['element_type'] ?? 'unknown')),
            ];
        }
        ksort($selection, SORT_STRING);
        $state = strtoupper((string) $request->get_param('editor_unsaved_changes_state'));
        if (!in_array($state, ['TRUE', 'FALSE', 'UNAVAILABLE', 'ERROR'], true)) {
            $state = 'UNAVAILABLE';
        }

        try {
            $issued = $this->tokens->issue(get_current_user_id(), $documentId, array_values($selection), $state);
            $url = add_query_arg([
                'page' => 'edis-evidence-create',
                'selection_token' => $issued['token'],
                'export_scope' => 'SINGLE_DOCUMENT',
            ], admin_url('admin.php'));
            return new \WP_REST_Response([
                'selection_token' => $issued['token'],
                'expires_at' => $issued['expires_at'],
                'create_export_url' => $url,
            ], 201);
        } catch (\Throwable $exception) {
            $integrity = $exception instanceof ExportIntegrityException
                ? $exception
                : new ExportIntegrityException(
                    'EDIS_INSPECTOR_SELECTION_PERSISTENCE_FAILED',
                    'The bounded Inspector selection could not be persisted.',
                    $exception,
                    [
                        'failure_phase' => 'inspector_selection_persistence',
                        'selected_document_count' => 1,
                    ],
                );
            $diagnostic = $this->diagnostics->capturePreJobFailure(
                get_current_user_id(),
                'INSPECTOR_SELECTION',
                '/edis-evidence-exporter/v3/inspector-selections',
                [
                    'privacy_mode' => null,
                    'collectors' => [],
                    'document_ids' => [$documentId],
                    'options' => ['export_scope' => 'SINGLE_DOCUMENT'],
                ],
                $integrity,
                [
                    'lifecycle_stage' => 'inspector_selection_persistence',
                    'subsystem' => 'SelectionTokenStore',
                    'operation_immediately_attempted' => 'persist one bounded Inspector selection token',
                    'last_successful_state' => 'INSPECTOR_REQUEST_VALIDATED',
                ],
                'edis_inspector_selection_failed',
            );
            return $this->diagnosticError($diagnostic);
        }
    }

    public function validateSelection(mixed $value): bool
    {
        if (!is_array($value) || $value === [] || count($value) > 50) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_array($item)) {
                return false;
            }
            $documentId = $item['document_id'] ?? null;
            $elementId = $item['elementor_element_id'] ?? null;
            if (!is_scalar($documentId) || !is_scalar($elementId) || !$this->validElementId((string) $elementId)) {
                return false;
            }
            if (isset($item['element_type']) && (!is_scalar($item['element_type']) || strlen((string) $item['element_type']) > 80)) {
                return false;
            }
        }
        return true;
    }

    private function expectedError(string $code, int $status, string $message, string $reasonCode): \WP_Error
    {
        return new \WP_Error($code, $message, [
            'status' => $status,
            'reason_code' => $reasonCode,
            'diagnostic_available' => false,
            'diagnostic_id' => null,
            'diagnostics_url' => null,
            'diagnostic_persistence_code' => null,
        ]);
    }

    /** @param array{diagnostic_available:bool,diagnostic_id:?string,diagnostic_persistence_code:?string} $diagnostic */
    private function diagnosticError(array $diagnostic): \WP_Error
    {
        $available = $diagnostic['diagnostic_available'] === true
            && is_string($diagnostic['diagnostic_id'])
            && preg_match('/\Aedis-diag-[a-f0-9]{32}\z/D', $diagnostic['diagnostic_id']) === 1;
        $diagnosticId = $available ? $diagnostic['diagnostic_id'] : null;
        $persistenceCode = !$available && is_string($diagnostic['diagnostic_persistence_code'] ?? null)
            ? $diagnostic['diagnostic_persistence_code']
            : null;
        $url = $available
            ? add_query_arg('diagnostic_id', $diagnosticId, admin_url('admin.php?page=edis-evidence-diagnostics'))
            : null;
        return new \WP_Error(
            'edis_inspector_selection_failed',
            $available
                ? __('The Inspector operation failed. Open EDIS Diagnostics with the returned diagnostic ID.', 'edis-evidence-exporter')
                : __('The Inspector operation failed and no Diagnostic artifact is available.', 'edis-evidence-exporter'),
            [
                'status' => 500,
                'diagnostic_available' => $available,
                'diagnostic_id' => $diagnosticId,
                'diagnostics_url' => $url,
                'diagnostic_persistence_code' => $available ? null : ($persistenceCode ?? 'EDIS_DIAGNOSTIC_PERSISTENCE_FAILED'),
            ],
        );
    }

    private function validElementId(string $value): bool
    {
        return $value !== '' && strlen($value) <= 128 && preg_match('/\A[A-Za-z0-9_-]+\z/D', $value) === 1;
    }
}
