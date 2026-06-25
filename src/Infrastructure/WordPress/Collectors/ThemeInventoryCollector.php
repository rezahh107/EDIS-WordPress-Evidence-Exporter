<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\WordPress\Collectors;

use EDIS\EvidenceExporter\Domain\CollectionResult;
use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;

final class ThemeInventoryCollector implements EvidenceCollector
{
    public function id(): string { return 'theme'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $theme = function_exists('wp_get_theme') ? wp_get_theme() : null;
        $data = [
            'stylesheet' => is_object($theme) && method_exists($theme, 'get_stylesheet') ? $theme->get_stylesheet() : null,
            'template' => is_object($theme) && method_exists($theme, 'get_template') ? $theme->get_template() : null,
            'name' => is_object($theme) && method_exists($theme, 'get') ? (string) $theme->get('Name') : null,
            'version' => is_object($theme) && method_exists($theme, 'get') ? (string) $theme->get('Version') : null,
            'is_child_theme' => function_exists('is_child_theme') ? is_child_theme() : false,
        ];
        return new CollectionResult($this->id(), TruthState::VERIFIED, EvidenceAvailability::AVAILABLE, ComponentType::SOURCE_COLLECTOR, $data, [], [], ['collector_id' => $this->id(), 'adapter_id' => 'wordpress.theme', 'adapter_version' => '1.0.0', 'source_kind' => 'WORDPRESS_REGISTRY', 'retrieval_strategy' => 'wp_get_theme']);
    }
}
