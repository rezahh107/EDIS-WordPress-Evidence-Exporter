<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ElementorInspectorContractTest extends TestCase
{
    public function testInspectorUsesOfficialEditorAndViewAwareContextMenuHooks(): void
    {
        $root = dirname(__DIR__, 2);
        $module = file_get_contents($root . '/src/Elementor/InspectorModule.php');
        $script = file_get_contents($root . '/assets/js/elementor-inspector.js');
        $bootstrap = file_get_contents($root . '/src/Bootstrap.php');
        $main = file_get_contents($root . '/edis-evidence-exporter.php');
        self::assertIsString($module);
        self::assertIsString($script);
        self::assertStringContainsString('elementor/editor/after_enqueue_scripts', $module);
        self::assertStringContainsString('elementor/editor/after_enqueue_styles', $module);
        self::assertStringContainsString('elements/${elementType}/contextMenuGroups', $script);
        self::assertStringContainsString("'elements/context-menu/groups'", $script);
        self::assertStringContainsString('(groups, elementType, maybeView)', $script);
        self::assertStringContainsString('isElementView(maybeView)', $script);
        self::assertStringNotContainsString('identityFromView(elementType)', $script);
        self::assertStringContainsString('new InspectorModule( $capability )', $bootstrap);
        self::assertStringNotContainsString('elementor/widgets/register', $module . $bootstrap . $main);
    }
}
