<?php
declare(strict_types=1);

namespace {
    $edisFailureFixtureArg = isset($argv[1]) && is_string($argv[1]) ? $argv[1] : '';
    if (PHP_SAPI === 'cli' && str_starts_with($edisFailureFixtureArg, 'failure-fixture:')) {
        $scenario = substr($edisFailureFixtureArg, strlen('failure-fixture:'));
        $GLOBALS['edis_fixture_options'] = [];
        $GLOBALS['edis_fixture_schedule_mode'] = 'success';
        $GLOBALS['edis_fixture_job_store'] = null;
        $GLOBALS['edis_fixture_site_documents'] = [101, 102];

        if (!class_exists('WP_Error')) {
            final class WP_Error
            {
                public function __construct(
                    private readonly string $code,
                    private readonly string $message = '',
                    private readonly mixed $data = null,
                ) {
                }
                public function get_error_code(): string { return $this->code; }
                public function get_error_message(): string { return $this->message; }
                public function get_error_data(): mixed { return $this->data; }
            }
        }
        if (!class_exists('WP_REST_Response')) {
            final class WP_REST_Response
            {
                public function __construct(private readonly mixed $data = null, private readonly int $status = 200) {}
                public function get_data(): mixed { return $this->data; }
                public function get_status(): int { return $this->status; }
            }
        }
        if (!class_exists('WP_REST_Request')) {
            final class WP_REST_Request
            {
                /** @param array<string,mixed> $params */
                public function __construct(private readonly array $params = []) {}
                public function get_param(string $key): mixed { return $this->params[$key] ?? null; }
            }
        }
        if (!class_exists('WP_Query')) {
            final class WP_Query
            {
                /** @var list<int> */
                public array $posts;
                /** @param array<string,mixed> $args */
                public function __construct(array $args = [])
                {
                    $this->posts = array_values(array_map('intval', (array) ($GLOBALS['edis_fixture_site_documents'] ?? [])));
                }
            }
        }

        function get_option(string $name, mixed $default = false): mixed
        {
            $options = $GLOBALS['edis_fixture_options'] ?? [];
            return is_array($options) && array_key_exists($name, $options) ? $options[$name] : $default;
        }
        function get_current_user_id(): int { return 7; }
        function current_user_can(string $capability, mixed ...$args): bool { return true; }
        function __(string $message, string $domain = ''): string { return $message; }
        function is_wp_error(mixed $value): bool { return $value instanceof \WP_Error; }
        function wp_next_scheduled(string $hook, array $args = []): false { return false; }
        function get_the_title(int $documentId): string { return 'Fixture'; }
        function get_post(int $documentId): ?object { return null; }
        function get_post_meta(int $documentId, string $key, bool $single = false): mixed
        {
            return match ($key) {
                '_elementor_data' => '[{"id":"fixture_' . $documentId . '","elType":"container","elements":[]}]',
                '_elementor_page_settings' => [],
                '_elementor_template_type' => 'page',
                '_elementor_edit_mode' => 'builder',
                '_elementor_version' => '4.1.3',
                default => '',
            };
        }
        function wp_schedule_single_event(int $timestamp, string $hook, array $args = [], bool $wpError = false): bool
        {
            $mode = (string) ($GLOBALS['edis_fixture_schedule_mode'] ?? 'success');
            if ($mode === 'throw_with_decoy') {
                $store = $GLOBALS['edis_fixture_job_store'] ?? null;
                if ($store instanceof \EDIS\EvidenceExporter\Infrastructure\Support\JobStore) {
                    $decoyId = '33333333-3333-4333-8333-333333333333';
                    if ($store->get($decoyId) === null) {
                        $store->create([
                            'job_id' => $decoyId,
                            'owner_id' => 7,
                            'status' => 'queued',
                            'phase' => 'initializing',
                            'created_at' => time(),
                            'expires_at' => time() + 3600,
                            'selected_document_count' => 0,
                            'schedule_state' => 'NOT_SCHEDULED',
                            'diagnostics' => [],
                        ]);
                    }
                }
                throw new \RuntimeException('fixture scheduling failure');
            }
            if ($mode === 'throw') {
                throw new \RuntimeException('fixture scheduling failure');
            }
            return true;
        }

        $pluginRoot = dirname(__DIR__, 2) . '/';
        require_once $pluginRoot . 'autoload.php';
        $root = sys_get_temp_dir() . '/edis-failure-root-' . bin2hex(random_bytes(6));
        $removeTree = static function (string $path) use (&$removeTree): void {
            if (is_file($path) || is_link($path)) { @unlink($path); return; }
            if (!is_dir($path)) { return; }
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') { $removeTree($path . '/' . $entry); }
            }
            @rmdir($path);
        };
        register_shutdown_function(static function () use (&$removeTree, $root): void { $removeTree($root); });

