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

final class DynamicReferencesCollector implements EvidenceCollector
{
    public function id(): string { return 'elementor_dynamic_references'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $documents = $artifacts['elementor_document_source']['data']['documents'] ?? [];
        if (!is_array($documents)) {
            return new CollectionResult(
                $this->id(),
                TruthState::PARTIAL,
                EvidenceAvailability::UNAVAILABLE,
                ComponentType::SOURCE_COLLECTOR,
                null,
                [new Diagnostic('EDIS_DOCUMENT_SOURCE_DEPENDENCY_MISSING', 'WARNING', 'SEMANTIC', 'diagnostic.elementor.document_source_dependency_missing')],
            );
        }

        $references = [];
        $kindCounts = [];

        $emit = function (array $record) use (&$references, &$kindCounts): void {
            $kind = (string) ($record['reference_kind'] ?? 'UNKNOWN');
            $references[] = $record;
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
        };

        $scanNode = function (array $node, string $path, string $documentId, array $pathMap = []) use (&$scanNode, $emit): void {
            $elementId = isset($node['id']) && is_scalar($node['id']) ? (string) $node['id'] : null;
            if ($elementId !== null && is_string($pathMap[$elementId]['source_path'] ?? null)) { $path = $pathMap[$elementId]['source_path']; }

            $classes = is_array($node['settings']['classes'] ?? null) ? $node['settings']['classes'] : null;
            if (is_array($classes) && ($classes['$$type'] ?? null) === 'classes' && is_array($classes['value'] ?? null)) {
                foreach (array_values($classes['value']) as $bindingOrder => $styleId) {
                    if (!is_scalar($styleId)) { continue; }
                    $styleIdString = (string) $styleId;
                    $localStyles = is_array($node['styles'] ?? null) ? $node['styles'] : [];
                    $isLocal = array_key_exists($styleIdString, $localStyles);
                    $emit([
                        'document_id' => $documentId,
                        'elementor_element_id' => $elementId,
                        'property_path' => $path . '.settings.classes.value[' . $bindingOrder . ']',
                        'reference_kind' => $isLocal ? 'LOCAL_CLASS_BINDING' : 'GLOBAL_CLASS_BINDING',
                        'binding_order' => $bindingOrder,
                        'target_id' => $styleIdString,
                        'target_present_in_local_styles' => $isLocal,
                        'reference_sha256' => 'sha256:' . hash('sha256', $styleIdString),
                        'raw_reference' => null,
                    ]);
                }
            }

            $scanScalar = function (mixed $value, string $currentPath) use (&$scanScalar, $emit, $documentId, $elementId): void {
                if (is_array($value)) {
                    foreach ($value as $key => $child) { $scanScalar($child, $currentPath . '.' . (string) $key); }
                    return;
                }
                if (!is_string($value) || $value === '') { return; }
                $kind = null;
                if (str_starts_with($value, 'globals/')) { $kind = 'LEGACY_GLOBAL_REFERENCE'; }
                elseif (str_contains($value, 'e-gv-')) { $kind = 'VARIABLE_REFERENCE'; }
                elseif (str_contains($value, 'var(--')) { $kind = 'CSS_CUSTOM_PROPERTY_REFERENCE'; }
                elseif (str_contains($value, '[elementor-tag') || str_contains($value, '__dynamic__')) { $kind = 'DYNAMIC_TAG_REFERENCE'; }
                if ($kind === null) { return; }
                $emit([
                    'document_id' => $documentId,
                    'elementor_element_id' => $elementId,
                    'property_path' => ltrim($currentPath, '.'),
                    'reference_kind' => $kind,
                    'binding_order' => null,
                    'target_id' => null,
                    'target_present_in_local_styles' => null,
                    'reference_sha256' => 'sha256:' . hash('sha256', $value),
                    'raw_reference' => $kind === 'LEGACY_GLOBAL_REFERENCE' ? $value : null,
                ]);
            };
            $scanScalar(is_array($node['settings'] ?? null) ? $node['settings'] : [], $path . '.settings');
            $scanScalar(is_array($node['styles'] ?? null) ? $node['styles'] : [], $path . '.styles');
            $scanScalar(is_array($node['interactions'] ?? null) ? $node['interactions'] : [], $path . '.interactions');

            foreach ((array) ($node['elements'] ?? []) as $index => $child) {
                if (is_array($child)) { $scanNode($child, $path . '.elements[' . $index . ']', $documentId, $pathMap); }
            }
        };

        foreach ($documents as $document) {
            if (!is_array($document)) { continue; }
            $documentId = (string) ($document['document_id'] ?? '');
            $pathMap=is_array($document['element_projection_index']??null)?$document['element_projection_index']:[];
            foreach ((array) ($document['elements'] ?? []) as $index => $node) {
                if (is_array($node)) { $scanNode($node, 'elements[' . $index . ']', $documentId, $pathMap); }
            }
        }

        usort($references, static fn(array $a, array $b): int => [
            (string) $a['document_id'],
            (string) ($a['elementor_element_id'] ?? ''),
            (string) $a['property_path'],
            (string) $a['reference_kind'],
            (int) ($a['binding_order'] ?? -1),
        ] <=> [
            (string) $b['document_id'],
            (string) ($b['elementor_element_id'] ?? ''),
            (string) $b['property_path'],
            (string) $b['reference_kind'],
            (int) ($b['binding_order'] ?? -1),
        ]);
        ksort($kindCounts, SORT_STRING);

        $availability = $documents === [] ? EvidenceAvailability::INSUFFICIENT : EvidenceAvailability::AVAILABLE;
        return new CollectionResult(
            $this->id(),
            TruthState::PARTIAL,
            $availability,
            ComponentType::SOURCE_COLLECTOR,
            [
                'references' => $references,
                'count' => count($references),
                'kind_counts' => (object) $kindCounts,
                'raw_sensitive_configuration_exported' => false,
                'supported_reference_kinds' => [
                    'LOCAL_CLASS_BINDING',
                    'GLOBAL_CLASS_BINDING',
                    'VARIABLE_REFERENCE',
                    'LEGACY_GLOBAL_REFERENCE',
                    'DYNAMIC_TAG_REFERENCE',
                    'CSS_CUSTOM_PROPERTY_REFERENCE',
                ],
            ],
            [],
            [],
            [
                'collector_id' => $this->id(),
                'adapter_id' => 'elementor.reference-scanner',
                'adapter_version' => '1.3.0',
                'source_kind' => 'SAVED_ELEMENTOR_DOCUMENTS',
                'retrieval_strategy' => 'typed_reference_and_class_binding_scan',
            ],
        );
    }
}
