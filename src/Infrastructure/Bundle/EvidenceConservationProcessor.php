<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Bundle;

use EDIS\EvidenceExporter\Domain\CollectionResult;
use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;
use EDIS\EvidenceExporter\Domain\Diagnostic;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;

final class EvidenceConservationProcessor implements EvidenceCollector
{
    public function id(): string { return 'evidence_conservation'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $documents = is_array($artifacts['elementor_document_source']['data']['documents'] ?? null)
            ? $artifacts['elementor_document_source']['data']['documents']
            : [];
        if ($documents === []) {
            return new CollectionResult(
                $this->id(),
                TruthState::VERIFIED,
                $context->exportScope() === 'METADATA_ONLY' ? EvidenceAvailability::NOT_APPLICABLE : EvidenceAvailability::INSUFFICIENT,
                ComponentType::BUNDLE_PROCESSOR,
                ['state' => 'NOT_APPLICABLE', 'checks' => (object) [], 'loss_detected' => false],
                [],
                [],
                $this->provenance(),
            );
        }

        $responsiveData = is_array($artifacts['elementor_responsive_declaration_index']['data'] ?? null)
            ? $artifacts['elementor_responsive_declaration_index']['data']
            : [];
        $legacySuffixes = array_values(array_filter((array) ($responsiveData['registered_breakpoint_ids'] ?? []), 'is_string'));
        $source = ['elements' => 0, 'atomic_variants' => 0, 'atomic_variant_properties' => 0, 'legacy_responsive' => 0, 'class_bindings' => 0];
        $scan = function (mixed $value, ?string $key = null) use (&$scan, &$source, $legacySuffixes): void {
            if (!is_array($value)) { return; }
            if (isset($value['id']) && (isset($value['elType']) || isset($value['widgetType']))) { $source['elements']++; }
            if (is_array($value['settings']['classes'] ?? null) && ($value['settings']['classes']['$$type'] ?? null) === 'classes' && is_array($value['settings']['classes']['value'] ?? null)) {
                $source['class_bindings'] += count($value['settings']['classes']['value']);
            }
            if (is_array($value['variants'] ?? null)) {
                foreach ($value['variants'] as $variant) {
                    if (!is_array($variant)) { continue; }
                    $breakpoint = $variant['meta']['breakpoint'] ?? null;
                    if (is_string($breakpoint) && $breakpoint !== '') {
                        $source['atomic_variants']++;
                        $source['atomic_variant_properties'] += is_array($variant['props'] ?? null) ? count($variant['props']) : 0;
                    }
                }
            }
            foreach ($value as $childKey => $child) {
                if (is_string($childKey)) {
                    foreach ($legacySuffixes as $suffix) {
                        if (str_ends_with($childKey, '_' . $suffix)) { $source['legacy_responsive']++; break; }
                    }
                }
                if (is_array($child)) { $scan($child, is_string($childKey) ? $childKey : null); }
            }
        };
        foreach ($documents as $document) {
            if (!is_array($document)) { continue; }
            $scan($document['elements'] ?? []);
            $scan($document['page_settings'] ?? []);
        }

        $structure = (int) ($artifacts['elementor_element_structure_index']['data']['count'] ?? 0);
        $responsive = $responsiveData;
        $references = is_array($artifacts['elementor_dynamic_references']['data']['references'] ?? null)
            ? $artifacts['elementor_dynamic_references']['data']['references']
            : [];
        $indexedClassBindings = 0;
        foreach ($references as $reference) {
            if (is_array($reference) && in_array($reference['reference_kind'] ?? null, ['LOCAL_CLASS_BINDING', 'GLOBAL_CLASS_BINDING'], true)) { $indexedClassBindings++; }
        }

        $checks = [
            'element_structure' => $this->check($source['elements'], $structure),
            'atomic_variants' => $this->check($source['atomic_variants'], (int) ($responsive['atomic_variant_count'] ?? 0)),
            'atomic_variant_properties' => $this->check($source['atomic_variant_properties'], (int) ($responsive['atomic_property_declaration_count'] ?? 0)),
            'legacy_responsive' => $this->check($source['legacy_responsive'], (int) ($responsive['legacy_suffix_declaration_count'] ?? 0)),
            'class_bindings' => $this->check($source['class_bindings'], $indexedClassBindings),
        ];
        $lossDetected = false;
        foreach ($checks as $check) { if (($check['state'] ?? '') === 'FAIL') { $lossDetected = true; break; } }
        $diagnostics = $lossDetected
            ? [new Diagnostic('EDIS_EVIDENCE_LOSS_DETECTED', 'ERROR', 'SEMANTIC', 'diagnostic.validation.evidence_loss_detected', ['failed_checks' => array_keys(array_filter($checks, static fn(array $check): bool => $check['state'] === 'FAIL'))])]
            : [];

        return new CollectionResult(
            $this->id(),
            TruthState::VERIFIED,
            $lossDetected ? EvidenceAvailability::ERROR : EvidenceAvailability::AVAILABLE,
            ComponentType::BUNDLE_PROCESSOR,
            ['state' => $lossDetected ? 'FAIL' : 'PASS', 'checks' => (object) $checks, 'loss_detected' => $lossDetected],
            $diagnostics,
            [],
            $this->provenance(),
        );
    }

    /** @return array{source_count:int,indexed_count:int,state:string} */
    private function check(int $sourceCount, int $indexedCount): array
    {
        return ['source_count' => $sourceCount, 'indexed_count' => $indexedCount, 'state' => $sourceCount === $indexedCount ? 'PASS' : 'FAIL'];
    }

    /** @return array<string,string> */
    private function provenance(): array
    {
        return [
            'collector_id' => $this->id(),
            'adapter_id' => 'edis.evidence-conservation',
            'adapter_version' => '1.1.0',
            'source_kind' => 'DERIVED_VALIDATION',
            'retrieval_strategy' => 'source_to_index_count_reconciliation_using_registered_breakpoint_ids',
        ];
    }
}
