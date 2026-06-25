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

final class DocumentIndexBuilder implements EvidenceCollector
{
    public function id(): string { return 'elementor_document_index'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $inventory = $artifacts['elementor_document_inventory']['data']['documents'] ?? [];
        if (!is_array($inventory)) {
            return new CollectionResult(
                $this->id(),
                TruthState::VERIFIED,
                EvidenceAvailability::UNAVAILABLE,
                ComponentType::INDEX_BUILDER,
                null,
                [new Diagnostic('EDIS_DOCUMENT_INVENTORY_DEPENDENCY_MISSING', 'ERROR', 'SEMANTIC', 'diagnostic.elementor.document_inventory_dependency_missing')],
            );
        }
        $selected = array_fill_keys(array_map('strval', $context->selectedDocumentIds), true);
        $strictIsolation = in_array($context->exportScope(), ['SINGLE_DOCUMENT', 'MULTIPLE_DOCUMENTS'], true)
            && $context->dependencyScope() !== 'FULL_SITE_CONTEXT';
        $rows = [];
        foreach ($inventory as $record) {
            if (!is_array($record)) { continue; }
            $id = (string) ($record['document_id'] ?? '');
            if ($strictIsolation && !isset($selected[$id])) { continue; }
            $rows[] = [
                'document_id' => $id,
                'document_type' => $record['document_type'] ?? null,
                'document_fingerprint' => $record['document_fingerprint'] ?? null,
                'raw_storage_bytes_sha256' => $record['raw_storage_bytes_sha256'] ?? null,
                'canonical_saved_source_sha256' => $record['canonical_saved_source_sha256'] ?? null,
                'saved_source_sha256' => $record['saved_source_sha256'] ?? null,
                'source_state' => $record['source_state'] ?? null,
                'public_routability' => $record['public_routability'] ?? false,
                'page_locator_candidates' => $record['page_locator_candidates'] ?? [],
                'architecture_kinds' => $record['architecture_kinds'] ?? ['unknown'],
                'selected' => isset($selected[$id]),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => strcmp((string) $a['document_id'], (string) $b['document_id']));
        return new CollectionResult(
            $this->id(),
            TruthState::VERIFIED,
            EvidenceAvailability::AVAILABLE,
            ComponentType::INDEX_BUILDER,
            ['documents' => $rows, 'count' => count($rows), 'strict_selection_isolation' => $strictIsolation],
            [],
            [[
                'component_id' => 'elementor_document_inventory',
                'source_semantic_payload_sha256' => $artifacts['elementor_document_inventory']['semantic_payload_sha256'] ?? null,
                'source_file_sha256_location' => 'package-manifest.json files[].sha256',
            ]],
            [
                'collector_id' => $this->id(),
                'adapter_id' => 'edis.document-index',
                'adapter_version' => '1.1.0',
                'source_kind' => 'DERIVED_INDEX',
                'retrieval_strategy' => 'scope_aware_deterministic_projection',
            ],
        );
    }
}
