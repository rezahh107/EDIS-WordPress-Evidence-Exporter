<?php
declare(strict_types=1);

namespace {
    if (!function_exists('wp_load_alloptions')) {
        function wp_load_alloptions(): array
        {
            $value = $GLOBALS['edis_feature_flags_test_options'] ?? [];
            return is_array($value) ? $value : [];
        }
        $GLOBALS['edis_feature_flags_test_stub_owned'] = true;
    }
}

namespace EDIS\EvidenceExporter\Tests\Unit {

use EDIS\EvidenceExporter\Application\ExportService;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Elementor\Collectors\FeatureFlagsCollector;
use EDIS\EvidenceExporter\Infrastructure\Elementor\Indexes\CapabilityEvidenceBuilder;
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportIntegrityException;
use EDIS\EvidenceExporter\Infrastructure\Support\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

final class ElementorPayloadContractRegressionTest extends TestCase
{
    public function testEmptyKitSettingsAndSiteSettingsIndexSerializeWithDeclaredJsonTypes(): void
    {
        [, $registry, $service, $context, $method, $schemaIndex, $validator] = $this->harness();
        $artifacts = [
            'elementor_kit_settings' => $this->artifact(
                'elementor_kit_settings',
                'SOURCE_COLLECTOR',
                'VERIFIED',
                ['kit_id' => '7', 'settings' => []],
            ),
            'elementor_site_settings_index' => $this->artifact(
                'elementor_site_settings_index',
                'INDEX_BUILDER',
                'PARTIAL',
                ['groups' => [], 'source' => 'active_kit_settings', 'ux_evaluation_performed' => false],
            ),
        ];

        foreach ($artifacts as $componentId => $artifact) {
            $decoded = $this->project($method, $service, $registry, $context, $componentId, $artifact);
            $this->assertSchemaPasses($validator, $schemaIndex, $registry, $componentId, $decoded->data);
            if ($componentId === 'elementor_kit_settings') {
                self::assertTrue(is_object($decoded->data->evidence->settings));
            } else {
                self::assertTrue(is_object($decoded->data->evidence->groups));
                self::assertSame('active_kit_settings', $decoded->data->evidence->source);
            }
        }
    }

    public function testFeatureFlagsEmptyAndOneRecordPublicProjection(): void
    {
        [, $registry, $service, $context, $method, $schemaIndex, $validator] = $this->harness();
        $cases = [
            [[], []],
            [
                ['elementor_experiment-container' => 'active'],
                [['name' => 'elementor_experiment-container', 'value' => 'active']],
            ],
        ];

        foreach ($cases as [$input, $expected]) {
            $artifact = $this->artifact(
                'elementor_feature_flags',
                'SOURCE_COLLECTOR',
                'PARTIAL',
                ['features' => $input, 'observed_count' => count($input), 'evidence_basis' => 'observed_options'],
            );
            $decoded = $this->project($method, $service, $registry, $context, 'elementor_feature_flags', $artifact);
            self::assertTrue(is_array($decoded->data->evidence->features));
            self::assertSame($expected, $this->featureRecords($decoded->data->evidence->features));
            self::assertSame(count($input), $decoded->data->evidence->observed_count);
            self::assertSame('observed_options', $decoded->data->evidence->evidence_basis);
            $this->assertSchemaPasses($validator, $schemaIndex, $registry, 'elementor_feature_flags', $decoded->data);
        }
    }

    public function testFeatureFlagsProjectionIsDeterministicAndPreservesRedaction(): void
    {
        [, $registry, $service, $context, $method, $schemaIndex, $validator] = $this->harness();
        $input = [
            'elementor_feature-zeta' => 'inactive',
            'elementor_beta_anything' => 'COMPLEX_VALUE_REDACTED',
            'elementor_experiment-container' => 'active',
        ];
        $artifact = $this->artifact(
            'elementor_feature_flags',
            'SOURCE_COLLECTOR',
            'PARTIAL',
            ['features' => $input, 'observed_count' => 3, 'evidence_basis' => 'observed_options'],
        );
        $first = $method->invoke($service, $registry->definition('elementor_feature_flags')->schemaId, '1.0.0', 'elementor_feature_flags', $artifact, $context);
        $second = $method->invoke($service, $registry->definition('elementor_feature_flags')->schemaId, '1.0.0', 'elementor_feature_flags', $artifact, $context);
        self::assertSame(CanonicalJson::encode($first), CanonicalJson::encode($second));

        $decoded = json_decode(CanonicalJson::encode($first), false, 512, JSON_THROW_ON_ERROR);
        self::assertSame([
            ['name' => 'elementor_beta_anything', 'value' => 'COMPLEX_VALUE_REDACTED'],
            ['name' => 'elementor_experiment-container', 'value' => 'active'],
            ['name' => 'elementor_feature-zeta', 'value' => 'inactive'],
        ], $this->featureRecords($decoded->data->evidence->features));
        $this->assertSchemaPasses($validator, $schemaIndex, $registry, 'elementor_feature_flags', $decoded->data);
    }

