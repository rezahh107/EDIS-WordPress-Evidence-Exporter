<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Collector;

use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;
use EDIS\EvidenceExporter\Domain\CollectionResult;
use EDIS\EvidenceExporter\Domain\Diagnostic;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;

final class CollectorRegistry
{
    /** @var array<string, CollectorDefinition> */
    private array $definitions = [];

    /** @param list<CollectorDefinition> $definitions */
    public static function fromDefinitions(array $definitions): self
    {
        $registry = new self();
        foreach ($definitions as $definition) {
            if (!$definition instanceof CollectorDefinition) { throw new \InvalidArgumentException('Invalid component definition.'); }
            $registry->register($definition);
        }
        $registry->assertDependencies();
        return $registry;
    }

    public function register(CollectorDefinition $definition): void
    {
        if (isset($this->definitions[$definition->id])) { throw new \LogicException('Duplicate component id: ' . $definition->id); }
        $this->definitions[$definition->id] = $definition;
        ksort($this->definitions, SORT_STRING);
    }

    public function create(string $id): EvidenceCollector
    {
        $definition=$this->definition($id); $component=($definition->factory)();
        if($component->id()!==$id){throw new \LogicException('Component factory id mismatch: '.$id);} return $component;
    }

    /** @param array<string, array<string, mixed>> $artifacts */
    public function execute(string $id, CollectionContext $context, array $artifacts): CollectionResult
    {
        $result=$this->create($id)->collect($context,$artifacts);
        return $this->applyDependencyContract($this->definition($id),$result,$artifacts);
    }

    public function definition(string $id): CollectorDefinition
    {
        $definition=$this->definitions[$id]??null;
        if(!$definition instanceof CollectorDefinition){throw new \OutOfBoundsException('Unknown component id: '.$id);} return $definition;
    }
    /** @return list<CollectorDefinition> */ public function definitions():array{return array_values($this->definitions);}
    /** @return list<CollectorDefinition> */ public function selectableDefinitions():array{return array_values(array_filter($this->definitions(),static fn(CollectorDefinition $d):bool=>$d->selectable));}
    /** @return list<string> */ public function ids():array{return array_keys($this->definitions);}
    /** @return list<string> */ public function executableIds():array{$ids=[];foreach($this->definitions as $d){if($d->implementation==='real'&&$d->declaredTruthState!==TruthState::UNSUPPORTED){$ids[]=$d->id;}}sort($ids,SORT_STRING);return $ids;}
    /** @return list<string> */ public function defaultSelectableIds():array{$ids=[];foreach($this->definitions as $d){if($d->selectable&&$d->defaultEnabled&&$this->isExecutable($d->id)){$ids[]=$d->id;}}sort($ids,SORT_STRING);return $ids;}
    public function isExecutable(string $id):bool{$d=$this->definition($id);return $d->implementation==='real'&&$d->declaredTruthState!==TruthState::UNSUPPORTED;}
    /** @return array<string,int> */ public function truthStateCounts():array{$c=['VERIFIED'=>0,'PARTIAL'=>0,'UNKNOWN'=>0,'UNSUPPORTED'=>0];foreach($this->definitions as $d){$c[$d->declaredTruthState->value]++;}return $c;}
    /** @return array<string,int> */ public function componentTypeCounts():array{$c=['SOURCE_COLLECTOR'=>0,'INDEX_BUILDER'=>0,'BUNDLE_PROCESSOR'=>0];foreach($this->definitions as $d){$c[$d->componentType->value]++;}return $c;}

    /** @param list<string> $selectedIds @return list<string> */
    public function executionPlan(array $selectedIds,string $dependencyScope='REQUIRED_DEPENDENCIES'): array
    {
        $selected=[];
        $include=function(string $id,bool $force=false)use(&$include,&$selected,$dependencyScope):void{
            $d=$this->definition($id);if(!$this->isExecutable($id)){throw new \InvalidArgumentException('Component is not executable: '.$id);}if(isset($selected[$id])){return;}$selected[$id]=true;
            foreach($d->dependencies as $dep){$depId=(string)$dep['id'];$kind=(string)$dep['kind'];if($kind==='REQUIRED'){$include($depId,true);}elseif($kind==='OPTIONAL'){if($dependencyScope==='FULL_SITE_CONTEXT'||isset($selected[$depId])){$include($depId,false);}}elseif($kind==='CONDITIONAL'&&$dependencyScope==='FULL_SITE_CONTEXT'){$include($depId,false);}}
        };
        foreach($selectedIds as $id){$include($id,true);}foreach($this->definitions as $d){if($d->componentType===ComponentType::BUNDLE_PROCESSOR&&$d->defaultEnabled){$selected[$d->id]=true;}}
        $ordered=[];$state=[];
        $visit=function(string $id)use(&$visit,&$ordered,&$state,&$selected):void{$current=$state[$id]??0;if($current===2){return;}if($current===1){throw new \LogicException('Component dependency cycle at: '.$id);}$state[$id]=1;foreach($this->definitions[$id]->dependencies as $dep){$depId=(string)$dep['id'];if(isset($selected[$depId])){$visit($depId);}}$state[$id]=2;$ordered[]=$id;};
        $ids=array_keys($selected);usort($ids,function(string $left,string $right):int{$rank=fn(string $id):int=>$this->definitions[$id]->componentType===ComponentType::BUNDLE_PROCESSOR?1:0;return [$rank($left),$left]<=>[$rank($right),$right];});foreach($ids as $id){$visit($id);}return $ordered;
    }