        $definitions = require $pluginRoot . 'config/collectors.php';
        $registry = \EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry::fromDefinitions($definitions);
        $filesystem = new \EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem();
        $settings = new \EDIS\EvidenceExporter\Admin\Settings\SettingsRepository();
        $jobs = new \EDIS\EvidenceExporter\Infrastructure\Support\JobStore($root . '/jobs', $filesystem);
        $artifacts = new \EDIS\EvidenceExporter\Infrastructure\Support\ArtifactStore($root . '/artifacts', $filesystem);
        $inputs = new \EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore($root . '/inputs', null, $filesystem);
        $files = new \EDIS\EvidenceExporter\Infrastructure\Support\ExportFileStore($settings, $root . '/bundles', $filesystem);
        $service = new \EDIS\EvidenceExporter\Application\ExportJobService(
            $registry,
            new \EDIS\EvidenceExporter\Application\ExportService($registry, $pluginRoot),
            $jobs,
            $artifacts,
            $files,
            $settings,
            $inputs,
            null,
            null,
        );
        $boundary = new \EDIS\EvidenceExporter\Application\ExportCreateConformance($service, $registry);
        $diagnosticStore = new \EDIS\EvidenceExporter\Infrastructure\Support\DiagnosticRecordStore($root . '/diagnostics', $filesystem, 3600);
        $diagnostics = new \EDIS\EvidenceExporter\Application\DiagnosticRecordService($diagnosticStore, $jobs, $pluginRoot, $filesystem);
        $controller = new \EDIS\EvidenceExporter\Rest\DiagnosticExportJobController($service, $boundary, $jobs, $diagnostics);
        $GLOBALS['edis_fixture_job_store'] = $jobs;

