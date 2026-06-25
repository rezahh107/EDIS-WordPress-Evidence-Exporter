<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Application\ExportService;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

final class ElementorPayloadContractRegressionTest extends TestCase
{
    public function testEmptyKitSettingsAndSiteSettingsIndexSerializeWithDeclaredJsonTypes(): void
    {
        $root = dirname(__DIR__, 2) . '/';
        $definitions = require $root . 'config/collectors.php';
        $registry = CollectorRegistry::fromDefinitions($definitions);
        $service = new ExportService($registry, $root);
        $context = new CollectionContext(
            [42],
            false,
            'analysis-regression',
            'bundle-regression',
            'Standard',
            ['export_scope' => 'SINGLE_DOCUMENT', 'dependency_scope' => 'REQUIRED_DEPENDENCIES'],
            '2026-01-01T00:00:00Z'
        );

        $artifacts = [
            'elementor_kit_settings' => [
                'component_id' => 'elementor_kit_settings',
                'component_type' => 'SOURCE_COLLECTOR',
                'source_truth_state' => 'VERIFIED',
                'source_availability' => 'AVAILABLE',
                'data' => ['kit_id' => '7', 'settings' => []],
                'diagnostics' => [],
                'source_references' => [],
                'provenance' => [],
            ],
            'elementor_site_settings_index' => [
                'component_id' => 'elementor_site_settings_index',
                'component_type' => 'INDEX_BUILDER',
                'source_truth_state' => 'PARTIAL',
                'source_availability' => 'AVAILABLE',
                'data' => ['groups' => [], 'source' => 'active_kit_settings', 'ux_evaluation_performed' => false],
                'diagnostics' => [],
                'source_references' => [],
                'provenance' => [],
            ],
        ];

        $method = (new \ReflectionClass($service))->getMethod('envelope');
        $method->setAccessible(true);
        $schemaIndex = json_decode((string) file_get_contents($root . 'schemas/schema-index.json'), true, 512, JSON_THROW_ON_ERROR);
        $validator = new JsonSchemaValidator($root);

        foreach ($artifacts as $componentId => $artifact) {
            $definition = $registry->definition($componentId);
            $envelope = $method->invoke($service, $definition->schemaId, $definition->schemaVersion, $componentId, $artifact, $context);
            $decoded = json_decode(CanonicalJson::encode($envelope), false, 512, JSON_THROW_ON_ERROR);
            $route = $schemaIndex['entries'][$definition->schemaId . '@' . $definition->schemaVersion];
            self::assertSame([], $validator->validate($decoded->data, $route['payload_schema']));
            if ($componentId === 'elementor_kit_settings') {
                self::assertTrue(is_object($decoded->data->evidence->settings));
            } else {
                self::assertTrue(is_object($decoded->data->evidence->groups));
                self::assertSame('active_kit_settings', $decoded->data->evidence->source);
            }
        }
    }

    public function testSchemaTypeFailureReportsExpectedAndActualTypes(): void
    {
        $root = dirname(__DIR__, 2) . '/';
        $validator = new JsonSchemaValidator($root);
        $schemaIndex = json_decode((string) file_get_contents($root . 'schemas/schema-index.json'), true, 512, JSON_THROW_ON_ERROR);
        $route = $schemaIndex['entries']['urn:edis:schema:index:site-settings@1.1.0'];
        $payload = (object) [
            'component_id' => 'elementor_site_settings_index',
            'component_type' => 'INDEX_BUILDER',
            'source_truth_state' => 'PARTIAL',
            'source_availability' => 'AVAILABLE',
            'evidence' => (object) [
                'groups' => (object) [],
                'source' => [],
                'ux_evaluation_performed' => false,
            ],
            'source_references' => [],
            'provenance' => (object) [],
            'evidence_scope' => (object) [
                'scope_kind' => 'KIT',
                'selected_document_ids' => [],
                'inclusion_reason' => 'REGRESSION_TEST',
            ],
        ];
        $errors = $validator->validate($payload, $route['payload_schema']);
        self::assertCount(1, $errors);
        self::assertSame('$.evidence.source', $errors[0]['path']);
        self::assertStringContainsString('Expected string|null; actual array.', $errors[0]['message']);
    }
}
