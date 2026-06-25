<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Admin\Page;

use EDIS\EvidenceExporter\Admin\Settings\SettingsRepository;
use EDIS\EvidenceExporter\Admin\View\ViewRenderer;
use EDIS\EvidenceExporter\Application\DiagnosticsService;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;

final class OverviewPage extends AbstractPage
{
    public function __construct(ViewRenderer $renderer,string $capability,private readonly CollectorRegistry $registry,private readonly JobStore $jobs,private readonly SettingsRepository $settings,private readonly DiagnosticsService $diagnostics){parent::__construct($renderer,$capability);}
    public function id():string{return 'overview';}
    public function render():void
    {
        $this->authorize();global $wp_version;
        $this->renderer->render($this->id(),[
            'pluginVersion'=>EDIS_EVIDENCE_EXPORTER_VERSION,
            'platformVersion'=>EDIS_EVIDENCE_BUILD_PLATFORM_VERSION,
            'manifestVersion'=>'1.2.0',
            'bundleSchemaVersion'=>'3.3.0',
            'wordpressVersion'=>is_string($wp_version)?$wp_version:get_bloginfo('version'),
            'phpVersion'=>PHP_VERSION,
            'elementorVersion'=>defined('ELEMENTOR_VERSION')?(string)ELEMENTOR_VERSION:null,
            'collectorCount'=>count($this->registry->ids()),
            'truthCounts'=>$this->registry->truthStateCounts(),
            'componentTypeCounts'=>$this->registry->componentTypeCounts(),
            'latestJob'=>$this->jobs->latestForUser(get_current_user_id()),
            'privacyMode'=>$this->settings->defaultPrivacyMode(),
            'diagnosticSummary'=>$this->diagnostics->summary(),
        ]);
    }
}
