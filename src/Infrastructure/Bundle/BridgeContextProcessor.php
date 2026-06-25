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
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;

final class BridgeContextProcessor implements EvidenceCollector
{
    public function id(): string { return 'bridge_source_context'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $environment = $artifacts['environment']['data'] ?? [];
        $documents = $artifacts['elementor_document_index']['data']['documents'] ?? [];
        $elements = $artifacts['elementor_element_structure_index']['data']['elements'] ?? [];
        if (!is_array($environment) || !is_array($documents)) {
            return new CollectionResult(
                $this->id(),
                TruthState::UNKNOWN,
                EvidenceAvailability::UNAVAILABLE,
                ComponentType::BUNDLE_PROCESSOR,
                null,
                [new Diagnostic('EDIS_BRIDGE_REQUIRED_INPUT_MISSING', 'ERROR', 'SEMANTIC', 'diagnostic.bridge.required_input_missing')],
            );
        }

        $selected = array_fill_keys(array_map('strval', $context->selectedDocumentIds), true);
        $strictIsolation = in_array($context->exportScope(), ['SINGLE_DOCUMENT', 'MULTIPLE_DOCUMENTS'], true)
            && $context->dependencyScope() !== 'FULL_SITE_CONTEXT';
        $documentRows = [];
        foreach ($documents as $row) {
            if (!is_array($row)) { continue; }
            $id = (string) ($row['document_id'] ?? '');
            if ($strictIsolation && !isset($selected[$id])) { continue; }
            $documentRows[] = [
                'document_id' => $id,
                'document_type' => $row['document_type'] ?? null,
                'document_fingerprint' => $row['document_fingerprint'] ?? null,
                'raw_storage_bytes_sha256' => $row['raw_storage_bytes_sha256'] ?? null,
                'canonical_saved_source_sha256' => $row['canonical_saved_source_sha256'] ?? null,
                'saved_source_sha256' => $row['saved_source_sha256'] ?? null,
                'page_locator_candidates' => $row['page_locator_candidates'] ?? [],
                'public_routability' => $row['public_routability'] ?? false,
                'source_state' => $row['source_state'] ?? null,
                'architecture_kinds' => $row['architecture_kinds'] ?? ['unknown'],
                'selected_for_export' => isset($selected[$id]),
            ];
        }
        usort($documentRows, static fn(array $a, array $b): int => strcmp((string) $a['document_id'], (string) $b['document_id']));

        $elementRows = [];
        foreach (is_array($elements) ? $elements : [] as $row) {
            if (!is_array($row)) { continue; }
            $documentId = (string) ($row['document_id'] ?? '');
            if ($strictIsolation && !isset($selected[$documentId])) { continue; }
            $elementRows[] = [
                'document_id' => $documentId,
                'document_fingerprint' => $row['document_fingerprint'] ?? null,
                'source_element_key' => $row['source_element_key'] ?? null,
                'source_record_sha256' => $row['source_record_sha256'] ?? null,
                'elementor_element_id' => $row['elementor_element_id'] ?? null,
                'id_occurrence_count' => $row['id_occurrence_count'] ?? 0,
                'id_uniqueness' => $row['id_uniqueness'] ?? 'MISSING',
                'parent_elementor_id' => $row['parent_elementor_id'] ?? null,
                'ancestor_elementor_ids' => $row['ancestor_elementor_ids'] ?? [],
                'source_path' => $row['source_path'] ?? null,
                'document_order' => $row['document_order'] ?? null,
                'element_kind' => $row['element_kind'] ?? null,
                'el_type' => $row['el_type'] ?? null,
                'widget_type' => $row['widget_type'] ?? null,
                'architecture_kind' => $row['architecture_kind'] ?? 'unknown',
            ];
        }
        usort($elementRows, static fn(array $a, array $b): int => [
            (string) $a['document_id'],
            (int) $a['document_order'],
        ] <=> [
            (string) $b['document_id'],
            (int) $b['document_order'],
        ]);

        $siteFingerprint = 'sha256:' . hash('sha256', CanonicalJson::encode([
            'home_url_sha256' => $environment['home_url_sha256'] ?? null,
            'site_url_sha256' => $environment['site_url_sha256'] ?? null,
            'multisite' => $environment['multisite'] ?? false,
            'site_path_scope' => $environment['site_path_scope'] ?? '/',
        ]));
        $siteCandidates = is_array($environment['site_locator_candidates'] ?? null) ? $environment['site_locator_candidates'] : [];
        $bridgeReady = $context->exportScope() === 'METADATA_ONLY'
            || ($documentRows !== [] && ($strictIsolation ? count($documentRows) === count($selected) : true));
        $diagnostics = [];
        if (!$bridgeReady) {
            $diagnostics[] = new Diagnostic('EDIS_BRIDGE_DOCUMENT_CONTEXT_INCOMPLETE', 'ERROR', 'SEMANTIC', 'diagnostic.bridge.document_context_incomplete', [
                'selected_document_count' => count($selected),
                'bridge_document_count' => count($documentRows),
            ]);
        }
        if ($siteCandidates === []) {
            $diagnostics[] = new Diagnostic('EDIS_BRIDGE_SITE_LOCATOR_CANDIDATES_MISSING', 'WARNING', 'SEMANTIC', 'diagnostic.bridge.site_locator_candidates_missing');
        }

        $evidence = [
            'analysis_set_id' => $context->analysisSetId,
            'wordpress_bundle_id' => $context->wordpressBundleId,
            'source_export_root_sha256' => null,
            'site_fingerprint' => $siteFingerprint,
            'url_normalization_profile' => 'EDIS-URL-1',
            'site_locator_candidates' => $siteCandidates,
            'multisite_mode' => !empty($environment['multisite']) ? 'MULTISITE' : 'SINGLE_SITE',
            'site_path_scope' => $environment['site_path_scope'] ?? '/',
            'export_scope' => $context->exportScope(),
            'dependency_scope' => $context->dependencyScope(),
            'strict_single_document_isolation' => $strictIsolation,
            'bridge_readiness' => $bridgeReady ? 'READY' : 'NOT_READY',
            'documents' => $documentRows,
            'elements' => $elementRows,
            'document_count' => count($documentRows),
            'element_count' => count($elementRows),
        ];

        $availability = $context->exportScope() === 'METADATA_ONLY'
            ? EvidenceAvailability::AVAILABLE
            : ($bridgeReady ? EvidenceAvailability::AVAILABLE : EvidenceAvailability::INSUFFICIENT);
        return new CollectionResult(
            $this->id(),
            TruthState::VERIFIED,
            $availability,
            ComponentType::BUNDLE_PROCESSOR,
            $evidence,
            $diagnostics,
            [[
                'component_id' => 'elementor_document_index',
                'source_semantic_payload_sha256' => $artifacts['elementor_document_index']['semantic_payload_sha256'] ?? null,
                'source_file_sha256_location' => 'package-manifest.json files[].sha256',
            ], [
                'component_id' => 'elementor_element_structure_index',
                'source_semantic_payload_sha256' => $artifacts['elementor_element_structure_index']['semantic_payload_sha256'] ?? null,
                'source_file_sha256_location' => 'package-manifest.json files[].sha256',
            ]],
            [
                'collector_id' => $this->id(),
                'adapter_id' => 'edis.bridge-context',
                'adapter_version' => '1.2.0',
                'source_kind' => 'DERIVED_BRIDGE_CONTEXT',
                'retrieval_strategy' => 'scope_aware_post_collection_projection',
            ],
        );
    }
}
