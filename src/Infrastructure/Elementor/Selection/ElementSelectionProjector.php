<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Elementor\Selection;

final class ElementSelectionProjector
{
    /**
     * @param list<mixed> $elements
     * @param list<array{document_id:string,elementor_element_id:string,include_descendants:bool,selection_reason:string}> $selection
     * @return array<string,mixed>
     */
    public function project(string $documentId, array $elements, array $selection): array
    {
        $index = [];
        $sourceNodeCount = 0;
        $walk = function (array $nodes, string $path, ?string $parentId, array $ancestors) use (&$walk, &$index, &$sourceNodeCount): void {
            foreach ($nodes as $offset => $node) {
                if (!is_array($node)) { continue; }
                $sourcePath = $path . '[' . $offset . ']';
                $elementId = isset($node['id']) && is_scalar($node['id']) ? (string)$node['id'] : '';
                $entry = [
                    'node' => $node,
                    'source_path' => $sourcePath,
                    'document_order' => $sourceNodeCount,
                    'parent_elementor_id' => $parentId,
                    'ancestor_elementor_ids' => array_values($ancestors),
                ];
                if ($elementId !== '') { $index[$elementId][] = $entry; }
                $sourceNodeCount++;
                $nextAncestors = $ancestors;
                if ($elementId !== '') { $nextAncestors[] = $elementId; }
                $children = is_array($node['elements'] ?? null) ? array_values($node['elements']) : [];
                if ($children !== []) { $walk($children, $sourcePath . '.elements', $elementId !== '' ? $elementId : $parentId, $nextAncestors); }
            }
        };
        $walk(array_values($elements), 'elements', null, []);

        $selected = [];
        foreach ($selection as $item) {
            if ((string)($item['document_id'] ?? '') !== $documentId) { continue; }
            $id = (string)($item['elementor_element_id'] ?? '');
            if ($id === '' || count($index[$id] ?? []) !== 1) { continue; }
            $entry = $index[$id][0];
            $selected[$id] = [
                'elementor_element_id' => $id,
                'include_descendants' => !empty($item['include_descendants']),
                'selection_reason' => (string)($item['selection_reason'] ?? 'USER_SELECTED'),
                'document_order' => (int)$entry['document_order'],
                'source_path' => (string)$entry['source_path'],
                'parent_elementor_id' => $entry['parent_elementor_id'],
                'ancestor_elementor_ids' => $entry['ancestor_elementor_ids'],
                'node' => $entry['node'],
            ];
        }
        uasort($selected, static function(array $a, array $b): int {
            return [$a['document_order'], $a['source_path'], $a['elementor_element_id']] <=> [$b['document_order'], $b['source_path'], $b['elementor_element_id']];
        });
        if ($selected === []) {
            return [
                'elements' => array_values(array_filter($elements, 'is_array')),
                'projection_index' => [],
                'selection_roots' => [],
                'source_element_count' => $sourceNodeCount,
                'projected_element_count' => $sourceNodeCount,
                'projected_identified_element_count' => count($index),
                'anonymous_node_count' => max(0, $sourceNodeCount - count($index)),
                'applied' => false,
            ];
        }

        // Remove roots already covered by an ancestor selected with descendants.
        $roots = [];
        foreach ($selected as $id => $entry) {
            $covered = false;
            foreach ((array)$entry['ancestor_elementor_ids'] as $ancestorId) {
                if (isset($selected[(string)$ancestorId]) && !empty($selected[(string)$ancestorId]['include_descendants'])) { $covered = true; break; }
            }
            if (!$covered) { $roots[$id] = $entry; }
        }

        $clone = function(array $node, bool $includeDescendants) use (&$clone): array {
            $copy = $node;
            $children = [];
            if ($includeDescendants) {
                foreach ((array)($node['elements'] ?? []) as $child) {
                    if (is_array($child)) { $children[] = $clone($child, true); }
                }
            }
            $copy['elements'] = $children;
            return $copy;
        };

        $projectedRoots = [];
        foreach ($roots as $entry) { $projectedRoots[] = $clone($entry['node'], (bool)$entry['include_descendants']); }

        $projectionIndex = [];
        $projectedNodeCount = 0;
        $anonymousNodeCount = 0;
        $scanProjection = function(array $nodes) use (&$scanProjection, &$projectionIndex, &$projectedNodeCount, &$anonymousNodeCount, $index): void {
            foreach ($nodes as $node) {
                if (!is_array($node)) { continue; }
                $projectedNodeCount++;
                $id = isset($node['id']) && is_scalar($node['id']) ? (string)$node['id'] : '';
                if ($id === '') { $anonymousNodeCount++; }
                elseif (count($index[$id] ?? []) === 1) {
                    $entry = $index[$id][0];
                    $projectionIndex[$id] = [
                        'source_path' => $entry['source_path'],
                        'document_order' => $entry['document_order'],
                        'parent_elementor_id' => $entry['parent_elementor_id'],
                        'ancestor_elementor_ids' => $entry['ancestor_elementor_ids'],
                    ];
                }
                $children = is_array($node['elements'] ?? null) ? array_values($node['elements']) : [];
                if ($children !== []) { $scanProjection($children); }
            }
        };
        $scanProjection($projectedRoots);
        uasort($projectionIndex, static fn(array $a, array $b): int => [$a['document_order'], $a['source_path']] <=> [$b['document_order'], $b['source_path']]);

        $selectionRoots = [];
        foreach ($selected as $entry) {
            $selectionRoots[] = [
                'elementor_element_id' => $entry['elementor_element_id'],
                'source_path' => $entry['source_path'],
                'document_order' => $entry['document_order'],
                'parent_elementor_id' => $entry['parent_elementor_id'],
                'ancestor_elementor_ids' => $entry['ancestor_elementor_ids'],
                'include_descendants' => (bool)$entry['include_descendants'],
                'selection_reason' => $entry['selection_reason'],
            ];
        }

        return [
            'elements' => $projectedRoots,
            'projection_index' => $projectionIndex,
            'selection_roots' => $selectionRoots,
            'source_element_count' => $sourceNodeCount,
            'projected_element_count' => $projectedNodeCount,
            'projected_identified_element_count' => count($projectionIndex),
            'anonymous_node_count' => $anonymousNodeCount,
            'applied' => true,
        ];
    }
}
