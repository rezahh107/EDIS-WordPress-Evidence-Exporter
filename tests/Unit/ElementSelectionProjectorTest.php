<?php
declare(strict_types=1);
namespace EDIS\EvidenceExporter\Tests\Unit;
use PHPUnit\Framework\TestCase;
use EDIS\EvidenceExporter\Infrastructure\Elementor\Selection\ElementSelectionProjector;
final class ElementSelectionProjectorTest extends TestCase
{
 public function testAnonymousWrapperPreservedAndOrderIndependent(): void
 {
  $elements=[['id'=>'a','elements'=>[['elType'=>'future-wrapper','elements'=>[['id'=>'g','elements'=>[]]]]]],['id'=>'b','elements'=>[]]];
  $p=new ElementSelectionProjector();
  $a=$p->project('1',$elements,[['document_id'=>'1','elementor_element_id'=>'b','include_descendants'=>false,'selection_reason'=>'USER_SELECTED'],['document_id'=>'1','elementor_element_id'=>'a','include_descendants'=>true,'selection_reason'=>'USER_SELECTED']]);
  $b=$p->project('1',$elements,[['document_id'=>'1','elementor_element_id'=>'a','include_descendants'=>true,'selection_reason'=>'USER_SELECTED'],['document_id'=>'1','elementor_element_id'=>'b','include_descendants'=>false,'selection_reason'=>'USER_SELECTED']]);
  self::assertSame($a,$b);self::assertSame('g',$a['elements'][0]['elements'][0]['elements'][0]['id']);
 }
}
