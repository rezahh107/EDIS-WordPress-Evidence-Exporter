<?php
/**
 * Plugin composition root.
 *
 * @package EDIS\EvidenceExporter
 */
declare(strict_types=1);

namespace EDIS\EvidenceExporter;

use EDIS\EvidenceExporter\Admin\AdminModule;
use EDIS\EvidenceExporter\Admin\Settings\SettingsRegistrar;
use EDIS\EvidenceExporter\Admin\Settings\SettingsRepository;
use EDIS\EvidenceExporter\Admin\Settings\SettingsSanitizer;
use EDIS\EvidenceExporter\Application\DiagnosticsService;
use EDIS\EvidenceExporter\Application\DocumentQueryService;
use EDIS\EvidenceExporter\Application\ExportJobService;
use EDIS\EvidenceExporter\Application\ExportService;
use EDIS\EvidenceExporter\Elementor\InspectorModule;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Support\ArtifactStore;
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportFileStore;
use EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore;
use EDIS\EvidenceExporter\Infrastructure\Support\InstallationIntegrity;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;
use EDIS\EvidenceExporter\Infrastructure\Support\SelectionTokenStore;
use EDIS\EvidenceExporter\Rest\DiagnosticsController;
use EDIS\EvidenceExporter\Rest\DocumentController;
use EDIS\EvidenceExporter\Rest\ExportJobController;
use EDIS\EvidenceExporter\Rest\InspectorSelectionController;
use EDIS\EvidenceExporter\WordPress\CliCommands;
use EDIS\EvidenceExporter\WordPress\DegradedModeIntegration;
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
	public function __construct( private readonly string $pluginRoot ) {}

	/**
	 * Register services required by the current WordPress runtime context.
	 */
	public function boot(): void {
		if ( ! CanonicalJson::environmentReady() ) {
			throw new \RuntimeException( 'EDIS requires a verified deterministic 64-bit PHP 8.2-8.5 runtime with fsync and mutable serialize_precision.' );
		}

		load_plugin_textdomain( 'edis-evidence-exporter', false, dirname( plugin_basename( $this->pluginRoot . 'edis-evidence-exporter.php' ) ) . '/languages' );

		$runtime = new RuntimeContext();
		if ( ! $runtime->requiresApplicationRuntime() ) {
			return;
		}

		$private_storage = new PrivateStorage();
		$integrity = InstallationIntegrity::verify( $this->pluginRoot );
		if ( 'PASS' !== ( $integrity['state'] ?? 'FAIL' ) ) {
			( new DegradedModeIntegration( $private_storage, (string) ( $integrity['code'] ?? 'EDIS_INSTALLATION_INTEGRITY_FAILED' ), $integrity ) )->register();
			return;
		}

		$collector_definitions = require $this->pluginRoot . 'config/collectors.php';
		$admin_config          = require $this->pluginRoot . 'config/admin.php';
		if ( ! is_array( $collector_definitions ) || ! is_array( $admin_config ) ) {
			( new DegradedModeIntegration( $private_storage, 'EDIS_CONFIGURATION_INVALID' ) )->register();
			return;
		}

		$registry           = CollectorRegistry::fromDefinitions( $collector_definitions );
		$settings           = new SettingsRepository();
		$settings_registrar = new SettingsRegistrar( $settings, new SettingsSanitizer( $registry ), $registry );
		$storage_test = $private_storage->selfTest();
		if ( ! $private_storage->acceptsSelfTestResult( $storage_test ) ) {
			$context = $private_storage->diagnosticContext();
			$context['storage_self_test'] = $storage_test;
			$context['required_security_state'] = 'OUTSIDE_WEB_ROOT';
			$context['required_multiprocess_lock_exclusion'] = 'PASS';
			( new DegradedModeIntegration( $private_storage, 'EDIS_PRIVATE_STORAGE_UNAVAILABLE', $context ) )->register();
			return;
		}

		$filesystem       = new DeterministicFilesystem();
		$job_store        = new JobStore( $private_storage->path( 'jobs' ), $filesystem );
		$artifact_store   = new ArtifactStore( $private_storage->path( 'artifacts' ), $filesystem );
		$file_store       = new ExportFileStore( $settings, $private_storage->path( 'bundles' ), $filesystem );
		$selection_tokens = new SelectionTokenStore( $private_storage->path( 'selections' ), 600, $filesystem );
		$input_snapshots  = new InputSnapshotStore( $private_storage->path( 'inputs' ), null, $filesystem );
		$export_service   = new ExportService( $registry, $this->pluginRoot );
		$job_service      = new ExportJobService( $registry, $export_service, $job_store, $artifact_store, $file_store, $settings, $input_snapshots, $private_storage );
		$document_service = new DocumentQueryService();
		$diagnostics      = new DiagnosticsService( $registry, $job_store, $artifact_store, $file_store, $settings, $input_snapshots, $job_service, $this->pluginRoot, $filesystem );
		$capability       = (string) $admin_config['capability'];

		( new PrivacyIntegration( $job_store, $artifact_store, $input_snapshots, $file_store ) )->register();
		( new SiteHealthIntegration( $private_storage, $job_store ) )->register();
		( new CliCommands( $job_service, $job_store, $diagnostics, $private_storage ) )->register();
		$worker_recovery = new WorkerRecovery( $job_store );

		if ( $runtime->isAdmin() ) {
			( new AdminModule( $this->pluginRoot, $admin_config, $registry, $settings, $settings_registrar, $job_store, $diagnostics, $selection_tokens ) )->register();
			( new InspectorModule( $capability ) )->register();
			$export_controller = new ExportJobController( $job_service, $job_store, $file_store, $capability );
			add_action( 'admin_post_edis_download_export', array( $export_controller, 'download' ) );
			add_action( 'admin_post_edis_download_bridge_context', array( $export_controller, 'downloadBridgeContext' ) );
		}

		if ( $runtime->isRest() ) {
			$export_controller = new ExportJobController( $job_service, $job_store, $file_store, $capability );
			add_action( 'rest_api_init', array( $export_controller, 'registerRoutes' ) );
			add_action( 'rest_api_init', array( new InspectorSelectionController( $selection_tokens, $capability ), 'registerRoutes' ) );
			add_action( 'rest_api_init', array( new DocumentController( $document_service, $capability ), 'registerRoutes' ) );
			add_action( 'rest_api_init', array( new DiagnosticsController( $diagnostics ), 'registerRoutes' ) );
		}

		add_action( 'edis_process_export_job', array( $job_service, 'process' ), 10, 1 );
		add_action( 'edis_cleanup_export_files', array( $worker_recovery, 'run' ), 5 );
		add_action( 'edis_cleanup_export_files', array( $file_store, 'cleanupExpired' ) );
		add_action( 'edis_cleanup_export_files', array( $job_store, 'cleanupExpired' ) );
		add_action( 'edis_cleanup_export_files', array( $selection_tokens, 'cleanupExpired' ) );
		add_action( 'edis_cleanup_export_files', array( $input_snapshots, 'cleanupExpired' ) );

		if ( $runtime->isAdmin() && ! wp_next_scheduled( 'edis_cleanup_export_files' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'edis_cleanup_export_files' );
		}
	}
}