    public function testFeatureFlagsCollectorAndCapabilityConsumerRemainMapBased(): void
    {
        if (($GLOBALS['edis_feature_flags_test_stub_owned'] ?? false) !== true) {
            self::fail('The focused Feature Flags collector stub was not installed.');
        }
        [, , , $context] = $this->harness();
        $GLOBALS['edis_feature_flags_test_options'] = [
            'unrelated' => 'ignore',
            'elementor_feature-zeta' => 'inactive',
            'elementor_experiment-container' => 'active',
            'elementor_beta_anything' => ['private' => 'value'],
        ];
        try {
            $result = (new FeatureFlagsCollector())->collect($context);
        } finally {
            unset($GLOBALS['edis_feature_flags_test_options']);
        }
        $expected = [
            'elementor_beta_anything' => 'COMPLEX_VALUE_REDACTED',
            'elementor_experiment-container' => 'active',
            'elementor_feature-zeta' => 'inactive',
        ];
        self::assertSame($expected, $result->data['features'] ?? null);

        $capability = (new CapabilityEvidenceBuilder())->collect($context, [
            'environment' => ['data' => []],
            'elementor_installation' => ['data' => []],
            'elementor_registered_widgets' => ['data' => ['widgets' => []]],
            'elementor_registered_document_types' => ['data' => ['document_types' => []]],
            'elementor_feature_flags' => $result->jsonSerialize(),
            'elementor_breakpoints' => ['data' => ['breakpoints' => []]],
            'elementor_architecture_index' => ['data' => ['totals' => []]],
        ]);
        self::assertSame($expected, $capability->data['observed_features'] ?? null);
    }

    public function testValidProjectedFeatureRecordsAreIdempotentAndReordered(): void
    {
        [, $registry, $service, $context, $method, $schemaIndex, $validator] = $this->harness();
        $records = [
            ['value' => 'inactive', 'name' => 'elementor_feature-zeta'],
            ['name' => 'elementor_experiment-container', 'value' => 'active'],
        ];
        $artifact = $this->artifact(
            'elementor_feature_flags',
            'SOURCE_COLLECTOR',
            'PARTIAL',
            ['features' => $records, 'observed_count' => 2, 'evidence_basis' => 'observed_options'],
        );
        $first = $this->project($method, $service, $registry, $context, 'elementor_feature_flags', $artifact);
        $artifact['data']['features'] = array_map(
            static fn (object $record): array => ['name' => $record->name, 'value' => $record->value],
            $first->data->evidence->features,
        );
        $second = $this->project($method, $service, $registry, $context, 'elementor_feature_flags', $artifact);

        self::assertSame([
            ['name' => 'elementor_experiment-container', 'value' => 'active'],
            ['name' => 'elementor_feature-zeta', 'value' => 'inactive'],
        ], $this->featureRecords($first->data->evidence->features));
        self::assertSame($this->featureRecords($first->data->evidence->features), $this->featureRecords($second->data->evidence->features));
        $this->assertSchemaPasses($validator, $schemaIndex, $registry, 'elementor_feature_flags', $second->data);
    }

