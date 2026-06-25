<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Admin\Settings;

use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;

final class SettingsSanitizer
{
    public function __construct(private readonly CollectorRegistry $registry)
    {
    }

    public function privacyMode(mixed $value): string
    {
        $mode = is_string($value) ? sanitize_text_field($value) : '';
        if (!in_array($mode, ['Strict', 'Standard', 'Diagnostic'], true)) {
            add_settings_error(SettingsRepository::OPTION_PRIVACY_MODE, 'invalid_privacy_mode', __('Invalid privacy mode. The safe Standard default was used.', 'edis-evidence-exporter'));
            return 'Standard';
        }
        return $mode;
    }

    /** @return list<string> */
    public function collectors(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $allowed = array_fill_keys($this->registry->executableIds(), true);
        $clean = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }
            $id = sanitize_key($item);
            if (isset($allowed[$id])) {
                $clean[$id] = true;
            }
        }
        $ids = array_keys($clean);
        sort($ids, SORT_STRING);
        return $ids;
    }

    public function retentionHours(mixed $value): int
    {
        $hours = absint($value);
        if ($hours < 1 || $hours > 168) {
            add_settings_error(SettingsRepository::OPTION_RETENTION_HOURS, 'invalid_retention', __('Retention must be between 1 and 168 hours.', 'edis-evidence-exporter'));
        }
        return max(1, min(168, $hours));
    }

    public function boolean(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'yes', 'on'], true);
    }
}
