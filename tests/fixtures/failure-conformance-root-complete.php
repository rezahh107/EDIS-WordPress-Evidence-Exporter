<?php
declare(strict_types=1);

use EDIS\EvidenceExporter\Admin\Settings\SettingsRepository;
use EDIS\EvidenceExporter\Application\DiagnosticRecordService;
use EDIS\EvidenceExporter\Application\DurableJobFailureException;
use EDIS\EvidenceExporter\Application\ExportCreateConformance;
use EDIS\EvidenceExporter\Application\ExportJobService;
use EDIS\EvidenceExporter\Application\ExportService;
use EDIS\EvidenceExporter\Application\FailureObservationException;
use EDIS\EvidenceExporter\Application\JobFailureCursor;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Support\ArtifactStore;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\DiagnosticRecordStore;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportFileStore;
use EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use EDIS\EvidenceExporter\Rest\DiagnosticExportJobController;

if ($argc !== 2) {
    fwrite(STDERR, "usage: php failure-conformance-root-complete.php <scenario>\n");
    exit(64);
}

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
function is_wp_error(mixed $value): bool { return $value instanceof WP_Error; }
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
        if ($store instanceof JobStore) {
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
        throw new RuntimeException('fixture scheduling failure');
    }
    if ($mode === 'throw') {
        throw new RuntimeException('fixture scheduling failure');
    }
    return true;
}

$root = sys_get_temp_dir() . '/edis-failure-root-' . bin2hex(random_bytes(6));
$pluginRoot = dirname(__DIR__, 2) . '/';
require_once $pluginRoot . 'autoload.php';

/** @return array{0:ExportJobService,1:ExportCreateConformance,2:JobStore,3:DiagnosticRecordService,4:DiagnosticExportJobController,5:string} */
function fixtureServices(string $root, string $pluginRoot): array
{
    $definitions = require $pluginRoot . 'config/collectors.php';
    $registry = CollectorRegistry::fromDefinitions($definitions);
    $filesystem = new DeterministicFilesystem();
    $settings = new SettingsRepository();
    $jobs = new JobStore($root . '/jobs', $filesystem);
    $artifacts = new ArtifactStore($root . '/artifacts', $filesystem);
    $inputs = new InputSnapshotStore($root . '/inputs', null, $filesystem);
    $files = new ExportFileStore($settings, $root . '/bundles', $filesystem);
    $service = new ExportJobService(
        $registry,
        new ExportService($registry, $pluginRoot),
        $jobs,
        $artifacts,
        $files,
        $settings,
        $inputs,
        null,
        null,
    );
    $boundary = new ExportCreateConformance($service, $registry);
    $diagnosticStore = new DiagnosticRecordStore($root . '/diagnostics', $filesystem, 3600);
    $diagnostics = new DiagnosticRecordService($diagnosticStore, $jobs, $pluginRoot, $filesystem);
    $controller = new DiagnosticExportJobController($service, $boundary, $jobs, $diagnostics);
    $GLOBALS['edis_fixture_job_store'] = $jobs;
    return [$service, $boundary, $jobs, $diagnostics, $controller, $root . '/diagnostics'];
}

