<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support\Json;

use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;

final class LosslessJsonScalarNode implements LosslessJsonNode
{
    public function __construct(private readonly mixed $value)
    {
        if (!is_string($value) && !is_bool($value) && $value !== null) {
            throw new \InvalidArgumentException('Lossless JSON scalar must be string, boolean, or null.');
        }
    }

    public function kind(): string
    {
        return $this->value === null ? 'null' : (is_bool($this->value) ? 'boolean' : 'string');
    }

    public function canonicalJson(): string
    {
        return CanonicalJson::encode($this->value);
    }

    public function toNative(): mixed { return $this->value; }
    public function toProcessingValue(): mixed { return $this->value; }
    public function jsonSerialize(): mixed { return $this->value; }
}
