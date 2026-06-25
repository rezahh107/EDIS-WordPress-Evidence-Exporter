<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Collector;

use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;

final class CollectorDefinition implements \JsonSerializable
{
    /**
     * @param list<array{id:string,kind:string,condition:?string}> $dependencies
     * @param array<string, mixed> $documentation
     * @param \Closure(): EvidenceCollector $factory
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $labelFa,
        public readonly string $description,
        public readonly string $descriptionFa,
        public readonly string $group,
        public readonly ComponentType $componentType,
        public readonly TruthState $declaredTruthState,
        public readonly EvidenceAvailability $defaultAvailability,
        public readonly string $implementation,
        public readonly array $dependencies,
        public readonly string $schemaId,
        public readonly string $schemaVersion,
        public readonly string $sourceKind,
        public readonly bool $selectable,
        public readonly bool $defaultEnabled,
        public readonly string $artifactPath,
        public readonly array $documentation,
        public readonly \Closure $factory,
    ) {
        if ($id === '' || $label === '' || $labelFa === '' || $group === '' || $artifactPath === '') {
            throw new \InvalidArgumentException('Component identity metadata is required.');
        }
        if (!in_array($implementation, ['real', 'generated'], true)) {
            throw new \InvalidArgumentException('Component implementation must be real or generated.');
        }
        foreach ($dependencies as $dependency) {
            if (!is_array($dependency) || !isset($dependency['id'], $dependency['kind'])) {
                throw new \InvalidArgumentException('Invalid component dependency declaration.');
            }
            if (!in_array($dependency['kind'], ['REQUIRED', 'OPTIONAL', 'CONDITIONAL'], true)) {
                throw new \InvalidArgumentException('Invalid component dependency kind.');
            }
        }
    }

    /** @return list<string> */
    public function dependencyIds(): array
    {
        return array_values(array_map(static fn (array $item): string => (string) $item['id'], $this->dependencies));
    }

    /** @return array<string, mixed> */
    public function localizedDocumentation(bool $rtl): array
    {
        $key = $rtl ? 'fa' : 'en';
        $value = $this->documentation[$key] ?? [];
        return is_array($value) ? $value : [];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'label_fa' => $this->labelFa,
            'description' => $this->description,
            'description_fa' => $this->descriptionFa,
            'group' => $this->group,
            'component_type' => $this->componentType->value,
            'source_truth_state' => $this->declaredTruthState->value,
            'default_availability' => $this->defaultAvailability->value,
            'implementation' => $this->implementation,
            'dependencies' => $this->dependencies,
            'schema_id' => $this->schemaId,
            'schema_version' => $this->schemaVersion,
            'source_kind' => $this->sourceKind,
            'selectable' => $this->selectable,
            'default_enabled' => $this->defaultEnabled,
            'artifact_path' => $this->artifactPath,
            'executable' => $this->implementation === 'real' && $this->declaredTruthState !== TruthState::UNSUPPORTED,
            'documentation' => $this->documentation,
        ];
    }
}
