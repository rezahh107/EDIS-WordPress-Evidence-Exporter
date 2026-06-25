<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Elementor;

final class InspectorModule
{
    public function __construct(private readonly string $capability = 'edis_export_evidence') {}

    public function register(): void
    {
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueueStyles']);
    }

    public function enqueueScripts(): void
    {
        if (!$this->allowed()) { return; }
        $documentId = $this->currentDocumentId();
        if ($documentId !== null && !current_user_can('edit_post', $documentId)) { return; }
        wp_enqueue_script('edis-elementor-inspector', EDIS_EVIDENCE_EXPORTER_URL . 'assets/js/elementor-inspector.js', [], EDIS_EVIDENCE_EXPORTER_VERSION, true);
        wp_localize_script('edis-elementor-inspector', 'EDISElementorInspector', [
            'selectionEndpoint' => esc_url_raw(rest_url('edis-evidence-exporter/v3/inspector-selections')),
            'restNonce' => wp_create_nonce('wp_rest'),
            'maxSelections' => 50,
            'version' => EDIS_EVIDENCE_EXPORTER_VERSION,
            'elementorVersion' => defined('ELEMENTOR_VERSION') ? (string) ELEMENTOR_VERSION : null,
            'compatibility' => [
                'viewAwareHooks' => [
                    'elements/section/contextMenuGroups',
                    'elements/column/contextMenuGroups',
                    'elements/widget/contextMenuGroups',
                ],
                'genericHook' => 'elements/context-menu/groups',
                'legacy' => 'OFFICIAL_VIEW_AWARE_HOOKS_STATICALLY_VERIFIED',
                'container' => 'INSUFFICIENT_EVIDENCE_REQUIRES_REAL_FIXTURE',
                'atomic' => 'INSUFFICIENT_EVIDENCE_REQUIRES_OFFICIAL_VIEW_IDENTITY_CONTRACT',
            ],
            'strings' => [
                'groupTitle' => __('EDIS Evidence Inspector', 'edis-evidence-exporter'),
                'exportElement' => __('Export this element', 'edis-evidence-exporter'),
                'exportSubtree' => __('Export this subtree + required dependencies', 'edis-evidence-exporter'),
                'addSelection' => __('Add subtree to EDIS selection', 'edis-evidence-exporter'),
                'removeSelection' => __('Remove from EDIS selection', 'edis-evidence-exporter'),
                'openSelection' => __('Export selected elements', 'edis-evidence-exporter'),
                'clearSelection' => __('Clear', 'edis-evidence-exporter'),
                'selectionCount' => __('EDIS selection: %d', 'edis-evidence-exporter'),
                'missingIdentity' => __('EDIS could not obtain a stable saved-source element identifier.', 'edis-evidence-exporter'),
                'selectionLimit' => __('The EDIS selection limit has been reached.', 'edis-evidence-exporter'),
                'documentChanged' => __('The Elementor document changed, so the previous EDIS selection was cleared.', 'edis-evidence-exporter'),
                'requestFailed' => __('The Inspector selection could not be transferred securely. Try again.', 'edis-evidence-exporter'),
                'removeItem' => __('Remove selection', 'edis-evidence-exporter'),
            ],
        ]);
    }

    public function enqueueStyles(): void
    {
        if (!$this->allowed()) { return; }
        wp_enqueue_style('edis-elementor-inspector', EDIS_EVIDENCE_EXPORTER_URL . 'assets/css/elementor-inspector.css', [], EDIS_EVIDENCE_EXPORTER_VERSION);
    }

    private function allowed(): bool
    {
        return $this->capability !== '' && current_user_can($this->capability);
    }

    private function currentDocumentId(): ?int
    {
        $raw = isset($_GET['post']) ? wp_unslash($_GET['post']) : (isset($_GET['post_id']) ? wp_unslash($_GET['post_id']) : '0');
        $value = absint($raw);
        return $value > 0 ? $value : null;
    }
}
