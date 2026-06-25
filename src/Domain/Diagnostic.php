<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Domain;

final class Diagnostic implements \JsonSerializable
{
    /** @param array<string, scalar|array|null> $context */
    public function __construct(
        public readonly string $code,
        public readonly string $severity,
        public readonly string $scope,
        public readonly string $messageKey,
        public readonly array $context = [],
    ) {
        if (!in_array($severity, ['INFO', 'WARNING', 'ERROR'], true)) {
            throw new \InvalidArgumentException('Invalid diagnostic severity.');
        }
        if (!in_array($scope, ['SEMANTIC', 'OPERATIONAL'], true)) {
            throw new \InvalidArgumentException('Invalid diagnostic scope.');
        }
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity,
            'scope' => $this->scope,
            'message_key' => $this->messageKey,
            'context' => $this->context === [] ? (object) [] : $this->context,
        ];
    }
}
