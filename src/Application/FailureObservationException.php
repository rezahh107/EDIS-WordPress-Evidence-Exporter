<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Application;

final class FailureObservationException extends \RuntimeException
{
    public function __construct(
        public readonly FailureObservation $observation,
        ?\Throwable $previous = null,
    ) {
        parent::__construct('EDIS operation failure observation.', 0, $previous);
    }
}
