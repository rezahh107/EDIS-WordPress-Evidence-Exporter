<?php
/**
 * Plugin Name: EDIS WordPress Evidence Exporter
 * Description: Deterministic local evidence export with explicit collector truth states and an accessible WordPress Admin workflow.
 * Version: 3.7.11
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Text Domain: edis-evidence-exporter
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 *
 * @package EDIS\EvidenceExporter
 */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EDIS_EVIDENCE_EXPORTER_VERSION', '3.7.11' );
define( 'EDIS_EVIDENCE_BUILD_PLATFORM_VERSION', '3.7.11' );
define( 'EDIS_EVIDENCE_BUNDLE_SCHEMA_VERSION', '3.3.0' );
define( 'EDIS_EVIDENCE_EXPORTER_PATH', plugin_dir_path( __FILE__ ) );
define( 'EDIS_EVIDENCE_EXPORTER_URL', plugin_dir_url( __FILE__ ) );

require_once EDIS_EVIDENCE_EXPORTER_PATH . 'autoload.php';

/**
 * Activate EDIS for one site or an entire network.
 *
 * @param bool $network_wide Whether activation is network-wide.
 */
function edis_evidence_exporter_activate( bool $network_wide = false ): void {
	try {
		\EDIS\EvidenceExporter\WordPress\LifecycleManager::activate( $network_wide );
	} catch ( \Throwable ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'EDIS Evidence Exporter could not be activated because its deterministic runtime or protected storage preflight failed.', 'edis-evidence-exporter' ),
			esc_html__( 'EDIS activation failed', 'edis-evidence-exporter' ),
			array( 'response' => 500 )
		);
	}
}

/**
 * Deactivate EDIS without deleting retained evidence.
 *
 * @param bool $network_wide Whether deactivation is network-wide.
 */
function edis_evidence_exporter_deactivate( bool $network_wide = false ): void {
	\EDIS\EvidenceExporter\WordPress\LifecycleManager::deactivate( $network_wide );
}

/**
 * Boot the plugin after WordPress has loaded all active plugins.
 */
function edis_evidence_exporter_boot(): void {
	\EDIS\EvidenceExporter\WordPress\LifecycleManager::registerMultisiteHooks();

	if (
		version_compare( PHP_VERSION, '8.2', '<' )
		|| version_compare( PHP_VERSION, '8.6', '>=' )
		|| PHP_INT_SIZE < 8
		|| ! \EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson::environmentReady()
	) {
		add_action( 'admin_notices', 'edis_evidence_exporter_runtime_notice' );
		return;
	}

	try {
		$bootstrap = new \EDIS\EvidenceExporter\Bootstrap( EDIS_EVIDENCE_EXPORTER_PATH );
		$bootstrap->boot();
	} catch ( \Throwable ) {
		add_action( 'admin_notices', 'edis_evidence_exporter_boot_notice' );
	}
}

/** Display the deterministic-runtime compatibility notice. */
function edis_evidence_exporter_runtime_notice(): void {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'EDIS Evidence Exporter is inactive because the current PHP runtime is outside the verified deterministic range (64-bit PHP 8.2–8.5 with fsync).', 'edis-evidence-exporter' ) . '</p></div>';
}

/** Display a privacy-safe boot failure notice. */
function edis_evidence_exporter_boot_notice(): void {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'EDIS Evidence Exporter could not initialize. Review Site Health and the EDIS diagnostics page for storage or runtime failures.', 'edis-evidence-exporter' ) . '</p></div>';
}

register_activation_hook( __FILE__, 'edis_evidence_exporter_activate' );
register_deactivation_hook( __FILE__, 'edis_evidence_exporter_deactivate' );
add_action( 'plugins_loaded', 'edis_evidence_exporter_boot' );