        $metadataRequest = static fn (array $overrides = []): array => array_replace_recursive([
            'privacy_mode' => 'Strict',
            'collectors' => ['environment'],
            'document_ids' => [],
            'options' => [
                'export_scope' => 'METADATA_ONLY',
                'dependency_scope' => 'SOURCE_ONLY',
                'include_original_documents' => false,
            ],
            'preflight_token' => '',
        ], $overrides);
        $baseJob = static fn (string $jobId, string $status = 'failed', array $overrides = []): array => array_replace([
            'job_id' => $jobId,
            'job_format_version' => '2.1.0',
            'implementation_version' => '3.7.15',
            'input_snapshot_format_version' => '2.0.0',
            'input_snapshot_id' => $jobId,
            'input_snapshot_sha256' => 'sha256:' . str_repeat('0', 64),
            'owner_id' => 7,
            'status' => $status,
            'phase' => $status === 'failed' ? 'failed' : 'initializing',
            'progress' => 0,
            'created_at' => time(),
            'expires_at' => time() + 3600,
            'cursor' => 0,
            'selected_components' => [],
            'selected_document_count' => 0,
            'completed_components' => [],
            'completed_step_records' => [],
            'diagnostics' => [],
            'last_error_code' => $status === 'failed' ? 'EDIS_EXISTING_FAILURE' : null,
            'last_error_at' => $status === 'failed' ? time() - 10 : null,
            'next_retry_at' => null,
            'lease_owner' => null,
            'lease_acquired_at' => null,
            'lease_expires_at' => null,
            'schedule_state' => 'NOT_SCHEDULED',
            'schedule_error' => null,
            'config' => ['document_ids' => []],
        ], $overrides);
        $diagnosticCount = static function () use ($root): int {
            $diagnosticRoot = $root . '/diagnostics';
            if (!is_dir($diagnosticRoot)) { return 0; }
            $count = 0;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($diagnosticRoot, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && str_starts_with($file->getFilename(), 'edis-diag-')) { $count++; }
            }
            return $count;
        };
        $errorResult = static function (\WP_Error $error): array {
            $data = $error->get_error_data();
            return ['code' => $error->get_error_code(), 'message' => $error->get_error_message(), 'data' => is_array($data) ? $data : []];
        };
        $emit = static function (array $data): never {
            echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            exit(0);
        };

        try {
            if ($scenario === 'maintenance_rejection') {
                $GLOBALS['edis_fixture_options']['edis_evidence_accept_new_jobs'] = false;
                $response = $controller->create(new \WP_REST_Request($metadataRequest()));
                if (!$response instanceof \WP_Error) { throw new \RuntimeException('Expected WP_Error.'); }
                $emit(['scenario' => $scenario, 'error' => $errorResult($response), 'diagnostic_count' => $diagnosticCount(), 'job_count' => count($jobs->jobsForUser(7))]);
            }
            if ($scenario === 'failed_job_lock_rejection') {
                $jobId = '11111111-1111-4111-8111-111111111111';
                $jobs->create($baseJob($jobId, 'failed'));
                $before = $jobs->get($jobId);
                $lock = $jobs->acquireLock($jobId, 0);
                if (!is_resource($lock)) { throw new \RuntimeException('Could not acquire fixture lock.'); }
                try { $response = $controller->resume(new \WP_REST_Request(['job_id' => $jobId])); }
                finally { $jobs->releaseLock($lock); }
                if (!$response instanceof \WP_Error) { throw new \RuntimeException('Expected WP_Error.'); }
                $after = $jobs->get($jobId);
                $emit([
                    'scenario' => $scenario,
                    'error' => $errorResult($response),
                    'diagnostic_count' => $diagnosticCount(),
                    'job_unchanged' => $before === $after,
                    'diagnostics_unchanged' => ($before['diagnostics'] ?? null) === ($after['diagnostics'] ?? null),
                    'last_error_code_unchanged' => ($before['last_error_code'] ?? null) === ($after['last_error_code'] ?? null),
                ]);
            }
            if ($scenario === 'active_lease_rejection') {
                $jobId = '22222222-2222-4222-8222-222222222222';
                $jobs->create($baseJob($jobId, 'queued', ['lease_owner' => 'worker-active', 'lease_expires_at' => time() + 300, 'schedule_state' => 'SCHEDULED_RECOVERY']));
                $before = $jobs->get($jobId);
                $response = $controller->advance(new \WP_REST_Request(['job_id' => $jobId, 'revision' => $before['revision']]));
                if (!$response instanceof \WP_Error) { throw new \RuntimeException('Expected WP_Error.'); }
                $emit(['scenario' => $scenario, 'error' => $errorResult($response), 'diagnostic_count' => $diagnosticCount(), 'job_unchanged' => $before === $jobs->get($jobId)]);
            }
            if ($scenario === 'stale_revision_rejection') {
                $jobId = '44444444-4444-4444-8444-444444444444';
                $jobs->create($baseJob($jobId, 'queued'));
                $before = $jobs->get($jobId);
                $requested = (int) ($before['revision'] ?? 0) + 7;
                $response = $controller->advance(new \WP_REST_Request(['job_id' => $jobId, 'revision' => $requested]));
                if (!$response instanceof \WP_Error) { throw new \RuntimeException('Expected WP_Error.'); }
                $emit(['scenario' => $scenario, 'error' => $errorResult($response), 'diagnostic_count' => $diagnosticCount(), 'job_unchanged' => $before === $jobs->get($jobId), 'requested_revision' => $requested]);
            }
            if ($scenario === 'durable_exact') {
                $GLOBALS['edis_fixture_schedule_mode'] = 'throw';
                try { $boundary->create(7, $metadataRequest()); throw new \RuntimeException('Expected durable failure.'); }
                catch (\EDIS\EvidenceExporter\Application\DurableJobFailureException $exception) {
                    $job = $jobs->get($exception->jobId);
                    $previous = $exception->getPrevious();
                    $emit([
                        'scenario' => $scenario,
                        'exception_class' => $exception::class,
                        'job_id' => $exception->jobId,
                        'job_exists' => is_array($job),
                        'cursor_matches' => is_array($job) && $exception->failureCursor === \EDIS\EvidenceExporter\Application\JobFailureCursor::capture($job),
                        'creation_policy' => $exception->observation->creationPolicy,
                        'stage' => $exception->observation->lifecycleStage,
                        'reason_code' => $exception->observation->reasonCode,
                        'previous_class' => $previous !== null ? $previous::class : null,
                        'job_count' => count($jobs->jobsForUser(7)),
                    ]);
                }
            }
            if ($scenario === 'cursor_truth_unpersisted_mutation') {
                if (!defined('DISABLE_WP_CRON')) { define('DISABLE_WP_CRON', true); }
                $GLOBALS['edis_fixture_schedule_mode'] = 'throw';
                try { $boundary->create(7, $metadataRequest()); throw new \RuntimeException('Expected durable failure.'); }
                catch (\EDIS\EvidenceExporter\Application\DurableJobFailureException $exception) {
                    $job = $jobs->get($exception->jobId);
                    $cursorState = is_array($exception->failureCursor['state'] ?? null) ? $exception->failureCursor['state'] : [];
                    $persistedDiagnostics = is_array($job['diagnostics'] ?? null) ? $job['diagnostics'] : [];
                    $persistedCodes = array_values(array_filter(array_map(static fn (mixed $item): ?string => is_array($item) && is_string($item['code'] ?? null) ? $item['code'] : null, $persistedDiagnostics)));
                    $emit([
                        'scenario' => $scenario,
                        'job_id' => $exception->jobId,
                        'job_exists' => is_array($job),
                        'cursor_matches_persisted' => is_array($job) && $exception->failureCursor === \EDIS\EvidenceExporter\Application\JobFailureCursor::capture($job),
                        'cursor_diagnostic_code' => $cursorState['diagnostic_code'] ?? null,
                        'persisted_has_cron_diagnostic' => in_array('EDIS_WP_CRON_INTERNAL_TRIGGER_DISABLED', $persistedCodes, true),
                        'creation_policy' => $exception->observation->creationPolicy,
                        'stage' => $exception->observation->lifecycleStage,
                    ]);
                }
            }
            if ($scenario === 'cursor_truth_controller') {
                if (!defined('DISABLE_WP_CRON')) { define('DISABLE_WP_CRON', true); }
                $GLOBALS['edis_fixture_schedule_mode'] = 'throw';
                $response = $controller->create(new \WP_REST_Request($metadataRequest()));
                if (!$response instanceof \WP_Error) { throw new \RuntimeException('Expected WP_Error.'); }
                $error = $errorResult($response);
                $diagnosticId = is_string($error['data']['diagnostic_id'] ?? null) ? $error['data']['diagnostic_id'] : '';
                $resolved = $diagnosticId !== '' ? $diagnostics->resolveForOwner(7, $diagnosticId, static fn (int $documentId): bool => true) : null;
                $record = is_array($resolved['record'] ?? null) ? $resolved['record'] : [];
                $jobId = is_string($record['operation_identity']['job_id'] ?? null) ? $record['operation_identity']['job_id'] : '';
                $job = $jobId !== '' ? $jobs->get($jobId) : null;
                $evidenceTypes = array_values(array_filter(array_map(static fn (mixed $item): ?string => is_array($item) && is_string($item['source_type'] ?? null) ? $item['source_type'] : null, (array) ($record['evidence_and_provenance'] ?? []))));
                $timelineTypes = array_values(array_filter(array_map(static fn (mixed $item): ?string => is_array($item) && is_string($item['event_type'] ?? null) ? $item['event_type'] : null, (array) ($record['causal_timeline'] ?? []))));
                $persistedDiagnostics = is_array($job['diagnostics'] ?? null) ? $job['diagnostics'] : [];
                $persistedCodes = array_values(array_filter(array_map(static fn (mixed $item): ?string => is_array($item) && is_string($item['code'] ?? null) ? $item['code'] : null, $persistedDiagnostics)));
                $emit([
                    'scenario' => $scenario,
                    'error' => $error,
                    'job_id' => $jobId,
                    'job_exists' => is_array($job),
                    'failure_stage' => $record['failure_location']['lifecycle_stage'] ?? null,
                    'has_persisted_job_record' => in_array('PERSISTED_JOB_RECORD', $evidenceTypes, true),
                    'has_persisted_transition' => in_array('PERSISTED_JOB_TRANSITION', $evidenceTypes, true),
                    'timeline_event' => in_array('JOB_OPERATION_EXCEPTION_CAUGHT', $timelineTypes, true) ? 'JOB_OPERATION_EXCEPTION_CAUGHT' : null,
                    'persisted_has_cron_diagnostic' => in_array('EDIS_WP_CRON_INTERNAL_TRIGGER_DISABLED', $persistedCodes, true),
                    'diagnostic_count' => $diagnosticCount(),
                ]);
            }
            if ($scenario === 'cursor_truth_persisted_transition') {
                $jobId = '55555555-5555-4555-8555-555555555555';
                $created = $jobs->create($baseJob($jobId, 'queued'));
                $failureCursor = \EDIS\EvidenceExporter\Application\JobFailureCursor::capture($created);
                $changed = $jobs->get($jobId);
                if (!is_array($changed)) { throw new \RuntimeException('Persisted fixture Job missing.'); }
                $changed['status'] = 'failed';
                $changed['phase'] = 'failed';
                $changed['last_error_code'] = 'EDIS_REAL_PERSISTED_FAILURE';
                $changed['last_error_at'] = time();
                $changed['diagnostics'][] = [
                    'code' => 'EDIS_REAL_PERSISTED_FAILURE',
                    'severity' => 'ERROR',
                    'scope' => 'OPERATIONAL',
                    'message_key' => 'diagnostic.fixture.persisted_failure',
                    'context' => ['failure_phase' => 'fixture_persisted_failure'],
                ];
                $jobs->save($changed);
                $persisted = $jobs->get($jobId);
                $capture = $diagnostics->captureJobFailure(
                    7,
                    $jobId,
                    'EXPORT_ADVANCE',
                    '/fixture/persisted-transition',
                    new \RuntimeException('fixture persisted failure'),
                    'edis_export_action_failed',
                    'JOB_FAILURE',
                    $failureCursor,
                );
                $diagnosticId = is_string($capture['diagnostic_id'] ?? null) ? $capture['diagnostic_id'] : '';
                $resolved = $diagnosticId !== '' ? $diagnostics->resolveForOwner(7, $diagnosticId, static fn (int $documentId): bool => true) : null;
                $record = is_array($resolved['record'] ?? null) ? $resolved['record'] : [];
                $evidenceTypes = array_values(array_filter(array_map(static fn (mixed $item): ?string => is_array($item) && is_string($item['source_type'] ?? null) ? $item['source_type'] : null, (array) ($record['evidence_and_provenance'] ?? []))));
                $timelineTypes = array_values(array_filter(array_map(static fn (mixed $item): ?string => is_array($item) && is_string($item['event_type'] ?? null) ? $item['event_type'] : null, (array) ($record['causal_timeline'] ?? []))));
                $emit([
                    'scenario' => $scenario,
                    'job_id' => $jobId,
                    'cursor_changed' => is_array($persisted) && \EDIS\EvidenceExporter\Application\JobFailureCursor::changed($failureCursor, $persisted),
                    'has_persisted_transition' => in_array('PERSISTED_JOB_TRANSITION', $evidenceTypes, true),
                    'timeline_event' => in_array('JOB_FAILURE_RECORDED', $timelineTypes, true) ? 'JOB_FAILURE_RECORDED' : null,
                    'internal_code' => $record['failure_classification']['internal_code'] ?? null,
                    'diagnostic_count' => $diagnosticCount(),
                ]);
            }
            if ($scenario === 'durable_entire_site') {
                $GLOBALS['edis_fixture_schedule_mode'] = 'throw';
                $request = $metadataRequest(['document_ids' => [999], 'options' => ['export_scope' => 'ENTIRE_SITE', 'dependency_scope' => 'SOURCE_ONLY', 'document_inventory_limit' => 50]]);
                try { $boundary->create(7, $request); throw new \RuntimeException('Expected durable failure.'); }
                catch (\EDIS\EvidenceExporter\Application\DurableJobFailureException $exception) {
                    $job = $jobs->get($exception->jobId);
                    $emit([
                        'scenario' => $scenario,
                        'job_id' => $exception->jobId,
                        'job_exists' => is_array($job),
                        'persisted_selected_document_count' => (int) ($job['selected_document_count'] ?? -1),
                        'raw_request_document_count' => count($request['document_ids']),
                        'persisted_documents' => $job['config']['document_ids'] ?? [],
                        'creation_policy' => $exception->observation->creationPolicy,
                        'cursor_matches' => is_array($job) && $exception->failureCursor === \EDIS\EvidenceExporter\Application\JobFailureCursor::capture($job),
                    ]);
                }
            }
            if ($scenario === 'durable_overlap') {
                $GLOBALS['edis_fixture_schedule_mode'] = 'throw_with_decoy';
                try { $boundary->create(7, $metadataRequest()); throw new \RuntimeException('Expected durable failure.'); }
                catch (\EDIS\EvidenceExporter\Application\DurableJobFailureException $exception) {
                    $job = $jobs->get($exception->jobId);
                    $decoyId = '33333333-3333-4333-8333-333333333333';
                    $emit([
                        'scenario' => $scenario,
                        'job_id' => $exception->jobId,
                        'job_exists' => is_array($job),
                        'decoy_exists' => is_array($jobs->get($decoyId)),
                        'not_decoy' => $exception->jobId !== $decoyId,
                        'owner_job_count' => count($jobs->jobsForUser(7)),
                        'cursor_matches' => is_array($job) && $exception->failureCursor === \EDIS\EvidenceExporter\Application\JobFailureCursor::capture($job),
                        'creation_policy' => $exception->observation->creationPolicy,
                    ]);
                }
            }
            if ($scenario === 'prejob_before_persistence') {
                $request = $metadataRequest(['preflight_token' => 'fixture-proof-without-authority']);
                try { $boundary->create(7, $request); throw new \RuntimeException('Expected pre-Job failure.'); }
                catch (\EDIS\EvidenceExporter\Application\FailureObservationException $exception) {
                    $emit([
                        'scenario' => $scenario,
                        'exception_class' => $exception::class,
                        'creation_policy' => $exception->observation->creationPolicy,
                        'stage' => $exception->observation->lifecycleStage,
                        'reason_code' => $exception->observation->reasonCode,
                        'job_count' => count($jobs->jobsForUser(7)),
                        'has_job_id_property' => property_exists($exception->observation, 'jobId'),
                    ]);
                }
            }
            if ($scenario === 'positive_create') {
                $result = $boundary->create(7, $metadataRequest());
                $jobId = (string) ($result['job_id'] ?? '');
                $job = $jobs->get($jobId);
                $emit([
                    'scenario' => $scenario,
                    'job_id' => $jobId,
                    'status' => $result['status'] ?? null,
                    'job_exists' => is_array($job),
                    'schedule_state' => $job['schedule_state'] ?? null,
                    'diagnostic_count' => $diagnosticCount(),
                    'job_count' => count($jobs->jobsForUser(7)),
                ]);
            }
            throw new \InvalidArgumentException('Unknown fixture scenario: ' . $scenario);
        } catch (\Throwable $exception) {
            fwrite(STDERR, json_encode(['scenario' => $scenario, 'error_class' => $exception::class, 'error_message' => $exception->getMessage()], JSON_UNESCAPED_SLASHES) . PHP_EOL);
            exit(1);
        }
    }
}

