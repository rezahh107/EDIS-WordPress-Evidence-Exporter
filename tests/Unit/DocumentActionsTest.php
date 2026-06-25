<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Admin\DocumentActions;
use PHPUnit\Framework\TestCase;

final class DocumentActionsTest extends TestCase
{
    public function testClassExists(): void
    {
        self::assertTrue(class_exists(DocumentActions::class));
    }

    public function testDocumentRoutePermissionClosureCanAccessControllerInstance(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/Rest/DocumentController.php');
        self::assertIsString($source);
        self::assertStringContainsString("'permission_callback' => [\$this, 'permission']", $source);
        self::assertStringContainsString("\$this->documents->query(", $source);
        self::assertStringNotContainsString("\$this->documents->search(", $source);
    }
}
