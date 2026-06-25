<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Domain\Contracts;

final class CollectionContext
{
    public const PRIVACY_MODES=['Strict','Standard','Diagnostic'];
    public const EXPORT_SCOPES=['SINGLE_DOCUMENT','MULTIPLE_DOCUMENTS','METADATA_ONLY','ENTIRE_SITE'];
    public const DEPENDENCY_SCOPES=['SOURCE_ONLY','REQUIRED_DEPENDENCIES','FULL_SITE_CONTEXT'];
    public const ELEMENT_SELECTION_SCOPES=['DOCUMENT','ELEMENT','SUBTREE','MULTI_SUBTREE'];

    /** @param list<int> $selectedDocumentIds @param array<string,mixed> $options */
    public function __construct(
        public readonly array $selectedDocumentIds,
        public readonly bool $includeOriginalDocuments,
        public readonly string $analysisSetId,
        public readonly string $wordpressBundleId,
        public readonly string $privacyMode='Standard',
        public readonly array $options=[],
        public readonly string $capturedAt=''
    ) {
        foreach($selectedDocumentIds as $id){if(!is_int($id)||$id<=0){throw new \InvalidArgumentException('Document identifiers must be positive integers.');}}
        if($analysisSetId===''||$wordpressBundleId===''){throw new \InvalidArgumentException('Analysis-set and bundle identifiers are required.');}
        if(!in_array($privacyMode,self::PRIVACY_MODES,true)){throw new \InvalidArgumentException('Invalid privacy mode.');}
        if($includeOriginalDocuments&&$privacyMode==='Strict'){throw new \InvalidArgumentException('Strict privacy mode cannot include original documents.');}
        if(!in_array($this->exportScope(),self::EXPORT_SCOPES,true)){throw new \InvalidArgumentException('Invalid export scope.');}
        if(!in_array($this->dependencyScope(),self::DEPENDENCY_SCOPES,true)){throw new \InvalidArgumentException('Invalid dependency scope.');}
        if(!in_array($this->elementSelectionScope(),self::ELEMENT_SELECTION_SCOPES,true)){throw new \InvalidArgumentException('Invalid element selection scope.');}
    }

    public function boolOption(string $name,bool $default=false):bool{return array_key_exists($name,$this->options)?(bool)$this->options[$name]:$default;}
    public function intOption(string $name,int $default,int $minimum,int $maximum):int{$value=array_key_exists($name,$this->options)?(int)$this->options[$name]:$default;return max($minimum,min($maximum,$value));}
    public function stringOption(string $name,string $default=''):string{$value=$this->options[$name]??$default;return is_string($value)?$value:$default;}
    public function exportScope():string{return $this->stringOption('export_scope','MULTIPLE_DOCUMENTS');}
    public function dependencyScope():string{return $this->stringOption('dependency_scope','REQUIRED_DEPENDENCIES');}
    public function elementSelectionScope():string{return $this->stringOption('element_selection_scope','DOCUMENT');}
    public function editorUnsavedChangesState():string{$state=strtoupper($this->stringOption('editor_unsaved_changes_state','UNAVAILABLE'));return in_array($state,['TRUE','FALSE','UNAVAILABLE','ERROR'],true)?$state:'UNAVAILABLE';}
    public function editorUnsavedChangesDetected():bool{return $this->editorUnsavedChangesState()==='TRUE'||$this->boolOption('editor_unsaved_changes_detected',false);}

    /** @return list<array{document_id:string,elementor_element_id:string,include_descendants:bool,selection_reason:string}> */
    public function elementSelection():array
    {
        $value=$this->options['element_selection']??[];
        if(!is_array($value)){return [];}
        $result=[];
        foreach($value as $item){
            if(!is_array($item)){continue;}
            $documentId=is_scalar($item['document_id']??null)?(string)$item['document_id']:'';
            $elementId=is_string($item['elementor_element_id']??null)?$item['elementor_element_id']:'';
            if($documentId===''||!(strlen($elementId)<=128&&$elementId!==''&&ctype_alnum(str_replace(['-','_'],'',$elementId)))){continue;}
            $result[]=['document_id'=>$documentId,'elementor_element_id'=>$elementId,'include_descendants'=>!empty($item['include_descendants']),'selection_reason'=>'USER_SELECTED'];
        }
        usort($result,static fn(array $a,array $b):int=>[$a['document_id'],$a['elementor_element_id'],(int)$a['include_descendants']]<=>[$b['document_id'],$b['elementor_element_id'],(int)$b['include_descendants']]);
        return $result;
    }

    /** @return list<array{document_id:string,elementor_element_id:string,include_descendants:bool,selection_reason:string}> */
    public function elementSelectionForDocument(string $documentId):array
    {
        return array_values(array_filter($this->elementSelection(),static fn(array $item):bool=>$item['document_id']===$documentId));
    }
}
