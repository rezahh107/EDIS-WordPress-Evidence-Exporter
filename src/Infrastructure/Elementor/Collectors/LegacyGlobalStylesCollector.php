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

final class LegacyGlobalStylesCollector implements EvidenceCollector
{
    public function id(): string { return 'elementor_legacy_global_styles'; }
    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $kit = $artifacts['elementor_kit_settings']['data']['settings'] ?? null;
        if (!is_array($kit)) {
            return new CollectionResult($this->id(), TruthState::VERIFIED, EvidenceAvailability::UNAVAILABLE, ComponentType::SOURCE_COLLECTOR, null, [new Diagnostic('EDIS_KIT_SETTINGS_DEPENDENCY_MISSING', 'WARNING', 'SEMANTIC', 'diagnostic.elementor.kit_settings_dependency_missing')]);
        }
        $keys = ['system_colors', 'custom_colors', 'system_typography', 'custom_typography', 'default_generic_fonts'];
        $data = [];
        foreach ($keys as $key) { if (array_key_exists($key, $kit)) { $data[$key] = $kit[$key]; } }
        return new CollectionResult($this->id(), TruthState::VERIFIED, $data === [] ? EvidenceAvailability::NOT_APPLICABLE : EvidenceAvailability::AVAILABLE, ComponentType::SOURCE_COLLECTOR, ['styles' => $data, 'reference_format' => 'globals/<type>?id=<global-id>'], [], [], ['collector_id' => $this->id(), 'adapter_id' => 'elementor.legacy-global-styles', 'adapter_version' => '1.0.0', 'source_kind' => 'ELEMENTOR_KIT_SETTINGS', 'retrieval_strategy' => 'extract_known_global_style_fields']);
    }
}
