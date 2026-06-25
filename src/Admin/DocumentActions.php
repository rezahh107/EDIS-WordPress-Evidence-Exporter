<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Admin;

use EDIS\EvidenceExporter\Infrastructure\Support\DocumentIdentity;

final class DocumentActions
{
    public function __construct(private readonly string $capability = 'edis_export_evidence')
    {
    }

    public function register(): void
    {
        add_filter('page_row_actions', [$this, 'rowActions'], 10, 2);
        add_filter('post_row_actions', [$this, 'rowActions'], 10, 2);
        foreach (['page', 'post'] as $type) {
            add_filter('bulk_actions-edit-' . $type, [$this, 'bulkActions']);
            add_filter('handle_bulk_actions-edit-' . $type, [$this, 'handleBulk'], 10, 3);
        }
    }

    /** @param array<string,string> $actions @return array<string,string> */
    public function rowActions(array $actions, \WP_Post $post): array
    {
        if (!$this->eligible($post)) {
            return $actions;
        }
        $url = add_query_arg(
            ['page' => 'edis-evidence-create', 'document_id' => $post->ID, 'export_scope' => 'SINGLE_DOCUMENT'],
            admin_url('admin.php'),
        );
        $actions['edis_evidence'] = '<a href="' . esc_url($url) . '">' . esc_html__('EDIS Evidence', 'edis-evidence-exporter') . '</a>';

        $last = get_post_meta($post->ID, '_edis_last_evidence_export', true);
        if (is_array($last)) {
            $raw = (string) get_post_meta($post->ID, '_elementor_data', true);
            $state = $this->exportState($raw, $last);
            if ($state !== null) {
                $actions['edis_evidence_status'] = '<span class="edis-row-status">' . esc_html(sprintf(
                    __('Last EDIS export: %s', 'edis-evidence-exporter'),
                    $state,
                )) . '</span>';
            }
        }
        return $actions;
    }

    /** @param array<string,mixed> $last */
    private function exportState(string $raw, array $last): ?string
    {
        $storedRaw = $last['raw_storage_bytes_sha256'] ?? null;
        if (is_string($storedRaw) && $storedRaw !== '') {
            $currentRaw = 'sha256:' . hash('sha256', $raw);
            return hash_equals($storedRaw, $currentRaw)
                ? __('current', 'edis-evidence-exporter')
                : __('stale', 'edis-evidence-exporter');
        }

        $storedCanonical = $last['canonical_saved_source_sha256'] ?? $last['saved_source_sha256'] ?? null;
        if (!is_string($storedCanonical) || $storedCanonical === '') {
            return null;
        }
        try {
            $current = DocumentIdentity::sourceHashes($raw)['canonical_saved_source_sha256'];
            if (!is_string($current) || $current === '') {
                return __('unknown', 'edis-evidence-exporter');
            }
            return hash_equals($storedCanonical, $current)
                ? __('current', 'edis-evidence-exporter')
                : __('stale', 'edis-evidence-exporter');
        } catch (\Throwable) {
            return __('unknown', 'edis-evidence-exporter');
        }
    }

    /** @param array<string,string> $actions @return array<string,string> */
    public function bulkActions(array $actions): array
    {
        if (current_user_can($this->capability)) {
            $actions['edis_evidence_export'] = __('Export EDIS Evidence', 'edis-evidence-exporter');
        }
        return $actions;
    }

    /** @param list<int|string> $postIds */
    public function handleBulk(string $redirectTo, string $action, array $postIds): string
    {
        if ($action !== 'edis_evidence_export') {
            return $redirectTo;
        }
        $ids = [];
        foreach ($postIds as $value) {
            $id = (int) $value;
            $post = get_post($id);
            if ($post instanceof \WP_Post && $this->eligible($post)) {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return add_query_arg('edis_no_eligible_documents', '1', $redirectTo);
        }
        return add_query_arg([
            'page' => 'edis-evidence-create',
            'document_ids' => implode(',', array_keys($ids)),
            'export_scope' => count($ids) === 1 ? 'SINGLE_DOCUMENT' : 'MULTIPLE_DOCUMENTS',
        ], admin_url('admin.php'));
    }

    private function eligible(\WP_Post $post): bool
    {
        if (!current_user_can($this->capability) || !current_user_can('edit_post', $post->ID)) {
            return false;
        }
        if (function_exists('metadata_exists')) {
            return metadata_exists('post', $post->ID, '_elementor_data');
        }
        return get_post_meta($post->ID, '_elementor_data', true) !== '';
    }
}
