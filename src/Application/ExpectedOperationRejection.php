<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Application;

final class ExpectedOperationRejection extends \RuntimeException
{
    /** @param array<string,scalar|null> $publicData */
    public function __construct(
        public readonly string $publicCode,
        public readonly int $httpStatus,
        public readonly array $publicData = [],
    ) {
        if (preg_match('/\Aedis_[a-z0-9_]{3,120}\z/D', $publicCode) !== 1) {
            throw new \InvalidArgumentException('Invalid public rejection code.');
        }
        if ($httpStatus < 400 || $httpStatus > 499) {
            throw new \InvalidArgumentException('Expected rejection must use a 4xx status.');
        }
        parent::__construct('EDIS expected operation rejection.');
    }
}
