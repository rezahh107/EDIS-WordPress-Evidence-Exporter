<?php
declare(strict_types=1);
namespace EDIS\EvidenceExporter\Admin\Page;
use EDIS\EvidenceExporter\Admin\View\ViewRenderer;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
final class DataSourcesPage extends AbstractPage
{
    public function __construct(ViewRenderer $renderer,string $capability,private readonly CollectorRegistry $registry){parent::__construct($renderer,$capability);}
    public function id():string{return 'data-sources';}
    public function render():void
    {
        $this->authorize();$runtime=[];
        foreach($this->registry->definitions() as $definition){
            $requiresElementor=str_starts_with($definition->id,'elementor_');
            $runtime[$definition->id]=$requiresElementor&&!defined('ELEMENTOR_VERSION')?'UNAVAILABLE':'AVAILABLE';
        }
        $this->renderer->render($this->id(),['definitions'=>$this->registry->definitions(),'runtimeSupport'=>$runtime,'componentTypeCounts'=>$this->registry->componentTypeCounts()]);
    }
}
