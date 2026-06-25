<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Bundle;

use EDIS\EvidenceExporter\Domain\CollectionResult;
use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;

final class SelectionSnapshotProcessor implements EvidenceCollector
{
    public function id(): string { return 'selection_snapshot'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $selected = array_values(array_unique(array_map('strval', $context->selectedDocumentIds)));
        sort($selected, SORT_STRING);
        $sourceDocuments = is_array($artifacts['elementor_document_source']['data']['documents'] ?? null)
            ? $artifacts['elementor_document_source']['data']['documents']
            : [];
        $inventory = is_array($artifacts['elementor_document_inventory']['data']['documents'] ?? null)
            ? $artifacts['elementor_document_inventory']['data']['documents']
            : [];

        $sourceHashes = [];
        $included = [];
        foreach ($sourceDocuments as $document) {
            if (!is_array($document)) { continue; }
            $id = (string) ($document['document_id'] ?? '');
            if ($id === '') { continue; }
            $sourceHashes[$id] = $document['canonical_saved_source_sha256'] ?? null;
            $included[] = [
                'document_id' => $id,
                'document_fingerprint' => $document['document_fingerprint'] ?? null,
                'canonical_saved_source_sha256' => $document['canonical_saved_source_sha256'] ?? null,
                'inclusion_reason' => 'USER_SELECTED',
            ];
        }
        ksort($sourceHashes, SORT_STRING);
        usort($included, static fn(array $a, array $b): int => strcmp((string) $a['document_id'], (string) $b['document_id']));

        $inventoryIds = [];
        foreach ($inventory as $document) {
            if (is_array($document) && isset($document['document_id'])) { $inventoryIds[] = (string) $document['document_id']; }
        }
        sort($inventoryIds, SORT_STRING);

        $artifactReasons = [
            ['path_prefix' => 'sources/elementor/documents/', 'inclusion_reason' => 'USER_SELECTED_DOCUMENT_SOURCE', 'required_by_documents' => $selected],
            ['path_prefix' => 'indexes/', 'inclusion_reason' => 'DERIVED_FROM_SELECTED_DOCUMENTS', 'required_by_documents' => $selected],
            ['path_prefix' => 'sources/elementor/breakpoints.json', 'inclusion_reason' => 'REQUIRED_SHARED_CONTEXT', 'required_by_documents' => $selected],
            ['path_prefix' => 'bridge/source-context.json', 'inclusion_reason' => 'BROWSER_BINDING_CONTEXT', 'required_by_documents' => $selected],
        ];

        $availability = $context->exportScope() === 'METADATA_ONLY'
            ? EvidenceAvailability::AVAILABLE
            : ($selected === [] ? EvidenceAvailability::INSUFFICIENT : EvidenceAvailability::AVAILABLE);

        return new CollectionResult(
            $this->id(),
            TruthState::VERIFIED,
            $availability,
            ComponentType::BUNDLE_PROCESSOR,
            [
                'export_scope' => $context->exportScope(),
                'dependency_scope' => $context->dependencyScope(),
                'selection_revision' => 4,
                'selected_at' => $context->capturedAt,
                'selected_document_ids' => $selected,
                'selection_scope' => $context->elementSelectionScope(),
                'selected_elements' => $context->elementSelection(),
                'editor_unsaved_changes_detected' => $context->editorUnsavedChangesDetected(),
                'editor_unsaved_changes_state' => $context->editorUnsavedChangesState(),
                'editor_selection_source' => $context->stringOption('editor_selection_source', 'NONE'),
                'exported_state' => 'LAST_SAVED_SOURCE',
                'selected_source_hashes' => (object) $sourceHashes,
                'hash_kind' => 'CANONICAL_SAVED_SOURCE',
                'included_documents' => $included,
                'inventory_document_ids' => $inventoryIds,
                'strict_single_document_isolation' => in_array($context->exportScope(), ['SINGLE_DOCUMENT', 'MULTIPLE_DOCUMENTS'], true) && $context->dependencyScope() !== 'FULL_SITE_CONTEXT',
                'artifact_inclusion_reasons' => $artifactReasons,
                'semantic_identity' => [
                    'export_scope' => $context->exportScope(),
                    'dependency_scope' => $context->dependencyScope(),
                    'selection_revision' => 4,
                    'selected_document_ids' => $selected,
                    'selected_source_hashes' => (object) $sourceHashes,
                    'hash_kind' => 'CANONICAL_SAVED_SOURCE',
                    'selection_scope' => $context->elementSelectionScope(),
                    'selected_elements' => $context->elementSelection(),
                    'exported_state' => 'LAST_SAVED_SOURCE',
                ],
                'operational_provenance' => [
                    'selected_at' => $context->capturedAt,
                    'editor_unsaved_changes_state' => $context->editorUnsavedChangesState(),
                    'editor_selection_source' => $context->stringOption('editor_selection_source', 'NONE'),
                ],
            ],
            [],
            [],
            [
                'collector_id' => $this->id(),
                'adapter_id' => 'edis.selection-snapshot',
                'adapter_version' => '1.2.0',
                'source_kind' => 'DERIVED_SELECTION_CONTRACT',
                'retrieval_strategy' => 'immutable_context_projection',
            ],
        );
    }
}
