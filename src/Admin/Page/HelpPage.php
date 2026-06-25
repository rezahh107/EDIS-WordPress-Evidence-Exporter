<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Admin\Page;

use EDIS\EvidenceExporter\Admin\View\ViewRenderer;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;

final class HelpPage extends AbstractPage
{
    public function __construct(ViewRenderer $renderer,string $capability,private readonly CollectorRegistry $registry){parent::__construct($renderer,$capability);}
    public function id():string{return 'help';}
    public function render():void
    {
        $this->authorize();
        $this->renderer->render($this->id(),['isRtl'=>is_rtl(),'definitions'=>$this->registry->definitions()]);
    }
}