    public function testMalformedProjectedFeatureRecordsFailClosedWithoutPrivateValues(): void
    {
        [, $registry, $service, $context, $method] = $this->harness();
        $artifact = $this->artifact(
            'elementor_feature_flags',
            'SOURCE_COLLECTOR',
            'PARTIAL',
            [
                'features' => [['name' => 'elementor_feature-secret', 'value' => ['private' => 'do-not-emit']]],
                'observed_count' => 1,
                'evidence_basis' => 'observed_options',
            ],
        );

        try {
            $this->project($method, $service, $registry, $context, 'elementor_feature_flags', $artifact);
            self::fail('Malformed projected Feature Flags must fail closed.');
        } catch (ExportIntegrityException $exception) {
            self::assertSame('EDIS_FEATURE_FLAGS_PROJECTION_INVALID', $exception->diagnosticCode);
            self::assertSame('public_artifact_projection', $exception->diagnosticContext['failure_phase'] ?? null);
            self::assertSame('elementor_feature_flags', $exception->diagnosticContext['component_id'] ?? null);
            self::assertSame(0, $exception->diagnosticContext['record_index'] ?? null);
            self::assertStringNotContainsString('do-not-emit', $exception->getMessage());
            self::assertArrayNotHasKey('value', $exception->diagnosticContext);
        }
    }

    public function testDeclaredObjectFieldsNormalizeEmptyAndPreserveMembers(): void
    {
        [, $registry, $service, $context, $method, $schemaIndex, $validator] = $this->harness();
        $cases = [
            [
                'elementor_performance_configuration',
                $this->artifact('elementor_performance_configuration', 'SOURCE_COLLECTOR', 'PARTIAL', [
                    'configuration' => [],
                    'evidence_basis' => 'observed_known_options',
                ]),
                ['configuration'],
            ],
            [
                'elementor_usage_summary',
                $this->artifact('elementor_usage_summary', 'INDEX_BUILDER', 'VERIFIED', [
                    'document_count' => 1,
                    'element_count' => 0,
                    'element_kinds' => [],
                    'widget_types' => [],
                    'responsive_declaration_count' => 0,
                    'reference_count' => 0,
                    'scores_emitted' => false,
                ]),
                ['element_kinds', 'widget_types'],
            ],
        ];
        foreach ($cases as [$componentId, $artifact, $fields]) {
            $decoded = $this->project($method, $service, $registry, $context, $componentId, $artifact);
            foreach ($fields as $field) {
                self::assertTrue(is_object($decoded->data->evidence->{$field}), $componentId . '.' . $field);
            }
            $this->assertSchemaPasses($validator, $schemaIndex, $registry, $componentId, $decoded->data);
        }

        $performance = $this->artifact('elementor_performance_configuration', 'SOURCE_COLLECTOR', 'PARTIAL', [
            'configuration' => [
                'elementor_optimized_image_loading' => '1',
                'elementor_css_print_method' => 'external',
            ],
            'evidence_basis' => 'observed_known_options',
        ]);
        $decodedPerformance = $this->project($method, $service, $registry, $context, 'elementor_performance_configuration', $performance);
        self::assertSame([
            'elementor_css_print_method' => 'external',
            'elementor_optimized_image_loading' => '1',
        ], get_object_vars($decodedPerformance->data->evidence->configuration));

        $usage = $this->artifact('elementor_usage_summary', 'INDEX_BUILDER', 'VERIFIED', [
            'document_count' => 1,
            'element_count' => 3,
            'element_kinds' => ['widget' => 2, 'container' => 1],
            'widget_types' => ['image' => 1, 'heading' => 1],
            'responsive_declaration_count' => 0,
            'reference_count' => 0,
            'scores_emitted' => false,
        ]);
        $decodedUsage = $this->project($method, $service, $registry, $context, 'elementor_usage_summary', $usage);
        self::assertSame(['container' => 1, 'widget' => 2], get_object_vars($decodedUsage->data->evidence->element_kinds));
        self::assertSame(['heading' => 1, 'image' => 1], get_object_vars($decodedUsage->data->evidence->widget_types));
    }

