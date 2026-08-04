<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Admin;

final class DiagnosticAdminAssets
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue'], 9);
    }

    public function enqueue(): void
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page === '' || !str_starts_with($page, 'edis-evidence')) {
            return;
        }
        $handle = 'edis-evidence-diagnostics';
        wp_enqueue_script(
            $handle,
            EDIS_EVIDENCE_EXPORTER_URL . 'assets/js/diagnostics.js',
            [],
            EDIS_EVIDENCE_EXPORTER_VERSION,
            true,
        );
        wp_localize_script($handle, 'EDISDiagnosticAdmin', [
            'restPrefix' => rest_url('edis-evidence-exporter/v3'),
            'diagnosticsUrl' => admin_url('admin.php?page=edis-evidence-diagnostics'),
            'strings' => [
                'diagnosticId' => __('Diagnostic ID', 'edis-evidence-exporter'),
                'copyId' => __('Copy ID', 'edis-evidence-exporter'),
                'openDiagnostics' => __('Open Diagnostics', 'edis-evidence-exporter'),
                'artifactUnavailable' => __('EDIS could not persist a diagnostic artifact for this failure.', 'edis-evidence-exporter'),
                'copied' => __('Diagnostic ID copied.', 'edis-evidence-exporter'),
                'copyJson' => __('Copy diagnostic JSON', 'edis-evidence-exporter'),
            ],
        ]);
    }
}
