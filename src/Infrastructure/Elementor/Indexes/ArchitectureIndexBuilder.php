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

final class ArchitectureIndexBuilder implements EvidenceCollector
{
    public function id(): string { return 'elementor_architecture_index'; }
    public function collect(CollectionContext $context,array $artifacts=[]): CollectionResult
    {
        $elements=$artifacts['elementor_element_structure_index']['data']['elements']??[];
        if(!is_array($elements)){return new CollectionResult($this->id(),TruthState::VERIFIED,EvidenceAvailability::UNAVAILABLE,ComponentType::INDEX_BUILDER,null,[new Diagnostic('EDIS_STRUCTURE_INDEX_DEPENDENCY_MISSING','ERROR','SEMANTIC','diagnostic.elementor.structure_index_dependency_missing')]);}
        $documents=[];$totals=['legacy'=>0,'container'=>0,'atomic'=>0,'unknown'=>0];
        foreach($elements as $element){if(!is_array($element)){continue;}$document=(string)($element['document_id']??'');$kind=(string)($element['architecture_kind']??'unknown');if(!isset($totals[$kind])){$kind='unknown';}$totals[$kind]++;$documents[$document][$kind]=($documents[$document][$kind]??0)+1;}
        ksort($documents,SORT_STRING);
        foreach($documents as &$counts){foreach(['legacy','container','atomic','unknown'] as $key){$counts[$key]=(int)($counts[$key]??0);}ksort($counts,SORT_STRING);}unset($counts);
        return new CollectionResult($this->id(),TruthState::VERIFIED,EvidenceAvailability::AVAILABLE,ComponentType::INDEX_BUILDER,['totals'=>(object)$totals,'documents'=>(object)$documents,'hybrid_documents'=>array_map('strval',array_keys(array_filter($documents,static fn(array $counts):bool=>count(array_filter($counts))>1)))],[],[],['collector_id'=>$this->id(),'adapter_id'=>'edis.architecture-index','adapter_version'=>'1.0.0','source_kind'=>'DERIVED_INDEX','retrieval_strategy'=>'count_classified_structure_records']);
    }
}
