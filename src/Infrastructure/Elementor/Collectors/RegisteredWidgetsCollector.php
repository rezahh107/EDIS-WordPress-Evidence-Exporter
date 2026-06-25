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

final class RegisteredWidgetsCollector implements EvidenceCollector
{
    public function id(): string { return 'elementor_registered_widgets'; }
    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $manager = class_exists('Elementor\\Plugin') && isset(\Elementor\Plugin::$instance) ? (\Elementor\Plugin::$instance->widgets_manager ?? null) : null;
        if (!is_object($manager) || !method_exists($manager, 'get_widget_types')) {
            return new CollectionResult($this->id(), TruthState::PARTIAL, EvidenceAvailability::UNAVAILABLE, ComponentType::SOURCE_COLLECTOR, null, [new Diagnostic('EDIS_WIDGET_MANAGER_UNAVAILABLE', 'WARNING', 'SEMANTIC', 'diagnostic.elementor.widget_manager_unavailable')]);
        }
        try { $types = $manager->get_widget_types(); } catch (\Throwable $e) { $types = []; }
        $rows = [];
        foreach (is_array($types) ? $types : [] as $key => $widget) {
            $name = is_object($widget) && method_exists($widget, 'get_name') ? (string) $widget->get_name() : (string) $key;
            $rows[] = ['name' => $name, 'title' => is_object($widget) && method_exists($widget, 'get_title') ? (string) $widget->get_title() : null, 'categories' => is_object($widget) && method_exists($widget, 'get_categories') ? array_values(array_map('strval', (array) $widget->get_categories())) : [], 'observed_registration' => true, 'class' => is_object($widget) ? get_class($widget) : null];
        }
        usort($rows, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
        return new CollectionResult($this->id(), TruthState::PARTIAL, EvidenceAvailability::AVAILABLE, ComponentType::SOURCE_COLLECTOR, ['widgets' => $rows, 'count' => count($rows)], [], [], ['collector_id' => $this->id(), 'adapter_id' => 'elementor.widgets-manager', 'adapter_version' => '1.0.0', 'source_kind' => 'ELEMENTOR_REGISTRY', 'retrieval_strategy' => 'get_widget_types']);
    }
}
