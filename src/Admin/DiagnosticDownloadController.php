<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Admin;

use EDIS\EvidenceExporter\Application\DiagnosticRecordService;

final class DiagnosticDownloadController
{
    public function __construct(
        private readonly DiagnosticRecordService $diagnostics,
        private readonly string $capability = 'edis_export_evidence',
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_edis_download_diagnostic', [$this, 'download']);
    }

    public function download(): void
    {
        if (!current_user_can($this->capability)) {
            wp_die(esc_html__('You do not have permission to download EDIS diagnostics.', 'edis-evidence-exporter'), '', ['response' => 403]);
        }
        check_admin_referer('edis_download_diagnostic');
        $diagnosticId = isset($_GET['diagnostic_id']) ? sanitize_text_field(wp_unslash($_GET['diagnostic_id'])) : '';
        $resolved = $this->diagnostics->resolveForOwner(
            get_current_user_id(),
            $diagnosticId,
            static fn (int $documentId): bool => current_user_can('edit_post', $documentId),
        );
        if (!is_array($resolved)) {
            wp_die(esc_html__('The diagnostic artifact is unavailable or expired.', 'edis-evidence-exporter'), '', ['response' => 404]);
        }
        $bytes = $resolved['bytes'];
        nocache_headers();
        header('Cache-Control: no-store, private, max-age=0');
        header('Content-Type: ' . DiagnosticRecordService::MEDIA_TYPE . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="edis-diagnostic-' . $diagnosticId . '.json"');
        header('Content-Length: ' . (string) strlen($bytes));
        header('X-Content-Type-Options: nosniff');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Exact validated canonical diagnostic bytes must not be mutated.
        echo $bytes;
        exit;
    }
}
