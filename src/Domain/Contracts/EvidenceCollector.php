<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Domain\Contracts;

use EDIS\EvidenceExporter\Domain\CollectionResult;

interface EvidenceCollector
{
    public function id(): string;

    /** @param array<string, array<string, mixed>> $artifacts */
    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult;
}
