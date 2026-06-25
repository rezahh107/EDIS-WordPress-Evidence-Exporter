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
use EDIS\EvidenceExporter\Infrastructure\Support\DocumentIdentity;
use EDIS\EvidenceExporter\Infrastructure\Support\UrlNormalizer;

final class DocumentInventoryCollector implements EvidenceCollector
{
    public function id(): string { return 'elementor_document_inventory'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        if (!class_exists('WP_Query')) {
            return new CollectionResult(
                $this->id(),
                TruthState::VERIFIED,
                EvidenceAvailability::UNAVAILABLE,
                ComponentType::SOURCE_COLLECTOR,
                null,
                [new Diagnostic('EDIS_WP_QUERY_UNAVAILABLE', 'ERROR', 'OPERATIONAL', 'diagnostic.wordpress.query_unavailable')],
                [],
                $this->provenance(),
            );
        }

        $strictSelection = in_array($context->exportScope(), ['SINGLE_DOCUMENT', 'MULTIPLE_DOCUMENTS'], true)
            && $context->dependencyScope() !== 'FULL_SITE_CONTEXT';
        $selected = array_values(array_unique(array_map('intval', $context->selectedDocumentIds)));
        sort($selected, SORT_NUMERIC);

        $queryArgs = [
            'post_type' => 'any',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'fields' => 'ids',
        ];
        if ($strictSelection) {
            $queryArgs['post__in'] = $selected === [] ? [0] : $selected;
            $queryArgs['posts_per_page'] = max(1, count($selected));
        } else {
            $limit = $context->intOption('document_inventory_limit', 500, 1, 2000);
            $queryArgs['posts_per_page'] = $limit;
            $queryArgs['meta_query'] = [
                'relation' => 'OR',
                ['key' => '_elementor_edit_mode', 'compare' => 'EXISTS'],
                ['key' => '_elementor_data', 'compare' => 'EXISTS'],
            ];
        }

        $query = new \WP_Query($queryArgs);
        $rows = [];
        foreach ((array) $query->posts as $postId) {
            $id = (int) $postId;
            if ($id <= 0) { continue; }
            $post = get_post($id);
            if (!is_object($post)) { continue; }
            $raw = (string) get_post_meta($id, '_elementor_data', true);
            $hashes = DocumentIdentity::sourceHashes($raw);
            $url = function_exists('get_permalink') ? get_permalink($id) : false;
            $locator = is_string($url) && $url !== ''
                ? UrlNormalizer::hash($url, (string) (wp_parse_url(home_url('/'), PHP_URL_PATH) ?: '/'))
                : null;
            $type = (string) (get_post_meta($id, '_elementor_template_type', true) ?: $post->post_type);
            $documentId = (string) $id;
            $storage = 'wordpress_postmeta';
            $rows[] = [
                'document_id' => $documentId,
                'document_type' => $type,
                'post_type' => (string) $post->post_type,
                'source_state' => (string) $post->post_status,
                'title' => $context->privacyMode === 'Diagnostic' ? (string) get_the_title($id) : null,
                'public_routability' => in_array((string) $post->post_status, ['publish', 'future'], true) && $locator !== null,
                'page_locator_candidates' => $locator ? [['page_locator_sha256' => $locator, 'locator_kind' => 'PUBLIC_PERMALINK']] : [],
                'raw_storage_bytes_sha256' => $hashes['raw_storage_bytes_sha256'],
                'canonical_saved_source_sha256' => $hashes['canonical_saved_source_sha256'],
                'saved_source_sha256' => $hashes['saved_source_sha256'],
                'document_fingerprint' => DocumentIdentity::fingerprint($documentId, $type, $storage),
                'elementor_version' => (string) get_post_meta($id, '_elementor_version', true),
                'architecture_kinds' => $this->architectureKinds($raw),
                'modified_gmt' => (string) $post->post_modified_gmt,
                'selected_for_export' => in_array($id, $selected, true),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => strcmp((string) $a['document_id'], (string) $b['document_id']));

        $limitReached = !$strictSelection && isset($queryArgs['posts_per_page']) && count($rows) >= (int) $queryArgs['posts_per_page'];
        $diagnostics = $limitReached
            ? [new Diagnostic('EDIS_DOCUMENT_INVENTORY_LIMIT_REACHED', 'WARNING', 'SEMANTIC', 'diagnostic.elementor.document_inventory_limit_reached', ['limit' => (int) $queryArgs['posts_per_page']])]
            : [];

        return new CollectionResult(
            $this->id(),
            TruthState::VERIFIED,
            EvidenceAvailability::AVAILABLE,
            ComponentType::SOURCE_COLLECTOR,
            [
                'documents' => $rows,
                'count' => count($rows),
                'bounded_limit' => (int) ($queryArgs['posts_per_page'] ?? count($rows)),
                'strict_selection_isolation' => $strictSelection,
                'selected_document_ids' => array_map('strval', $selected),
                'hash_contract' => [
                    'raw_storage_bytes_sha256' => 'Exact _elementor_data storage bytes.',
                    'canonical_saved_source_sha256' => 'EDIS-CJ-2 hash of decoded saved source.',
                    'saved_source_sha256' => 'Compatibility alias for canonical_saved_source_sha256 in bundle schema 3.1.0.',
                    'exported_artifact_sha256_location' => 'package-manifest.json files[].sha256',
                ],
            ],
            $diagnostics,
            [],
            $this->provenance(),
        );
    }

    /** @return list<string> */
    private function architectureKinds(string $raw): array
    {
        $kinds = [];
        if (str_contains($raw, '"elType":"section"') || str_contains($raw, '"elType":"column"') || str_contains($raw, '"widgetType"')) { $kinds[] = 'legacy'; }
        if (str_contains($raw, '"elType":"container"')) { $kinds[] = 'container'; }
        if (str_contains($raw, '"elType":"e-') || str_contains($raw, '"editor_settings"') || str_contains($raw, '"interactions"')) { $kinds[] = 'atomic'; }
        return $kinds === [] ? ['unknown'] : array_values(array_unique($kinds));
    }

    /** @return array<string,string> */
    private function provenance(): array
    {
        return [
            'collector_id' => $this->id(),
            'adapter_id' => 'wordpress.elementor-document-query',
            'adapter_version' => '1.2.0',
            'source_kind' => 'WORDPRESS_POSTS_AND_META',
            'retrieval_strategy' => 'scope_aware_bounded_wp_query',
        ];
    }
}
