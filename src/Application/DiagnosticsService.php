<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Application;

use EDIS\EvidenceExporter\Admin\Settings\SettingsRepository;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Support\ArtifactStore;
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportFileStore;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore;

final class DiagnosticsService
{
    private DeterministicFilesystem $filesystem;

    public function __construct(
        private readonly CollectorRegistry $registry,
        private readonly JobStore $jobs,
        private readonly ArtifactStore $artifacts,
        private readonly ExportFileStore $files,
        private readonly SettingsRepository $settings,
        private readonly InputSnapshotStore $inputs,
        private readonly ExportJobService $worker,
        private readonly string $pluginRoot,
        ?DeterministicFilesystem $filesystem = null,
    ) {
        $this->filesystem = $filesystem ?? new DeterministicFilesystem();
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        global $wp_version;
        $latest = function_exists('is_user_logged_in') && is_user_logged_in() ? $this->jobs->latestForUser(get_current_user_id()) : null;
        $cronDisabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $componentCount = count($this->registry->ids());
        /* translators: %d: Number of registered evidence components. */
        $componentDetail = sprintf(__('%d components registered', 'edis-evidence-exporter'), $componentCount);
        $checks = [
            $this->check('wordpress_version', version_compare((string) $wp_version, '6.5', '>='), __('WordPress compatibility', 'edis-evidence-exporter'), (string) $wp_version),
            $this->check('php_version', version_compare(PHP_VERSION, '8.2', '>=') && version_compare(PHP_VERSION, '8.6', '<'), __('PHP compatibility', 'edis-evidence-exporter'), PHP_VERSION . ' (verified range: 8.2–8.5)'),
            $this->check('php_64_bit', PHP_INT_SIZE >= 8, __('64-bit PHP runtime', 'edis-evidence-exporter'), PHP_INT_SIZE >= 8 ? __('Available', 'edis-evidence-exporter') : __('Unavailable', 'edis-evidence-exporter')),
            $this->check('canonical_json_environment', CanonicalJson::environmentReady(), __('Deterministic JSON environment', 'edis-evidence-exporter'), CanonicalJson::environmentReady() ? __('EDIS-CJ-2 ready', 'edis-evidence-exporter') : __('Unsupported PHP version, serialize_precision, fsync, or integer width', 'edis-evidence-exporter')),
            $this->check('manifest', $this->manifestValid(), __('Manifest loading', 'edis-evidence-exporter'), $this->manifestValid() ? __('Loaded', 'edis-evidence-exporter') : __('Invalid or unreadable', 'edis-evidence-exporter')),
            $this->check('registry', $componentCount > 0, __('Component registry', 'edis-evidence-exporter'), $componentDetail),
            $this->check('jobs_writable', $this->jobs->rootWritable(), __('Job storage', 'edis-evidence-exporter'), $this->jobs->rootWritable() ? __('Writable', 'edis-evidence-exporter') : __('Not writable', 'edis-evidence-exporter')),
            $this->check('artifacts_writable', $this->artifacts->rootWritable(), __('Artifact storage', 'edis-evidence-exporter'), $this->artifacts->rootWritable() ? __('Writable', 'edis-evidence-exporter') : __('Not writable', 'edis-evidence-exporter')),
            $this->check('input_snapshots_writable', $this->inputs->rootWritable(), __('Immutable input snapshot storage', 'edis-evidence-exporter'), $this->inputs->rootWritable() ? __('Writable', 'edis-evidence-exporter') : __('Not writable', 'edis-evidence-exporter')),
            $this->check('bundles_writable', $this->files->rootWritable(), __('Bundle storage', 'edis-evidence-exporter'), $this->files->rootWritable() ? __('Writable', 'edis-evidence-exporter') : __('Not writable', 'edis-evidence-exporter')),
            $this->check('zip', $this->files->zipBackendAvailable(), __('ZIP backend', 'edis-evidence-exporter'), $this->files->zipBackendAvailable() ? __('Available', 'edis-evidence-exporter') : __('Unavailable', 'edis-evidence-exporter')),
            $this->check('json', function_exists('json_encode'), __('JSON support', 'edis-evidence-exporter'), function_exists('json_encode') ? __('Available', 'edis-evidence-exporter') : __('Unavailable', 'edis-evidence-exporter')),
            $this->check('rest', function_exists('register_rest_route'), __('REST API', 'edis-evidence-exporter'), function_exists('register_rest_route') ? __('Available', 'edis-evidence-exporter') : __('Unavailable', 'edis-evidence-exporter')),
            $this->check('executor', true, __('Job executor mode', 'edis-evidence-exporter'), __('Hybrid REST worker with WP-Cron recovery', 'edis-evidence-exporter')),
            $this->check('cron', !$cronDisabled, __('WP-Cron recovery', 'edis-evidence-exporter'), $cronDisabled ? __('Disabled; REST worker remains available', 'edis-evidence-exporter') : __('Enabled as recovery', 'edis-evidence-exporter'), $cronDisabled ? 'warning' : 'pass'),
            $this->check('stale_queued', count($this->jobs->staleJobs('queued')) === 0, __('Stale queued jobs', 'edis-evidence-exporter'), (string) count($this->jobs->staleJobs('queued')), count($this->jobs->staleJobs('queued')) === 0 ? 'pass' : 'warning'),
            $this->check('stale_running', count($this->jobs->staleJobs('running')) === 0, __('Stale running jobs', 'edis-evidence-exporter'), (string) count($this->jobs->staleJobs('running')), count($this->jobs->staleJobs('running')) === 0 ? 'pass' : 'warning'),
            $this->check('cleanup', $this->settings->cleanupEnabled(), __('Automatic cleanup', 'edis-evidence-exporter'), $this->settings->cleanupEnabled() ? __('Enabled', 'edis-evidence-exporter') : __('Disabled by setting', 'edis-evidence-exporter'), $this->settings->cleanupEnabled() ? 'pass' : 'warning'),
        ];
        $counts = ['pass' => 0, 'warning' => 0, 'error' => 0];
        foreach ($checks as $check) { $counts[$check['state']] = ($counts[$check['state']] ?? 0) + 1; }
        return [
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'plugin_version' => EDIS_EVIDENCE_EXPORTER_VERSION,
            'platform_version' => EDIS_EVIDENCE_BUILD_PLATFORM_VERSION,
            'checks' => $checks,
            'summary' => $counts,
            'latest_job' => $latest,
            'worker' => [
                'mode' => 'HYBRID_REST_WITH_CRON_RECOVERY',
                'wp_cron_disabled' => $cronDisabled,
                'last_heartbeat' => is_array($latest) ? ($latest['last_heartbeat'] ?? null) : null,
                'last_successful_step_at' => is_array($latest) ? ($latest['last_successful_step_at'] ?? null) : null,
                'schedule_state' => is_array($latest) ? ($latest['schedule_state'] ?? null) : null,
                'schedule_error' => is_array($latest) ? ($latest['schedule_error'] ?? null) : null,
            ],
            'privacy_safe' => true,
        ];
    }

    /** @return array<string, int> */
    public function summary(): array { return $this->report()['summary']; }

    /** @return array<string, mixed> */
    public function workerTest(int $ownerId): array { return $this->worker->safeWorkerTest($ownerId); }

    /** @return array<string, mixed> */
    private function check(string $id, bool $passed, string $label, string $detail, ?string $state = null): array
    {
        return ['id' => $id, 'label' => $label, 'state' => $state ?? ($passed ? 'pass' : 'error'), 'detail' => $detail];
    }

    private function manifestValid(): bool
    {
        $path = $this->pluginRoot . 'plugin.manifest.json';
        if (!is_file($path)) { return false; }
        try {
            $raw = $this->filesystem->read($path);
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return false;
        }
        return is_array($decoded) && ($decoded['plugin']['version'] ?? null) === EDIS_EVIDENCE_EXPORTER_VERSION;
    }
}
