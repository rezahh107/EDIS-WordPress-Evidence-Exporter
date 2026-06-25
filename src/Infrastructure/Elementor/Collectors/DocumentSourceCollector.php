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
use EDIS\EvidenceExporter\Infrastructure\Elementor\Selection\ElementSelectionProjector;
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\DocumentIdentity;
use EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore;
use EDIS\EvidenceExporter\Infrastructure\Support\Json\LosslessJsonArrayNode;
use EDIS\EvidenceExporter\Infrastructure\Support\Json\LosslessJsonParseException;

final class DocumentSourceCollector implements EvidenceCollector
{
    private InputSnapshotStore $inputs;

    public function __construct(?InputSnapshotStore $inputs = null)
    {
        $this->inputs = $inputs ?? new InputSnapshotStore();
    }

    public function id(): string { return 'elementor_document_source'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $snapshotId = $context->stringOption('input_snapshot_id');
        $snapshotSha256 = $context->stringOption('input_snapshot_sha256');
        $provenance = [
            'collector_id' => $this->id(),
            'adapter_id' => 'elementor.saved-document',
            'adapter_version' => '1.4.0',
            'source_kind' => 'IMMUTABLE_PRIVATE_INPUT_SNAPSHOT',
            'retrieval_strategy' => 'job_bound_snapshot_with_bounded_element_projection',
            'input_snapshot_id' => $snapshotId,
            'input_snapshot_sha256' => $snapshotSha256,
        ];
        if ($context->exportScope() === 'METADATA_ONLY') {
            return new CollectionResult($this->id(), TruthState::VERIFIED, EvidenceAvailability::NOT_APPLICABLE, ComponentType::SOURCE_COLLECTOR, ['documents' => [], 'count' => 0, 'metadata_only' => true], [], [], $provenance);
        }
        if ($context->selectedDocumentIds === []) {
            return new CollectionResult($this->id(), TruthState::VERIFIED, EvidenceAvailability::INSUFFICIENT, ComponentType::SOURCE_COLLECTOR, ['documents' => [], 'count' => 0, 'metadata_only' => false], [new Diagnostic('EDIS_NO_DOCUMENTS_SELECTED', 'ERROR', 'SEMANTIC', 'diagnostic.elementor.no_documents_selected')], [], $provenance);
        }
        if ($snapshotId === '' || $snapshotSha256 === '' || !$this->inputs->verify($snapshotId, $snapshotSha256)) {
            return new CollectionResult(
                $this->id(),
                TruthState::UNKNOWN,
                EvidenceAvailability::ERROR,
                ComponentType::SOURCE_COLLECTOR,
                ['documents' => [], 'count' => 0, 'metadata_only' => false],
                [new Diagnostic('EDIS_RESUME_INPUT_MISMATCH', 'ERROR', 'SEMANTIC', 'diagnostic.export.input_snapshot_mismatch')],
                [],
                $provenance,
            );
        }

        $rows = [];
        $diagnostics = [];
        $ids = array_values(array_unique($context->selectedDocumentIds));
        sort($ids, SORT_NUMERIC);
        $projector = new ElementSelectionProjector();
        foreach ($ids as $id) {
            $captured = $this->inputs->document($snapshotId, $id);
            $raw = is_array($captured) && is_string($captured['raw_source'] ?? null) ? $captured['raw_source'] : '';
            if ($raw === '') {
                $diagnostics[] = new Diagnostic('EDIS_INPUT_SNAPSHOT_INTEGRITY_FAILED', 'ERROR', 'SEMANTIC', 'diagnostic.export.input_snapshot_integrity_failed', ['document_id' => (string) $id]);
                continue;
            }
            try {
                $losslessDocument = DocumentIdentity::decodeLossless($raw);
            } catch (LosslessJsonParseException $exception) {
                $diagnostics[] = new Diagnostic($exception->diagnosticCode, 'ERROR', 'SEMANTIC', 'diagnostic.elementor.document_source_invalid', ['document_id' => (string) $id, 'offset' => $exception->offset]);
                continue;
            } catch (\Throwable) {
                $diagnostics[] = new Diagnostic('EDIS_DOCUMENT_SOURCE_INVALID', 'ERROR', 'SEMANTIC', 'diagnostic.elementor.document_source_invalid', ['document_id' => (string) $id]);
                continue;
            }
            if (!$losslessDocument instanceof LosslessJsonArrayNode) {
                $diagnostics[] = new Diagnostic('EDIS_DOCUMENT_SOURCE_ROOT_NOT_ARRAY', 'ERROR', 'SEMANTIC', 'diagnostic.elementor.document_source_invalid', ['document_id' => (string) $id]);
                continue;
            }
            $decoded = $losslessDocument->toProcessingValue();
            if (!is_array($decoded) || !array_is_list($decoded)) {
                $diagnostics[] = new Diagnostic('EDIS_DOCUMENT_SOURCE_PROCESSING_VIEW_INVALID', 'ERROR', 'SEMANTIC', 'diagnostic.elementor.document_source_invalid', ['document_id' => (string) $id]);
                continue;
            }
            $type = is_string($captured['document_type'] ?? null) && $captured['document_type'] !== '' ? $captured['document_type'] : 'unknown';
            $settings = $captured['page_settings'] ?? [];
            if (!is_array($settings) && !$settings instanceof \stdClass) { $settings = []; }
            $documentId = (string) $id;
            $storage = 'wordpress_postmeta';
            $hashes = DocumentIdentity::sourceHashes($raw);
            $fingerprint = DocumentIdentity::fingerprint($documentId, $type, $storage);
            $fullElements = array_values(array_filter($decoded, 'is_array'));
            $projection = $projector->project($documentId, $fullElements, $context->elementSelectionForDocument($documentId));
            $projectedHash = 'sha256:' . hash('sha256', CanonicalJson::encode([
                'canonical_saved_source_sha256' => $hashes['canonical_saved_source_sha256'],
                'elements' => $projection['elements'],
                'selection_roots' => $projection['selection_roots'],
            ]));
            $record = [
                'scope' => 'DOCUMENT',
                'document_ref' => [
                    'document_id' => $documentId,
                    'document_fingerprint' => $fingerprint,
                    'document_type' => $type,
                    'post_type' => is_string($captured['post_type'] ?? null) ? $captured['post_type'] : null,
                    'source_storage_kind' => $storage,
                    'canonical_saved_source_sha256' => $hashes['canonical_saved_source_sha256'],
                ],
                'document_id' => $documentId,
                'document_type' => $type,
                'document_fingerprint' => $fingerprint,
                'source_state' => is_string($captured['post_status'] ?? null) ? $captured['post_status'] : null,
                'source_storage_kind' => $storage,
                'raw_source_representation' => is_string($captured['raw_source_representation'] ?? null) ? $captured['raw_source_representation'] : 'UNKNOWN',
                'raw_storage_bytes_sha256' => $hashes['raw_storage_bytes_sha256'],
                'canonical_saved_source_sha256' => $hashes['canonical_saved_source_sha256'],
                'saved_source_sha256' => $hashes['saved_source_sha256'],
                'projected_source_sha256' => $projectedHash,
                'input_snapshot_id' => $snapshotId,
                'input_snapshot_sha256' => $snapshotSha256,
                'input_snapshot_captured_at' => is_string($captured['snapshot_captured_at'] ?? null) ? $captured['snapshot_captured_at'] : null,
                'source_post_modified_gmt_at_snapshot' => is_string($captured['post_modified_gmt'] ?? null) ? $captured['post_modified_gmt'] : null,
                'elementor_version' => is_string($captured['elementor_version'] ?? null) ? $captured['elementor_version'] : null,
                'page_settings' => $settings instanceof \stdClass ? $settings : (object) $settings,
                'elements' => $projection['elements'],
                'selection_projection_applied' => $projection['applied'],
                'selection_roots' => $projection['selection_roots'],
                'element_projection_index' => (object) $projection['projection_index'],
                'full_source_element_count' => $projection['source_element_count'],
                'projected_element_count' => $projection['projected_element_count'],
                'projected_identified_element_count' => $projection['projected_identified_element_count'],
                'anonymous_projected_node_count' => $projection['anonymous_node_count'],
                'exported_state' => 'LAST_SAVED_SOURCE_FROM_IMMUTABLE_JOB_SNAPSHOT',
                'json_evidence_profile' => [
                    'parser' => 'EDIS-LOSSLESS-JSON-1',
                    'canonicalization' => CanonicalJson::PROFILE,
                    'duplicate_object_keys_rejected' => true,
                    'numeric_object_keys_preserved' => true,
                    'empty_object_array_distinction_preserved' => true,
                    'unicode_key_order' => CanonicalJson::OBJECT_KEY_ORDER,
                    'unicode_normalization' => CanonicalJson::UNICODE_NORMALIZATION,
                ],
            ];
            if ($context->includeOriginalDocuments && $context->privacyMode !== 'Strict') {
                if ($projection['applied']) {
                    $record['original_selected_projection'] = $projection['elements'];
                } else {
                    $record['original_saved_document'] = $losslessDocument;
                }
            }
            $rows[] = $record;
        }

        $availability = $rows === [] ? EvidenceAvailability::ERROR : ($diagnostics === [] ? EvidenceAvailability::AVAILABLE : EvidenceAvailability::PARTIAL);
        $references = array_map(static fn(array $row): array => [
            'document_id' => $row['document_id'],
            'property_path' => '_elementor_data',
            'raw_storage_bytes_sha256' => $row['raw_storage_bytes_sha256'],
            'canonical_saved_source_sha256' => $row['canonical_saved_source_sha256'],
            'projected_source_sha256' => $row['projected_source_sha256'],
            'document_fingerprint' => $row['document_fingerprint'],
            'input_snapshot_sha256' => $row['input_snapshot_sha256'],
        ], $rows);

        return new CollectionResult(
            $this->id(), TruthState::VERIFIED, $availability, ComponentType::SOURCE_COLLECTOR,
            [
                'documents' => $rows,
                'count' => count($rows),
                'original_documents_included' => $context->includeOriginalDocuments && $context->privacyMode !== 'Strict',
                'export_scope' => $context->exportScope(),
                'element_selection_scope' => $context->elementSelectionScope(),
                'input_snapshot' => [
                    'snapshot_id' => $snapshotId,
                    'snapshot_sha256' => $snapshotSha256,
                    'immutable' => true,
                    'worker_reads_live_document_source' => false,
                ],
                'hash_contract' => [
                    'raw_storage_bytes_sha256' => 'Exact _elementor_data bytes captured into the immutable job input snapshot.',
                    'canonical_saved_source_sha256' => 'EDIS-CJ-2 hash of the losslessly parsed complete saved source captured for the job.',
                    'projected_source_sha256' => 'EDIS-CJ-2 hash of the bounded selected element projection plus its complete-source hash.',
                    'saved_source_sha256' => 'Compatibility alias for canonical_saved_source_sha256 in bundle schema 3.3.0.',
                    'exported_artifact_sha256_location' => 'package-manifest.json files[].sha256',
                ],
            ],
            $diagnostics, $references, $provenance,
        );
    }
}
