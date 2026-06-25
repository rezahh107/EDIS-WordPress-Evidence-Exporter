<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;
use PHPUnit\Framework\TestCase;use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
final class ExecutionPlanTest extends TestCase{public function testBundleProcessorsAreLast():void{$definitions=require dirname(__DIR__,2).'/config/collectors.php';$registry=CollectorRegistry::fromDefinitions($definitions);$plan=$registry->executionPlan($registry->defaultSelectableIds());$positions=array_flip($plan);self::assertGreaterThan($positions['elementor_usage_summary']??-1,$positions['bridge_source_context']);self::assertGreaterThan($positions['bridge_source_context'],$positions['bundle_diagnostics']);self::assertGreaterThan($positions['bundle_diagnostics'],$positions['estimated_export_size']);self::assertGreaterThan($positions['estimated_export_size'],$positions['source_coverage']);}}
