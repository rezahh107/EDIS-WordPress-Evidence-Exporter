<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/tools/validation/ValidationSupport.php';

final class ValidationEvidenceHardeningTest extends TestCase
{
    private ?string $temporary = null;

    /**
     * Static test/CI-only failure-surface authority.
     *
     * Tuple: id, classification owner, Diagnostic creation policy, status,
     * production entrypoints, executable test evidence, optional justification.
     * Production runtime must never read or depend on this fixture.
     *
     * @var list<array{0:string,1:string,2:string,3:string,4:list<string>,5:list<string>,6?:string}>
     */
    private const FAILURE_SURFACES = [
        ['REST_REQUEST_VALIDATION', 'WordPress REST argument schema', 'NONE', 'CONFORMING', ['src/Rest/DiagnosticExportJobController.php', 'src/Rest/InspectorSelectionController.php'], ['tests/Unit/RestHardeningContractTest.php']],
        ['AUTHORIZATION_OWNERSHIP_DENIAL', 'REST permission and object authorizers', 'NONE', 'CONFORMING', ['src/Rest/DiagnosticExportJobController.php', 'src/Rest/DiagnosticsController.php'], ['tests/integration/canonical-diagnostic-rest.php']],
        ['PREFLIGHT_STRUCTURED_BLOCKERS', 'ExportJobService::preflightNormalized', 'NONE', 'CONFORMING', ['src/Application/ExportJobService.php'], ['tests/Unit/ExportJobIntegrityTest.php']],
        ['PREFLIGHT_EXCEPTIONAL_EXECUTION', 'ExportJobService orchestration', 'PRE_JOB', 'CONFORMING', ['src/Rest/DiagnosticExportJobController.php'], ['tests/Unit/PreflightProofTest.php']],
        ['EXPORT_CREATE_NORMALIZATION', 'ExportCreateConformance', 'NONE', 'CONFORMING', ['src/Application/JobFailureCursor.php'], ['tests/Unit/ValidationEvidenceHardeningTest.php']],
        ['PREFLIGHT_PROOF_VERIFICATION', 'PreflightProof', 'PRE_JOB', 'CONFORMING', ['src/Infrastructure/Support/PreflightProof.php'], ['tests/Unit/PreflightProofTest.php']],
        ['PREFLIGHT_SOURCE_REVALIDATION', 'PreflightProof and ExportCreateConformance', 'PRE_JOB', 'CONFORMING', ['src/Infrastructure/Support/PreflightProof.php', 'src/Application/JobFailureCursor.php'], ['tests/Unit/PreflightProofTest.php']],
        ['COLLECTOR_SELECTION', 'ExportCreateConformance', 'NONE', 'CONFORMING', ['src/Application/JobFailureCursor.php'], ['tests/Unit/ExecutionPlanTest.php']],
        ['EXECUTION_PLAN_CONSTRUCTION', 'CollectorRegistry', 'PRE_JOB', 'CONFORMING', ['src/Infrastructure/Collector/CollectorRegistry.php', 'src/Application/JobFailureCursor.php'], ['tests/Unit/CollectorRegistryTest.php', 'tests/Unit/ExecutionPlanTest.php']],
        ['JOB_IDENTITY_PREPARATION', 'ExportJobService orchestration', 'PRE_JOB', 'CONFORMING', ['src/Application/ExportJobService.php', 'src/Application/JobFailureCursor.php'], ['tests/Unit/ValidationEvidenceHardeningTest.php']],
        ['INPUT_SNAPSHOT_CAPTURE', 'InputSnapshotStore', 'PRE_JOB', 'CONFORMING', ['src/Infrastructure/Support/InputSnapshotStore.php'], ['tests/Unit/InputSnapshotStoreTest.php']],
        ['SNAPSHOT_SELECTION_VALIDATION', 'ExportJobService', 'PRE_JOB', 'CONFORMING', ['src/Application/ExportJobService.php'], ['tests/Unit/ExportJobIntegrityTest.php']],
        ['SELECTION_SNAPSHOT_CONSTRUCTION', 'ExportJobService', 'PRE_JOB', 'CONFORMING', ['src/Application/ExportJobService.php'], ['tests/Unit/ExportJobIntegrityTest.php']],
        ['JOB_PERSISTENCE', 'JobStore through ExportJobService', 'PRE_JOB', 'CONFORMING', ['src/Infrastructure/Support/JobStore.php', 'src/Application/JobFailureCursor.php'], ['tests/Unit/JobStoreOperationsTest.php', 'tests/Unit/ValidationEvidenceHardeningTest.php']],
        ['POST_CREATE_SCHEDULING', 'ExportCreateConformance durable boundary', 'JOB_BOUND', 'CONFORMING', ['src/Application/JobFailureCursor.php', 'src/Rest/DiagnosticExportJobController.php'], ['tests/Unit/ValidationEvidenceHardeningTest.php']],
        ['JOB_ADVANCE', 'DiagnosticExportJobController and ExportJobService', 'JOB_BOUND', 'CONFORMING', ['src/Rest/DiagnosticExportJobController.php', 'src/Application/ExportJobService.php'], ['tests/Unit/ValidationEvidenceHardeningTest.php']],
        ['JOB_RESUME', 'DiagnosticExportJobController and ExportJobService', 'JOB_BOUND', 'CONFORMING', ['src/Rest/DiagnosticExportJobController.php', 'src/Application/ExportJobService.php'], ['tests/Unit/ValidationEvidenceHardeningTest.php']],
        ['JOB_RETRY', 'DiagnosticExportJobController and ExportJobService', 'JOB_BOUND', 'CONFORMING', ['src/Rest/DiagnosticExportJobController.php', 'src/Application/ExportJobService.php'], ['tests/Unit/ValidationEvidenceHardeningTest.php']],
        ['JOB_CANCEL', 'DiagnosticExportJobController and ExportJobService', 'JOB_BOUND', 'CONFORMING', ['src/Rest/DiagnosticExportJobController.php', 'src/Application/ExportJobService.php'], ['tests/Unit/ValidationEvidenceHardeningTest.php']],
        ['CRON_WORKER', 'DiagnosticWorkerRunner', 'WORKER_BOUND', 'CONFORMING', ['src/WordPress/DiagnosticWorkerRunner.php', 'src/Bootstrap.php'], ['tests/Unit/RootCompleteRepairTest.php']],
        ['WPCLI_WORKER', 'DiagnosticWorkerRunner', 'WORKER_BOUND', 'CONFORMING', ['src/WordPress/CliCommands.php', 'src/WordPress/DiagnosticWorkerRunner.php'], ['tests/Unit/ValidationEvidenceHardeningTest.php']],
        ['SAFE_WORKER_TEST', 'DiagnosticsService and DiagnosticWorkerRunner', 'WORKER_BOUND', 'CONFORMING', ['src/Application/DiagnosticsService.php', 'src/WordPress/DiagnosticWorkerRunner.php'], ['tests/diagnostics-envelope.test.mjs', 'tests/Unit/RootCompleteRepairTest.php']],
        ['INSPECTOR_SELECTION', 'InspectorSelectionController', 'PRE_JOB', 'CONFORMING', ['src/Rest/InspectorSelectionController.php'], ['tests/diagnostics-envelope.test.mjs', 'tests/Unit/ValidationEvidenceHardeningTest.php']],
        ['INSPECTOR_BROWSER_CONSUMER', 'window.EDISDiagnosticEnvelope', 'NONE', 'CONFORMING', ['assets/js/elementor-inspector.js', 'assets/js/diagnostics.js'], ['tests/diagnostics-envelope.test.mjs']],
        ['COMPONENT_EVIDENCE_COLLECTION', 'CollectorRegistry and CollectionResult', 'NONE', 'CONFORMING', ['src/Infrastructure/Collector/CollectorRegistry.php'], ['tests/Unit/CollectorRegistryTest.php']],
        ['DIAGNOSTIC_PROJECTION', 'DiagnosticRecordService', 'UNAVAILABLE_ONLY', 'CONFORMING', ['src/Application/DiagnosticRecordService.php'], ['tests/Unit/CanonicalDiagnosticRecordTest.php']],
        ['DIAGNOSTIC_PERSISTENCE', 'DiagnosticRecordStore', 'UNAVAILABLE_ONLY', 'CONFORMING', ['src/Infrastructure/Support/DiagnosticRecordStore.php'], ['tests/Unit/CanonicalDiagnosticRecordTest.php']],
        ['DIAGNOSTIC_CAPACITY', 'DiagnosticRecordStore', 'UNAVAILABLE_ONLY', 'CONFORMING', ['src/Infrastructure/Support/DiagnosticRecordStore.php'], ['tests/Unit/CanonicalDiagnosticRecordTest.php']],
        ['DIAGNOSTIC_EXPIRY', 'DiagnosticRecordStore and DiagnosticRecordService', 'NONE', 'CONFORMING', ['src/Infrastructure/Support/DiagnosticRecordStore.php'], ['tests/Unit/CanonicalDiagnosticRecordTest.php', 'tests/integration/canonical-diagnostic-rest.php']],
        ['CANONICAL_REST_RETRIEVAL', 'DiagnosticsController and CanonicalDiagnosticResponseAdapter', 'NONE', 'CONFORMING', ['src/Rest/DiagnosticsController.php', 'src/Rest/CanonicalDiagnosticResponseAdapter.php'], ['tests/integration/canonical-diagnostic-rest.php']],
        ['ADMIN_EXPORT_BROWSER', 'window.EDISDiagnosticEnvelope', 'NONE', 'CONFORMING', ['assets/js/admin.js', 'assets/js/admin-jobs.js', 'assets/js/diagnostics.js'], ['tests/diagnostics-envelope.test.mjs']],
        ['DOWNLOAD_AUTHORIZATION', 'Download permission and token boundary', 'NONE', 'CONFORMING', ['src/Rest/ExportJobController.php', 'src/Admin/DiagnosticDownloadController.php'], ['tests/Unit/RestHardeningContractTest.php']],
        ['DOWNLOAD_INTEGRITY', 'ExportFileStore under exact Job authority', 'JOB_BOUND', 'CONFORMING', ['src/Infrastructure/Support/ExportFileStore.php', 'src/Rest/ExportJobController.php'], ['tests/Unit/ExportJobIntegrityTest.php']],
        ['UNSUPPORTED_RUNTIME', 'Plugin deterministic runtime gate', 'UNAVAILABLE_ONLY', 'CONFORMING', ['edis-evidence-exporter.php', 'src/Bootstrap.php'], ['tests/runtime-smoke.php']],
        ['DEGRADED_STORAGE', 'PrivateStorage and DegradedModeIntegration', 'UNAVAILABLE_ONLY', 'CONFORMING', ['src/Infrastructure/Support/PrivateStorage.php', 'src/WordPress/DegradedModeIntegration.php'], ['tests/Unit/PrivateStorageTest.php']],
        ['BOOTSTRAP_FAILURE', 'Plugin bootstrap boundary', 'UNAVAILABLE_ONLY', 'CONFORMING', ['edis-evidence-exporter.php', 'src/Bootstrap.php'], ['tests/runtime-smoke.php']],
        ['INSTALLATION_INTEGRITY', 'InstallationIntegrity', 'UNAVAILABLE_ONLY', 'CONFORMING', ['src/Infrastructure/Support/InstallationIntegrity.php', 'src/Bootstrap.php'], ['tests/Unit/InstallationIntegrityTest.php']],
        ['ACTIVATION', 'LifecycleManager', 'UNAVAILABLE_ONLY', 'CONFORMING', ['src/WordPress/LifecycleManager.php'], ['tests/Unit/WordPressIntegrationContractTest.php']],
        ['MULTISITE_ACTIVATION_ROLLBACK', 'LifecycleManager', 'UNAVAILABLE_ONLY', 'CONFORMING', ['src/WordPress/LifecycleManager.php'], ['tests/Unit/WordPressIntegrationContractTest.php']],
        ['ROUTINE_CLEANUP', 'Artifact stores', 'NONE', 'CONFORMING', ['src/Bootstrap.php', 'src/Infrastructure/Support/DiagnosticRecordStore.php'], ['tests/Unit/CanonicalDiagnosticRecordTest.php']],
        ['RECOVERY_CALLBACK', 'DiagnosticWorkerRunner and WorkerRecovery', 'WORKER_BOUND', 'CONFORMING', ['src/WordPress/DiagnosticWorkerRunner.php', 'src/WordPress/WorkerRecovery.php'], ['tests/Unit/RootCompleteRepairTest.php']],
        ['LEGACY_CONTROLLER_REGISTRATION', 'Bootstrap composition root', 'NONE', 'NOT_APPLICABLE', ['src/Bootstrap.php', 'src/Rest/ExportJobController.php'], ['tests/Unit/ValidationEvidenceHardeningTest.php'], 'ExportJobController is retained only for authorized download handlers and has no REST registration in Bootstrap.'],
    ];

