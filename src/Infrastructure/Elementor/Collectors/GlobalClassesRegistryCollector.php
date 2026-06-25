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

final class GlobalClassesRegistryCollector implements EvidenceCollector
{
    public function id(): string { return 'elementor_global_classes_registry'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $kitId = $this->activeKitId();
        $key = '_elementor_global_classes';
        $provenance = [
            'collector_id' => $this->id(),
            'adapter_id' => 'elementor.global-classes-repository',
            'adapter_version' => '1.1.0',
            'source_kind' => 'ELEMENTOR_KIT_META',
            'retrieval_strategy' => 'official_meta_key',
            'candidate_meta_keys' => [$key],
        ];
        if ($kitId <= 0 || !function_exists('get_post_meta')) {
            return new CollectionResult(
                $this->id(),
                TruthState::PARTIAL,
                EvidenceAvailability::UNAVAILABLE,
                ComponentType::SOURCE_COLLECTOR,
                ['kit_id' => $kitId > 0 ? (string) $kitId : null, 'meta_key' => $key, 'registry' => null],
                [new Diagnostic('EDIS_GLOBAL_CLASSES_KIT_UNAVAILABLE', 'WARNING', 'SEMANTIC', 'diagnostic.elementor.global_classes_kit_unavailable')],
                [],
                $provenance,
            );
        }
        $value = get_post_meta($kitId, $key, true);
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) { $value = $decoded; }
        }
        if (!is_array($value) || $value === []) {
            return new CollectionResult(
                $this->id(),
                TruthState::PARTIAL,
                EvidenceAvailability::NOT_APPLICABLE,
                ComponentType::SOURCE_COLLECTOR,
                ['kit_id' => (string) $kitId, 'meta_key' => $key, 'registry' => null],
                [new Diagnostic('EDIS_GLOBAL_CLASSES_NOT_PRESENT', 'INFO', 'SEMANTIC', 'diagnostic.elementor.global_classes_not_present')],
                [],
                $provenance,
            );
        }
        return new CollectionResult(
            $this->id(),
            TruthState::PARTIAL,
            EvidenceAvailability::AVAILABLE,
            ComponentType::SOURCE_COLLECTOR,
            ['kit_id' => (string) $kitId, 'meta_key' => $key, 'registry' => $value],
            [],
            [['document_id' => (string) $kitId, 'property_path' => $key]],
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
