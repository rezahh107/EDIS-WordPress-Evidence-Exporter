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

final class PerformanceConfigurationCollector implements EvidenceCollector
{
    public function id(): string { return 'elementor_performance_configuration'; }
    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $keys = ['elementor_css_print_method','elementor_optimized_image_loading','elementor_lazy_load_background_images','elementor_experiment-e_optimized_css_loading','elementor_experiment-additional_custom_breakpoints','elementor_unfiltered_files_upload','elementor_maintenance_mode_mode'];
        $options = [];
        foreach ($keys as $key) {
            if (!function_exists('get_option')) { break; }
            $value = get_option($key, null);
            if ($value !== null) { $options[$key] = is_scalar($value) ? $value : 'COMPLEX_VALUE_REDACTED'; }
        }
        ksort($options, SORT_STRING);
        return new CollectionResult($this->id(), TruthState::PARTIAL, EvidenceAvailability::AVAILABLE, ComponentType::SOURCE_COLLECTOR, ['configuration' => $options, 'evidence_basis' => 'observed_known_options'], [], [], ['collector_id' => $this->id(), 'adapter_id' => 'elementor.performance-options', 'adapter_version' => '1.0.0', 'source_kind' => 'WORDPRESS_OPTIONS', 'retrieval_strategy' => 'explicit_allowlist']);
    }
}
