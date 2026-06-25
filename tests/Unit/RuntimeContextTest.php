<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\WordPress\RuntimeContext;
use PHPUnit\Framework\TestCase;

final class RuntimeContextTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $server;
    /** @var array<string,mixed> */
    private array $get;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->get = $_GET;
        $_SERVER = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_GET = $this->get;
    }

    public function testRestRouteQueryActivatesApplicationRuntime(): void
    {
        $_GET['rest_route'] = '/edis-evidence-exporter/v3/jobs';
        $runtime = new RuntimeContext();
        self::assertTrue($runtime->isRest());
        self::assertTrue($runtime->requiresApplicationRuntime());
    }

    public function testWpJsonPathActivatesApplicationRuntime(): void
    {
        $_SERVER['REQUEST_URI'] = '/wp-json/edis-evidence-exporter/v3/jobs';
        $runtime = new RuntimeContext();
        self::assertTrue($runtime->isRest());
    }

    public function testNormalFrontendRequestDoesNotLoadApplicationRuntime(): void
    {
        $_SERVER['REQUEST_URI'] = '/sample-page/';
        $runtime = new RuntimeContext();
        self::assertFalse($runtime->isRest());
        self::assertFalse($runtime->requiresApplicationRuntime());
    }

    public function testNonScalarRequestValuesAreRejectedWithoutWarnings(): void
    {
        $_GET['rest_route'] = ['unexpected'];
        $_SERVER['REQUEST_URI'] = ['unexpected'];
        $runtime = new RuntimeContext();
        self::assertFalse($runtime->isRest());
    }
}
