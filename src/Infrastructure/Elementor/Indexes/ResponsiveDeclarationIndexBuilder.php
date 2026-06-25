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

final class ResponsiveDeclarationIndexBuilder implements EvidenceCollector
{
    public function id(): string { return 'elementor_responsive_declaration_index'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $documents = $artifacts['elementor_document_source']['data']['documents'] ?? [];
        if (!is_array($documents)) {
            return new CollectionResult(
                $this->id(),
                TruthState::VERIFIED,
                EvidenceAvailability::UNAVAILABLE,
                ComponentType::INDEX_BUILDER,
                null,
                [new Diagnostic('EDIS_DOCUMENT_SOURCE_DEPENDENCY_MISSING', 'ERROR', 'SEMANTIC', 'diagnostic.elementor.document_source_dependency_missing')],
            );
        }

        $breakpointIds = $this->breakpointIds($artifacts);
        $rows = [];
        $legacyCount = 0;
        $atomicVariantCount = 0;
        $atomicPropertyCount = 0;

        $scanNode = function (array $node, string $path, string $documentId, array $pathMap = []) use (&$scanNode, &$rows, &$legacyCount, &$atomicVariantCount, &$atomicPropertyCount, $breakpointIds): void {
            $elementId = isset($node['id']) && is_scalar($node['id']) ? (string) $node['id'] : null;
            if ($elementId !== null && is_string($pathMap[$elementId]['source_path'] ?? null)) { $path = $pathMap[$elementId]['source_path']; }

            $scanLegacy = function (mixed $value, string $currentPath) use (&$scanLegacy, &$rows, &$legacyCount, $documentId, $elementId, $breakpointIds): void {
                if (!is_array($value)) { return; }
                foreach ($value as $key => $child) {
                    $keyString = (string) $key;
                    $next = $currentPath === '' ? $keyString : $currentPath . '.' . $keyString;
                    if (is_string($key)) {
                        foreach ($breakpointIds as $suffix) {
                            $needle = '_' . $suffix;
                            if (str_ends_with($key, $needle)) {
                                $rows[] = [
                                    'declaration_kind' => 'LEGACY_SUFFIX',
                                    'document_id' => $documentId,
                                    'elementor_element_id' => $elementId,
                                    'style_id' => null,
                                    'variant_index' => null,
                                    'property' => substr($key, 0, -strlen($needle)),
                                    'original_property_key' => $key,
                                    'device' => $suffix,
                                    'breakpoint_id' => $suffix,
                                    'state' => null,
                                    'saved_value' => $child,
                                    'source_path' => $next,
                                ];
                                $legacyCount++;
                                break;
                            }
                        }
                    }
                    if (is_array($child)) { $scanLegacy($child, $next); }
                }
            };
            $scanLegacy(is_array($node['settings'] ?? null) ? $node['settings'] : [], $path . '.settings');

            $styles = is_array($node['styles'] ?? null) ? $node['styles'] : [];
            foreach ($styles as $styleId => $style) {
                if (!is_array($style)) { continue; }
                $variants = is_array($style['variants'] ?? null) ? array_values($style['variants']) : [];
                foreach ($variants as $variantIndex => $variant) {
                    if (!is_array($variant)) { continue; }
                    $meta = is_array($variant['meta'] ?? null) ? $variant['meta'] : [];
                    $breakpoint = isset($meta['breakpoint']) && is_scalar($meta['breakpoint']) ? (string) $meta['breakpoint'] : null;
                    if ($breakpoint === null || $breakpoint === '') { continue; }
                    $atomicVariantCount++;
                    $props = is_array($variant['props'] ?? null) ? $variant['props'] : [];
                    foreach ($props as $property => $savedValue) {
                        $rows[] = [
                            'declaration_kind' => 'ATOMIC_STYLE_VARIANT',
                            'document_id' => $documentId,
                            'elementor_element_id' => $elementId,
                            'style_id' => (string) $styleId,
                            'variant_index' => $variantIndex,
                            'property' => (string) $property,
                            'original_property_key' => (string) $property,
                            'device' => $breakpoint,
                            'breakpoint_id' => $breakpoint,
                            'state' => isset($meta['state']) && is_scalar($meta['state']) ? (string) $meta['state'] : null,
                            'saved_value' => $savedValue,
                            'source_path' => $path . '.styles.' . (string) $styleId . '.variants[' . $variantIndex . '].props.' . (string) $property,
                        ];
                        $atomicPropertyCount++;
                    }
                }
            }

            $children = is_array($node['elements'] ?? null) ? array_values($node['elements']) : [];
            foreach ($children as $index => $child) {
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
            $pageSettings = is_array($document['page_settings'] ?? null) ? $document['page_settings'] : [];
            $scanPage = function (mixed $value, string $path) use (&$scanPage, &$rows, &$legacyCount, $documentId, $breakpointIds): void {
                if (!is_array($value)) { return; }
                foreach ($value as $key => $child) {
                    $next = $path . '.' . (string) $key;
                    if (is_string($key)) {
                        foreach ($breakpointIds as $suffix) {
                            $needle = '_' . $suffix;
                            if (str_ends_with($key, $needle)) {
                                $rows[] = [
                                    'declaration_kind' => 'LEGACY_SUFFIX',
                                    'document_id' => $documentId,
                                    'elementor_element_id' => null,
                                    'style_id' => null,
                                    'variant_index' => null,
                                    'property' => substr($key, 0, -strlen($needle)),
                                    'original_property_key' => $key,
                                    'device' => $suffix,
                                    'breakpoint_id' => $suffix,
                                    'state' => null,
                                    'saved_value' => $child,
                                    'source_path' => $next,
                                ];
                                $legacyCount++;
                                break;
                            }
                        }
                    }
                    if (is_array($child)) { $scanPage($child, $next); }
                }
            };
            $scanPage($pageSettings, 'page_settings');
        }

        usort($rows, static fn(array $a, array $b): int => [
            (string) $a['document_id'],
            (string) ($a['elementor_element_id'] ?? ''),
            (string) $a['source_path'],
            (string) $a['declaration_kind'],
        ] <=> [
            (string) $b['document_id'],
            (string) ($b['elementor_element_id'] ?? ''),
            (string) $b['source_path'],
            (string) $b['declaration_kind'],
        ]);

        $breakpoints = $artifacts['elementor_breakpoints']['data']['breakpoints'] ?? [];
        $availability = $documents === [] ? EvidenceAvailability::INSUFFICIENT : EvidenceAvailability::AVAILABLE;
        return new CollectionResult(
            $this->id(),
            TruthState::VERIFIED,
            $availability,
            ComponentType::INDEX_BUILDER,
            [
                'declarations' => $rows,
                'count' => count($rows),
                'legacy_suffix_declaration_count' => $legacyCount,
                'atomic_variant_count' => $atomicVariantCount,
                'atomic_property_declaration_count' => $atomicPropertyCount,
                'breakpoint_evidence' => is_array($breakpoints) ? $breakpoints : [],
                'inheritance_resolved' => false,
                'supported_declaration_kinds' => ['LEGACY_SUFFIX', 'ATOMIC_STYLE_VARIANT'],
                'registered_breakpoint_ids' => $breakpointIds,
                'legacy_suffix_detection_source' => 'ELEMENTOR_BREAKPOINT_MANAGER_EVIDENCE',
            ],
            [],
            [],
            [
                'collector_id' => $this->id(),
                'adapter_id' => 'edis.responsive-index',
                'adapter_version' => '1.4.0',
                'source_kind' => 'DERIVED_INDEX',
                'retrieval_strategy' => 'manager_registered_legacy_suffix_and_atomic_variant_scan',
            ],
        );
    }

    /** @param array<string,array<string,mixed>> $artifacts @return list<string> */
    private function breakpointIds(array $artifacts): array
    {
        $rows = $artifacts['elementor_breakpoints']['data']['breakpoints'] ?? [];
        $ids = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row) || !is_string($row['id'] ?? null)) {
                continue;
            }
            $id = trim($row['id']);
            if ($id !== '' && $id !== 'desktop') {
                $ids[$id] = true;
            }
        }
        $result = array_keys($ids);
        usort($result, static fn (string $left, string $right): int => [strlen($right), $left] <=> [strlen($left), $right]);
        return $result;
    }
}
