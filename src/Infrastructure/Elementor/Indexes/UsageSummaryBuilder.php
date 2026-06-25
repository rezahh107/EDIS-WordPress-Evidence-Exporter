<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Elementor\Indexes;

use EDIS\EvidenceExporter\Domain\CollectionResult;
use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;
use EDIS\EvidenceExporter\Domain\Diagnostic;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\DocumentWalker;

final class UsageSummaryBuilder implements EvidenceCollector
{
    public function id(): string { return 'elementor_usage_summary'; }
    public function collect(CollectionContext $context,array $artifacts=[]): CollectionResult
    {
        $elements=$artifacts['elementor_element_structure_index']['data']['elements']??[];
        if(!is_array($elements)){return new CollectionResult($this->id(),TruthState::VERIFIED,EvidenceAvailability::UNAVAILABLE,ComponentType::INDEX_BUILDER,null,[new Diagnostic('EDIS_STRUCTURE_INDEX_DEPENDENCY_MISSING','ERROR','SEMANTIC','diagnostic.elementor.structure_index_dependency_missing')]);}
        $widgetTypes=[];$elementKinds=[];$documents=[];
        foreach($elements as $element){if(!is_array($element)){continue;}$documents[(string)($element['document_id']??'')]=true;$kind=(string)($element['element_kind']??'unknown');$elementKinds[$kind]=($elementKinds[$kind]??0)+1;$widget=$element['widget_type']??null;if(is_string($widget)&&$widget!==''){$widgetTypes[$widget]=($widgetTypes[$widget]??0)+1;}}
        ksort($widgetTypes,SORT_STRING);ksort($elementKinds,SORT_STRING);
        $responsive=$artifacts['elementor_responsive_declaration_index']['data']['count']??0;$refs=$artifacts['elementor_reference_index']['data']['count']??0;
        return new CollectionResult($this->id(),TruthState::VERIFIED,EvidenceAvailability::AVAILABLE,ComponentType::INDEX_BUILDER,['document_count'=>count($documents),'element_count'=>count($elements),'element_kinds'=>$elementKinds,'widget_types'=>$widgetTypes,'responsive_declaration_count'=>(int)$responsive,'reference_count'=>(int)$refs,'scores_emitted'=>false],[],[],['collector_id'=>$this->id(),'adapter_id'=>'edis.usage-summary','adapter_version'=>'1.0.0','source_kind'=>'DERIVED_INDEX','retrieval_strategy'=>'deterministic_counts']);
    }
}
