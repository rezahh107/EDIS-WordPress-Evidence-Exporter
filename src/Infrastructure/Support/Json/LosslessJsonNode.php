<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support\Json;

interface LosslessJsonNode extends \JsonSerializable
{
    public function kind(): string;
    public function canonicalJson(): string;
    public function toNative(): mixed;
    public function toProcessingValue(): mixed;
}
