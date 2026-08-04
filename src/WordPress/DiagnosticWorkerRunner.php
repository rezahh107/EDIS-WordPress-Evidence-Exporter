<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\WordPress;

use EDIS\EvidenceExporter\Application\DiagnosticRecordService;
use EDIS\EvidenceExporter\Application\ExportJobService;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;

final class DiagnosticWorkerRunner
{
    public function __construct(
        private readonly ExportJobService $worker,
        private readonly JobStore $jobs,
        private readonly DiagnosticRecordService $diagnostics,
    ) {
    }

    public function process(string $jobId): void
    {
        $before = $this->jobs->get($jobId);
        $beforeErrorAt = is_array($before) ? (int) ($before['last_error_at'] ?? 0) : 0;
        $this->worker->process($jobId);
        $after = $this->jobs->get($jobId);
        if (!is_array($after)
            || ($after['status'] ?? null) !== 'failed'
            || (int) ($after['last_error_at'] ?? 0) <= $beforeErrorAt) {
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
}