/** @return array<string,mixed> */
function metadataRequest(array $overrides = []): array
{
    return array_replace_recursive([
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
}

/** @return array<string,mixed> */
function baseJob(string $jobId, string $status = 'failed', array $overrides = []): array
{
    return array_replace([
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
}

function diagnosticCount(string $root): int
{
    $count = 0;
    if (!is_dir($root)) {
        return 0;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && str_starts_with($file->getFilename(), 'edis-diag-')) {
            $count++;
        }
    }
    return $count;
}

/** @return array<string,mixed> */
function errorResult(WP_Error $error): array
{
    $data = $error->get_error_data();
    return [
        'code' => $error->get_error_code(),
        'message' => $error->get_error_message(),
        'data' => is_array($data) ? $data : [],
    ];
}

/** @param array<string,mixed> $data */
function emitResult(array $data): never
{
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

function removeTree(string $path): void
{
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') { removeTree($path . '/' . $entry); }
    }
    @rmdir($path);
}

$scenario = $argv[1];
try {
    [$service, $boundary, $jobs, $diagnostics, $controller, $diagnosticRoot] = fixtureServices($root, $pluginRoot);

    if ($scenario === 'maintenance_rejection') {
        $GLOBALS['edis_fixture_options']['edis_evidence_accept_new_jobs'] = false;
        $response = $controller->create(new WP_REST_Request(metadataRequest()));
        if (!$response instanceof WP_Error) { throw new RuntimeException('Expected WP_Error.'); }
        emitResult(['scenario' => $scenario, 'error' => errorResult($response), 'diagnostic_count' => diagnosticCount($diagnosticRoot), 'job_count' => count($jobs->jobsForUser(7))]);
    }

    if ($scenario === 'failed_job_lock_rejection') {
        $jobId = '11111111-1111-4111-8111-111111111111';
        $jobs->create(baseJob($jobId, 'failed'));
        $before = $jobs->get($jobId);
        $lock = $jobs->acquireLock($jobId, 0);
        if (!is_resource($lock)) { throw new RuntimeException('Could not acquire fixture lock.'); }
        try {
            $response = $controller->resume(new WP_REST_Request(['job_id' => $jobId]));
        } finally {
            $jobs->releaseLock($lock);
        }
        if (!$response instanceof WP_Error) { throw new RuntimeException('Expected WP_Error.'); }
        $after = $jobs->get($jobId);
        emitResult([
            'scenario' => $scenario,
            'error' => errorResult($response),
            'diagnostic_count' => diagnosticCount($diagnosticRoot),
            'job_unchanged' => $before === $after,
            'diagnostics_unchanged' => ($before['diagnostics'] ?? null) === ($after['diagnostics'] ?? null),
            'last_error_code_unchanged' => ($before['last_error_code'] ?? null) === ($after['last_error_code'] ?? null),
        ]);
    }

    if ($scenario === 'active_lease_rejection') {
        $jobId = '22222222-2222-4222-8222-222222222222';
        $jobs->create(baseJob($jobId, 'queued', [
            'lease_owner' => 'worker-active',
            'lease_expires_at' => time() + 300,
            'schedule_state' => 'SCHEDULED_RECOVERY',
        ]));
        $before = $jobs->get($jobId);
        $response = $controller->advance(new WP_REST_Request(['job_id' => $jobId, 'revision' => $before['revision']]));
        if (!$response instanceof WP_Error) { throw new RuntimeException('Expected WP_Error.'); }
        emitResult(['scenario' => $scenario, 'error' => errorResult($response), 'diagnostic_count' => diagnosticCount($diagnosticRoot), 'job_unchanged' => $before === $jobs->get($jobId)]);
    }

    if ($scenario === 'stale_revision_rejection') {
        $jobId = '44444444-4444-4444-8444-444444444444';
        $jobs->create(baseJob($jobId, 'queued'));
        $before = $jobs->get($jobId);
        $requested = (int) ($before['revision'] ?? 0) + 7;
        $response = $controller->advance(new WP_REST_Request(['job_id' => $jobId, 'revision' => $requested]));
        if (!$response instanceof WP_Error) { throw new RuntimeException('Expected WP_Error.'); }
        emitResult(['scenario' => $scenario, 'error' => errorResult($response), 'diagnostic_count' => diagnosticCount($diagnosticRoot), 'job_unchanged' => $before === $jobs->get($jobId), 'requested_revision' => $requested]);
    }

    if ($scenario === 'durable_exact') {
        $GLOBALS['edis_fixture_schedule_mode'] = 'throw';
        try {
            $boundary->create(7, metadataRequest());
            throw new RuntimeException('Expected durable failure.');
        } catch (DurableJobFailureException $exception) {
            $job = $jobs->get($exception->jobId);
            $previous = $exception->getPrevious();
            emitResult([
                'scenario' => $scenario,
                'exception_class' => $exception::class,
                'job_id' => $exception->jobId,
                'job_exists' => is_array($job),
                'cursor_matches' => is_array($job) && $exception->failureCursor === JobFailureCursor::capture($job),
                'creation_policy' => $exception->observation->creationPolicy,
                'stage' => $exception->observation->lifecycleStage,
                'reason_code' => $exception->observation->reasonCode,
                'previous_class' => $previous !== null ? $previous::class : null,
                'job_count' => count($jobs->jobsForUser(7)),
            ]);
        }
    }

    if ($scenario === 'durable_entire_site') {
        $GLOBALS['edis_fixture_schedule_mode'] = 'throw';
        $request = metadataRequest([
            'document_ids' => [999],
            'options' => ['export_scope' => 'ENTIRE_SITE', 'dependency_scope' => 'SOURCE_ONLY', 'document_inventory_limit' => 50],
        ]);
        try {
            $boundary->create(7, $request);
            throw new RuntimeException('Expected durable failure.');
        } catch (DurableJobFailureException $exception) {
            $job = $jobs->get($exception->jobId);
            emitResult([
                'scenario' => $scenario,
                'job_id' => $exception->jobId,
                'job_exists' => is_array($job),
                'persisted_selected_document_count' => (int) ($job['selected_document_count'] ?? -1),
                'raw_request_document_count' => count($request['document_ids']),
                'persisted_documents' => $job['config']['document_ids'] ?? [],
                'creation_policy' => $exception->observation->creationPolicy,
                'cursor_matches' => is_array($job) && $exception->failureCursor === JobFailureCursor::capture($job),
            ]);
        }
    }

    if ($scenario === 'durable_overlap') {
        $GLOBALS['edis_fixture_schedule_mode'] = 'throw_with_decoy';
        try {
            $boundary->create(7, metadataRequest());
            throw new RuntimeException('Expected durable failure.');
        } catch (DurableJobFailureException $exception) {
            $job = $jobs->get($exception->jobId);
            $decoyId = '33333333-3333-4333-8333-333333333333';
            emitResult([
                'scenario' => $scenario,
                'job_id' => $exception->jobId,
                'job_exists' => is_array($job),
                'decoy_exists' => is_array($jobs->get($decoyId)),
                'not_decoy' => $exception->jobId !== $decoyId,
                'owner_job_count' => count($jobs->jobsForUser(7)),
                'cursor_matches' => is_array($job) && $exception->failureCursor === JobFailureCursor::capture($job),
                'creation_policy' => $exception->observation->creationPolicy,
            ]);
        }
    }

    if ($scenario === 'prejob_before_persistence') {
        $request = metadataRequest(['preflight_token' => 'fixture-proof-without-authority']);
        try {
            $boundary->create(7, $request);
            throw new RuntimeException('Expected pre-Job failure.');
        } catch (FailureObservationException $exception) {
            emitResult([
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
        $GLOBALS['edis_fixture_schedule_mode'] = 'success';
        $result = $boundary->create(7, metadataRequest());
        $jobId = (string) ($result['job_id'] ?? '');
        $job = $jobs->get($jobId);
        emitResult([
            'scenario' => $scenario,
            'job_id' => $jobId,
            'status' => $result['status'] ?? null,
            'job_exists' => is_array($job),
            'schedule_state' => $job['schedule_state'] ?? null,
            'diagnostic_count' => diagnosticCount($diagnosticRoot),
            'job_count' => count($jobs->jobsForUser(7)),
        ]);
    }

    throw new InvalidArgumentException('Unknown scenario: ' . $scenario);
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode([
        'scenario' => $scenario,
        'error_class' => $exception::class,
        'error_message' => $exception->getMessage(),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
} finally {
    removeTree($root);
}
