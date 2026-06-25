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
use EDIS\EvidenceExporter\Infrastructure\Support\DocumentWalker;

final class ElementStructureIndexBuilder implements EvidenceCollector
{
    public function id(): string { return 'elementor_element_structure_index'; }

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

        $labels = [];
        foreach ((array) ($artifacts['elementor_registered_widgets']['data']['widgets'] ?? []) as $widget) {
            if (is_array($widget) && is_string($widget['name'] ?? null) && is_string($widget['title'] ?? null)) {
                $labels[$widget['name']] = $widget['title'];
            }
        }

        $walker = new DocumentWalker();
        $records = [];
        $duplicates = [];
        $sourceReferences = [];
        foreach ($documents as $document) {
            if (!is_array($document)) { continue; }
            $documentId = (string) ($document['document_id'] ?? '');
            $fingerprint = (string) ($document['document_fingerprint'] ?? '');
            if ($fingerprint === '') {
                return new CollectionResult(
                    $this->id(),
                    TruthState::UNKNOWN,
                    EvidenceAvailability::ERROR,
                    ComponentType::INDEX_BUILDER,
                    null,
                    [new Diagnostic('EDIS_DOCUMENT_FINGERPRINT_MISSING', 'ERROR', 'SEMANTIC', 'diagnostic.elementor.document_fingerprint_missing', ['document_id' => $documentId])],
                );
            }
            $items = $walker->structure(
                $documentId,
                is_array($document['elements'] ?? null) ? $document['elements'] : [],
                $fingerprint,
                $labels,
                is_array($document['element_projection_index'] ?? null) ? $document['element_projection_index'] : [],
            );
            foreach ($items as $item) {
                if (($item['id_uniqueness'] ?? '') === 'DUPLICATE') {
                    $duplicates[] = [
                        'document_id' => $documentId,
                        'elementor_element_id' => $item['elementor_element_id'],
                        'source_path' => $item['source_path'],
                    ];
                }
                $records[] = $item;
            }
            $sourceReferences[] = [
                'component_id' => 'elementor_document_source',
                'document_id' => $documentId,
                'source_semantic_input_sha256' => $document['canonical_saved_source_sha256'] ?? null,
                'document_fingerprint' => $fingerprint,
            ];
        }

        $availability = $documents === [] ? EvidenceAvailability::INSUFFICIENT : EvidenceAvailability::AVAILABLE;
        $diagnostics = $duplicates !== []
            ? [new Diagnostic('EDIS_DUPLICATE_ELEMENTOR_IDS', 'WARNING', 'SEMANTIC', 'diagnostic.elementor.duplicate_ids', ['count' => count($duplicates)])]
            : [];

        return new CollectionResult(
            $this->id(),
            TruthState::VERIFIED,
            $availability,
            ComponentType::INDEX_BUILDER,
            [
                'elements' => $records,
                'count' => count($records),
                'duplicate_id_records' => $duplicates,
                'label_contract' => [
                    'OBSERVED' => 'Stored editor metadata',
                    'DERIVED' => 'Human-readable path generated from technical type and sibling order',
                ],
                'source_element_key_algorithm' => [
                    'version' => '1.0.0',
                    'canonicalization' => 'EDIS-CJ-2',
                    'fields' => ['architecture_kind', 'document_fingerprint', 'document_order', 'elementor_element_id', 'source_path'],
                ],
                'source_record_hash_includes_uniqueness' => true,
            ],
            $diagnostics,
            $sourceReferences,
            [
                'collector_id' => $this->id(),
                'adapter_id' => 'edis.document-walker',
                'adapter_version' => '1.3.0',
                'source_kind' => 'DERIVED_INDEX',
                'retrieval_strategy' => 'deterministic_preorder_walk_with_original_projection_paths',
            ],
        );
    }
}
