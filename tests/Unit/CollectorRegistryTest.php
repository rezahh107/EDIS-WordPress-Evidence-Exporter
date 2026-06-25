<?php
declare(strict_types=1);
namespace EDIS\EvidenceExporter\Tests\Unit;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use PHPUnit\Framework\TestCase;
final class CollectorRegistryTest extends TestCase
{
    public function testManifestDefinitionsCreateDependencyOrderedPlan():void
    {
        $definitions=require dirname(__DIR__,2).'/config/collectors.php';
        $registry=CollectorRegistry::fromDefinitions($definitions);
        $plan=$registry->executionPlan(['elementor_usage_summary']);
        self::assertContains('elementor_document_source',$plan);
        self::assertContains('elementor_element_structure_index',$plan);
        self::assertContains('source_coverage',$plan);
        self::assertLessThan(array_search('elementor_usage_summary',$plan,true),array_search('elementor_element_structure_index',$plan,true));
    }
    public function testNoRegisteredComponentIsDeclaredUnsupported():void
    {
        $definitions=require dirname(__DIR__,2).'/config/collectors.php';
        $registry=CollectorRegistry::fromDefinitions($definitions);
        self::assertSame(0,$registry->truthStateCounts()['UNSUPPORTED']);
    }
}
