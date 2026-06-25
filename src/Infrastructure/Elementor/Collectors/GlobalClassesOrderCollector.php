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
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\UrlNormalizer;

final class GlobalClassesOrderCollector implements EvidenceCollector
{
    public function id(): string { return 'elementor_global_classes_order'; }
    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $kitId = $this->activeKitId();
        $key = '_elementor_global_classes_order';
        $value = $kitId > 0 && function_exists('get_post_meta') ? get_post_meta($kitId, $key, true) : null;
        if (is_string($value) && $value !== '') { $decoded = json_decode($value, true); if (is_array($decoded)) { $value = $decoded; } }
        if (!is_array($value)) { $value = []; }
        $order = isset($value['order']) && is_array($value['order']) ? array_values(array_filter($value['order'], 'is_scalar')) : [];
        return new CollectionResult($this->id(), TruthState::PARTIAL, $order === [] ? EvidenceAvailability::NOT_APPLICABLE : EvidenceAvailability::AVAILABLE, ComponentType::SOURCE_COLLECTOR, ['kit_id' => $kitId > 0 ? (string) $kitId : null, 'meta_key' => $key, 'order' => array_map('strval', $order), 'raw_envelope' => $value], [], $kitId > 0 ? [['document_id' => (string) $kitId, 'property_path' => $key]] : [], ['collector_id' => $this->id(), 'adapter_id' => 'elementor.global-classes-order', 'adapter_version' => '1.0.0', 'source_kind' => 'ELEMENTOR_KIT_META', 'retrieval_strategy' => 'official_meta_key']);
    }

    private function activeKitId(): int
    {
        $id = function_exists('get_option') ? (int) get_option('elementor_active_kit', 0) : 0;
        if ($id > 0) { return $id; }
        if (class_exists('Elementor\\Plugin') && isset(\Elementor\Plugin::$instance) && isset(\Elementor\Plugin::$instance->kits_manager) && method_exists(\Elementor\Plugin::$instance->kits_manager, 'get_active_id')) {
            try { return (int) \Elementor\Plugin::$instance->kits_manager->get_active_id(); } catch (\Throwable $e) { return 0; }
        }
        return 0;
    }
}
