<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\WordPress;

use EDIS\EvidenceExporter\Application\DiagnosticRecordService;
use EDIS\EvidenceExporter\Application\ExportJobService;
use EDIS\EvidenceExporter\Application\JobFailureCursor;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;

final class DiagnosticWorkerRunner
{
    /** @var \Closure(string):void */
    private readonly \Closure $processJob;

    public function __construct(
        ExportJobService $service,
        private readonly JobStore $jobs,
        private readonly DiagnosticRecordService $diagnostics,
        ?callable $processJob = null,
    ) {
        $this->processJob = $processJob !== null
            ? \Closure::fromCallable($processJob)
            : static fn (string $jobId): mixed => $service->process($jobId);
    }

    public function process(string $jobId): void
    {
        $before = $this->jobs->get($jobId);
        $failureCursor = is_array($before) ? JobFailureCursor::capture($before) : null;

        ($this->processJob)($jobId);

        $after = $this->jobs->get($jobId);
        if (!is_array($after)
            || (string) ($after['status'] ?? '') !== 'failed'
            || !JobFailureCursor::changed($failureCursor, $after)
        ) {
            return;
        }

        $jobIdValue = (string) ($after['job_id'] ?? '');
        $ownerId = (int) ($after['owner_id'] ?? 0);
        if ($ownerId <= 0 || preg_match('/\A[a-f0-9-]{36}\z/D', $jobIdValue) !== 1) {
            return;
        }

        $this->diagnostics->captureJobFailure(
            $ownerId,
            $jobIdValue,
            'BACKGROUND_WORKER',
            null,
            new \RuntimeException('The background Worker persisted a failed Job transition.'),
            null,
            'WORKER_FAILURE',
            $failureCursor,
        );
    }
}
