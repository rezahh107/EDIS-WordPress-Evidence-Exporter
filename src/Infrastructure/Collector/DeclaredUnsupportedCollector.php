<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Collector;

use EDIS\EvidenceExporter\Domain\CollectionResult;
use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;
use EDIS\EvidenceExporter\Domain\Diagnostic;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;

final class DeclaredUnsupportedCollector implements EvidenceCollector
{
    public function __construct(
        private readonly string $componentId,
        private readonly ComponentType $componentType,
        private readonly TruthState $truthState,
        private readonly EvidenceAvailability $availability,
        private readonly string $sourceKind,
    ) {
    }

    public function id(): string
    {
        return $this->componentId;
    }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        return new CollectionResult(
            $this->componentId,
            $this->truthState,
            $this->availability,
            $this->componentType,
            null,
            [new Diagnostic('EDIS_COMPONENT_NOT_IMPLEMENTED', 'INFO', 'SEMANTIC', 'diagnostic.component_not_implemented')],
            [['source_kind' => $this->sourceKind]],
        );
    }
}
