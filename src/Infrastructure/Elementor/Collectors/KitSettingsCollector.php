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
use EDIS\EvidenceExporter\Infrastructure\Support\PrivacyProjection;

final class KitSettingsCollector implements EvidenceCollector
{
    private PrivacyProjection $privacy;

    public function __construct(?PrivacyProjection $privacy = null)
    {
        $this->privacy = $privacy ?? new PrivacyProjection();
    }

    public function id(): string { return 'elementor_kit_settings'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $kitId = $this->activeKitId();
        if ($kitId <= 0 || !function_exists('get_post_meta')) {
            return new CollectionResult($this->id(), TruthState::VERIFIED, EvidenceAvailability::UNAVAILABLE, ComponentType::SOURCE_COLLECTOR, null, [new Diagnostic('EDIS_KIT_SETTINGS_UNAVAILABLE', 'WARNING', 'SEMANTIC', 'diagnostic.elementor.kit_settings_unavailable')]);
        }
        $settings = get_post_meta($kitId, '_elementor_page_settings', true);
        if (!is_array($settings)) { $settings = []; }
        ksort($settings, SORT_STRING);
        $projection = $this->privacy->projectWithSummary($settings, $context->privacyMode);
        $safeSettings = is_array($projection['value'] ?? null) ? $projection['value'] : [];
        ksort($safeSettings, SORT_STRING);

        return new CollectionResult(
            $this->id(),
            TruthState::VERIFIED,
            EvidenceAvailability::AVAILABLE,
            ComponentType::SOURCE_COLLECTOR,
            [
                'kit_id' => (string) $kitId,
                'settings' => (object) $safeSettings,
                'privacy_projection' => $projection['summary'],
            ],
            [],
            [],
            [
                'collector_id' => $this->id(),
                'adapter_id' => 'elementor.kit-postmeta',
                'adapter_version' => '1.3.0',
                'source_kind' => 'WORDPRESS_POST_META',
                'retrieval_strategy' => 'get_post_meta',
                'source_document_id' => (string) $kitId,
                'source_property_path' => '_elementor_page_settings',
                'privacy_projection' => 'EDIS_PATH_AWARE_PRE_COMMIT_1',
            ]
        );
    }

    private function activeKitId(): int
    {
        $id = function_exists('get_option') ? (int) get_option('elementor_active_kit', 0) : 0;
        if ($id > 0) { return $id; }
        if (class_exists('Elementor\\Plugin') && isset(\Elementor\Plugin::$instance) && isset(\Elementor\Plugin::$instance->kits_manager) && method_exists(\Elementor\Plugin::$instance->kits_manager, 'get_active_id')) {
            return (int) \Elementor\Plugin::$instance->kits_manager->get_active_id();
        }
        return 0;
    }
}