    protected function tearDown(): void
    {
        if ($this->temporary !== null && is_dir($this->temporary)) {
            $this->remove($this->temporary);
        }
    }

    public function testFailureSurfaceAuthorityIsCompleteStaticAndFinal(): void
    {
        $root = dirname(__DIR__, 2);
        $allowedPolicies = ['NONE', 'PRE_JOB', 'JOB_BOUND', 'WORKER_BOUND', 'UNAVAILABLE_ONLY'];
        $allowedStatuses = ['CONFORMING', 'NOT_APPLICABLE'];
        $ids = [];
        foreach (self::FAILURE_SURFACES as $family) {
            [$id, $owner, $policy, $status, $entrypoints, $tests] = $family;
            self::assertMatchesRegularExpression('/\A[A-Z0-9_]{3,96}\z/D', $id);
            self::assertArrayNotHasKey($id, $ids, 'Duplicate operation family: ' . $id);
            $ids[$id] = true;
            self::assertNotSame('', trim($owner));
            self::assertContains($policy, $allowedPolicies, $id);
            self::assertContains($status, $allowedStatuses, $id);
            self::assertNotSame([], $entrypoints, $id . ' lacks production entrypoints.');
            self::assertNotSame([], $tests, $id . ' lacks executable evidence.');
            foreach (array_merge($entrypoints, $tests) as $path) {
                self::assertFileExists($root . '/' . $path, $id . ' missing evidence: ' . $path);
            }
            if ($status === 'NOT_APPLICABLE') {
                self::assertNotSame('', trim((string) ($family[6] ?? '')), $id . ' requires justification.');
            }
        }
        self::assertGreaterThanOrEqual(40, count($ids));
        foreach (['EXPORT_CREATE_NORMALIZATION', 'POST_CREATE_SCHEDULING', 'WPCLI_WORKER', 'INSPECTOR_SELECTION', 'DOWNLOAD_INTEGRITY', 'LEGACY_CONTROLLER_REGISTRATION'] as $required) {
            self::assertArrayHasKey($required, $ids);
        }
        foreach (['src', 'assets', 'templates', 'edis-evidence-exporter.php', 'autoload.php'] as $runtimePath) {
            $bytes = $this->readRuntimeTree($root . '/' . $runtimePath);
            self::assertStringNotContainsString('FAILURE_SURFACES', $bytes, 'Production runtime depends on the test-only authority.');
        }
    }

