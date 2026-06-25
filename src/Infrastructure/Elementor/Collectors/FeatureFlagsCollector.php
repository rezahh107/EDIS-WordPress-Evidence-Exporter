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

final class FeatureFlagsCollector implements EvidenceCollector
{
    public function id(): string { return 'elementor_feature_flags'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        if (!function_exists('wp_load_alloptions')) {
            return new CollectionResult($this->id(), TruthState::PARTIAL, EvidenceAvailability::UNAVAILABLE, ComponentType::SOURCE_COLLECTOR, null, [new Diagnostic('EDIS_FEATURE_OPTIONS_UNAVAILABLE', 'WARNING', 'SEMANTIC', 'diagnostic.elementor.feature_options_unavailable')]);
        }
        $options = wp_load_alloptions();
        $flags = [];
        foreach ($options as $name => $value) {
            $key = (string) $name;
            if (!str_starts_with($key, 'elementor_experiment-') && !str_starts_with($key, 'elementor_feature-') && !str_starts_with($key, 'elementor_beta')) {
                continue;
            }
            $flags[$key] = is_scalar($value) ? (string) $value : 'COMPLEX_VALUE_REDACTED';
        }
        ksort($flags, SORT_STRING);
        return new CollectionResult($this->id(), TruthState::PARTIAL, EvidenceAvailability::AVAILABLE, ComponentType::SOURCE_COLLECTOR, ['features' => $flags, 'observed_count' => count($flags), 'evidence_basis' => 'observed_options'], [], [], ['collector_id' => $this->id(), 'adapter_id' => 'elementor.feature-options', 'adapter_version' => '1.0.0', 'source_kind' => 'WORDPRESS_OPTIONS', 'retrieval_strategy' => 'bounded_prefix_filter']);
    }
}
