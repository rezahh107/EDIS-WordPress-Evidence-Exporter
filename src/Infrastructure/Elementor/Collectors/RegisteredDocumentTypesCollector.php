<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Elementor\Collectors;

use EDIS\EvidenceExporter\Domain\CollectionResult;
use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;
use EDIS\EvidenceExporter\Domain\Diagnostic;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;

final class RegisteredDocumentTypesCollector implements EvidenceCollector
{
    public function id(): string { return 'elementor_registered_document_types'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $manager = class_exists('Elementor\\Plugin') && isset(\Elementor\Plugin::$instance) ? (\Elementor\Plugin::$instance->documents ?? null) : null;
        if (!is_object($manager)) {
            return new CollectionResult($this->id(), TruthState::PARTIAL, EvidenceAvailability::UNAVAILABLE, ComponentType::SOURCE_COLLECTOR, null, [new Diagnostic('EDIS_DOCUMENT_MANAGER_UNAVAILABLE', 'WARNING', 'SEMANTIC', 'diagnostic.elementor.document_manager_unavailable')]);
        }

        $usableMethods = [];
        foreach (['get_document_types', 'get_document_type_classes'] as $method) {
            if (method_exists($manager, $method)) { $usableMethods[] = $method; }
        }
        if ($usableMethods === []) {
            return new CollectionResult($this->id(), TruthState::PARTIAL, EvidenceAvailability::UNAVAILABLE, ComponentType::SOURCE_COLLECTOR, null, [new Diagnostic('EDIS_DOCUMENT_TYPE_API_UNAVAILABLE', 'WARNING', 'SEMANTIC', 'diagnostic.elementor.document_type_api_unavailable')]);
        }

        $types = null;
        $retrievalMethod = null;
        $failures = [];
        foreach ($usableMethods as $method) {
            try {
                $candidate = $manager->{$method}();
            } catch (\Throwable $exception) {
                $failures[] = get_class($exception);
                continue;
            }
            if (!is_array($candidate)) {
                $failures[] = 'UNUSABLE_RETURN_TYPE';
                continue;
            }
            $types = $candidate;
            $retrievalMethod = $method;
            break;
        }
        if (!is_array($types) || !is_string($retrievalMethod)) {
            throw new \RuntimeException('All usable Elementor document-type observation APIs failed.');
        }

        $rows = [];
        foreach ($types as $key => $value) {
            $rows[] = [
                'type' => (string) $key,
                'class' => is_string($value) ? $value : (is_object($value) ? get_class($value) : null),
                'observed_registration' => true,
            ];
        }
        usort($rows, static fn(array $a, array $b): int => strcmp($a['type'], $b['type']));
        $availability = $rows === [] ? EvidenceAvailability::INSUFFICIENT : EvidenceAvailability::AVAILABLE;
        $diagnostics = $rows === []
            ? [new Diagnostic('EDIS_DOCUMENT_TYPE_REGISTRY_EMPTY', 'INFO', 'SEMANTIC', 'diagnostic.elementor.document_type_registry_empty')]
            : [];
        if ($failures !== []) {
            $diagnostics[] = new Diagnostic(
                'EDIS_DOCUMENT_TYPE_FALLBACK_USED',
                'WARNING',
                'SEMANTIC',
                'diagnostic.elementor.document_type_fallback_used',
                ['failed_attempt_count' => count($failures), 'successful_method' => $retrievalMethod],
            );
        }
        return new CollectionResult(
            $this->id(),
            TruthState::PARTIAL,
            $availability,
            ComponentType::SOURCE_COLLECTOR,
            ['document_types' => $rows, 'count' => count($rows)],
            $diagnostics,
            [],
            ['collector_id' => $this->id(), 'adapter_id' => 'elementor.documents-manager', 'adapter_version' => '1.1.0', 'source_kind' => 'ELEMENTOR_REGISTRY', 'retrieval_strategy' => $retrievalMethod],
        );
    }
}
