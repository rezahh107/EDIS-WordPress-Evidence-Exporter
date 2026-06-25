<?php
declare(strict_types=1);
namespace EDIS\EvidenceExporter\Infrastructure\Elementor\Indexes;
use EDIS\EvidenceExporter\Domain\CollectionResult;use EDIS\EvidenceExporter\Domain\ComponentType;use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;use EDIS\EvidenceExporter\Domain\EvidenceAvailability;use EDIS\EvidenceExporter\Domain\TruthState;
final class CapabilityEvidenceBuilder implements EvidenceCollector
{
 public function id():string{return 'elementor_capability_evidence';}
 public function collect(CollectionContext $context,array $artifacts=[]):CollectionResult
 {
  $environment=$artifacts['environment']['data']??[];$installation=$artifacts['elementor_installation']['data']??[];$widgets=$artifacts['elementor_registered_widgets']['data']['widgets']??[];$types=$artifacts['elementor_registered_document_types']['data']['document_types']??[];$features=$artifacts['elementor_feature_flags']['data']['features']??[];$breakpoints=$artifacts['elementor_breakpoints']['data']['breakpoints']??[];$architecture=$artifacts['elementor_architecture_index']['data']['totals']??[];
  $atomicObserved=(int)($architecture['atomic']??0)>0||in_array('e-div-block',array_map(static fn(array $r):string=>(string)($r['type']??''),is_array($types)?$types:[]),true);
  $evidence=['versions'=>['wordpress'=>$environment['wordpress_version']??null,'php'=>$environment['php_version']??null,'elementor'=>$installation['elementor_version']??null,'elementor_pro'=>$installation['elementor_pro_version']??null],'observed_registration'=>['widgets'=>is_array($widgets)?array_values(array_filter(array_map(static fn(array $row):string=>(string)($row['name']??''),$widgets))):[],'document_types'=>is_array($types)?array_values(array_filter(array_map(static fn(array $row):string=>(string)($row['type']??''),$types))):[]],'observed_features'=>is_array($features)?$features:[],'observed_breakpoints'=>is_array($breakpoints)?$breakpoints:[],'document_usage'=>['architecture_totals'=>is_array($architecture)?$architecture:[]],'support_evidence'=>['variables'=>['basis'=>'collector_availability','value'=>$artifacts['elementor_variables_registry']['source_availability']??'UNAVAILABLE'],'global_classes'=>['basis'=>'collector_availability','value'=>$artifacts['elementor_global_classes_registry']['source_availability']??'UNAVAILABLE'],'atomic_editor'=>['basis'=>'observed_document_usage_or_registration','value'=>$atomicObserved?'OBSERVED':'UNKNOWN']],'version_expectations_are_capabilities'=>false];
  return new CollectionResult($this->id(),TruthState::PARTIAL,EvidenceAvailability::AVAILABLE,ComponentType::INDEX_BUILDER,$evidence,[],[],['collector_id'=>$this->id(),'adapter_id'=>'edis.capability-evidence','adapter_version'=>'1.1.0','source_kind'=>'DERIVED_INDEX','retrieval_strategy'=>'factual_evidence_aggregation']);
 }
}
