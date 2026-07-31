<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

final class ExportIntegrityException extends \RuntimeException
{
    /** @param array<string,mixed> $diagnosticContext */
    public function __construct(
        public readonly string $diagnosticCode,
        string $message,
        ?\Throwable $previous = null,
        public readonly array $diagnosticContext = [],
    ) {
        parent::__construct($message, 0, $previous);
    }
}
