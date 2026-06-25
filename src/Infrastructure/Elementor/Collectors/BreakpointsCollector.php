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

final class BreakpointsCollector implements EvidenceCollector
{
    public function id(): string { return 'elementor_breakpoints'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $manager = class_exists('Elementor\\Plugin') && isset(\Elementor\Plugin::$instance)
            ? (\Elementor\Plugin::$instance->breakpoints ?? null)
            : null;
        if (!is_object($manager)) {
            return new CollectionResult(
                $this->id(),
                TruthState::VERIFIED,
                EvidenceAvailability::UNAVAILABLE,
                ComponentType::SOURCE_COLLECTOR,
                null,
                [new Diagnostic('EDIS_BREAKPOINT_MANAGER_UNAVAILABLE', 'WARNING', 'SEMANTIC', 'diagnostic.elementor.breakpoint_manager_unavailable')],
            );
        }

        $method = method_exists($manager, 'get_active_breakpoints')
            ? 'get_active_breakpoints'
            : (method_exists($manager, 'get_breakpoints') ? 'get_breakpoints' : null);
        if ($method === null) {
            return new CollectionResult(
                $this->id(),
                TruthState::VERIFIED,
                EvidenceAvailability::UNAVAILABLE,
                ComponentType::SOURCE_COLLECTOR,
                null,
                [new Diagnostic('EDIS_BREAKPOINT_API_UNAVAILABLE', 'WARNING', 'SEMANTIC', 'diagnostic.elementor.breakpoint_api_unavailable')],
            );
        }

        try {
            $items = $manager->{$method}();
        } catch (\Throwable) {
            $items = [];
        }
        $rows = [];
        $order = 0;
        foreach (is_array($items) ? $items : [] as $key => $breakpoint) {
            $id = is_string($key)
                ? $key
                : (is_object($breakpoint) && method_exists($breakpoint, 'get_name') ? (string) $breakpoint->get_name() : (string) $key);
            $value = is_object($breakpoint) && method_exists($breakpoint, 'get_value') ? $breakpoint->get_value() : null;
            $label = is_object($breakpoint) && method_exists($breakpoint, 'get_label') ? (string) $breakpoint->get_label() : $id;
            $active = $method === 'get_active_breakpoints' ? true : null;
            $rows[] = [
                'id' => $id,
                'label' => $label,
                'active' => $active,
                'active_state' => $active === true ? 'OBSERVED_ACTIVE_COLLECTION' : 'UNVERIFIED_ALL_COLLECTION',
                'value' => is_numeric($value) ? (int) $value : null,
                'unit' => 'px',
                'direction' => null,
                'direction_state' => 'UNVERIFIED_FROM_PUBLIC_MANAGER_API',
                'manager_order' => $order,
                'cascade_order' => $order,
                'order_source' => 'ELEMENTOR_MANAGER_RETURN_ORDER',
                'source' => [
                    'adapter' => 'elementor_breakpoints_manager',
                    'retrieval_method' => $method,
                    'collection_scope' => $method === 'get_active_breakpoints' ? 'ACTIVE_BREAKPOINTS' : 'ALL_BREAKPOINTS',
                ],
            ];
            $order++;
        }

        return new CollectionResult(
            $this->id(),
            TruthState::VERIFIED,
            $rows === [] ? EvidenceAvailability::INSUFFICIENT : EvidenceAvailability::AVAILABLE,
            ComponentType::SOURCE_COLLECTOR,
            [
                'breakpoints' => $rows,
                'desktop_is_base' => true,
                'retrieval_method' => $method,
                'ordering_is_observed_not_inferred' => true,
                'direction_is_resolved' => false,
            ],
            [],
            [],
            [
                'collector_id' => $this->id(),
                'adapter_id' => 'elementor.breakpoints-manager',
                'adapter_version' => '1.2.0',
                'source_kind' => 'ELEMENTOR_MANAGER',
                'retrieval_strategy' => $method,
            ],
        );
    }
}