    /** @param array<string,array<string,mixed>> $artifacts */
    private function applyDependencyContract(CollectorDefinition $definition,CollectionResult $result,array $artifacts):CollectionResult
    {
        $truth=$result->truthState;$availability=$result->availability;$diagnostics=$result->diagnostics;$references=$result->sourceReferences;
        foreach($definition->dependencies as $dependency){
            $dependencyId=(string)$dependency['id'];$kind=(string)$dependency['kind'];$artifact=$artifacts[$dependencyId]??null;
            if(!is_array($artifact)){if($kind==='REQUIRED'){$truth=$this->lowerTruth($truth,TruthState::UNKNOWN);$availability=$this->worseAvailability($availability,EvidenceAvailability::UNAVAILABLE);$diagnostics[]=new Diagnostic('EDIS_REQUIRED_DEPENDENCY_MISSING','ERROR','SEMANTIC','diagnostic.component.required_dependency_missing',['component_id'=>$definition->id,'dependency_id'=>$dependencyId]);}continue;}
            $depTruth=TruthState::tryFrom((string)($artifact['source_truth_state']??''))??TruthState::UNKNOWN;
            $depAvailability=EvidenceAvailability::tryFrom((string)($artifact['source_availability']??''))??EvidenceAvailability::ERROR;
            $references[]=['component_id'=>$dependencyId,'dependency_kind'=>$kind,'artifact_payload_sha256'=>'sha256:'.hash('sha256',CanonicalJson::encode($artifact)),'source_truth_state'=>$depTruth->value,'source_availability'=>$depAvailability->value];
            if($kind!=='REQUIRED'){continue;}
            $previousTruth=$truth;$previousAvailability=$availability;
            $truth=$this->lowerTruth($truth,$depTruth);$availability=$this->worseAvailability($availability,$depAvailability);
            if($truth!==$previousTruth||$availability!==$previousAvailability){$diagnostics[]=new Diagnostic('EDIS_REQUIRED_DEPENDENCY_STATE_PROPAGATED','WARNING','SEMANTIC','diagnostic.component.required_dependency_state_propagated',['component_id'=>$definition->id,'dependency_id'=>$dependencyId,'dependency_truth_state'=>$depTruth->value,'dependency_availability'=>$depAvailability->value]);}
        }
        usort($references,static fn(array $a,array $b):int=>[(string)($a['component_id']??''),(string)($a['dependency_kind']??'')]<=>[(string)($b['component_id']??''),(string)($b['dependency_kind']??'')]);
        return new CollectionResult($result->componentId,$truth,$availability,$result->componentType,$result->data,$diagnostics,$references,$result->provenance);
    }

    private function lowerTruth(TruthState $current,TruthState $dependency):TruthState
    {
        $rank=['UNSUPPORTED'=>0,'UNKNOWN'=>1,'PARTIAL'=>2,'VERIFIED'=>3];return $rank[$dependency->value]<$rank[$current->value]?$dependency:$current;
    }
    private function worseAvailability(EvidenceAvailability $current,EvidenceAvailability $dependency):EvidenceAvailability
    {
        $rank=['AVAILABLE'=>6,'NOT_APPLICABLE'=>5,'PARTIAL'=>4,'INSUFFICIENT'=>3,'DISABLED'=>2,'UNAVAILABLE'=>1,'ERROR'=>0];return $rank[$dependency->value]<$rank[$current->value]?$dependency:$current;
    }
    private function assertDependencies():void{foreach($this->definitions as $d){foreach($d->dependencies as $dep){if(!isset($this->definitions[(string)$dep['id']])){throw new \LogicException('Unknown component dependency: '.(string)$dep['id']);}}}}
}
