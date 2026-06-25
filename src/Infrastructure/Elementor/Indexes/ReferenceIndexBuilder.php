<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Elementor\Indexes;

use EDIS\EvidenceExporter\Domain\CollectionResult;
use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;
use EDIS\EvidenceExporter\Domain\Diagnostic;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;

final class ReferenceIndexBuilder implements EvidenceCollector
{
    public function id(): string { return 'elementor_reference_index'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $refs = $artifacts['elementor_dynamic_references']['data']['references'] ?? [];
        if (!is_array($refs)) {
            return new CollectionResult(
                $this->id(),
                TruthState::PARTIAL,
                EvidenceAvailability::UNAVAILABLE,
                ComponentType::INDEX_BUILDER,
                null,
                [new Diagnostic('EDIS_DYNAMIC_REFERENCE_DEPENDENCY_MISSING', 'ERROR', 'SEMANTIC', 'diagnostic.elementor.dynamic_reference_dependency_missing')],
            );
        }
        $registries = [
            'variables' => [
                'availability' => $artifacts['elementor_variables_registry']['source_availability'] ?? 'UNAVAILABLE',
                'truth' => $artifacts['elementor_variables_registry']['source_truth_state'] ?? 'UNKNOWN',
            ],
            'global_classes' => [
                'availability' => $artifacts['elementor_global_classes_registry']['source_availability'] ?? 'UNAVAILABLE',
                'truth' => $artifacts['elementor_global_classes_registry']['source_truth_state'] ?? 'UNKNOWN',
            ],
            'legacy_globals' => [
                'availability' => $artifacts['elementor_legacy_global_styles']['source_availability'] ?? 'UNAVAILABLE',
                'truth' => $artifacts['elementor_legacy_global_styles']['source_truth_state'] ?? 'UNKNOWN',
            ],
        ];
        $availability = ($artifacts['elementor_dynamic_references']['source_availability'] ?? null) === 'INSUFFICIENT'
            ? EvidenceAvailability::INSUFFICIENT
            : EvidenceAvailability::AVAILABLE;
        return new CollectionResult(
            $this->id(),
            TruthState::PARTIAL,
            $availability,
            ComponentType::INDEX_BUILDER,
            [
                'references' => array_values($refs),
                'count' => count($refs),
                'registry_evidence' => $registries,
                'resolved_values_emitted' => false,
                'resolution_owner' => 'PYTHON',
            ],
            [],
            [[
                'component_id' => 'elementor_dynamic_references',
                'source_semantic_payload_sha256' => $artifacts['elementor_dynamic_references']['semantic_payload_sha256'] ?? null,
                'source_file_sha256_location' => 'package-manifest.json files[].sha256',
            ]],
            [
                'collector_id' => $this->id(),
                'adapter_id' => 'edis.reference-index',
                'adapter_version' => '1.1.0',
                'source_kind' => 'DERIVED_INDEX',
                'retrieval_strategy' => 'deterministic_reference_projection',
            ],
        );
    }
}
