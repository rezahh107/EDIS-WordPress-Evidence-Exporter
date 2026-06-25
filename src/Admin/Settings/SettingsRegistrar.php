<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Admin\Settings;

use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;

final class SettingsRegistrar
{
    private const GROUP = 'edis_evidence_settings';
    private const PAGE = 'edis-evidence-settings';

    public function __construct(
        private readonly SettingsRepository $repository,
        private readonly SettingsSanitizer $sanitizer,
        private readonly CollectorRegistry $registry,
    ) {
    }

    public function register(): void
    {
        register_setting(self::GROUP, SettingsRepository::OPTION_PRIVACY_MODE, [
            'type' => 'string', 'default' => 'Standard', 'sanitize_callback' => [$this->sanitizer, 'privacyMode'],
        ]);
        register_setting(self::GROUP, SettingsRepository::OPTION_COLLECTORS, [
            'type' => 'array', 'default' => ['environment', 'plugin', 'theme', 'elementor_installation'], 'sanitize_callback' => [$this->sanitizer, 'collectors'],
        ]);
        register_setting(self::GROUP, SettingsRepository::OPTION_RETENTION_HOURS, [
            'type' => 'integer', 'default' => 24, 'sanitize_callback' => [$this->sanitizer, 'retentionHours'],
        ]);
        foreach ([SettingsRepository::OPTION_DIAGNOSTIC_METADATA, SettingsRepository::OPTION_ORIGINAL_DOCUMENTS, SettingsRepository::OPTION_CLEANUP, SettingsRepository::OPTION_RETAIN_UNINSTALL] as $option) {
            register_setting(self::GROUP, $option, ['type' => 'boolean', 'default' => in_array($option, [SettingsRepository::OPTION_CLEANUP, SettingsRepository::OPTION_RETAIN_UNINSTALL], true), 'sanitize_callback' => [$this->sanitizer, 'boolean']]);
        }

        add_settings_section('edis_evidence_defaults', __('Export defaults', 'edis-evidence-exporter'), [$this, 'sectionDescription'], self::PAGE);
        add_settings_field(SettingsRepository::OPTION_PRIVACY_MODE, __('Default privacy mode', 'edis-evidence-exporter'), [$this, 'privacyField'], self::PAGE, 'edis_evidence_defaults');
        add_settings_field(SettingsRepository::OPTION_COLLECTORS, __('Default collectors', 'edis-evidence-exporter'), [$this, 'collectorsField'], self::PAGE, 'edis_evidence_defaults');
        add_settings_field(SettingsRepository::OPTION_RETENTION_HOURS, __('Temporary export retention', 'edis-evidence-exporter'), [$this, 'retentionField'], self::PAGE, 'edis_evidence_defaults');
        add_settings_field(SettingsRepository::OPTION_DIAGNOSTIC_METADATA, __('Diagnostic metadata', 'edis-evidence-exporter'), [$this, 'diagnosticField'], self::PAGE, 'edis_evidence_defaults');
        add_settings_field(SettingsRepository::OPTION_ORIGINAL_DOCUMENTS, __('Original documents', 'edis-evidence-exporter'), [$this, 'originalField'], self::PAGE, 'edis_evidence_defaults');
        add_settings_field(SettingsRepository::OPTION_CLEANUP, __('Automatic cleanup', 'edis-evidence-exporter'), [$this, 'cleanupField'], self::PAGE, 'edis_evidence_defaults');
        add_settings_field(SettingsRepository::OPTION_RETAIN_UNINSTALL, __('Uninstall data retention', 'edis-evidence-exporter'), [$this, 'retainUninstallField'], self::PAGE, 'edis_evidence_defaults');
    }