namespace EDIS\EvidenceExporter\Tests\Unit {

use EDIS\EvidenceExporter\Admin\Settings\SettingsRepository;
use EDIS\EvidenceExporter\Application\ExpectedOperationRejection;
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
            $actual = $exception instanceof ExportIntegrityException ? $exception : ($exception->getPrevious() ?? $exception);
            self::assertInstanceOf(ExportIntegrityException::class, $actual);
            self::assertSame('EDIS_JOB_FORMAT_INCOMPATIBLE', $actual->diagnosticCode);
        }
    }

    public function testPreviousWorkerJobCannotResumeAfter315(): void
    {
        [$service] = $this->service();
        $reflection = new \ReflectionClass(ExportJobService::class);
        self::assertSame('3.7.15', $reflection->getConstant('IMPLEMENTATION_VERSION'));
        $method = new \ReflectionMethod($service, 'assertJobCompatible');
        try {
            $method->invoke($service, [
                'job_id' => 'previous-worker-job',
                'job_format_version' => '2.1.0',
                'implementation_version' => '3.7.14',
                'input_snapshot_format_version' => '2.0.0',
            ]);
            self::fail('Expected the 3.7.14 worker job to be rejected.');
        } catch (\Throwable $exception) {
            $actual = $exception instanceof ExportIntegrityException ? $exception : ($exception->getPrevious() ?? $exception);
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
            'implementation_version' => '3.7.15',
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
                'implementation_version' => '3.7.15',
                'observed_at' => '2026-07-30T00:00:00Z',
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
            $actual = $exception instanceof ExportIntegrityException ? $exception : ($exception->getPrevious() ?? $exception);
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
            'implementation_version' => '3.7.12',
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
            try { $service->resume('resume-atomic', 7); }
            catch (ExpectedOperationRejection) { $failed = true; }
            self::assertTrue($failed);
            self::assertSame($before, file_get_contents($path));
            $job = $store->get('resume-atomic');
            self::assertSame('failed', $job['status']);
            self::assertCount(0, $job['diagnostics']);
        } finally {
            $store->releaseLock($lock);
        }
    }

    public function testActiveLeaseRejectionDoesNotFailOrMutateJob(): void
    {
        [$service, , $inputs, $root] = $this->service();
        $store = new JobStore($root . '/jobs');
        $jobId = '11111111-1111-4111-8111-111111111111';
        $manifest = $inputs->capture($jobId, [], time() + 3600);
        $store->create($this->queuedJob($jobId, $manifest, ['lease_owner' => 'active-worker', 'lease_expires_at' => time() + 300, 'schedule_state' => 'SCHEDULED_RECOVERY']));
        $path = $root . '/jobs/' . $jobId . '.json';
        $before = file_get_contents($path);
        try {
            $service->advance($jobId, 7, null, 500);
            self::fail('Expected active lease rejection.');
        } catch (ExpectedOperationRejection $exception) {
            self::assertSame('edis_export_job_busy', $exception->publicCode);
            self::assertSame(409, $exception->httpStatus);
        }
        self::assertSame($before, file_get_contents($path));
        $after = $store->get($jobId);
        self::assertSame('queued', $after['status']);
        self::assertNull($after['last_error_code'] ?? null);
        self::assertSame([], $after['diagnostics']);
    }

    public function testStaleRevisionRejectionDoesNotAcquireLeaseOrMutateJob(): void
    {
        [$service, , $inputs, $root] = $this->service();
        $store = new JobStore($root . '/jobs');
        $jobId = '22222222-2222-4222-8222-222222222222';
        $manifest = $inputs->capture($jobId, [], time() + 3600);
        $created = $store->create($this->queuedJob($jobId, $manifest));
        $path = $root . '/jobs/' . $jobId . '.json';
        $before = file_get_contents($path);
        $staleRevision = (int) ($created['revision'] ?? 0) + 1;
        try {
            $service->advance($jobId, 7, $staleRevision, 500);
            self::fail('Expected stale revision rejection.');
        } catch (ExpectedOperationRejection $exception) {
            self::assertSame('edis_export_revision_conflict', $exception->publicCode);
            self::assertSame($staleRevision, $exception->publicData['expected_revision'] ?? null);
            self::assertSame((int) ($created['revision'] ?? 0), $exception->publicData['actual_revision'] ?? null);
        }
        self::assertSame($before, file_get_contents($path));
        $after = $store->get($jobId);
        self::assertSame('queued', $after['status']);
        self::assertNull($after['lease_owner'] ?? null);
        self::assertNull($after['last_error_code'] ?? null);
        self::assertSame([], $after['diagnostics']);
    }

    /** T-REJ-001 */
    public function testMaintenanceRejectionRemainsExpectedAcrossCreateBoundary(): void
    {
        $result = $this->fixture('maintenance_rejection');
        self::assertSame('edis_export_jobs_unavailable', $result['error']['code']);
        self::assertSame(409, $result['error']['data']['status']);
        self::assertSame('MAINTENANCE', $result['error']['data']['schedule_state']);
        $this->assertNoDiagnosticEnvelope($result['error']['data']);
        self::assertSame(0, $result['diagnostic_count']);
        self::assertSame(0, $result['job_count']);
    }

    /** T-REJ-002 */
    public function testFailedJobLockRejectionStaysExpectedThroughController(): void
    {
        $result = $this->fixture('failed_job_lock_rejection');
        self::assertSame('edis_export_job_busy', $result['error']['code']);
        self::assertSame(409, $result['error']['data']['status']);
        self::assertSame('LOCKED', $result['error']['data']['schedule_state']);
        $this->assertNoDiagnosticEnvelope($result['error']['data']);
        self::assertSame(0, $result['diagnostic_count']);
        self::assertTrue($result['job_unchanged']);
        self::assertTrue($result['diagnostics_unchanged']);
        self::assertTrue($result['last_error_code_unchanged']);
    }

    /** T-REJ-003 */
    public function testActiveLeaseAndStaleRevisionPreserveExactExpectedSemantics(): void
    {
        $lease = $this->fixture('active_lease_rejection');
        self::assertSame('edis_export_job_busy', $lease['error']['code']);
        self::assertSame(409, $lease['error']['data']['status']);
        self::assertSame('EDIS_ACTIVE_WORKER_LEASE', $lease['error']['data']['reason_code']);
        self::assertSame('SCHEDULED_RECOVERY', $lease['error']['data']['schedule_state']);
        $this->assertNoDiagnosticEnvelope($lease['error']['data']);
        self::assertSame(0, $lease['diagnostic_count']);
        self::assertTrue($lease['job_unchanged']);

        $revision = $this->fixture('stale_revision_rejection');
        self::assertSame('edis_export_revision_conflict', $revision['error']['code']);
        self::assertSame(409, $revision['error']['data']['status']);
        self::assertSame('EDIS_STALE_JOB_REVISION', $revision['error']['data']['reason_code']);
        self::assertSame($revision['requested_revision'], $revision['error']['data']['expected_revision']);
        self::assertIsInt($revision['error']['data']['actual_revision']);
        $this->assertNoDiagnosticEnvelope($revision['error']['data']);
        self::assertSame(0, $revision['diagnostic_count']);
        self::assertTrue($revision['job_unchanged']);
    }

    /** T-DUR-001 */
    public function testPostPersistenceSchedulingFailureCarriesExactDurableJob(): void
    {
        $result = $this->fixture('durable_exact');
        self::assertSame('EDIS\\EvidenceExporter\\Application\\DurableJobFailureException', $result['exception_class']);
        self::assertMatchesRegularExpression('/\A[a-f0-9-]{36}\z/D', $result['job_id']);
        self::assertTrue($result['job_exists']);
        self::assertTrue($result['cursor_matches']);
        self::assertSame('JOB_BOUND', $result['creation_policy']);
        self::assertSame('post_create_scheduling', $result['stage']);
        self::assertSame('EDIS_POST_CREATE_SCHEDULING_FAILED', $result['reason_code']);
        self::assertSame('RuntimeException', $result['previous_class']);
        self::assertSame(1, $result['job_count']);
    }

    /** T-DUR-002 */
    public function testEntireSiteAttributionUsesNormalizedPersistedJobNotRawDocumentCount(): void
    {
        $result = $this->fixture('durable_entire_site');
        self::assertTrue($result['job_exists']);
        self::assertSame('JOB_BOUND', $result['creation_policy']);
        self::assertTrue($result['cursor_matches']);
        self::assertSame(1, $result['raw_request_document_count']);
        self::assertSame(2, $result['persisted_selected_document_count']);
        self::assertSame([101, 102], $result['persisted_documents']);
    }

    /** T-DUR-003 */
    public function testOverlappingSameOwnerCreateCannotAmbiguateExactJobAttribution(): void
    {
        $result = $this->fixture('durable_overlap');
        self::assertTrue($result['job_exists']);
        self::assertTrue($result['decoy_exists']);
        self::assertTrue($result['not_decoy']);
        self::assertSame(2, $result['owner_job_count']);
        self::assertTrue($result['cursor_matches']);
        self::assertSame('JOB_BOUND', $result['creation_policy']);
    }

    /** T-DUR-004 */
    public function testMaterialFailureBeforePersistenceRemainsPreJobWithoutIdentity(): void
    {
        $result = $this->fixture('prejob_before_persistence');
        self::assertSame('EDIS\\EvidenceExporter\\Application\\FailureObservationException', $result['exception_class']);
        self::assertSame('PRE_JOB', $result['creation_policy']);
        self::assertSame('preflight_proof_verification', $result['stage']);
        self::assertSame('EDIS_PREFLIGHT_PROOF_AUTHORITY_UNAVAILABLE', $result['reason_code']);
        self::assertSame(0, $result['job_count']);
        self::assertFalse($result['has_job_id_property']);
    }

    /** T-POS-001 */
    public function testValidCreateStillPersistsAndSchedulesExistingRecovery(): void
    {
        $result = $this->fixture('positive_create');
        self::assertMatchesRegularExpression('/\A[a-f0-9-]{36}\z/D', $result['job_id']);
        self::assertSame('queued', $result['status']);
        self::assertTrue($result['job_exists']);
        self::assertSame('SCHEDULED_RECOVERY', $result['schedule_state']);
        self::assertSame(0, $result['diagnostic_count']);
        self::assertSame(1, $result['job_count']);
    }

    /** T-CUR-001 */
    public function testUnpersistedSchedulingMutationCannotContaminateFailureCursor(): void
    {
        $result = $this->fixture('cursor_truth_unpersisted_mutation');
        self::assertMatchesRegularExpression('/\A[a-f0-9-]{36}\z/D', $result['job_id']);
        self::assertTrue($result['job_exists']);
        self::assertTrue($result['cursor_matches_persisted']);
        self::assertNull($result['cursor_diagnostic_code']);
        self::assertFalse($result['persisted_has_cron_diagnostic']);
        self::assertSame('JOB_BOUND', $result['creation_policy']);
        self::assertSame('post_create_scheduling', $result['stage']);
    }

    /** T-CUR-002 */
    public function testControllerDiagnosticDoesNotInventPersistedTransitionFromUncommittedMutation(): void
    {
        $result = $this->fixture('cursor_truth_controller');
        self::assertSame('edis_export_post_create_failed', $result['error']['code']);
        self::assertSame(500, $result['error']['data']['status']);
        self::assertTrue($result['error']['data']['diagnostic_available']);
        self::assertMatchesRegularExpression('/\Aedis-diag-[a-f0-9]{32}\z/D', $result['error']['data']['diagnostic_id']);
        self::assertMatchesRegularExpression('/\A[a-f0-9-]{36}\z/D', $result['job_id']);
        self::assertTrue($result['job_exists']);
        self::assertSame('post_create_scheduling', $result['failure_stage']);
        self::assertTrue($result['has_persisted_job_record']);
        self::assertFalse($result['has_persisted_transition']);
        self::assertSame('JOB_OPERATION_EXCEPTION_CAUGHT', $result['timeline_event']);
        self::assertFalse($result['persisted_has_cron_diagnostic']);
        self::assertSame(1, $result['diagnostic_count']);
    }

    /** T-CUR-003 */
    public function testRealPersistedTransitionStillProducesPersistedTransitionEvidence(): void
    {
        $result = $this->fixture('cursor_truth_persisted_transition');
        self::assertMatchesRegularExpression('/\A[a-f0-9-]{36}\z/D', $result['job_id']);
        self::assertTrue($result['cursor_changed']);
        self::assertTrue($result['has_persisted_transition']);
        self::assertSame('JOB_FAILURE_RECORDED', $result['timeline_event']);
        self::assertSame('EDIS_REAL_PERSISTED_FAILURE', $result['internal_code']);
        self::assertSame(1, $result['diagnostic_count']);
    }

    /** T-DEV-001 */
    public function testPreoperationPersistedCursorMethodLockIsStructurallyEnforced(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string) file_get_contents($root . '/src/Application/ExportJobService.php');
        $createStart = strpos($source, 'public function create(int $ownerId, array $request): array');
        $advanceStart = strpos($source, 'public function advance(string $jobId');
        self::assertIsInt($createStart);
        self::assertIsInt($advanceStart);
        $create = substr($source, $createStart, $advanceStart - $createStart);

        $persistence = strpos($create, "'job_persistence'");
        $baseline = strpos($create, '$failureCursor = JobFailureCursor::capture($job);');
        $schedule = strpos($create, '$this->scheduleRecovery($job);');
        self::assertIsInt($persistence);
        self::assertIsInt($baseline);
        self::assertIsInt($schedule);
        self::assertLessThan($baseline, $persistence);
        self::assertLessThan($schedule, $baseline);
        self::assertSame(1, substr_count($create, 'JobFailureCursor::capture($job)'));
        self::assertStringContainsString('                $failureCursor,', $create);
        self::assertStringNotContainsString('$this->jobs->get(', $create);
    }

    public function testSelectedMethodDeviationGuardsRejectHeuristicOrUntypedRegression(): void
    {
        $root = dirname(__DIR__, 2);
        $boundarySource = (string) file_get_contents($root . '/src/Application/JobFailureCursor.php');
        self::assertStringNotContainsString('resolveSingleNewDurableJob', $boundarySource);
        self::assertStringNotContainsString('jobIdsForOwner', $boundarySource);
        self::assertStringNotContainsString('jobsForUser(', $boundarySource);
        self::assertStringNotContainsString('private readonly JobStore $jobs', $boundarySource);

        $boundary = substr($boundarySource, (int) strpos($boundarySource, 'final class ExportCreateConformance'));
        $expectedCatch = strpos($boundary, 'catch (ExpectedOperationRejection $exception)');
        $durableCatch = strpos($boundary, 'catch (DurableJobFailureException $exception)');
        $genericCatch = strpos($boundary, 'catch (\\Throwable $exception)');
        self::assertIsInt($expectedCatch);
        self::assertIsInt($durableCatch);
        self::assertIsInt($genericCatch);
        self::assertLessThan($genericCatch, $expectedCatch);
        self::assertLessThan($genericCatch, $durableCatch);

        $serviceSource = (string) file_get_contents($root . '/src/Application/ExportJobService.php');
        self::assertStringContainsString("'job_persistence'", $serviceSource);
        self::assertStringContainsString("'post_create_scheduling'", $serviceSource);
        self::assertStringContainsString('JobFailureCursor::capture($job)', $serviceSource);
        self::assertStringContainsString('throw new DurableJobFailureException(', $serviceSource);

        $controllerSource = (string) file_get_contents($root . '/src/Rest/DiagnosticExportJobController.php');
        $runStart = (int) strpos($controllerSource, 'private function runAction');
        $runEnd = (int) strpos($controllerSource, 'private function expectedActionResult');
        $runAction = substr($controllerSource, $runStart, $runEnd - $runStart);
        $typed = strpos($runAction, 'catch (ExpectedOperationRejection $rejection)');
        $generic = strpos($runAction, 'catch (\\Throwable $exception)');
        self::assertIsInt($typed);
        self::assertIsInt($generic);
        self::assertLessThan($generic, $typed);

        $bootstrap = (string) file_get_contents($root . '/src/Bootstrap.php');
        self::assertStringContainsString('new ExportCreateConformance($jobService, $registry)', $bootstrap);
        self::assertStringNotContainsString('new ExportCreateConformance($jobService, $registry, $jobStore)', $bootstrap);
    }

    /** @return array<string,mixed> */
    private function queuedJob(string $jobId, array $manifest, array $overrides = []): array
    {
        return array_replace([
            'job_id' => $jobId,
            'job_format_version' => '2.1.0',
            'implementation_version' => '3.7.15',
            'input_snapshot_format_version' => '2.0.0',
            'input_snapshot_id' => $jobId,
            'input_snapshot_sha256' => $manifest['snapshot_sha256'],
            'owner_id' => 7,
            'status' => 'queued',
            'phase' => 'initializing',
            'cursor' => 0,
            'selected_components' => [],
            'completed_components' => [],
            'completed_step_records' => [],
            'diagnostics' => [],
            'lease_owner' => null,
            'lease_expires_at' => null,
            'schedule_state' => 'NOT_SCHEDULED',
            'last_error_code' => null,
            'expires_at' => time() + 3600,
        ], $overrides);
    }

    /** @param array<string,mixed> $data */
    private function assertNoDiagnosticEnvelope(array $data): void
    {
        self::assertFalse($data['diagnostic_available']);
        self::assertNull($data['diagnostic_id']);
        self::assertNull($data['diagnostics_url']);
        self::assertNull($data['diagnostic_persistence_code']);
    }

    /** @return array<string,mixed> */
    private function fixture(string $scenario): array
    {
        $root = dirname(__DIR__, 2);
        $command = [PHP_BINARY, __FILE__, 'failure-fixture:' . $scenario];
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        self::assertIsResource($process, 'Could not start failure-conformance fixture.');
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        self::assertSame(0, $exit, $scenario . ' failed: ' . trim((string) $stderr));
        $decoded = json_decode(trim((string) $stdout), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame($scenario, $decoded['scenario'] ?? null);
        return $decoded;
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
}
