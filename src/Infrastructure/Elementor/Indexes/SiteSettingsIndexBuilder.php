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

final class SiteSettingsIndexBuilder implements EvidenceCollector
{
    public function id(): string { return 'elementor_site_settings_index'; }
    public function collect(CollectionContext $context,array $artifacts=[]): CollectionResult
    {
        $settings=$artifacts['elementor_kit_settings']['data']['settings']??null;
        if(is_object($settings)){$settings=get_object_vars($settings);}
        if(!is_array($settings)){return new CollectionResult($this->id(),TruthState::PARTIAL,EvidenceAvailability::UNAVAILABLE,ComponentType::INDEX_BUILDER,null,[new Diagnostic('EDIS_KIT_SETTINGS_DEPENDENCY_MISSING','WARNING','SEMANTIC','diagnostic.elementor.kit_settings_dependency_missing')]);}
        $groups=['global_colors'=>[],'global_typography'=>[],'layout'=>[],'identity'=>[],'lightbox'=>[],'other'=>[]];
        foreach($settings as $key=>$value){$name=(string)$key;$normalizedName=strtolower($name);$group='other';if(str_contains($normalizedName,'color')){$group='global_colors';}elseif(str_contains($normalizedName,'typography')||str_contains($normalizedName,'font')){$group='global_typography';}elseif($this->containsAny($normalizedName,['container','content_width','space','breakpoint','viewport'])){$group='layout';}elseif($this->containsAny($normalizedName,['logo','site_name','description'])){$group='identity';}elseif(str_contains($normalizedName,'lightbox')){$group='lightbox';}$groups[$group][$name]=$value;}
        $normalizedGroups=[];foreach($groups as $groupId=>$values){$normalizedGroups[$groupId]=(object)$values;}return new CollectionResult($this->id(),TruthState::PARTIAL,EvidenceAvailability::AVAILABLE,ComponentType::INDEX_BUILDER,['groups'=>(object)$normalizedGroups,'source'=>'active_kit_settings','ux_evaluation_performed'=>false],[],[],['collector_id'=>$this->id(),'adapter_id'=>'edis.site-settings-index','adapter_version'=>'1.1.0','source_kind'=>'DERIVED_INDEX','retrieval_strategy'=>'deterministic_key_classification']);
    }

    /** @param list<string> $needles */
    private function containsAny(string $value,array $needles): bool
    {
        foreach($needles as $needle){if(str_contains($value,$needle)){return true;}}
        return false;
    }
}
