<?php
declare(strict_types=1);
namespace EDIS\EvidenceExporter\Infrastructure\Bundle;
use EDIS\EvidenceExporter\Domain\CollectionResult;use EDIS\EvidenceExporter\Domain\ComponentType;use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;use EDIS\EvidenceExporter\Domain\EvidenceAvailability;use EDIS\EvidenceExporter\Domain\TruthState;
final class BundleDiagnosticsProcessor implements EvidenceCollector
{
 public function id():string{return 'bundle_diagnostics';}
 public function collect(CollectionContext $context,array $artifacts=[]):CollectionResult
 {
  $rows=[];foreach($artifacts as $componentId=>$artifact){foreach((array)($artifact['diagnostics']??[]) as $diagnostic){if($diagnostic instanceof \JsonSerializable){$diagnostic=$diagnostic->jsonSerialize();}if(!is_array($diagnostic)){continue;}$diagnostic['component_id']=(string)$componentId;if(($diagnostic['context']??null)===[]){$diagnostic['context']=(object)[];}$rows[]=$diagnostic;}}
  usort($rows,static fn(array $a,array $b):int=>[(string)($a['severity']??''),(string)($a['code']??''),(string)($a['component_id']??'')]<=>[(string)($b['severity']??''),(string)($b['code']??''),(string)($b['component_id']??'')]);
  return new CollectionResult($this->id(),TruthState::VERIFIED,EvidenceAvailability::AVAILABLE,ComponentType::BUNDLE_PROCESSOR,['diagnostics'=>$rows,'count'=>count($rows)],[],[],['collector_id'=>$this->id(),'adapter_id'=>'edis.bundle-diagnostics','adapter_version'=>'1.1.0','source_kind'=>'DERIVED_DIAGNOSTICS','retrieval_strategy'=>'post_collection_aggregate']);
 }
}
