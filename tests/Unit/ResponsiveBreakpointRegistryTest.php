<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Infrastructure\Elementor\Indexes\ResponsiveDeclarationIndexBuilder;
use PHPUnit\Framework\TestCase;

final class ResponsiveBreakpointRegistryTest extends TestCase
{
    public function testLegacySuffixDetectionUsesObservedBreakpointIdsOnly(): void
    {
        $context = new CollectionContext([42], false, 'analysis', 'bundle', 'Standard', [
            'export_scope' => 'SINGLE_DOCUMENT',
            'dependency_scope' => 'REQUIRED_DEPENDENCIES',
            'element_selection_scope' => 'DOCUMENT',
        ], '2026-06-15T00:00:00Z');
        $artifacts = [
            'elementor_breakpoints' => ['data' => ['breakpoints' => [
                ['id' => 'mobile'],
                ['id' => 'tablet_custom'],
            ]]],
            'elementor_document_source' => ['data' => ['documents' => [[
                'document_id' => '42',
                'page_settings' => [],
                'element_projection_index' => [],
                'elements' => [[
                    'id' => 'abc',
                    'elType' => 'container',
                    'settings' => [
                        'gap_mobile' => 8,
                        'gap_tablet_custom' => 12,
                        'gap_tablet' => 16,
                    ],
                    'elements' => [],
                ]],
            ]]]],
        ];
        $result = (new ResponsiveDeclarationIndexBuilder())->collect($context, $artifacts);
        self::assertIsArray($result->data);
        self::assertSame(['tablet_custom', 'mobile'], $result->data['registered_breakpoint_ids']);
        self::assertSame(2, $result->data['legacy_suffix_declaration_count']);
        $keys = array_column($result->data['declarations'], 'original_property_key');
        self::assertContains('gap_mobile', $keys);
        self::assertContains('gap_tablet_custom', $keys);
        self::assertFalse(in_array('gap_tablet', $keys, true));
    }
}
