<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Domain;

final class CollectionResult implements \JsonSerializable
{
    /**
     * @param array<string, mixed>|list<mixed>|null $data
     * @param list<Diagnostic|array<string, mixed>> $diagnostics
     * @param list<array<string, scalar|array|null>> $sourceReferences
     * @param array<string, scalar|array|null> $provenance
     */
    public function __construct(
        public readonly string $componentId,
        public readonly TruthState $truthState,
        public readonly EvidenceAvailability $availability,
        public readonly ComponentType $componentType,
        public readonly array|null $data,
        public readonly array $diagnostics = [],
        public readonly array $sourceReferences = [],
        public readonly array $provenance = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'component_id' => $this->componentId,
            'component_type' => $this->componentType->value,
            'source_truth_state' => $this->truthState->value,
            'source_availability' => $this->availability->value,
            'data' => $this->data,
            'diagnostics' => $this->diagnostics,
            'source_references' => $this->sourceReferences,
            'provenance' => $this->provenance,
        ];
    }
}
