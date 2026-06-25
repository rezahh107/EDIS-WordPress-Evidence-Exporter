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

final class InstallationCollector implements EvidenceCollector
{
    public function id(): string { return 'elementor_installation'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $active = defined('ELEMENTOR_VERSION') || class_exists('Elementor\\Plugin');
        $pro = defined('ELEMENTOR_PRO_VERSION');
        $data = [
            'active' => $active,
            'elementor_version' => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : null,
            'elementor_pro_active' => $pro,
            'elementor_pro_version' => $pro ? ELEMENTOR_PRO_VERSION : null,
            'plugin_instance_available' => class_exists('Elementor\\Plugin') && isset(\Elementor\Plugin::$instance),
        ];
        $availability = $active ? EvidenceAvailability::AVAILABLE : EvidenceAvailability::UNAVAILABLE;
        $diagnostics = $active ? [] : [new Diagnostic('EDIS_ELEMENTOR_NOT_ACTIVE', 'WARNING', 'SEMANTIC', 'diagnostic.elementor.not_active')];
        return new CollectionResult($this->id(), TruthState::VERIFIED, $availability, ComponentType::SOURCE_COLLECTOR, $data, $diagnostics, [], ['collector_id' => $this->id(), 'adapter_id' => 'elementor.constants', 'adapter_version' => '1.0.0', 'source_kind' => 'ELEMENTOR_INSTALLATION', 'retrieval_strategy' => 'constants_and_plugin_instance']);
    }
}
