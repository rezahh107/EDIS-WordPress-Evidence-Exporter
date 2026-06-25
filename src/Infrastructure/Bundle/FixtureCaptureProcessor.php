<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Bundle;

use EDIS\EvidenceExporter\Domain\CollectionResult;
use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;

/** Creates bounded authoring metadata for a controlled real Elementor fixture. */
final class FixtureCaptureProcessor implements EvidenceCollector
{
    public function id(): string { return 'fixture_capture'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        $enabled = $context->boolOption('fixture_mode', false);
        if (!$enabled) {
            return new CollectionResult(
                $this->id(), TruthState::VERIFIED, EvidenceAvailability::NOT_APPLICABLE,
                ComponentType::BUNDLE_PROCESSOR,
                ['enabled' => false, 'fixture_verification_state' => 'NOT_REQUESTED', 'environment_notes' => (object) [], 'expected_behavior_template' => [], 'documents' => []],
                [], [], $this->provenance(),
            );
        }

        $environment = is_array($artifacts['environment']['data'] ?? null) ? $artifacts['environment']['data'] : [];
        $installation = is_array($artifacts['elementor_installation']['data'] ?? null) ? $artifacts['elementor_installation']['data'] : [];
        $selection = is_array($artifacts['selection_snapshot']['data'] ?? null) ? $artifacts['selection_snapshot']['data'] : [];
        $documents = is_array($artifacts['elementor_document_source']['data']['documents'] ?? null) ? $artifacts['elementor_document_source']['data']['documents'] : [];
        $documentNotes = [];
        foreach ($documents as $document) {
            if (!is_array($document)) { continue; }
            $documentNotes[] = [
                'document_id' => (string) ($document['document_id'] ?? ''),
                'document_type' => $document['document_type'] ?? null,
                'architecture_kinds' => $document['architecture_kinds'] ?? [],
                'canonical_saved_source_sha256' => $document['canonical_saved_source_sha256'] ?? null,
            ];
        }
        usort($documentNotes, static fn(array $a, array $b): int => strcmp((string) $a['document_id'], (string) $b['document_id']));

        return new CollectionResult(
            $this->id(),
            TruthState::VERIFIED,
            $documentNotes === [] && $context->exportScope() !== 'METADATA_ONLY' ? EvidenceAvailability::INSUFFICIENT : EvidenceAvailability::AVAILABLE,
            ComponentType::BUNDLE_PROCESSOR,
            [
                'enabled' => true,
                'fixture_verification_state' => 'UNVERIFIED_REAL_FIXTURE',
                'environment_notes' => (object) [
                    'wordpress_version' => $environment['wordpress_version'] ?? null,
                    'php_version' => $environment['php_version'] ?? PHP_VERSION,
                    'elementor_version' => $installation['elementor_version'] ?? null,
                    'elementor_pro_version' => $installation['elementor_pro_version'] ?? null,
                    'privacy_mode' => $context->privacyMode,
                    'export_scope' => $context->exportScope(),
                    'dependency_scope' => $context->dependencyScope(),
                    'selected_document_ids' => $selection['selected_document_ids'] ?? array_map('strval', $context->selectedDocumentIds),
                ],
                'documents' => $documentNotes,
                'expected_behavior_template' => [
                    'Record only settings deliberately configured by the fixture author.',
                    'Do not describe expected Python findings unless backed by a versioned rule fixture.',
                    'Record Elementor, Elementor Pro, WordPress, PHP, theme, active addons, breakpoints and editor mode.',
                    'Attach screenshots only when they contain no secrets or private content.',
                ],
            ],
            [], [], $this->provenance(),
        );
    }

    /** @return array<string,string> */
    private function provenance(): array
    {
        return [
            'collector_id' => $this->id(),
            'adapter_id' => 'edis.fixture-authoring-metadata',
            'adapter_version' => '1.0.0',
            'source_kind' => 'DERIVED_FIXTURE_METADATA',
            'retrieval_strategy' => 'bounded_projection_of_export_environment_and_selection',
        ];
    }
}
