<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Admin\Page;

use EDIS\EvidenceExporter\Admin\Settings\SettingsRepository;
use EDIS\EvidenceExporter\Admin\View\ViewRenderer;

final class SettingsPage extends AbstractPage
{
    public function __construct(ViewRenderer $renderer, string $capability, private readonly SettingsRepository $settings)
    {
        parent::__construct($renderer, $capability);
    }

    public function id(): string
    {
        return 'settings';
    }

    public function render(): void
    {
        $this->authorize();
        $this->renderer->render($this->id(), ['settings' => $this->settings->all()]);
    }
}
