<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Admin\Page;

use EDIS\EvidenceExporter\Admin\View\ViewRenderer;
use EDIS\EvidenceExporter\Application\DiagnosticsService;

final class DiagnosticsPage extends AbstractPage
{
    public function __construct(ViewRenderer $renderer, string $capability, private readonly DiagnosticsService $diagnostics)
    {
        parent::__construct($renderer, $capability);
    }

    public function id(): string
    {
        return 'diagnostics';
    }

    public function render(): void
    {
        $this->authorize();
        $diagnosticId = isset($_GET['diagnostic_id']) ? sanitize_text_field(wp_unslash($_GET['diagnostic_id'])) : '';
        $lookupAttempted = $diagnosticId !== '';
        if (preg_match('/\Aedis-diag-[a-f0-9]{32}\z/D', $diagnosticId) !== 1) {
            $diagnosticId = '';
        }
        $selected = $diagnosticId !== ''
            ? $this->diagnostics->resolveDiagnostic(get_current_user_id(), $diagnosticId)
            : null;
        $downloadUrl = is_array($selected)
            ? wp_nonce_url(
                add_query_arg(
                    ['action' => 'edis_download_diagnostic', 'diagnostic_id' => $diagnosticId],
                    admin_url('admin-post.php'),
                ),
                'edis_download_diagnostic',
            )
            : null;
        $this->renderer->render($this->id(), [
            'report' => $this->diagnostics->report(),
            'diagnosticId' => $diagnosticId,
            'lookupAttempted' => $lookupAttempted,
            'selectedDiagnostic' => is_array($selected) ? $selected['record'] : null,
            'selectedDiagnosticBytes' => is_array($selected) ? $selected['bytes'] : null,
            'recentDiagnostics' => $this->diagnostics->recentDiagnostics(get_current_user_id(), 10),
            'downloadUrl' => $downloadUrl,
        ]);
    }
}
