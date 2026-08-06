<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Application;

final class DurableJobFailureException extends \RuntimeException
{
    /** @param array{revision:int,signature:string,state:array<string,mixed>}|null $failureCursor */
    public function __construct(
        public readonly string $jobId,
        public readonly FailureObservation $observation,
        public readonly ?array $failureCursor,
        ?\Throwable $previous = null,
    ) {
        if (preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/D', $jobId) !== 1) {
            throw new \InvalidArgumentException('Durable Job failure requires an exact UUID Job identity.');
        }
        if ($observation->creationPolicy !== 'JOB_BOUND') {
            throw new \InvalidArgumentException('Durable Job failure must use JOB_BOUND policy.');
        }
        parent::__construct('EDIS failure after durable Job confirmation.', 0, $previous);
    }
}
