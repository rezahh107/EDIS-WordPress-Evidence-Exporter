<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support\Json;

final class LosslessJsonParseException extends \RuntimeException
{
    public function __construct(
        public readonly string $diagnosticCode,
        public readonly int $offset,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
