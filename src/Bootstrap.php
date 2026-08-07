<?php
/**
 * Plugin composition root.
 *
 * @package EDIS\EvidenceExporter
 */
declare(strict_types=1);

namespace EDIS\EvidenceExporter;

use EDIS\EvidenceExporter\Admin\AdminModule;
use EDIS\EvidenceExporter\Admin\DiagnosticAdminAssets;
use EDIS\EvidenceExporter\Admin\DiagnosticDownloadController;
use EDIS\EvidenceExporter\Admin\Settings\SettingsRegistrar;
use EDIS\EvidenceExporter\Admin\Settings\SettingsRepository;
use EDIS\EvidenceExporter\Admin\Settings\SettingsSanitizer;
use EDIS\EvidenceExporter\Application\DiagnosticRecordService;
use EDIS\EvidenceExporter\Application\DiagnosticsService;
use EDIS\EvidenceExporter\Application\DocumentQueryService;
use EDIS\EvidenceExporter\Application\ExportCreateConformance;
use EDIS\EvidenceExporter\Application\ExportJobService;
use EDIS\EvidenceExporter\Application\ExportService;
use EDIS\EvidenceExporter\Elementor\InspectorModule;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Support\ArtifactStore;
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\DiagnosticRecordStore;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportFileStore;
use EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore;
use EDIS\EvidenceExporter\Infrastructure\Support\InstallationIntegrity;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;
use EDIS\EvidenceExporter\Infrastructure\Support\SelectionTokenStore;
use EDIS\EvidenceExporter\Rest\CanonicalDiagnosticResponseAdapter;
use EDIS\EvidenceExporter\Rest\DiagnosticExportJobController;
use EDIS\EvidenceExporter\Rest\DiagnosticsController;
use EDIS\EvidenceExporter\Rest\DocumentController;
use EDIS\EvidenceExporter\Rest\ExportJobController;
use EDIS\EvidenceExporter\Rest\InspectorSelectionController;
use EDIS\EvidenceExporter\WordPress\CliCommands;
use EDIS\EvidenceExporter\WordPress\DegradedModeIntegration;
use EDIS\EvidenceExporter\WordPress\DiagnosticWorkerRunner;
use EDIS\EvidenceExporter\WordPress\PrivacyIntegration;
use EDIS\EvidenceExporter\WordPress\RuntimeContext;
use EDIS\EvidenceExporter\WordPress\SiteHealthIntegration;
use EDIS\EvidenceExporter\WordPress\WorkerRecovery;

/**
 * Compose and register request-scoped plugin services.
 */
final class Bootstrap {
    /**
     * @param string $pluginRoot Absolute plugin root with trailing slash.
     */
    public function __construct(private readonly string $pluginRoot) {}

