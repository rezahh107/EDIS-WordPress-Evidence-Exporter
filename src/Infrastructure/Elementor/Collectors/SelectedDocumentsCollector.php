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

final class SelectedDocumentsCollector implements EvidenceCollector
{
    public function id(): string { return 'elementor_selected_documents'; }
    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        return new CollectionResult($this->id(), TruthState::UNKNOWN, EvidenceAvailability::UNAVAILABLE, ComponentType::SOURCE_COLLECTOR, null, [new Diagnostic('EDIS_LEGACY_COMPONENT_REPLACED', 'INFO', 'OPERATIONAL', 'diagnostic.component.replaced', ['replacement' => 'elementor_document_source'])]);
    }
}
