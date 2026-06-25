<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RestHardeningContractTest extends TestCase
{
    public function testRestRoutesDeclareArgsAndDoNotExposeRawExceptionMessages(): void
    {
        $controllers = [
            'src/Rest/ExportJobController.php',
            'src/Rest/DiagnosticsController.php',
            'src/Rest/DocumentController.php',
            'src/Rest/InspectorSelectionController.php',
        ];

        foreach ($controllers as $relative) {
            $source = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
            self::assertStringContainsString("'permission_callback'", $source, $relative . ' must declare REST permissions.');
            self::assertStringContainsString("'args'", $source, $relative . ' must declare REST args, even when empty.');
            self::assertStringNotContainsString('$exception->getMessage(), [\'status\'', $source, $relative . ' must not expose raw exception messages as REST responses.');
            self::assertStringNotContainsString('new \\WP_Error(\'edis_worker_test_failed\', $exception->getMessage()', $source);
            self::assertStringNotContainsString('new \\WP_Error(\'edis_invalid_inspector_selection\', $exception->getMessage()', $source);
        }
    }


    public function testDocumentListingIsObjectAuthorizedBeforePaginationTotalsAreCalculated(): void
    {
        $root = dirname(__DIR__, 2);
        $service = (string) file_get_contents($root . '/src/Application/DocumentQueryService.php');
        $controller = (string) file_get_contents($root . '/src/Rest/DocumentController.php');

        self::assertStringContainsString('current_user_can(\'edit_post\', $documentId)', $service);
        self::assertStringContainsString('authorizedMatchingIds', $service);
        self::assertStringContainsString("'fields' => 'ids'", $service);
        self::assertStringContainsString("'no_found_rows' => true", $service);
        self::assertStringNotContainsString('$query->found_posts', $service);
        self::assertStringNotContainsString('$query->max_num_pages', $service);
        self::assertStringContainsString('\'total\' => $total', $service);
        self::assertStringContainsString('\'total_pages\' => $totalPages', $service);
        self::assertStringNotContainsString("current_user_can('edit_post', \$id)", $controller);
    }


    public function testDocumentQueryTotalsExcludeDocumentsWithoutObjectPermission(): void
    {
        if (!class_exists('WP_Query', false)) {
            eval('class WP_Query { public array $posts = []; public function __construct(array $args) { $this->posts = (($args["fields"] ?? null) === "ids") ? [11, 12] : []; } }');
        }
        $service = new \EDIS\EvidenceExporter\Application\DocumentQueryService(
            static fn (int $documentId): bool => $documentId === 11,
        );

        $result = $service->query('', 2, 1);

        self::assertSame([], $result['items']);
        self::assertSame(1, $result['total']);
        self::assertSame(1, $result['total_pages']);
        self::assertSame(2, $result['page']);

        $unauthorizedInclude = $service->query('', 1, 20, [12]);
        self::assertSame([], $unauthorizedInclude['items']);
        self::assertSame(0, $unauthorizedInclude['total']);
        self::assertSame(0, $unauthorizedInclude['total_pages']);
    }

    public function testInspectorSelectionRouteHasBoundedSchema(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Rest/InspectorSelectionController.php');
        self::assertStringContainsString("'document_id'", $source);
        self::assertStringContainsString("'selection'", $source);
        self::assertStringContainsString("'minItems' => 1", $source);
        self::assertStringContainsString("'maxItems' => 50", $source);
        self::assertStringContainsString('validateSelection', $source);
    }

    public function testAssetAndStaticAnalysisConfigsArePresent(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertFileExists($root . '/package.json');
        self::assertFileExists($root . '/package-lock.json');
        self::assertFileExists($root . '/phpstan.neon.dist');
        self::assertFileExists($root . '/phpcs-wordpress-boundary.xml');
        self::assertFileExists($root . '/phpcs-deterministic-core.xml');
        self::assertFileExists($root . '/tools/ci/check-js.mjs');
        self::assertFileExists($root . '/tools/ci/check-css.mjs');
    }
}