    public function testWrongContainersRemainRejectedAtExactPaths(): void
    {
        [, $registry, $service, $context, $method, $schemaIndex, $validator] = $this->harness();

        $feature = $this->artifact('elementor_feature_flags', 'SOURCE_COLLECTOR', 'PARTIAL', [
            'features' => ['elementor_experiment-container' => 'active'],
            'observed_count' => 1,
            'evidence_basis' => 'observed_options',
        ]);
        $featurePayload = $this->project($method, $service, $registry, $context, 'elementor_feature_flags', $feature)->data;
        $featurePayload->evidence->features = (object) ['elementor_experiment-container' => 'active'];
        $this->assertTypeFailureAt($validator, $schemaIndex, $registry, 'elementor_feature_flags', $featurePayload, '$.evidence.features');

        $performance = $this->artifact('elementor_performance_configuration', 'SOURCE_COLLECTOR', 'PARTIAL', [
            'configuration' => [],
            'evidence_basis' => 'observed_known_options',
        ]);
        $performancePayload = $this->project($method, $service, $registry, $context, 'elementor_performance_configuration', $performance)->data;
        $performancePayload->evidence->configuration = [];
        $this->assertTypeFailureAt($validator, $schemaIndex, $registry, 'elementor_performance_configuration', $performancePayload, '$.evidence.configuration');

        $usage = $this->artifact('elementor_usage_summary', 'INDEX_BUILDER', 'VERIFIED', [
            'document_count' => 1,
            'element_count' => 0,
            'element_kinds' => [],
            'widget_types' => [],
            'responsive_declaration_count' => 0,
            'reference_count' => 0,
            'scores_emitted' => false,
        ]);
        $usagePayload = $this->project($method, $service, $registry, $context, 'elementor_usage_summary', $usage)->data;
        $usagePayload->evidence->element_kinds = [];
        $this->assertTypeFailureAt($validator, $schemaIndex, $registry, 'elementor_usage_summary', $usagePayload, '$.evidence.element_kinds');
        $usagePayload = $this->project($method, $service, $registry, $context, 'elementor_usage_summary', $usage)->data;
        $usagePayload->evidence->widget_types = [];
        $this->assertTypeFailureAt($validator, $schemaIndex, $registry, 'elementor_usage_summary', $usagePayload, '$.evidence.widget_types');
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

    private function harness(): array
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
            '2026-01-01T00:00:00Z',
        );
        $method = (new \ReflectionClass($service))->getMethod('envelope');
        $method->setAccessible(true);
        $schemaIndex = json_decode((string) file_get_contents($root . 'schemas/schema-index.json'), true, 512, JSON_THROW_ON_ERROR);
        return [$root, $registry, $service, $context, $method, $schemaIndex, new JsonSchemaValidator($root)];
    }

    private function artifact(string $componentId, string $componentType, string $truthState, array $data): array
    {
        return [
            'component_id' => $componentId,
            'component_type' => $componentType,
            'source_truth_state' => $truthState,
            'source_availability' => 'AVAILABLE',
            'data' => $data,
            'diagnostics' => [],
            'source_references' => [],
            'provenance' => [],
        ];
    }

    private function project(\ReflectionMethod $method, ExportService $service, CollectorRegistry $registry, CollectionContext $context, string $componentId, array $artifact): object
    {
        $definition = $registry->definition($componentId);
        $envelope = $method->invoke($service, $definition->schemaId, $definition->schemaVersion, $componentId, $artifact, $context);
        return json_decode(CanonicalJson::encode($envelope), false, 512, JSON_THROW_ON_ERROR);
    }

    /** @param list<object> $records @return list<array{name:string,value:string}> */
    private function featureRecords(array $records): array
    {
        return array_map(
            static fn (object $record): array => ['name' => $record->name, 'value' => $record->value],
            $records,
        );
    }

    private function assertSchemaPasses(JsonSchemaValidator $validator, array $schemaIndex, CollectorRegistry $registry, string $componentId, object $payload): void
    {
        $definition = $registry->definition($componentId);
        $route = $schemaIndex['entries'][$definition->schemaId . '@' . $definition->schemaVersion];
        self::assertSame([], $validator->validate($payload, $route['payload_schema']));
    }

    private function assertTypeFailureAt(JsonSchemaValidator $validator, array $schemaIndex, CollectorRegistry $registry, string $componentId, object $payload, string $expectedPath): void
    {
        $definition = $registry->definition($componentId);
        $route = $schemaIndex['entries'][$definition->schemaId . '@' . $definition->schemaVersion];
        $errors = $validator->validate($payload, $route['payload_schema']);
        self::assertNotSame([], $errors);
        $paths = array_column($errors, 'path');
        self::assertContains($expectedPath, $paths);
        $matching = array_values(array_filter($errors, static fn (array $error): bool => ($error['path'] ?? null) === $expectedPath));
        self::assertNotSame([], $matching);
        self::assertStringContainsString('Expected', (string) ($matching[0]['message'] ?? ''));
    }
}
}