    public function testMutationEquivalentFailureConformanceGuards(): void
    {
        $root = dirname(__DIR__, 2);
        $inspector = (string) file_get_contents($root . '/src/Rest/InspectorSelectionController.php');
        self::assertStringNotContainsString("'edis-' . substr(hash", $inspector);
        self::assertStringNotContainsString('getMessage()', $inspector);
        self::assertStringContainsString('capturePreJobFailure', $inspector);
        self::assertStringContainsString("'diagnostic_available' => false", $inspector);

        $browser = (string) file_get_contents($root . '/assets/js/elementor-inspector.js');
        self::assertStringContainsString('EDISDiagnosticEnvelope', $browser);
        self::assertStringContainsString('shared.classify(payload)', $browser);
        self::assertStringContainsString('shared.render(target, result)', $browser);

        $cli = (string) file_get_contents($root . '/src/WordPress/CliCommands.php');
        self::assertStringContainsString('DiagnosticWorkerRunner', $cli);
        self::assertStringContainsString('$this->worker->process($id)', $cli);
        self::assertStringNotContainsString('$this->jobsService->process(', $cli);

        $bootstrap = (string) file_get_contents($root . '/src/Bootstrap.php');
        self::assertStringContainsString('DiagnosticExportJobController', $bootstrap);
        self::assertStringContainsString('DiagnosticWorkerRunner', $bootstrap);
        self::assertStringNotContainsString("[$downloadController, 'registerRoutes']", $bootstrap);

        $controller = (string) file_get_contents($root . '/src/Rest/DiagnosticExportJobController.php');
        foreach (['EDIS_ACTIVE_WORKER_LEASE', 'EDIS_STALE_JOB_REVISION', 'expectedActionResult', 'DurableJobFailureException'] as $needle) {
            self::assertStringContainsString($needle, $controller);
        }
        self::assertStringContainsString("'diagnostic_available' => false", $controller);

        $carrier = (string) file_get_contents($root . '/src/Application/JobFailureCursor.php');
        self::assertStringContainsString('post_create_scheduling', $carrier);
        self::assertStringContainsString('EDIS_EXPORT_CREATE_STAGE_UNAVAILABLE', $carrier);
        self::assertStringNotContainsString('getTrace', $carrier);
        self::assertStringNotContainsString('getMessage()', $carrier);
    }

