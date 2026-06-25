<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support\Json;

use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;

final class LosslessJsonObjectNode implements LosslessJsonNode
{
    /** @param list<array{0:string,1:LosslessJsonNode}> $members */
    public function __construct(private readonly array $members) {}

    public function kind(): string { return 'object'; }
    public function canonicalJson(): string
    {
        $members = $this->members;
        usort($members, static fn (array $a, array $b): int => CanonicalJson::compareObjectKeys($a[0], $b[0]));
        $parts = [];
        foreach ($members as [$key, $value]) {
            $parts[] = CanonicalJson::encode($key) . ':' . $value->canonicalJson();
        }
        return '{' . implode(',', $parts) . '}';
    }
    public function toNative(): object
    {
        $object = new \stdClass();
        foreach ($this->members as [$key, $value]) { $object->{$key} = $value->toNative(); }
        return $object;
    }
    public function toProcessingValue(): mixed
    {
        if ($this->members === []) { return new \stdClass(); }
        foreach ($this->members as [$key]) {
            if (preg_match('/^(?:0|[1-9][0-9]*)$/D', $key) === 1) {
                return $this;
            }
        }
        $result = [];
        foreach ($this->members as [$key, $value]) { $result[$key] = $value->toProcessingValue(); }
        return $result;
    }
    public function jsonSerialize(): mixed { return $this->toNative(); }
    /** @return list<array{0:string,1:LosslessJsonNode}> */
    public function members(): array { return $this->members; }
}
