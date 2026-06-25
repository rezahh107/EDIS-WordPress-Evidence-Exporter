<?php
declare(strict_types=1);
namespace EDIS\EvidenceExporter\Infrastructure\Bundle;
use EDIS\EvidenceExporter\Domain\CollectionResult;use EDIS\EvidenceExporter\Domain\ComponentType;use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;use EDIS\EvidenceExporter\Domain\EvidenceAvailability;use EDIS\EvidenceExporter\Domain\TruthState;use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
final class EstimatedSizeProcessor implements EvidenceCollector
{
 public function id():string{return 'estimated_export_size';}
 public function collect(CollectionContext $context,array $artifacts=[]):CollectionResult
 {
  $records=[];$total=0;foreach($artifacts as $id=>$artifact){$bytes=strlen(CanonicalJson::encode($artifact));$records[]=['component_id'=>(string)$id,'estimated_canonical_bytes'=>$bytes];$total+=$bytes;}usort($records,static fn(array $a,array $b):int=>strcmp($a['component_id'],$b['component_id']));
  return new CollectionResult($this->id(),TruthState::VERIFIED,EvidenceAvailability::AVAILABLE,ComponentType::BUNDLE_PROCESSOR,['estimated_canonical_bytes'=>$total,'components'=>$records,'estimate_only'=>true],[],[],['collector_id'=>$this->id(),'adapter_id'=>'edis.estimated-size','adapter_version'=>'1.1.0','source_kind'=>'DERIVED_BUNDLE_METADATA','retrieval_strategy'=>'post_collection_canonical_size_sum']);
 }
}
