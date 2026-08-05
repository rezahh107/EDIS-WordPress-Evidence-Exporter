<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Application\JobFailureCursor;
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\DiagnosticRecordStore;
use PHPUnit\Framework\TestCase;

final class DiagnosticFollowupRepairTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $path) { $this->remove($path); }
    }

    public function testFailureCursorDetectsRepeatedSameCodeOccurrenceByRevisionAndObservedTime(): void
    {
        $job = [
            'revision' => 8,
            'status' => 'failed',
            'phase' => 'failed',
            'current_component' => 'environment',
            'last_error_code' => 'EDIS_EXPORT_ADVANCE_FAILED',
            'last_error_at' => 100,
            'diagnostics' => [['code' => 'EDIS_EXPORT_ADVANCE_FAILED', 'scope' => 'OPERATIONAL', 'context' => ['failure_phase' => 'collecting']]],
        ];
        $before = JobFailureCursor::capture($job);
        self::assertFalse(JobFailureCursor::changed($before, $job));
        $job['revision'] = 9;
        $job['last_error_at'] = 101;
        self::assertTrue(JobFailureCursor::changed($before, $job));
        self::assertSame($before, JobFailureCursor::capture(array_replace($job, ['revision' => 8, 'last_error_at' => 100])));
    }

    public function testJobDiagnosticExpiryClampsToSmallerFutureAuthorityAndRejectsNoOverlap(): void
    {
        $root = sys_get_temp_dir() . '/edis-life-' . bin2hex(random_bytes(5));
        mkdir($root, 0777, true);
        $this->cleanup[] = $root;
        $store = new DiagnosticRecordStore($root, new DeterministicFilesystem(), 7200);
        $near = time() + 120;
        $clamped = $store->expirationForJob($near);
        self::assertIsInt($clamped);
        self::assertLessThanOrEqual($near, $clamped);
        self::assertGreaterThan(time(), $clamped);
        self::assertNull($store->expirationForJob(time()));
        self::assertGreaterThan(time() + 3500, $store->defaultExpiration());
    }

    public function testLockedSourceContractsRemainExplicit(): void
    {
        $root = dirname(__DIR__, 2) . '/';
        $controller = (string) file_get_contents($root . 'src/Rest/DiagnosticsController.php');
        $exportController = (string) file_get_contents($root . 'src/Rest/DiagnosticExportJobController.php');
        $service = (string) file_get_contents($root . 'src/Application/DiagnosticRecordService.php');
        $builder = (string) file_get_contents($root . 'tools/release/build-release.py');
        self::assertStringContainsString('rest_pre_serve_request', $controller);
        self::assertStringContainsString("\$resolved['bytes']", $controller);
        self::assertStringNotContainsString("\$resolved['record']", $controller);
        self::assertStringContainsString('JobFailureCursor::capture', $exportController);
        self::assertStringContainsString('failureCursor', $service);
        self::assertStringContainsString('expirationForJob', $service);
        self::assertStringNotContainsString("worker_version != plugin_version", $builder);
        self::assertStringNotContainsString("'diagnostic_available' => false,\n                            'diagnostic_id' => null", $exportController);
    }

    private function remove(string $path): void
    {
        if (is_file($path) || is_link($path)) { @unlink($path); return; }
        if (!is_dir($path)) { return; }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') { $this->remove($path . '/' . $entry); }
        }
        @rmdir($path);
    }
}
