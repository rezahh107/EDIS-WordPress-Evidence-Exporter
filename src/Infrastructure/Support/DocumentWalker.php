<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

final class DocumentWalker
{
    /** @param list<mixed> $elements @param array<string,string> $widgetLabels @param array<string,array<string,mixed>> $projectionIndex @return list<array<string,mixed>> */
    public function structure(string $documentId, array $elements, string $documentFingerprint, array $widgetLabels = [], array $projectionIndex = []): array
    {
        $records = [];
        $order = 0;
        $occurrences = [];

        $scan = function (
            array $nodes,
            string $path,
            ?string $parentId,
            array $ancestors,
            array $displayAncestors
        ) use (&$scan, &$records, &$order, &$occurrences, $documentId, $documentFingerprint, $widgetLabels, $projectionIndex): void {
            $typeCounters = [];
            foreach ($nodes as $index => $node) {
                if (!is_array($node)) { continue; }
                $fallbackPath = $path . '[' . $index . ']';
                $elementId = isset($node['id']) && is_scalar($node['id']) ? (string) $node['id'] : null;
                $projection = $elementId !== null && isset($projectionIndex[$elementId]) && is_array($projectionIndex[$elementId]) ? $projectionIndex[$elementId] : null;
                $sourcePath = is_array($projection) && is_string($projection['source_path'] ?? null) ? $projection['source_path'] : $fallbackPath;
                if ($elementId !== null && $elementId !== '') {
                    $occurrences[$elementId] = ($occurrences[$elementId] ?? 0) + 1;
                }

                $architecture = $this->architectureKind($node);
                $elType = isset($node['elType']) && is_scalar($node['elType']) ? (string) $node['elType'] : null;
                $widgetType = isset($node['widgetType']) && is_scalar($node['widgetType']) ? (string) $node['widgetType'] : null;
                $technical = $widgetType ?: ($elType ?: 'unknown');
                $typeCounters[$technical] = ($typeCounters[$technical] ?? 0) + 1;
                [$observedLabel, $observedSource] = $this->observedLabel($node);
                $registered = $widgetType !== null ? ($widgetLabels[$widgetType] ?? null) : null;
                $base = $observedLabel ?? $registered ?? $this->humanize($technical);
                $labelState = $observedLabel !== null ? 'OBSERVED' : 'DERIVED';
                $displayLabel = $observedLabel ?? ($base . ' ' . $typeCounters[$technical]);
                $displayPath = array_merge($displayAncestors, [$displayLabel]);

                $record = [
                    'document_id' => $documentId,
                    'document_fingerprint' => $documentFingerprint,
                    'elementor_element_id' => $elementId,
                    'parent_elementor_id' => is_array($projection) ? ($projection['parent_elementor_id'] ?? $parentId) : $parentId,
                    'ancestor_elementor_ids' => is_array($projection) && is_array($projection['ancestor_elementor_ids'] ?? null) ? array_values($projection['ancestor_elementor_ids']) : array_values($ancestors),
                    'source_path' => $sourcePath,
                    'document_order' => is_array($projection) && is_int($projection['document_order'] ?? null) ? $projection['document_order'] : $order,
                    'element_kind' => $this->elementKind($node),
                    'el_type' => $elType,
                    'widget_type' => $widgetType,
                    'technical_name' => $technical,
                    'registered_display_name' => $registered,
                    'editor_label' => $observedLabel,
                    'editor_label_source' => $observedSource,
                    'display_label' => $displayLabel,
                    'display_label_state' => $labelState,
                    'derived_display_path' => implode(' > ', $displayPath),
                    'architecture_kind' => $architecture,
                    'schema_version' => isset($node['version']) && is_scalar($node['version']) ? (string) $node['version'] : null,
                ];
                $record['source_element_key'] = 'sha256:' . hash('sha256', CanonicalJson::encode([
                    'architecture_kind' => $architecture,
                    'document_fingerprint' => $documentFingerprint,
                    'document_order' => $record['document_order'],
                    'elementor_element_id' => $elementId,
                    'source_path' => $sourcePath,
                ]));
                $records[] = $record;
                $order++;

                $nextAncestors = $ancestors;
                if ($elementId !== null && $elementId !== '') { $nextAncestors[] = $elementId; }
                $children = isset($node['elements']) && is_array($node['elements']) ? array_values($node['elements']) : [];
                if ($children !== []) {
                    $scan($children, $sourcePath . '.elements', $elementId ?: $parentId, $nextAncestors, $displayPath);
                }
            }
        };

        $scan($elements, 'elements', null, [], []);

        foreach ($records as &$record) {
            $id = $record['elementor_element_id'];
            $count = is_string($id) && $id !== '' ? (int) ($occurrences[$id] ?? 0) : 0;
            $record['id_occurrence_count'] = $count;
            $record['id_uniqueness'] = $count === 1 ? 'UNIQUE' : ($count > 1 ? 'DUPLICATE' : 'MISSING');
            $hashInput = $record;
            unset($hashInput['source_record_sha256']);
            $record['source_record_sha256'] = 'sha256:' . hash('sha256', CanonicalJson::encode($hashInput));
        }
        unset($record);

        return $records;
    }

    /** @param array<string,mixed> $node @return array{0:?string,1:?string} */
    private function observedLabel(array $node): array
    {
        $settings = is_array($node['settings'] ?? null) ? $node['settings'] : [];
        $editor = is_array($node['editor_settings'] ?? null) ? $node['editor_settings'] : [];
        foreach ([['_title', $settings], ['title', $editor], ['label', $editor]] as [$key, $source]) {
            if (isset($source[$key]) && is_scalar($source[$key]) && trim((string) $source[$key]) !== '') {
                return [trim((string) $source[$key]), $source === $settings ? 'settings.' . $key : 'editor_settings.' . $key];
            }
        }
        return [null, null];
    }

    private function humanize(string $value): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $value));
    }

    /** @param array<string,mixed> $node */
    private function architectureKind(array $node): string
    {
        $elType = isset($node['elType']) && is_scalar($node['elType']) ? (string) $node['elType'] : '';
        if (str_starts_with($elType, 'e-') || array_key_exists('styles', $node) || array_key_exists('editor_settings', $node) || array_key_exists('interactions', $node)) {
            return 'atomic';
        }
        if ($elType === 'container') { return 'container'; }
        if (in_array($elType, ['section', 'column', 'widget'], true)) { return 'legacy'; }
        return 'unknown';
    }

    /** @param array<string,mixed> $node */
    private function elementKind(array $node): string
    {
        $elType = isset($node['elType']) && is_scalar($node['elType']) ? (string) $node['elType'] : '';
        if (isset($node['widgetType']) && is_scalar($node['widgetType'])) { return 'widget'; }
        if (str_starts_with($elType, 'e-')) { return 'atomic_element'; }
        return $elType !== '' ? $elType : 'unknown';
    }
}
