<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Admin\Settings;

final class SettingsRepository
{
    public const OPTION_PRIVACY_MODE = 'edis_evidence_default_privacy_mode';
    public const OPTION_COLLECTORS = 'edis_evidence_default_collectors';
    public const OPTION_RETENTION_HOURS = 'edis_evidence_retention_hours';
    public const OPTION_DIAGNOSTIC_METADATA = 'edis_evidence_include_diagnostic_metadata';
    public const OPTION_ORIGINAL_DOCUMENTS = 'edis_evidence_include_original_documents';
    public const OPTION_CLEANUP = 'edis_evidence_cleanup_enabled';
    public const OPTION_RETAIN_UNINSTALL = 'edis_evidence_retain_data_on_uninstall';

    public function defaultPrivacyMode(): string
    {
        $value = get_option(self::OPTION_PRIVACY_MODE, 'Standard');
        return is_string($value) && in_array($value, ['Strict', 'Standard', 'Diagnostic'], true) ? $value : 'Standard';
    }

    /** @return list<string> */
    public function defaultCollectors(): array
    {
        $value = get_option(self::OPTION_COLLECTORS, ['environment', 'plugin', 'theme', 'elementor_installation']);
        if (!is_array($value)) {
            return ['environment', 'plugin', 'theme', 'elementor_installation'];
        }
        $items = array_values(array_unique(array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== '')));
        sort($items, SORT_STRING);
        return $items;
    }

    public function retentionHours(): int
    {
        $value = (int) get_option(self::OPTION_RETENTION_HOURS, 24);
        return max(1, min(168, $value));
    }

    public function includeDiagnosticMetadata(): bool
    {
        return (bool) get_option(self::OPTION_DIAGNOSTIC_METADATA, false);
    }

    public function includeOriginalDocuments(): bool
    {
        return (bool) get_option(self::OPTION_ORIGINAL_DOCUMENTS, false);
    }

    public function cleanupEnabled(): bool
    {
        return (bool) get_option(self::OPTION_CLEANUP, true);
    }

    public function retainDataOnUninstall(): bool
    {
        return (bool) get_option(self::OPTION_RETAIN_UNINSTALL, true);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return [
            'privacy_mode' => $this->defaultPrivacyMode(),
            'collectors' => $this->defaultCollectors(),
            'retention_hours' => $this->retentionHours(),
            'include_diagnostic_metadata' => $this->includeDiagnosticMetadata(),
            'include_original_documents' => $this->includeOriginalDocuments(),
            'cleanup_enabled' => $this->cleanupEnabled(),
            'retain_data_on_uninstall' => $this->retainDataOnUninstall(),
        ];
    }
}