    public function testFailureObservationRejectsUnsafeContextAndPreservesNotProvenBoundary(): void
    {
        $class = '\\EDIS\\EvidenceExporter\\Application\\FailureObservation';
        $observation = new $class(
            'EDIS_TEST_BOUNDARY',
            'request_normalization',
            'UNEXPECTED_MATERIAL_FAILURE',
            'PRE_JOB',
            'NOT_PROVEN',
            ['validation_stage' => 'request_normalization'],
        );
        self::assertSame('NOT_PROVEN', $observation->retryability);
        self::assertObjectNotHasProperty('jobId', $observation);
        $exception = $observation->asIntegrityException(new \RuntimeException('token=secret /absolute/path raw source'));
        self::assertSame('EDIS_TEST_BOUNDARY', $exception->diagnosticCode);
        self::assertArrayNotHasKey('message', $exception->diagnosticContext);
        self::assertArrayNotHasKey('trace', $exception->diagnosticContext);

        $this->expectException(\InvalidArgumentException::class);
        new $class(
            'EDIS_TEST_BOUNDARY',
            'request_normalization',
            'UNEXPECTED_MATERIAL_FAILURE',
            'PRE_JOB',
            'NOT_PROVEN',
            ['authorization_header' => 'Bearer secret'],
        );
    }

