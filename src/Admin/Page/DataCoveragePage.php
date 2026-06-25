<?php
declare(strict_types=1);
namespace EDIS\EvidenceExporter\Admin\Page;
use EDIS\EvidenceExporter\Admin\View\ViewRenderer;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
final class DataCoveragePage extends AbstractPage
{
    public function __construct(ViewRenderer $renderer,string $capability,private readonly CollectorRegistry $registry,private readonly JobStore $jobs){parent::__construct($renderer,$capability);}
    public function id():string{return 'data-coverage';}
    public function render():void{$this->authorize();$this->renderer->render($this->id(),['counts'=>$this->registry->truthStateCounts(),'componentTypeCounts'=>$this->registry->componentTypeCounts(),'definitions'=>$this->registry->definitions(),'latestJob'=>$this->jobs->latestForUser(get_current_user_id())]);}
}