    public function reset(): void
    {
        if (!current_user_can('edis_export_evidence')) {
            wp_die(esc_html__('You do not have permission to reset these settings.', 'edis-evidence-exporter'), '', ['response' => 403]);
        }
        check_admin_referer('edis_evidence_reset_settings');
        foreach ([
            SettingsRepository::OPTION_PRIVACY_MODE,
            SettingsRepository::OPTION_COLLECTORS,
            SettingsRepository::OPTION_RETENTION_HOURS,
            SettingsRepository::OPTION_DIAGNOSTIC_METADATA,
            SettingsRepository::OPTION_ORIGINAL_DOCUMENTS,
            SettingsRepository::OPTION_CLEANUP,
            SettingsRepository::OPTION_RETAIN_UNINSTALL,
        ] as $option) {
            delete_option($option);
        }
        wp_safe_redirect(add_query_arg(['page' => self::PAGE, 'settings-reset' => '1'], admin_url('admin.php')));
        exit;
    }

    public function sectionDescription(): void
    {
        echo '<p>' . esc_html__('These values prefill new exports. Every export can still be reviewed before it starts.', 'edis-evidence-exporter') . '</p>';
    }

    public function privacyField(): void
    {
        $current = $this->repository->defaultPrivacyMode();
        echo '<select name="' . esc_attr(SettingsRepository::OPTION_PRIVACY_MODE) . '">';
        foreach (['Strict', 'Standard', 'Diagnostic'] as $mode) {
            echo '<option value="' . esc_attr($mode) . '" ' . selected($current, $mode, false) . '>' . esc_html($mode) . '</option>';
        }
        echo '</select><p class="description">' . esc_html__('Strict minimizes sensitive evidence. Diagnostic can include more technical metadata.', 'edis-evidence-exporter') . '</p>';
    }

    public function collectorsField(): void
    {
        $selected = array_fill_keys($this->repository->defaultCollectors(), true);
        echo '<fieldset><legend class="screen-reader-text">' . esc_html__('Choose default collectors', 'edis-evidence-exporter') . '</legend>';
        foreach ($this->registry->definitions() as $definition) {
            if (!$this->registry->isExecutable($definition->id)) {
                continue;
            }
            echo '<label class="edis-settings-collector"><input type="checkbox" name="' . esc_attr(SettingsRepository::OPTION_COLLECTORS) . '[]" value="' . esc_attr($definition->id) . '" ' . checked(isset($selected[$definition->id]), true, false) . '> ' . esc_html($definition->label) . ' <code>' . esc_html($definition->id) . '</code></label>';
        }
        echo '</fieldset><p class="description">' . esc_html__('Only executable manifest-declared collectors can be saved as defaults.', 'edis-evidence-exporter') . '</p>';
    }

    public function retentionField(): void
    {
        echo '<input class="small-text" type="number" min="1" max="168" name="' . esc_attr(SettingsRepository::OPTION_RETENTION_HOURS) . '" value="' . esc_attr((string) $this->repository->retentionHours()) . '"> ' . esc_html__('hours', 'edis-evidence-exporter');
    }

    public function diagnosticField(): void
    {
        $this->checkbox(SettingsRepository::OPTION_DIAGNOSTIC_METADATA, $this->repository->includeDiagnosticMetadata(), __('Include diagnostic metadata by default.', 'edis-evidence-exporter'));
    }

    public function originalField(): void
    {
        $this->checkbox(SettingsRepository::OPTION_ORIGINAL_DOCUMENTS, $this->repository->includeOriginalDocuments(), __('Include original saved Elementor documents by default. This is unavailable in Strict mode.', 'edis-evidence-exporter'));
    }

    public function cleanupField(): void
    {
        $this->checkbox(SettingsRepository::OPTION_CLEANUP, $this->repository->cleanupEnabled(), __('Remove expired temporary bundles automatically.', 'edis-evidence-exporter'));
    }

    public function retainUninstallField(): void
    {
        $this->checkbox(SettingsRepository::OPTION_RETAIN_UNINSTALL, $this->repository->retainDataOnUninstall(), __('Retain completed evidence and private operational data when the plugin is deleted. Disable only when uninstall must remove all EDIS data.', 'edis-evidence-exporter'));
    }

    private function checkbox(string $name, bool $checked, string $label): void
    {
        echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . '> ' . esc_html($label) . '</label>';
    }
}
