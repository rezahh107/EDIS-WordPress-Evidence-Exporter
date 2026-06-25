<?php
declare(strict_types=1);
namespace EDIS\EvidenceExporter\Infrastructure\Bundle;
use EDIS\EvidenceExporter\Domain\CollectionResult;use EDIS\EvidenceExporter\Domain\ComponentType;use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;use EDIS\EvidenceExporter\Domain\EvidenceAvailability;use EDIS\EvidenceExporter\Domain\TruthState;
final class SourceCoverageProcessor implements EvidenceCollector
{
 public function id():string{return 'source_coverage';}
 public function collect(CollectionContext $context,array $artifacts=[]):CollectionResult
 {
  $components=[];$truth=['VERIFIED'=>0,'PARTIAL'=>0,'UNKNOWN'=>0,'UNSUPPORTED'=>0];$availability=['AVAILABLE'=>0,'PARTIAL'=>0,'INSUFFICIENT'=>0,'DISABLED'=>0,'UNAVAILABLE'=>0,'NOT_APPLICABLE'=>0,'ERROR'=>0];
  foreach($artifacts as $id=>$artifact){$t=(string)($artifact['source_truth_state']??'UNKNOWN');$a=(string)($artifact['source_availability']??'ERROR');$truth[$t]=($truth[$t]??0)+1;$availability[$a]=($availability[$a]??0)+1;$components[(string)$id]=['component_type'=>$artifact['component_type']??null,'source_truth_state'=>$t,'source_availability'=>$a,'diagnostic_count'=>count((array)($artifact['diagnostics']??[]))];}
  $components[$this->id()]=['component_type'=>'BUNDLE_PROCESSOR','source_truth_state'=>'VERIFIED','source_availability'=>'AVAILABLE','diagnostic_count'=>0];$truth['VERIFIED']++;$availability['AVAILABLE']++;ksort($components,SORT_STRING);
  return new CollectionResult($this->id(),TruthState::VERIFIED,EvidenceAvailability::AVAILABLE,ComponentType::BUNDLE_PROCESSOR,['components'=>(object)$components,'source_component_count'=>count($components),'truth_summary'=>(object)$truth,'availability_summary'=>(object)$availability,'export_scope'=>$context->exportScope(),'dependency_scope'=>$context->dependencyScope(),'coverage_is_not_quality_score'=>true],[],[],['collector_id'=>$this->id(),'adapter_id'=>'edis.source-coverage','adapter_version'=>'1.1.0','source_kind'=>'DERIVED_COVERAGE','retrieval_strategy'=>'final_post_processing_aggregate']);
 }
}
