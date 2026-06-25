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
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\UrlNormalizer;

final class RegisteredDocumentTypesCollector implements EvidenceCollector
{
    public function id(): string { return 'elementor_registered_document_types'; }
    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $manager = class_exists('Elementor\\Plugin') && isset(\Elementor\Plugin::$instance) ? (\Elementor\Plugin::$instance->documents ?? null) : null;
        $types = [];
        if (is_object($manager)) {
            foreach (['get_document_types', 'get_document_type_classes'] as $method) {
                if (!method_exists($manager, $method)) { continue; }
                try { $candidate = $manager->{$method}(); } catch (\Throwable $e) { $candidate = []; }
                if (is_array($candidate)) { $types = $candidate; break; }
            }
        }
        $rows = [];
        foreach ($types as $key => $value) { $rows[] = ['type' => (string) $key, 'class' => is_string($value) ? $value : (is_object($value) ? get_class($value) : null), 'observed_registration' => true]; }
        usort($rows, static fn(array $a, array $b): int => strcmp($a['type'], $b['type']));
        $availability = $rows === [] ? EvidenceAvailability::INSUFFICIENT : EvidenceAvailability::AVAILABLE;
        return new CollectionResult($this->id(), TruthState::PARTIAL, $availability, ComponentType::SOURCE_COLLECTOR, ['document_types' => $rows, 'count' => count($rows)], $rows === [] ? [new Diagnostic('EDIS_DOCUMENT_TYPE_REGISTRY_EMPTY', 'INFO', 'SEMANTIC', 'diagnostic.elementor.document_type_registry_empty')] : [], [], ['collector_id' => $this->id(), 'adapter_id' => 'elementor.documents-manager', 'adapter_version' => '1.0.0', 'source_kind' => 'ELEMENTOR_REGISTRY', 'retrieval_strategy' => 'bounded_manager_introspection']);
    }
}
