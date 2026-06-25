<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\WordPress\Collectors;

use EDIS\EvidenceExporter\Domain\CollectionResult;
use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;

final class PluginInventoryCollector implements EvidenceCollector
{
    public function id(): string { return 'plugin'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        if (!function_exists('get_plugins') && defined('ABSPATH')) {
            $path = ABSPATH . 'wp-admin/includes/plugin.php';
            if (is_file($path)) { require_once $path; }
        }
        $plugins = function_exists('get_plugins') ? get_plugins() : [];
        $active = function_exists('get_option') ? (array) get_option('active_plugins', []) : [];
        $rows = [];
        foreach ($plugins as $file => $metadata) {
            $rows[] = [
                'file' => (string) $file,
                'name' => isset($metadata['Name']) ? (string) $metadata['Name'] : '',
                'version' => isset($metadata['Version']) ? (string) $metadata['Version'] : '',
                'active' => in_array($file, $active, true) || (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network($file)),
                'network_only' => !empty($metadata['Network']),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => strcmp($a['file'], $b['file']));
        return new CollectionResult($this->id(), TruthState::VERIFIED, EvidenceAvailability::AVAILABLE, ComponentType::SOURCE_COLLECTOR, ['plugins' => $rows, 'count' => count($rows)], [], [], ['collector_id' => $this->id(), 'adapter_id' => 'wordpress.plugins', 'adapter_version' => '1.0.0', 'source_kind' => 'WORDPRESS_REGISTRY', 'retrieval_strategy' => 'get_plugins']);
    }
}
