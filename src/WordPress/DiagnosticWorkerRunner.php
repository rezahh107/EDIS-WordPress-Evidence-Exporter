<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\WordPress;

use EDIS\EvidenceExporter\Application\DiagnosticRecordService;
use EDIS\EvidenceExporter\Application\ExportJobService;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;

final class DiagnosticWorkerRunner
{
    private readonly ?\Closure $processJob;

    public function __construct(
        private readonly ExportJobService $worker,
        private readonly JobStore $jobs,
        private readonly DiagnosticRecordService $diagnostics,
        ?callable $processJob = null,
    ) {
        $this->processJob = $processJob !== null ? \Closure::fromCallable($processJob) : null;
    }

    public function process(string $jobId): void
    {
        $before = $this->jobs->get($jobId);
        if ($this->processJob instanceof \Closure) {
            ($this->processJob)($jobId);
        } else {
            $this->worker->process($jobId);
        }
        $after = $this->jobs->get($jobId);
        $afterSignature = $this->failureSignature($after);
        if ($afterSignature === null) {
            return;
        }
        $beforeSignature = $this->failureSignature($before);
        if ($beforeSignature !== null && hash_equals($beforeSignature, $afterSignature)) {
            return;
        }
        $this->diagnostics->captureJobFailure(
            (int) ($after['owner_id'] ?? 0),
            $jobId,
            'BACKGROUND_WORKER_PROCESS',
            null,
            new \RuntimeException('Background worker failure was persisted by the Job state machine.'),
            'edis_background_worker_failed',
            'WORKER_FAILURE',
        );
    }

    /** @param array<string,mixed>|null $job */
    private function failureSignature(?array $job): ?string
    {
        if (!is_array($job) || ($job['status'] ?? null) !== 'failed') {
            return null;
        }
        return implode("\0", [
            (string) ($job['job_id'] ?? ''),
            (string) ($job['last_error_code'] ?? ''),
            (string) ($job['phase'] ?? ''),
            is_scalar($job['current_component'] ?? null) ? (string) $job['current_component'] : '',
        ]);
    }
}