    /**
     * Register services required by the current WordPress runtime context.
     */
    public function boot(): void {
        if (!CanonicalJson::environmentReady()) {
            throw new \RuntimeException('EDIS requires a verified deterministic 64-bit PHP 8.2-8.5 runtime with fsync and mutable serialize_precision.');
        }

        load_plugin_textdomain('edis-evidence-exporter', false, dirname(plugin_basename($this->pluginRoot . 'edis-evidence-exporter.php')) . '/languages');

        $runtime = new RuntimeContext();
        if (!$runtime->requiresApplicationRuntime()) {
            return;
        }

        $privateStorage = new PrivateStorage();
        $integrity = InstallationIntegrity::verify($this->pluginRoot);
        if ('PASS' !== ($integrity['state'] ?? 'FAIL')) {
            (new DegradedModeIntegration($privateStorage, (string) ($integrity['code'] ?? 'EDIS_INSTALLATION_INTEGRITY_FAILED'), $integrity))->register();
            return;
        }

        $collectorDefinitions = require $this->pluginRoot . 'config/collectors.php';
        $adminConfig = require $this->pluginRoot . 'config/admin.php';
        if (!is_array($collectorDefinitions) || !is_array($adminConfig)) {
            (new DegradedModeIntegration($privateStorage, 'EDIS_CONFIGURATION_INVALID'))->register();
            return;
        }

        $registry = CollectorRegistry::fromDefinitions($collectorDefinitions);
        $settings = new SettingsRepository();
        $settingsRegistrar = new SettingsRegistrar($settings, new SettingsSanitizer($registry), $registry);
        $storageTest = $privateStorage->selfTest();
        if (!$privateStorage->acceptsSelfTestResult($storageTest)) {
            $context = $privateStorage->diagnosticContext();
            $context['storage_self_test'] = $storageTest;
            $context['required_security_state'] = 'OUTSIDE_WEB_ROOT';
            $context['required_multiprocess_lock_exclusion'] = 'PASS';
            (new DegradedModeIntegration($privateStorage, 'EDIS_PRIVATE_STORAGE_UNAVAILABLE', $context))->register();
            return;
        }

        $filesystem = new DeterministicFilesystem();
        $jobStore = new JobStore($privateStorage->path('jobs'), $filesystem);
        $artifactStore = new ArtifactStore($privateStorage->path('artifacts'), $filesystem);
        $fileStore = new ExportFileStore($settings, $privateStorage->path('bundles'), $filesystem);
        $selectionTokens = new SelectionTokenStore($privateStorage->path('selections'), 600, $filesystem);
        $inputSnapshots = new InputSnapshotStore($privateStorage->path('inputs'), null, $filesystem);
        $diagnosticStore = new DiagnosticRecordStore($privateStorage->path('diagnostics'), $filesystem, $settings->retentionHours() * 3600);
        $diagnosticRecords = new DiagnosticRecordService($diagnosticStore, $jobStore, $this->pluginRoot, $filesystem);
        $exportService = new ExportService($registry, $this->pluginRoot);
        $jobService = new ExportJobService($registry, $exportService, $jobStore, $artifactStore, $fileStore, $settings, $inputSnapshots, $privateStorage);
        $createBoundary = new ExportCreateConformance($jobService, $registry);
        $documentService = new DocumentQueryService();
        $diagnostics = new DiagnosticsService($registry, $jobStore, $artifactStore, $fileStore, $settings, $inputSnapshots, $jobService, $this->pluginRoot, $filesystem, $diagnosticRecords);
        $capability = (string) $adminConfig['capability'];
        $workerRecovery = new WorkerRecovery($jobStore);
        $diagnosticWorker = new DiagnosticWorkerRunner($jobService, $jobStore, $diagnosticRecords);

        (new PrivacyIntegration($jobStore, $artifactStore, $inputSnapshots, $fileStore))->register();
        (new SiteHealthIntegration($privateStorage, $jobStore))->register();
        (new CliCommands($diagnosticWorker, $jobStore, $diagnostics, $privateStorage))->register();

        if ($runtime->isAdmin()) {
            (new AdminModule($this->pluginRoot, $adminConfig, $registry, $settings, $settingsRegistrar, $jobStore, $diagnostics, $selectionTokens))->register();
            (new DiagnosticAdminAssets())->register();
            (new DiagnosticDownloadController($diagnosticRecords, $capability))->register();
            (new InspectorModule( $capability ))->register();

            // ExportJobController is deliberately isolated as a download-only
            // adapter. REST registration belongs exclusively to
            // DiagnosticExportJobController.
            $downloadController = new ExportJobController($jobService, $jobStore, $fileStore, $capability);
            add_action('admin_post_edis_download_export', [$downloadController, 'download']);
            add_action('admin_post_edis_download_bridge_context', [$downloadController, 'downloadBridgeContext']);
        }

        if ($runtime->isRest()) {
            $exportController = new DiagnosticExportJobController($jobService, $createBoundary, $jobStore, $diagnosticRecords, $capability);
            add_action('rest_api_init', [$exportController, 'registerRoutes']);
            add_action('rest_api_init', [new InspectorSelectionController($selectionTokens, $diagnosticRecords, $capability), 'registerRoutes']);
            add_action('rest_api_init', [new DocumentController($documentService, $capability), 'registerRoutes']);
            CanonicalDiagnosticResponseAdapter::register();
            add_action('rest_api_init', [new DiagnosticsController($diagnostics), 'registerRoutes']);
        }

        add_action('edis_process_export_job', [$diagnosticWorker, 'process'], 10, 1);
        add_action('edis_cleanup_export_files', [$workerRecovery, 'run'], 5);
        add_action('edis_cleanup_export_files', [$fileStore, 'cleanupExpired']);
        add_action('edis_cleanup_export_files', [$jobStore, 'cleanupExpired']);
        add_action('edis_cleanup_export_files', [$selectionTokens, 'cleanupExpired']);
        add_action('edis_cleanup_export_files', [$inputSnapshots, 'cleanupExpired']);
        add_action('edis_cleanup_export_files', [$diagnosticRecords, 'cleanupExpired']);

        if ($runtime->isAdmin() && !wp_next_scheduled('edis_cleanup_export_files')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'edis_cleanup_export_files');
        }
    }
}
