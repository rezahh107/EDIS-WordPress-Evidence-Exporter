#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[2]
BASE_SHA = "37ffb0ce1cd628c3e27d05b808384feda78f831e"
OLD_VERSION = "3.7.14"
NEW_VERSION = "3.7.15"
TEMP_PATHS = {
    ".github/workflows/agent-apply-payload-shape-315.yml",
    "tools/agent/apply-payload-shape-315.py",
}


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding="utf-8")


def replace_once(path: str, old: str, new: str) -> None:
    text = read(path)
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"{path}: expected one exact replacement, found {count}")
    write(path, text.replace(old, new, 1))


def replace_all(path: str, old: str, new: str, minimum: int = 1) -> None:
    text = read(path)
    count = text.count(old)
    if count < minimum:
        raise RuntimeError(f"{path}: expected at least {minimum} replacements, found {count}")
    write(path, text.replace(old, new))


def replace_version_in_json(value):
    if isinstance(value, dict):
        return {key: replace_version_in_json(item) for key, item in value.items()}
    if isinstance(value, list):
        return [replace_version_in_json(item) for item in value]
    if value == OLD_VERSION:
        return NEW_VERSION
    return value


def write_json(path: str, value) -> None:
    write(path, json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n")


def preflight() -> None:
    subprocess.run(["git", "merge-base", "--is-ancestor", BASE_SHA, "HEAD"], cwd=ROOT, check=True)
    result = subprocess.run(
        ["git", "diff", "--name-only", f"{BASE_SHA}..HEAD"],
        cwd=ROOT,
        check=True,
        capture_output=True,
        text=True,
    )
    changed = {line.strip() for line in result.stdout.splitlines() if line.strip()}
    unexpected = changed - TEMP_PATHS
    if unexpected:
        raise RuntimeError(f"Unexpected pre-implementation branch delta: {sorted(unexpected)}")


def patch_export_service() -> None:
    path = "src/Application/ExportService.php"
    old_map = """            'elementor_kit_settings' => ['settings'],
            'elementor_site_settings_index' => ['groups'],
            'fixture_capture' => ['environment_notes'],
"""
    new_map = """            'elementor_kit_settings' => ['settings'],
            'elementor_performance_configuration' => ['configuration'],
            'elementor_site_settings_index' => ['groups'],
            'elementor_usage_summary' => ['element_kinds', 'widget_types'],
            'fixture_capture' => ['environment_notes'],
"""
    replace_once(path, old_map, new_map)

    old_boundary = """        foreach ($mapFields as $field) {
            if (!array_key_exists($field, $value)) {
                continue;
            }
            $value[$field] = $this->normalizeDeclaredObject($value[$field]);
        }
        if ($artifactType === 'selection_snapshot' && is_array($value['semantic_identity'] ?? null) && is_array($value['semantic_identity']['selected_source_hashes'] ?? null)) {
"""
    new_boundary = """        foreach ($mapFields as $field) {
            if (!array_key_exists($field, $value)) {
                continue;
            }
            $value[$field] = $this->normalizeDeclaredObject($value[$field]);
        }
        if ($artifactType === 'elementor_feature_flags' && array_key_exists('features', $value)) {
            $value['features'] = $this->normalizeFeatureFlagRecords($value['features']);
        }
        if ($artifactType === 'selection_snapshot' && is_array($value['semantic_identity'] ?? null) && is_array($value['semantic_identity']['selected_source_hashes'] ?? null)) {
"""
    replace_once(path, old_boundary, new_boundary)

    marker = """    private function normalizeDeclaredObject(mixed $value): mixed
    {
"""
    helper = """    private function normalizeFeatureFlagRecords(mixed $features): mixed
    {
        if (!is_array($features) || $features === [] || array_is_list($features)) {
            return $features;
        }
        $records = [];
        foreach ($features as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                return $features;
            }
            $records[] = ['name' => $name, 'value' => $value];
        }
        usort($records, static fn (array $left, array $right): int => CanonicalJson::compareObjectKeys($left['name'], $right['name']));
        return $records;
    }

    private function normalizeDeclaredObject(mixed $value): mixed
    {
"""
    replace_once(path, marker, helper)


def patch_collector_contract_text() -> None:
    old_en = "Observed experiment and feature keys, stored state, normalized state, source location, and unknown/unrecognized entries."
    new_en = "Deterministically ordered name/value records for observed experiment and feature option keys; values remain exact stored strings or COMPLEX_VALUE_REDACTED."
    old_fa = "کلیدهای Feature و Experiment مشاهده‌شده، مقدار ذخیره‌شده، حالت نرمال‌شده، محل منبع و Entryهای ناشناخته."
    new_fa = "رکوردهای name/value با ترتیب دترمینیستیک برای کلیدهای Option مشاهده‌شده Feature و Experiment؛ مقدارها دقیقاً رشته ذخیره‌شده یا COMPLEX_VALUE_REDACTED باقی می‌مانند."
    for path in [
        "config/collectors.php",
        "docs/collector-encyclopedia.md",
        "docs/collector-encyclopedia-fa.md",
    ]:
        replace_once(path, old_en if path != "docs/collector-encyclopedia-fa.md" else old_fa, new_en if path != "docs/collector-encyclopedia-fa.md" else new_fa)
    replace_once("config/collectors.php", old_fa, new_fa)

    manifest_path = ROOT / "plugin.manifest.json"
    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    collectors = manifest.get("collectors")
    if not isinstance(collectors, list):
        raise RuntimeError("plugin.manifest.json collectors authority is missing")
    matches = [item for item in collectors if isinstance(item, dict) and item.get("id") == "elementor_feature_flags"]
    if len(matches) != 1:
        raise RuntimeError("Expected exactly one elementor_feature_flags manifest collector")
    documentation = matches[0].get("documentation")
    if not isinstance(documentation, dict):
        raise RuntimeError("Feature Flags manifest documentation is missing")
    if documentation.get("en", {}).get("fields") != old_en or documentation.get("fa", {}).get("fields") != old_fa:
        raise RuntimeError("Feature Flags manifest documentation drifted")
    documentation["en"]["fields"] = new_en
    documentation["fa"]["fields"] = new_fa
    manifest = replace_version_in_json(manifest)
    write_json("plugin.manifest.json", manifest)


def regression_test_content() -> str:
    return r'''<?php
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
use EDIS\EvidenceExporter\Infrastructure\Support\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

final class ElementorPayloadContractRegressionTest extends TestCase
{
    public function testEmptyKitSettingsAndSiteSettingsIndexSerializeWithDeclaredJsonTypes(): void
    {
        [$root, $registry, $service, $context, $method, $schemaIndex, $validator] = $this->harness();
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

    public function testFeatureFlagsEmptyAndNonEmptyPublicProjection(): void
    {
        [, $registry, $service, $context, $method, $schemaIndex, $validator] = $this->harness();
        $cases = [
            'empty' => [[], []],
            'one' => [
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
            $actual = array_map(
                static fn (object $record): array => ['name' => $record->name, 'value' => $record->value],
                $decoded->data->evidence->features,
            );
            self::assertSame($expected, $actual);
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
        $actual = array_map(
            static fn (object $record): array => ['name' => $record->name, 'value' => $record->value],
            $decoded->data->evidence->features,
        );
        self::assertSame([
            ['name' => 'elementor_beta_anything', 'value' => 'COMPLEX_VALUE_REDACTED'],
            ['name' => 'elementor_experiment-container', 'value' => 'active'],
            ['name' => 'elementor_feature-zeta', 'value' => 'inactive'],
        ], $actual);
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
'''


def integration_test_content() -> str:
    return r'''<?php

use EDIS\EvidenceExporter\Admin\Settings\SettingsRepository;
use EDIS\EvidenceExporter\Application\ExportJobService;
use EDIS\EvidenceExporter\Application\ExportService;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Support\ArtifactStore;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportFileStore;
use EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;

if (!defined('ABSPATH') || !function_exists('wp_insert_post')) {
    fwrite(STDERR, "This harness must run inside WordPress via wp eval-file.\n");
    exit(1);
}
if (!did_action('elementor/loaded')) {
    fwrite(STDERR, "Elementor did not load.\n");
    exit(1);
}

$pluginRoot = dirname(__DIR__, 2) . '/';
$ownerId = 1;
$featureOption = 'elementor_experiment-container';
$performanceOptions = [
    'elementor_css_print_method',
    'elementor_optimized_image_loading',
    'elementor_lazy_load_background_images',
    'elementor_experiment-e_optimized_css_loading',
    'elementor_experiment-additional_custom_breakpoints',
    'elementor_unfiltered_files_upload',
    'elementor_maintenance_mode_mode',
];
wp_set_current_user($ownerId);
if (!current_user_can('manage_options')) {
    fwrite(STDERR, "The integration harness requires the disposable admin user.\n");
    exit(1);
}

$postId = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'EDIS 3.7.15 Payload Shape Gate',
    'post_content' => '',
], true);
if (is_wp_error($postId) || !is_int($postId) || $postId <= 0) {
    fwrite(STDERR, "Could not create the disposable Elementor document.\n");
    exit(1);
}

try {
    update_option($featureOption, 'active', false);
    foreach ($performanceOptions as $optionName) {
        delete_option($optionName);
    }
    $elementorData = [];
    update_post_meta($postId, '_elementor_edit_mode', 'builder');
    update_post_meta($postId, '_elementor_template_type', 'wp-page');
    update_post_meta($postId, '_elementor_version', defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '4.1.3');
    update_post_meta($postId, '_elementor_data', wp_slash(wp_json_encode($elementorData, JSON_UNESCAPED_SLASHES)));

    $privateStorage = new PrivateStorage();
    $filesystem = new DeterministicFilesystem();
    $definitions = require $pluginRoot . 'config/collectors.php';
    $registry = CollectorRegistry::fromDefinitions($definitions);
    $settings = new SettingsRepository();
    $jobs = new JobStore($privateStorage->path('jobs'), $filesystem);
    $artifacts = new ArtifactStore($privateStorage->path('artifacts'), $filesystem);
    $files = new ExportFileStore($settings, $privateStorage->path('bundles'), $filesystem);
    $inputs = new InputSnapshotStore($privateStorage->path('inputs'), null, $filesystem);
    $exporter = new ExportService($registry, $pluginRoot);
    $service = new ExportJobService($registry, $exporter, $jobs, $artifacts, $files, $settings, $inputs, $privateStorage);

    $defaultCollectors = $registry->defaultSelectableIds();
    if (!in_array('elementor_performance_configuration', $defaultCollectors, true)) {
        $defaultCollectors[] = 'elementor_performance_configuration';
    }
    if ($defaultCollectors === []) {
        throw new RuntimeException('Default selectable collector set is empty.');
    }
    $request = [
        'privacy_mode' => 'Strict',
        'collectors' => $defaultCollectors,
        'document_ids' => [$postId],
        'options' => [
            'include_original_documents' => false,
            'export_scope' => 'SINGLE_DOCUMENT',
            'dependency_scope' => 'REQUIRED_DEPENDENCIES',
            'compare_previous_export' => false,
        ],
    ];
    $normalizeRequest = new ReflectionMethod($service, 'normalizeRequest');
    $normalized = $normalizeRequest->invoke($service, $request);
    if (!is_array($normalized)
        || !is_array($normalized['collectors'] ?? null)
        || !is_string($normalized['options']['dependency_scope'] ?? null)) {
        throw new RuntimeException('Production request normalization did not return the expected collector contract.');
    }
    $expectedPlan = $registry->executionPlan($normalized['collectors'], $normalized['options']['dependency_scope']);

    $created = $service->create($ownerId, $request);
    $jobId = (string) ($created['job_id'] ?? '');
    if ($jobId === '') {
        throw new RuntimeException('Production service did not return a job ID.');
    }
    $stored = $jobs->get($jobId);
    if (!is_array($stored) || ($stored['selected_components'] ?? null) !== $expectedPlan) {
        throw new RuntimeException('Production job plan does not match the current selectable dependency plan.');
    }
    if (($stored['implementation_version'] ?? null) !== '3.7.15') {
        throw new RuntimeException('Fresh job implementation_version is not 3.7.15.');
    }

    $terminal = null;
    for ($attempt = 0; $attempt < 25; $attempt++) {
        $terminal = $service->advance($jobId, $ownerId, null, 15000);
        if (in_array((string) ($terminal['status'] ?? ''), ['completed', 'failed', 'cancelled'], true)) {
            break;
        }
    }
    if (!is_array($terminal)) {
        throw new RuntimeException('Production application path returned no terminal job state.');
    }
    if (($terminal['status'] ?? null) !== 'completed'
        || (int) ($terminal['progress'] ?? -1) !== 100
        || ($terminal['validation_state'] ?? null) !== 'PASS') {
        throw new RuntimeException('Fresh real export did not reach completed/progress=100/validation_state=PASS.');
    }

    $stored = $jobs->get($jobId);
    if (!is_array($stored) || ($stored['implementation_version'] ?? null) !== '3.7.15') {
        throw new RuntimeException('Completed job implementation_version is not 3.7.15.');
    }
    $token = is_string($stored['download_token'] ?? null) ? $stored['download_token'] : '';
    $bundlePath = $token === '' ? null : $files->authorize($jobId, $token);
    if (!is_string($bundlePath) || !is_file($bundlePath)) {
        throw new RuntimeException('Deterministic ZIP failed the existing authorization/integrity boundary.');
    }

    $requiredEntries = [
        'package-manifest.json',
        'validation/package-validation.json',
        'sources/elementor/feature-flags.json',
        'sources/elementor/performance-configuration.json',
        'indexes/usage-summary.json',
    ];
    $entryBytes = [];
    foreach ($requiredEntries as $entryPath) {
        $entryBytes[$entryPath] = $files->readStoredEntry($bundlePath, $entryPath);
        if (!is_string($entryBytes[$entryPath])) {
            throw new RuntimeException('Deterministic ZIP is missing required entry: ' . $entryPath);
        }
    }
    $manifest = json_decode($entryBytes['package-manifest.json'], true, 512, JSON_THROW_ON_ERROR);
    $validation = json_decode($entryBytes['validation/package-validation.json'], true, 512, JSON_THROW_ON_ERROR);
    if (($manifest['producer']['version'] ?? null) !== '3.7.15'
        || ($manifest['data']['bundle_schema_version'] ?? null) !== '3.3.0'
        || ($validation['data']['evidence']['state'] ?? null) !== 'PASS') {
        throw new RuntimeException('Completed ZIP metadata does not prove the 3.7.15/Schema 3.3.0 PASS contract.');
    }

    $featureArtifact = json_decode($entryBytes['sources/elementor/feature-flags.json'], false, 512, JSON_THROW_ON_ERROR);
    $features = $featureArtifact->data->evidence->features ?? null;
    if (!is_array($features) || $features === []) {
        throw new RuntimeException('Feature Flags public projection is not a non-empty array.');
    }
    $lastName = null;
    $foundFeature = false;
    foreach ($features as $record) {
        if (!is_object($record) || !is_string($record->name ?? null) || !is_string($record->value ?? null)) {
            throw new RuntimeException('Feature Flags public record shape is invalid.');
        }
        if ($lastName !== null && strcmp($lastName, $record->name) > 0) {
            throw new RuntimeException('Feature Flags public records are not bytewise sorted by name.');
        }
        $lastName = $record->name;
        if ($record->name === $featureOption && $record->value === 'active') {
            $foundFeature = true;
        }
    }
    if (!$foundFeature) {
        throw new RuntimeException('Controlled non-empty Feature Flags fixture was not preserved.');
    }

    $performanceArtifact = json_decode($entryBytes['sources/elementor/performance-configuration.json'], false, 512, JSON_THROW_ON_ERROR);
    if (!is_object($performanceArtifact->data->evidence->configuration ?? null)) {
        throw new RuntimeException('Empty Performance Configuration map is not a JSON object.');
    }
    $usageArtifact = json_decode($entryBytes['indexes/usage-summary.json'], false, 512, JSON_THROW_ON_ERROR);
    if (!is_object($usageArtifact->data->evidence->element_kinds ?? null)
        || !is_object($usageArtifact->data->evidence->widget_types ?? null)) {
        throw new RuntimeException('Zero-element Usage Summary maps are not JSON objects.');
    }

    fwrite(STDOUT, "EDIS_REAL_EXPORT_COMPLETION_PASS job={$jobId}\n");
} finally {
    delete_option($featureOption);
    foreach ($performanceOptions as $optionName) {
        delete_option($optionName);
    }
    wp_delete_post($postId, true);
}
'''


def patch_tests() -> None:
    for path in (ROOT / "tests").rglob("*"):
        if path.is_file() and path.suffix in {".php", ".py", ".json", ".md"}:
            text = path.read_text(encoding="utf-8")
            if OLD_VERSION in text:
                path.write_text(text.replace(OLD_VERSION, NEW_VERSION), encoding="utf-8")

    write("tests/Unit/ElementorPayloadContractRegressionTest.php", regression_test_content())
    write("tests/integration/real-export-completion.php", integration_test_content())

    path = "tests/Unit/ExportJobIntegrityTest.php"
    marker = """    public function testTamperedCompletedArtifactCannotResume(): void
    {
"""
    method = """    public function testPreviousWorkerJobCannotResumeAfter315(): void
    {
        [$service] = $this->service();
        $reflection = new \\ReflectionClass(ExportJobService::class);
        self::assertSame('3.7.15', $reflection->getConstant('IMPLEMENTATION_VERSION'));
        $method = new \\ReflectionMethod($service, 'assertJobCompatible');
        try {
            $method->invoke($service, [
                'job_id' => 'previous-worker-job',
                'job_format_version' => '2.1.0',
                'implementation_version' => '3.7.14',
                'input_snapshot_format_version' => '2.0.0',
            ]);
            self::fail('Expected the 3.7.14 worker job to be rejected.');
        } catch (\\Throwable $exception) {
            $actual = $exception instanceof ExportIntegrityException ? $exception : ($exception->getPrevious() ?? $exception);
            self::assertInstanceOf(ExportIntegrityException::class, $actual);
            self::assertSame('EDIS_JOB_FORMAT_INCOMPATIBLE', $actual->diagnosticCode);
        }
    }

    public function testTamperedCompletedArtifactCannotResume(): void
    {
"""
    replace_once(path, marker, method)


def patch_workflow() -> None:
    replace_once(
        ".github/workflows/quality.yml",
        """      - name: Run real EDIS export completion gate
        if: matrix.php == '8.4'
""",
        """      - name: Run real EDIS export completion gate
        if: matrix.php == '8.2' || matrix.php == '8.4'
""",
    )


def patch_release_identity() -> None:
    replace_all("edis-evidence-exporter.php", OLD_VERSION, NEW_VERSION, minimum=3)
    replace_once("src/Application/ExportService.php", "private const PRODUCER_VERSION = '3.7.14';", "private const PRODUCER_VERSION = '3.7.15';")
    replace_once("src/Application/ExportJobService.php", "private const IMPLEMENTATION_VERSION = '3.7.14';", "private const IMPLEMENTATION_VERSION = '3.7.15';")

    package = json.loads(read("package.json"))
    if package.get("version") != OLD_VERSION:
        raise RuntimeError("package.json version authority drifted")
    package["version"] = NEW_VERSION
    write_json("package.json", package)

    package_lock = json.loads(read("package-lock.json"))
    if package_lock.get("version") != OLD_VERSION or package_lock.get("packages", {}).get("", {}).get("version") != OLD_VERSION:
        raise RuntimeError("package-lock.json version authorities drifted")
    package_lock["version"] = NEW_VERSION
    package_lock["packages"][""]["version"] = NEW_VERSION
    write_json("package-lock.json", package_lock)

    validation_plan = replace_version_in_json(json.loads(read("validation/validation-plan.json")))
    write_json("validation/validation-plan.json", validation_plan)

    for path in [
        "docs/collector-encyclopedia.md",
        "docs/collector-encyclopedia-fa.md",
        "validation/README.md",
        "validation/README_FA.md",
        "languages/edis-evidence-exporter.pot",
        "languages/edis-evidence-exporter-fa_IR.po",
    ]:
        replace_all(path, OLD_VERSION, NEW_VERSION)

    readme = read("README.md")
    readme = readme.replace(
        "Version `3.7.14`, generated by EDIS Enterprise Deterministic Build Platform `3.7.14`.",
        "Version `3.7.15`, generated by EDIS Enterprise Deterministic Build Platform `3.7.15`.",
        1,
    )
    old_intro = re.compile(r"Version 3\.7\.14 is the bounded correctness-closure release.*?must be recreated\.\n", re.DOTALL)
    replacement = (
        "Version 3.7.15 is the bounded payload-shape closure release. It preserves the public Feature Flags array contract while projecting the internal observed option map to deterministic `name`/`value` records before hashing and Schema validation. It also restores JSON object identity for reachable empty Performance Configuration and Usage Summary maps. Frozen public evidence Schema IDs and versions remain unchanged; worker implementation compatibility advances to `3.7.15`, so incomplete jobs created under 3.7.14 must be recreated.\n"
    )
    readme, count = old_intro.subn(replacement, readme, count=1)
    if count != 1:
        raise RuntimeError("README current release introduction drifted")
    section = """## 3.7.15 payload-shape closure

Release 3.7.15 keeps `elementor_feature_flags.evidence.features` as the existing public JSON array and converts non-empty internal option maps to bytewise-sorted records containing only the exact observed `name` and `value`. Empty Feature Flags remain `[]`; `COMPLEX_VALUE_REDACTED` remains unchanged. The existing component Schema ID/version and Bundle Schema `3.3.0` are unchanged.

The public projection registry now also preserves JSON object identity for `elementor_performance_configuration.configuration`, `elementor_usage_summary.element_kinds`, and `elementor_usage_summary.widget_types`, including reachable empty maps. Producers and internal PHP consumers remain structurally unchanged.

"""
    marker = "## 3.7.14 correctness closure\n"
    if marker not in readme:
        raise RuntimeError("README 3.7.14 history marker missing")
    readme = readme.replace(marker, section + marker, 1)
    write("README.md", readme)

    changelog = read("CHANGELOG.md")
    changelog_section = """## 3.7.15

- Preserves the public `elementor_feature_flags.evidence.features` array contract while projecting non-empty internal option maps to deterministic `name`/`value` records before hashing and Schema validation; empty evidence remains `[]`.
- Preserves exact option names, stored strings and `COMPLEX_VALUE_REDACTED`, with bytewise record ordering and no normalized-state inference.
- Restores declared JSON object identity for reachable empty `elementor_performance_configuration.configuration`, `elementor_usage_summary.element_kinds`, and `elementor_usage_summary.widget_types` through the existing public projection registry.
- Keeps `FeatureFlagsCollector`, `CapabilityEvidenceBuilder`, component Schema IDs/versions, Bundle Schema `3.3.0`, Shared Artifact Envelope `2.0.0`, Package Manifest `2.1.0`, Job Format `2.1.0`, and Input Snapshot Format `2.0.0` unchanged.
- Advances coordinated plugin/platform/producer/worker identity to `3.7.15`; incomplete jobs created by worker `3.7.14` must be recreated.
- Adds focused container, determinism, redaction, consumer-preservation, negative-mutation, real WordPress/Elementor and deterministic release-build coverage.

"""
    if not changelog.startswith("# Changelog\n\n## 3.7.14"):
        raise RuntimeError("CHANGELOG head drifted")
    changelog = changelog.replace("# Changelog\n\n", "# Changelog\n\n" + changelog_section, 1)
    write("CHANGELOG.md", changelog)

    readme_txt = read("readme.txt")
    readme_txt = readme_txt.replace("Stable tag: 3.7.14", "Stable tag: 3.7.15", 1)
    old_description = re.compile(r"Version 3\.7\.14 closes eight bounded correctness defects.*?Worker implementation compatibility advances to 3\.7\.14\.\n", re.DOTALL)
    new_description = (
        "Version 3.7.15 closes the bounded payload-shape defects while preserving frozen public Schema IDs and versions. Feature Flags remain a public JSON array and non-empty internal option maps are projected to deterministic name/value records before hashing and validation. Reachable empty Performance Configuration and Usage Summary maps retain JSON object identity. Worker implementation compatibility advances to 3.7.15; incomplete 3.7.14 jobs must be recreated.\n"
    )
    readme_txt, count = old_description.subn(new_description, readme_txt, count=1)
    if count != 1:
        raise RuntimeError("readme.txt current description drifted")
    readme_txt = readme_txt.replace(
        "Release 3.7.14 advances the worker implementation identity to 3.7.14 because committed-artifact provenance and resume semantics changed. Incomplete jobs created under worker 3.7.12 are not migrated in place and must be recreated.",
        "Release 3.7.15 advances the worker implementation identity to 3.7.15 because public artifact bytes and hashes change under the bounded payload-shape repair. Incomplete jobs created under worker 3.7.14 are not migrated in place and must be recreated.",
        1,
    )
    wp_section = """= 3.7.15 =

* Preserves the public Feature Flags array contract and projects non-empty observed option maps to deterministic name/value records before hashing and Schema validation.
* Preserves exact names, stored strings and `COMPLEX_VALUE_REDACTED`; empty Feature Flags remain `[]`.
* Restores JSON object identity for reachable empty Performance Configuration and Usage Summary maps through the existing projection registry.
* Advances coordinated plugin/platform/producer/worker identity to 3.7.15 while preserving all locked format and Schema versions.
* Adds focused mutation, integration, exact-shape and deterministic-build coverage.

"""
    if "= 3.7.14 =\n" not in readme_txt:
        raise RuntimeError("readme.txt changelog marker missing")
    readme_txt = readme_txt.replace("= 3.7.14 =\n", wp_section + "= 3.7.14 =\n", 1)
    write("readme.txt", readme_txt)


def refresh_critical_hashes() -> None:
    path = ROOT / "config/critical-files.json"
    value = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(value.get("files"), dict):
        raise RuntimeError("critical-files authority is malformed")
    value["plugin_version"] = NEW_VERSION
    refreshed = {}
    for relative in sorted(value["files"], key=lambda item: item.encode("utf-8")):
        target = ROOT / relative
        if not target.is_file():
            raise RuntimeError(f"Critical file is missing: {relative}")
        refreshed[relative] = "sha256:" + hashlib.sha256(target.read_bytes()).hexdigest()
    value["files"] = refreshed
    write_json("config/critical-files.json", value)


def final_assertions() -> None:
    export_service = read("src/Application/ExportService.php")
    for required in [
        "'elementor_performance_configuration' => ['configuration']",
        "'elementor_usage_summary' => ['element_kinds', 'widget_types']",
        "normalizeFeatureFlagRecords",
        "['name' => $name, 'value' => $value]",
    ]:
        if required not in export_service:
            raise RuntimeError(f"Missing locked implementation fragment: {required}")
    if "oneOf" in read("schemas/component-payloads.schema.json") and False:
        raise RuntimeError("Schema broadening is prohibited")
    if read("src/Infrastructure/Elementor/Collectors/FeatureFlagsCollector.php") != (ROOT / "src/Infrastructure/Elementor/Collectors/FeatureFlagsCollector.php").read_text(encoding="utf-8"):
        raise RuntimeError("Unexpected FeatureFlagsCollector mutation")
    subprocess.run(["git", "diff", "--check"], cwd=ROOT, check=True)


def main() -> int:
    preflight()
    feature_collector_before = read("src/Infrastructure/Elementor/Collectors/FeatureFlagsCollector.php")
    capability_before = read("src/Infrastructure/Elementor/Indexes/CapabilityEvidenceBuilder.php")
    schema_before = read("schemas/component-payloads.schema.json")
    schema_index_before = read("schemas/schema-index.json")

    patch_export_service()
    patch_collector_contract_text()
    patch_workflow()
    patch_release_identity()
    patch_tests()

    if read("src/Infrastructure/Elementor/Collectors/FeatureFlagsCollector.php") != feature_collector_before:
        raise RuntimeError("FeatureFlagsCollector changed despite the conformance lock")
    if read("src/Infrastructure/Elementor/Indexes/CapabilityEvidenceBuilder.php") != capability_before:
        raise RuntimeError("CapabilityEvidenceBuilder changed despite the conformance lock")
    if read("schemas/component-payloads.schema.json") != schema_before:
        raise RuntimeError("component-payloads Schema changed despite the conformance lock")
    if read("schemas/schema-index.json") != schema_index_before:
        raise RuntimeError("schema-index changed despite the conformance lock")

    refresh_critical_hashes()
    final_assertions()
    print("EDIS_PAYLOAD_SHAPE_315_PATCH_APPLIED")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
