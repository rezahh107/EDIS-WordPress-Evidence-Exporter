<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Admin\Settings\SettingsRepository;
use EDIS\EvidenceExporter\Application\ExportJobService;
use EDIS\EvidenceExporter\Application\ExportService;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Support\ArtifactStore;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportFileStore;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportIntegrityException;
use EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use PHPUnit\Framework\TestCase;

final class ExportJobIntegrityTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $path) {
            $this->remove($path);
        }
    }

    public function testOldJobFormatCannotResumeSilently(): void
    {
        [$service] = $this->service();
        $method = new \ReflectionMethod($service, 'assertJobCompatible');
        try {
            $method->invoke($service, ['job_id' => 'legacy-job']);
            self::fail('Expected the legacy job to be rejected.');
        } catch (\ReflectionException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $actual = $exception instanceof \ReflectionException ? $exception : ($exception->getPrevious() ?? $exception);
            self::assertInstanceOf(ExportIntegrityException::class, $actual);
            self::assertSame('EDIS_JOB_FORMAT_INCOMPATIBLE', $actual->diagnosticCode);
        }
    }

    public function testTamperedCompletedArtifactCannotResume(): void
    {
        [$service, $artifacts, $inputs, $root] = $this->service();
        $manifest = $inputs->capture('job-integrity', [], time() + 3600);
        $job = [
            'job_id' => 'job-integrity',
            'job_format_version' => '2.1.0',
            'implementation_version' => '3.7.11',
            'input_snapshot_format_version' => '2.0.0',
            'input_snapshot_id' => 'job-integrity',
            'input_snapshot_sha256' => $manifest['snapshot_sha256'],
            'analysis_set_id' => 'analysis-set',
            'wordpress_bundle_id' => 'bundle-id',
            'completed_components' => ['environment'],
            'cursor' => 1,
        ];
        $artifacts->put('job-integrity', 'environment', ['source_truth_state' => 'VERIFIED']);
        $stepInputMethod = new \ReflectionMethod($service, 'stepInputSha256');
        $stepInput = $stepInputMethod->invoke($service, 'environment', $job, []);
        $job['completed_step_records'] = [
            'environment' => [
                'component_id' => 'environment',
                'component_schema_version' => '1.0.0',
                'implementation_version' => '3.7.11',
                'input_snapshot_sha256' => $manifest['snapshot_sha256'],
                'step_input_sha256' => $stepInput,
                'artifact_file_sha256' => $artifacts->fileSha256('job-integrity', 'environment'),
            ],
        ];
        $resumeMethod = new \ReflectionMethod($service, 'assertResumeState');
        $resumeMethod->invoke($service, $job, ['environment'], 1);

        file_put_contents($root . '/artifacts/job-integrity/environment.json', '{"tampered":true}');
        try {
            $resumeMethod->invoke($service, $job, ['environment'], 1);
            self::fail('Expected the tampered artifact to be rejected.');
        } catch (\Throwable $exception) {
            $actual = $exception->getPrevious() ?? $exception;
            self::assertInstanceOf(ExportIntegrityException::class, $actual);
            self::assertSame('EDIS_RESUME_ARTIFACT_MISMATCH', $actual->diagnosticCode);
        }
    }

    public function testResumeFailureToAcquireLockDoesNotMutateJob(): void
    {
        [$service, , $inputs, $root] = $this->service();
        $store = new JobStore($root . '/jobs');
        $manifest = $inputs->capture('resume-atomic', [], time() + 3600);
        $store->create([
            'job_id' => 'resume-atomic',
            'job_format_version' => '2.1.0',
            'implementation_version' => '3.7.11',
            'input_snapshot_format_version' => '2.0.0',
            'input_snapshot_id' => 'resume-atomic',
            'input_snapshot_sha256' => $manifest['snapshot_sha256'],
            'owner_id' => 7,
            'status' => 'failed',
            'phase' => 'failed',
            'cursor' => 0,
            'selected_components' => [],
            'diagnostics' => [],
            'expires_at' => time() + 3600,
        ]);
        $path = $root . '/jobs/resume-atomic.json';
        $before = file_get_contents($path);
        $lock = $store->acquireLock('resume-atomic', 0);
        self::assertTrue(is_resource($lock));
        try {
            $failed = false;
            try {
                $service->resume('resume-atomic', 7);
            } catch (\RuntimeException) {
                $failed = true;
            }
            self::assertTrue($failed);
            self::assertSame($before, file_get_contents($path));
            $job = $store->get('resume-atomic');
            self::assertSame('failed', $job['status']);
            self::assertCount(0, $job['diagnostics']);
        } finally {
            $store->releaseLock($lock);
        }
    }

    /** @return array{ExportJobService,ArtifactStore,InputSnapshotStore,string} */
    private function service(): array
    {
        $root = sys_get_temp_dir() . '/edis-job-integrity-test-' . bin2hex(random_bytes(6));
        $this->cleanup[] = $root;
        $pluginRoot = dirname(__DIR__, 2) . '/';
        $definitions = require $pluginRoot . 'config/collectors.php';
        $registry = CollectorRegistry::fromDefinitions($definitions);
        $settings = new SettingsRepository();
        $artifacts = new ArtifactStore($root . '/artifacts');
        $inputs = new InputSnapshotStore($root . '/inputs', static fn (int $id): ?array => null);
        $service = new ExportJobService(
            $registry,
            new ExportService($registry, $pluginRoot),
            new JobStore($root . '/jobs'),
            $artifacts,
            new ExportFileStore($settings, $root . '/bundles'),
            $settings,
            $inputs,
        );
        return [$service, $artifacts, $inputs, $root];
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . '/' . $entry);
            }
        }
        rmdir($path);
    }
}