    public function testDurableJobCarrierRejectsFabricatedOrPreJobIdentity(): void
    {
        $observationClass = '\\EDIS\\EvidenceExporter\\Application\\FailureObservation';
        $exceptionClass = '\\EDIS\\EvidenceExporter\\Application\\DurableJobFailureException';
        $observation = new $observationClass(
            'EDIS_POST_CREATE_SCHEDULING_FAILED',
            'post_create_scheduling',
            'MATERIAL_JOB_INCIDENT',
            'JOB_BOUND',
            'RETRY_ALLOWED',
        );
        $exception = new $exceptionClass('11111111-1111-4111-8111-111111111111', $observation, null);
        self::assertSame('11111111-1111-4111-8111-111111111111', $exception->jobId);

        $this->expectException(\InvalidArgumentException::class);
        new $exceptionClass('latest-job', $observation, null);
    }

    public function testSkippedRequiredLocalGateIsIncompleteNotPass(): void
    {
        $gates = $this->allPassGates();
        $gates['npm_ci'] = ['state' => 'NOT_RUN', 'reason' => 'skipped_by_command_line'];
        $summary = \EdisValidationSummary::summarize($gates);
        self::assertSame('INCOMPLETE', $summary['local_state']);
        self::assertContains('npm_ci', $summary['incomplete_local_gates']);
        self::assertFalse($summary['production_ready_verified']);
    }

