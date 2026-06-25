<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Elementor\Indexes;

use EDIS\EvidenceExporter\Domain\CollectionResult;
use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;

final class UnknownStructureLedgerBuilder implements EvidenceCollector
{
    private const KNOWN_ELEMENT_KEYS = [
        'id', 'version', 'elType', 'widgetType', 'isInner', 'settings', 'editor_settings', 'styles', 'interactions', 'elements',
    ];

    public function id(): string { return 'elementor_unknown_structure_ledger'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $documents = is_array($artifacts['elementor_document_source']['data']['documents'] ?? null)
            ? $artifacts['elementor_document_source']['data']['documents']
            : [];
        $rows = [];
        $scan = function (array $nodes, string $path, string $documentId, array $pathMap = []) use (&$scan, &$rows): void {
            foreach ($nodes as $index => $node) {
                if (!is_array($node)) { continue; }
                $fallbackPath = $path . '[' . $index . ']';
                $elementId = isset($node['id']) && is_scalar($node['id']) ? (string) $node['id'] : null;
                $nodePath = $elementId !== null && is_string($pathMap[$elementId]['source_path'] ?? null) ? $pathMap[$elementId]['source_path'] : $fallbackPath;
                foreach ($node as $key => $_value) {
                    if (!is_string($key) || in_array($key, self::KNOWN_ELEMENT_KEYS, true)) { continue; }
                    $rows[] = [
                        'document_id' => $documentId,
                        'elementor_element_id' => $elementId,
                        'source_path' => $nodePath . '.' . $key,
                        'unknown_key' => $key,
                        'preserved_in_raw_source' => true,
                        'indexed' => false,
                    ];
                }
                $children = is_array($node['elements'] ?? null) ? array_values($node['elements']) : [];
                if ($children !== []) { $scan($children, $nodePath . '.elements', $documentId, $pathMap); }
            }
        };
        foreach ($documents as $document) {
            if (!is_array($document)) { continue; }
            $pathMap=is_array($document['element_projection_index']??null)?$document['element_projection_index']:[];
            $scan((array) ($document['elements'] ?? []), 'elements', (string) ($document['document_id'] ?? ''), $pathMap);
        }
        usort($rows, static fn(array $a, array $b): int => [(string) $a['document_id'], (string) $a['source_path']] <=> [(string) $b['document_id'], (string) $b['source_path']]);
        $availability = $documents === [] ? EvidenceAvailability::NOT_APPLICABLE : EvidenceAvailability::AVAILABLE;
        return new CollectionResult(
            $this->id(),
            TruthState::PARTIAL,
            $availability,
            ComponentType::INDEX_BUILDER,
            [
                'unknown_paths' => $rows,
                'count' => count($rows),
                'known_top_level_element_keys' => self::KNOWN_ELEMENT_KEYS,
                'raw_source_preserved' => true,
                'absence_of_ledger_entries_does_not_prove_full_schema_coverage' => true,
            ],
            [],
            [],
            [
                'collector_id' => $this->id(),
                'adapter_id' => 'edis.unknown-structure-ledger',
                'adapter_version' => '1.1.0',
                'source_kind' => 'DERIVED_DIAGNOSTIC_INDEX',
                'retrieval_strategy' => 'known_element_key_comparison',
            ],
        );
    }
}
