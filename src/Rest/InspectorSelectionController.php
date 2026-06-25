<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Rest;

use EDIS\EvidenceExporter\Infrastructure\Support\SelectionTokenStore;

final class InspectorSelectionController
{
    public function __construct(private readonly SelectionTokenStore $tokens, private readonly string $capability = 'edis_export_evidence') {}

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
            : new \WP_Error('edis_inspector_forbidden', __('You do not have permission to export inspector selections.', 'edis-evidence-exporter'), ['status' => 403]);
    }

    public function create(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $documentId = (int) $request->get_param('document_id');
            if ($documentId <= 0 || !current_user_can('edit_post', $documentId)) {
                return new \WP_Error('edis_inspector_forbidden', __('You cannot export evidence for this document.', 'edis-evidence-exporter'), ['status' => 403]);
            }
            $raw = $request->get_param('selection');
            if (!$this->validateSelection($raw)) {
                throw new \InvalidArgumentException('invalid_inspector_selection_shape');
            }
            $selection = [];
            foreach ($raw as $item) {
                $itemDocument = (string) ($item['document_id'] ?? '');
                $elementId = (string) ($item['elementor_element_id'] ?? '');
                if ($itemDocument !== (string) $documentId || !$this->validElementId($elementId)) {
                    throw new \InvalidArgumentException('invalid_inspector_selection_identity');
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
            $issued = $this->tokens->issue(get_current_user_id(), $documentId, array_values($selection), $state);
            $url = add_query_arg(['page' => 'edis-evidence-create', 'selection_token' => $issued['token'], 'export_scope' => 'SINGLE_DOCUMENT'], admin_url('admin.php'));
            return new \WP_REST_Response(['selection_token' => $issued['token'], 'expires_at' => $issued['expires_at'], 'create_export_url' => $url], 201);
        } catch (\Throwable $exception) {
            return new \WP_Error('edis_invalid_inspector_selection', __('The inspector selection could not be accepted. Review EDIS diagnostics for details.', 'edis-evidence-exporter'), [
                'status' => 400,
                'diagnostic_id' => 'edis-' . substr(hash('sha256', 'edis_invalid_inspector_selection|' . $exception::class . '|' . $exception->getMessage()), 0, 16),
            ]);
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

    private function validElementId(string $value): bool
    {
        return $value !== '' && strlen($value) <= 128 && preg_match('/\A[A-Za-z0-9_-]+\z/D', $value) === 1;
    }
}