    public function testExternalNotRunDoesNotChangeCompletedLocalState(): void
    {
        $gates = $this->allPassGates();
        $gates['plugin_check'] = ['state' => 'NOT_RUN', 'reason' => 'requires_runtime'];
        $summary = \EdisValidationSummary::summarize($gates);
        self::assertSame('PASS', $summary['local_state']);
        self::assertSame('NOT_RUN', $summary['external_state']);
        self::assertContains('plugin_check', $summary['unresolved_external_gates']);
        self::assertFalse($summary['production_ready_verified']);
    }

    public function testAllRequiredGatesPassPromotesProductionReady(): void
    {
        $summary = \EdisValidationSummary::summarize($this->allPassGates());
        self::assertSame('PASS', $summary['local_state']);
        self::assertSame('PASS', $summary['external_state']);
        self::assertTrue($summary['production_ready_verified']);
    }

    public function testLargeStdoutAndStderrAreCapturedWithoutPipeDeadlock(): void
    {
        $result = \EdisValidationProcess::run(
            dirname(__DIR__, 2),
            ['php', '-r', 'fwrite(STDOUT, str_repeat("O", 262144)); fwrite(STDERR, str_repeat("E", 262144));'],
        );
        self::assertTrue($result['started'] ?? false);
        self::assertSame(0, $result['exit_code'] ?? null);
        self::assertSame(262144, $result['stdout_bytes'] ?? null);
        self::assertSame(262144, $result['stderr_bytes'] ?? null);
        self::assertIsString($result['stdout_sha256'] ?? null);
        self::assertIsString($result['stderr_sha256'] ?? null);
    }

    public function testEvidenceWriterAtomicallyReplacesAndVerifiesReport(): void
    {
        $this->temporary = sys_get_temp_dir() . '/edis-validation-writer-' . bin2hex(random_bytes(6));
        mkdir($this->temporary, 0700, true);
        $target = $this->temporary . '/evidence.json';
        file_put_contents($target, "old\n");
        \EdisValidationEvidenceWriter::write($target, "{\"state\":\"PASS\"}\n");
        self::assertSame("{\"state\":\"PASS\"}\n", file_get_contents($target));
        self::assertSame(hash('sha256', "{\"state\":\"PASS\"}\n"), hash_file('sha256', $target));
    }

    /** @return array<string,array<string,string>> */
    private function allPassGates(): array
    {
        $gates = [];
        foreach (array_merge(\EdisValidationSummary::LOCAL_REQUIRED, \EdisValidationSummary::EXTERNAL_REQUIRED) as $id) {
            $gates[$id] = ['state' => 'PASS'];
        }
        return $gates;
    }

    private function readRuntimeTree(string $path): string
    {
        if (is_file($path)) {
            return (string) file_get_contents($path);
        }
        if (!is_dir($path)) {
            return '';
        }
        $bytes = '';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $bytes .= (string) file_get_contents($file->getPathname());
            }
        }
        return $bytes;
    }

    private function remove(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->remove($path . '/' . $entry);
        }
        rmdir($path);
    }
}
