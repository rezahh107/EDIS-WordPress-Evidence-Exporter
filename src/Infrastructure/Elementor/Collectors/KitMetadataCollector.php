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

final class KitMetadataCollector implements EvidenceCollector
{
    public function id(): string { return 'elementor_kit_metadata'; }
    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $kitId = $this->activeKitId();
        if ($kitId <= 0) {
            return new CollectionResult($this->id(), TruthState::VERIFIED, EvidenceAvailability::UNAVAILABLE, ComponentType::SOURCE_COLLECTOR, ['active_kit_id' => null], [new Diagnostic('EDIS_ACTIVE_KIT_NOT_FOUND', 'WARNING', 'SEMANTIC', 'diagnostic.elementor.active_kit_not_found')]);
        }
        $post = function_exists('get_post') ? get_post($kitId) : null;
        $data = ['active_kit_id' => (string) $kitId, 'post_type' => is_object($post) ? (string) ($post->post_type ?? '') : null, 'post_status' => is_object($post) ? (string) ($post->post_status ?? '') : null, 'modified_gmt' => is_object($post) ? (string) ($post->post_modified_gmt ?? '') : null];
        return new CollectionResult($this->id(), TruthState::VERIFIED, EvidenceAvailability::AVAILABLE, ComponentType::SOURCE_COLLECTOR, $data, [], [['source_location' => 'wp_posts:' . $kitId]], ['collector_id' => $this->id(), 'adapter_id' => 'elementor.active-kit', 'adapter_version' => '1.0.0', 'source_kind' => 'ELEMENTOR_KIT', 'retrieval_strategy' => 'active_kit_option_or_manager']);
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
