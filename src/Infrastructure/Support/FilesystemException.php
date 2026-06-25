<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

final class FilesystemException extends \RuntimeException
{
    public function __construct(
        public readonly string $diagnosticCode,
        public readonly string $operation,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
