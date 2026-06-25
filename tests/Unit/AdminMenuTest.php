<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminMenuTest extends TestCase
{
    public function testGeneratedAdminConfigurationContainsSevenUniquePages(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/admin.php';
        self::assertSame('edis_export_evidence', $config['capability']);
        self::assertCount(7, $config['pages']);
        $slugs = array_column($config['pages'], 'slug');
        self::assertCount(count($slugs), array_unique($slugs));
        self::assertSame($config['menu_slug'], $config['pages'][0]['slug']);
    }
}
