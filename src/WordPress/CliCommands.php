<?php
/**
 * WP-CLI operational commands.
 *
 * @package EDIS\EvidenceExporter
 */
declare(strict_types=1);

namespace EDIS\EvidenceExporter\WordPress;

use EDIS\EvidenceExporter\Application\DiagnosticsService;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;

/**
 * Register bounded WP-CLI operational and recovery commands.
 */
final class CliCommands {
    public function __construct(
        private readonly DiagnosticWorkerRunner $worker,
        private readonly JobStore $jobs,
        private readonly DiagnosticsService $diagnostics,
        private readonly PrivateStorage $storage,
    ) {}

    public function register(): void {
        if (!defined('WP_CLI') || !WP_CLI || !class_exists('\\WP_CLI')) {
            return;
        }
        \WP_CLI::add_command( 'edis status', [$this, 'status'] );
        \WP_CLI::add_command( 'edis worker run', [$this, 'workerRun'] );
        \WP_CLI::add_command( 'edis worker status', [$this, 'workerStatus'] );
        \WP_CLI::add_command( 'edis jobs repair', [$this, 'jobsRepair'] );
        \WP_CLI::add_command( 'edis storage self-test', [$this, 'storageSelfTest'] );
        \WP_CLI::add_command( 'edis storage paths', [$this, 'storagePaths'] );
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function status(array $args, array $assocArgs): void {
        $this->printJson($this->diagnostics->report());
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function workerRun(array $args, array $assocArgs): void {
        $jobId = isset($assocArgs['job_id']) ? sanitize_text_field((string) $assocArgs['job_id']) : '';
        $ids = $jobId !== '' ? [$jobId] : $this->jobs->runnableJobIds(10);
        foreach ($ids as $id) {
            // WP-CLI uses the same occurrence-aware boundary as Cron. Calling
            // ExportJobService::process() directly would bypass canonical
            // Worker Diagnostic handling.
            $this->worker->process($id);
        }
        \WP_CLI::success(sprintf(
            /* translators: %d: processed job count. */
            __('Processed %d eligible Job(s).', 'edis-evidence-exporter'),
            count($ids),
        ));
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function workerStatus(array $args, array $assocArgs): void {
        $this->printJson(['runnable' => $this->jobs->runnableJobIds(100), 'stale' => $this->jobs->staleJobs()]);
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function jobsRepair(array $args, array $assocArgs): void {
        $apply = isset($assocArgs['apply']);
        $result = $this->jobs->repairStaleJobs($apply);
        $this->printJson($result);
        if (!$apply) {
            \WP_CLI::warning(__('Dry run only. Re-run with --apply to queue repairable stale Jobs.', 'edis-evidence-exporter'));
        }
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function storageSelfTest(array $args, array $assocArgs): void {
        $result = $this->storage->selfTest(true);
        $this->printJson([
            'diagnostic_context' => $this->storage->diagnosticContext(),
            'self_test' => $result,
        ]);
        if (!$this->storage->acceptsSelfTestResult($result)) {
            \WP_CLI::error(__('EDIS storage self-test failed.', 'edis-evidence-exporter'));
        }
        \WP_CLI::success(__('EDIS storage self-test passed.', 'edis-evidence-exporter'));
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function storagePaths(array $args, array $assocArgs): void {
        $this->printJson($this->storage->diagnosticContext());
    }

    /** @param array<string,mixed> $data */
    private function printJson(array $data): void {
        $encoded = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            \WP_CLI::error(__('EDIS could not encode the command result.', 'edis-evidence-exporter'));
        }
        \WP_CLI::line($encoded);
    }
}
