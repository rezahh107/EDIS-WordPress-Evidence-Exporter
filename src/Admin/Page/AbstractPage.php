<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Admin\Page;

use EDIS\EvidenceExporter\Admin\View\ViewRenderer;

abstract class AbstractPage implements PageInterface
{
    public function __construct(
        protected readonly ViewRenderer $renderer,
        protected readonly string $capability,
    ) {
    }

    final protected function authorize(): void
    {
        if (!current_user_can($this->capability)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'edis-evidence-exporter'), '', ['response' => 403]);
        }
    }
}
