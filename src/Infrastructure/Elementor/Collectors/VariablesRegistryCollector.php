<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Elementor\Collectors;

use EDIS\EvidenceExporter\Domain\CollectionResult;
use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;
use EDIS\EvidenceExporter\Domain\Diagnostic;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;

final class VariablesRegistryCollector implements EvidenceCollector
{
    public function id(): string { return 'elementor_variables_registry'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $kitId = $this->activeKitId();
        $keys = [];
        foreach (['Elementor\\Modules\\Variables\\Constants', 'Elementor\\Modules\\AtomicWidgets\\Styles\\Constants'] as $class) {
            $constant = $class . '::VARIABLES_META_KEY';
            if (defined($constant)) { $keys[] = (string) constant($constant); }
        }
        $keys = array_values(array_unique(array_merge($keys, ['_elementor_variables', 'elementor_variables'])));
        $provenance = [
            'collector_id' => $this->id(),
            'adapter_id' => 'elementor.variables-registry',
            'adapter_version' => '1.1.0',
            'source_kind' => 'ELEMENTOR_KIT_META',
            'retrieval_strategy' => 'official_constant_then_bounded_meta_fallback',
            'candidate_meta_keys' => $keys,
        ];
        if ($kitId <= 0 || !function_exists('get_post_meta')) {
            return new CollectionResult(
                $this->id(),
                TruthState::PARTIAL,
                EvidenceAvailability::UNAVAILABLE,
                ComponentType::SOURCE_COLLECTOR,
                ['kit_id' => $kitId > 0 ? (string) $kitId : null, 'registry' => null, 'candidate_meta_keys' => $keys],
                [new Diagnostic('EDIS_VARIABLES_KIT_UNAVAILABLE', 'WARNING', 'SEMANTIC', 'diagnostic.elementor.variables_kit_unavailable')],
                [],
                $provenance,
            );
        }
        $registry = null;
        $usedKey = null;
        foreach ($keys as $key) {
            $value = get_post_meta($kitId, $key, true);
            if (is_array($value) && $value !== []) { $registry = $value; $usedKey = $key; break; }
            if (is_string($value) && $value !== '') {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) { $registry = $decoded; $usedKey = $key; break; }
            }
        }
        if (!is_array($registry)) {
            return new CollectionResult(
                $this->id(),
                TruthState::PARTIAL,
                EvidenceAvailability::NOT_APPLICABLE,
                ComponentType::SOURCE_COLLECTOR,
                ['kit_id' => (string) $kitId, 'registry' => null, 'candidate_meta_keys' => $keys],
                [new Diagnostic('EDIS_VARIABLES_REGISTRY_NOT_PRESENT', 'INFO', 'SEMANTIC', 'diagnostic.elementor.variables_registry_not_present')],
                [],
                $provenance,
            );
        }
        return new CollectionResult(
            $this->id(),
            TruthState::PARTIAL,
            EvidenceAvailability::AVAILABLE,
            ComponentType::SOURCE_COLLECTOR,
            [
                'kit_id' => (string) $kitId,
                'meta_key' => $usedKey,
                'registry' => $registry,
                'envelope_fields' => [
                    'data' => array_key_exists('data', $registry),
                    'watermark' => array_key_exists('watermark', $registry),
                    'version' => array_key_exists('version', $registry),
                ],
            ],
            [],
            [['document_id' => (string) $kitId, 'property_path' => (string) $usedKey]],
            $provenance,
        );
    }

    private function activeKitId(): int
    {
        $id = function_exists('get_option') ? (int) get_option('elementor_active_kit', 0) : 0;
        if ($id > 0) { return $id; }
        if (class_exists('Elementor\\Plugin') && isset(\Elementor\Plugin::$instance) && isset(\Elementor\Plugin::$instance->kits_manager) && method_exists(\Elementor\Plugin::$instance->kits_manager, 'get_active_id')) {
            try { return (int) \Elementor\Plugin::$instance->kits_manager->get_active_id(); } catch (\Throwable) { return 0; }
        }
        return 0;
    }
}
