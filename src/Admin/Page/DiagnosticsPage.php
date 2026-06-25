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
        $this->renderer->render($this->id(), ['report' => $this->diagnostics->report()]);
    }
}
