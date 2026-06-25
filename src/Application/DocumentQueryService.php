<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Application;

use EDIS\EvidenceExporter\Infrastructure\Support\DocumentIdentity;

final class DocumentQueryService
{
    private const AUTHORIZATION_SCAN_BATCH_SIZE = 250;

    /** @var \Closure(int):bool */
    private readonly \Closure $canEditDocument;

    public function __construct(?callable $canEditDocument = null)
    {
        $this->canEditDocument = $canEditDocument !== null
            ? \Closure::fromCallable($canEditDocument)
            : static fn (int $documentId): bool => current_user_can('edit_post', $documentId);
    }

    /** @return array<string,mixed> */
    public function query(string $search, int $page, int $perPage, array $include = []): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $authorizedIds = $this->authorizedMatchingIds($search, $include);
        $total = count($authorizedIds);
        $totalPages = $total === 0 ? 0 : (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $pageIds = array_slice($authorizedIds, $offset, $perPage);

        if ($pageIds === []) {
            return [
                'items' => [],
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ];
        }

        $query = new \WP_Query(array_merge($this->baseQueryArgs($search), [
            'post__in' => $pageIds,
            'posts_per_page' => count($pageIds),
            'orderby' => 'post__in',
            'no_found_rows' => true,
        ]));

        $items = [];
        foreach ($query->posts as $post) {
            if (!$post instanceof \WP_Post || !$this->canEdit($post->ID)) {
                continue;
            }
            $raw = (string) get_post_meta($post->ID, '_elementor_data', true);
            $inspection = DocumentIdentity::inspectSource($raw);
            $hashes = $inspection['hashes'];
            $items[] = [
                'id' => $post->ID,
                'title' => get_the_title($post) ?: __('(no title)', 'edis-evidence-exporter'),
                'post_type' => $post->post_type,
                'status' => $post->post_status,
                'elementor_version' => (string) get_post_meta($post->ID, '_elementor_version', true),
                'document_type' => (string) (get_post_meta($post->ID, '_elementor_template_type', true) ?: $post->post_type),
                'architecture_kinds' => $this->architectureKinds($inspection['processing_value']),
                'canonical_saved_source_sha256' => $hashes['canonical_saved_source_sha256'],
                'saved_source_sha256' => $hashes['canonical_saved_source_sha256'],
                'edit_allowed' => true,
            ];
        }

        return [
            'items' => $items,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ];
    }

    /** Backward-compatible alias for integrations that used the original intended method name. @return array<string,mixed> */
    public function search(string $search, int $page, int $perPage, array $include = []): array
    {
        return $this->query($search, $page, $perPage, $include);
    }

    private function canEdit(int $documentId): bool
    {
        return ($this->canEditDocument)($documentId) === true;
    }

    /** @param list<int|string> $include @return list<int> */
    private function authorizedMatchingIds(string $search, array $include): array
    {
        if ($include !== []) {
            $requested = [];
            foreach ($include as $value) {
                $id = (int) $value;
                if ($id > 0 && !isset($requested[$id]) && $this->canEdit($id)) {
                    $requested[$id] = true;
                }
            }
            $requestedIds = array_keys($requested);
            if ($requestedIds === []) {
                return [];
            }

            $query = new \WP_Query(array_merge($this->baseQueryArgs($search), [
                'fields' => 'ids',
                'post__in' => $requestedIds,
                'posts_per_page' => count($requestedIds),
                'orderby' => 'post__in',
                'no_found_rows' => true,
            ]));

            $authorized = [];
            foreach ($query->posts as $value) {
                $id = (int) $value;
                if ($id > 0 && $this->canEdit($id)) {
                    $authorized[] = $id;
                }
            }
            return $authorized;
        }

        $authorized = [];
        $seen = [];
        $paged = 1;
        do {
            $query = new \WP_Query(array_merge($this->baseQueryArgs($search), [
                'fields' => 'ids',
                'posts_per_page' => self::AUTHORIZATION_SCAN_BATCH_SIZE,
                'paged' => $paged,
                'orderby' => 'ID',
                'order' => 'ASC',
                'no_found_rows' => true,
            ]));
            $ids = is_array($query->posts) ? $query->posts : [];
            foreach ($ids as $value) {
                $id = (int) $value;
                if ($id > 0 && !isset($seen[$id])) {
                    $seen[$id] = true;
                    if ($this->canEdit($id)) {
                        $authorized[] = $id;
                    }
                }
            }
            ++$paged;
        } while (count($ids) === self::AUTHORIZATION_SCAN_BATCH_SIZE);

        return $authorized;
    }

    /** @return array<string,mixed> */
    private function baseQueryArgs(string $search): array
    {
        return [
            'post_type' => 'any',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            's' => $search,
            'ignore_sticky_posts' => true,
            'meta_query' => [['key' => '_elementor_data', 'compare' => 'EXISTS']],
        ];
    }

    /** @return list<string> */
    private function architectureKinds(?array $decoded): array
    {
        if (!is_array($decoded)) {
            return ['unknown'];
        }
        $kinds = [];
        $root = array_is_list($decoded)
            ? $decoded
            : (is_array($decoded['elements'] ?? null) ? $decoded['elements'] : []);
        $stack = [$root];
        while ($stack !== []) {
            $nodes = array_pop($stack);
            if (!is_array($nodes)) {
                continue;
            }
            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }
                $elType = (string) ($node['elType'] ?? '');
                $widgetType = (string) ($node['widgetType'] ?? '');
                if (in_array($elType, ['section', 'column'], true) || $widgetType !== '') {
                    $kinds['legacy'] = true;
                }
                if ($elType === 'container') {
                    $kinds['container'] = true;
                }
                if (str_starts_with($elType, 'e-') || array_key_exists('editor_settings', $node) || array_key_exists('interactions', $node)) {
                    $kinds['atomic'] = true;
                }
                $children = $node['elements'] ?? null;
                if (is_array($children) && $children !== []) {
                    $stack[] = $children;
                }
            }
        }
        $result = array_keys($kinds);
        sort($result, SORT_STRING);
        return $result === [] ? ['unknown'] : $result;
    }
}
