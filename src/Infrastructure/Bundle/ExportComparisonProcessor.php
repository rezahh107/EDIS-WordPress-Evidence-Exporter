<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Bundle;

use EDIS\EvidenceExporter\Domain\CollectionResult;
use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;

/**
 * Produces a bounded deterministic source-evidence comparison against the
 * previous completed EDIS export metadata stored for each selected document.
 * It never evaluates UX quality and never compares rendered browser facts.
 */
final class ExportComparisonProcessor implements EvidenceCollector
{
    public function id(): string { return 'export_comparison'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $enabled = $context->boolOption('compare_previous_export', true);
        if (!$enabled || $context->exportScope() === 'METADATA_ONLY') {
            return new CollectionResult(
                $this->id(),
                TruthState::VERIFIED,
                EvidenceAvailability::NOT_APPLICABLE,
                ComponentType::BUNDLE_PROCESSOR,
                [
                    'enabled' => $enabled,
                    'comparison_scope' => 'SOURCE_EVIDENCE_ONLY',
                    'documents' => [],
                    'document_count' => 0,
                    'scores_emitted' => false,
                ],
                [],
                [],
                $this->provenance(),
            );
        }

        $sourceDocuments = is_array($artifacts['elementor_document_source']['data']['documents'] ?? null)
            ? $artifacts['elementor_document_source']['data']['documents']
            : [];
        $currentSummaries = $this->currentSummaries($artifacts);
        $records = [];
        foreach ($sourceDocuments as $document) {
            if (!is_array($document)) { continue; }
            $documentId = (string) ($document['document_id'] ?? '');
            if ($documentId === '') { continue; }
            $previous = function_exists('get_post_meta')
                ? get_post_meta((int) $documentId, '_edis_last_evidence_export', true)
                : null;
            $previous = is_array($previous) ? $previous : [];
            $currentHash = $document['canonical_saved_source_sha256'] ?? null;
            $previousHash = $previous['canonical_saved_source_sha256'] ?? ($previous['saved_source_sha256'] ?? null);
            $previousSummary = is_array($previous['source_summary'] ?? null) ? $previous['source_summary'] : [];
            $currentSummary = $currentSummaries[$documentId] ?? $this->emptySummary();

            $state = $previousHash === null
                ? 'NO_PREVIOUS_EXPORT'
                : ($currentHash === $previousHash ? 'UNCHANGED' : 'CHANGED');
            $records[] = [
                'document_id' => $documentId,
                'comparison_state' => $state,
                'current_canonical_saved_source_sha256' => $currentHash,
                'previous_canonical_saved_source_sha256' => $previousHash,
                'previous_exported_at' => $previous['exported_at'] ?? null,
                'current_summary' => (object) $currentSummary,
                'previous_summary' => $previousSummary === [] ? (object) [] : (object) $previousSummary,
                'deltas' => (object) $this->deltas($currentSummary, $previousSummary),
            ];
        }
        usort($records, static fn(array $a, array $b): int => strcmp((string) $a['document_id'], (string) $b['document_id']));

        return new CollectionResult(
            $this->id(),
            TruthState::VERIFIED,
            $records === [] ? EvidenceAvailability::INSUFFICIENT : EvidenceAvailability::AVAILABLE,
            ComponentType::BUNDLE_PROCESSOR,
            [
                'enabled' => true,
                'comparison_scope' => 'SOURCE_EVIDENCE_ONLY',
                'documents' => $records,
                'document_count' => count($records),
                'scores_emitted' => false,
            ],
            [],
            [],
            $this->provenance(),
        );
    }

    /** @param array<string,array<string,mixed>> $artifacts @return array<string,array<string,int>> */
    private function currentSummaries(array $artifacts): array
    {
        $summaries = [];
        foreach ((array) ($artifacts['elementor_element_structure_index']['data']['elements'] ?? []) as $record) {
            if (!is_array($record)) { continue; }
            $id = (string) ($record['document_id'] ?? '');
            if ($id === '') { continue; }
            $summaries[$id] ??= $this->emptySummary();
            $summaries[$id]['element_count']++;
        }
        foreach ((array) ($artifacts['elementor_responsive_declaration_index']['data']['declarations'] ?? []) as $record) {
            if (!is_array($record)) { continue; }
            $id = (string) ($record['document_id'] ?? '');
            if ($id === '') { continue; }
            $summaries[$id] ??= $this->emptySummary();
            $summaries[$id]['responsive_declaration_count']++;
        }
        foreach ((array) ($artifacts['elementor_dynamic_references']['data']['references'] ?? []) as $record) {
            if (!is_array($record)) { continue; }
            $id = (string) ($record['document_id'] ?? '');
            if ($id === '') { continue; }
            $summaries[$id] ??= $this->emptySummary();
            $summaries[$id]['reference_count']++;
        }
        ksort($summaries, SORT_STRING);
        return $summaries;
    }

    /** @return array{element_count:int,responsive_declaration_count:int,reference_count:int} */
    private function emptySummary(): array
    {
        return ['element_count' => 0, 'responsive_declaration_count' => 0, 'reference_count' => 0];
    }

    /** @param array<string,int> $current @param array<string,mixed> $previous @return array<string,int|null> */
    private function deltas(array $current, array $previous): array
    {
        $result = [];
        foreach (array_keys($this->emptySummary()) as $key) {
            $result[$key] = isset($previous[$key]) && is_numeric($previous[$key])
                ? (int) $current[$key] - (int) $previous[$key]
                : null;
        }
        return $result;
    }

    /** @return array<string,string> */
    private function provenance(): array
    {
        return [
            'collector_id' => $this->id(),
            'adapter_id' => 'edis.previous-source-comparison',
            'adapter_version' => '1.0.0',
            'source_kind' => 'DERIVED_SOURCE_DIFF',
            'retrieval_strategy' => 'compare_current_source_hash_and_bounded_counts_to_previous_export_metadata',
        ];
    }
}
