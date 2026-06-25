<?php
declare(strict_types=1);
namespace EDIS\EvidenceExporter\Admin\Page;
use EDIS\EvidenceExporter\Admin\Settings\SettingsRepository;use EDIS\EvidenceExporter\Admin\View\ViewRenderer;use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
final class CreateExportPage extends AbstractPage
{
 public function __construct(ViewRenderer $renderer,string $capability,private readonly CollectorRegistry $registry,private readonly SettingsRepository $settings){parent::__construct($renderer,$capability);}public function id():string{return 'create-export';}
 public function render():void{$this->authorize();$groups=[];foreach($this->registry->definitions() as $definition){$groups[$definition->group][]=$definition;}ksort($groups,SORT_STRING);$this->renderer->render($this->id(),['collectorGroups'=>$groups,'defaults'=>$this->settings->all(),'requestedExportScope'=>$this->requestedScope()]);}
 private function requestedScope():string{$scope=isset($_GET['export_scope'])?sanitize_text_field(wp_unslash($_GET['export_scope'])):'MULTIPLE_DOCUMENTS';return in_array($scope,['SINGLE_DOCUMENT','MULTIPLE_DOCUMENTS','METADATA_ONLY','ENTIRE_SITE'],true)?$scope:'MULTIPLE_DOCUMENTS';}
}
