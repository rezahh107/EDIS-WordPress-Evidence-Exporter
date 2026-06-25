<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support\Json;

final class LosslessJsonArrayNode implements LosslessJsonNode
{
    /** @param list<LosslessJsonNode> $items */
    public function __construct(private readonly array $items) {}

    public function kind(): string { return 'array'; }
    public function canonicalJson(): string
    {
        return '[' . implode(',', array_map(static fn (LosslessJsonNode $node): string => $node->canonicalJson(), $this->items)) . ']';
    }
    /** @return list<mixed> */
    public function toNative(): array { return array_map(static fn (LosslessJsonNode $node): mixed => $node->toNative(), $this->items); }
    /** @return list<mixed> */
    public function toProcessingValue(): array { return array_map(static fn (LosslessJsonNode $node): mixed => $node->toProcessingValue(), $this->items); }
    public function jsonSerialize(): mixed { return $this->toNative(); }
    /** @return list<LosslessJsonNode> */
    public function items(): array { return $this->items; }
}
