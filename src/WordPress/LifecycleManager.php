<?php
/**
 * WordPress lifecycle integration.
 *
 * @package EDIS\EvidenceExporter
 */
declare(strict_types=1);

namespace EDIS\EvidenceExporter\WordPress;

use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;

/**
 * Manage activation, deactivation and multisite initialization.
 */
final class LifecycleManager {
	public const OPTION_INSTALLED_VERSION = 'edis_evidence_installed_version';
	public const OPTION_ACCEPT_NEW_JOBS   = 'edis_evidence_accept_new_jobs';
	public const OPTION_RETAIN_UNINSTALL  = 'edis_evidence_retain_data_on_uninstall';

	/**
	 * Activate EDIS for one site or an entire network.
	 *
	 * @param bool $network_wide Whether activation is network-wide.
	 */
	public static function activate( bool $network_wide = false ): void {
		self::assertRuntime();

		if ( $network_wide && function_exists( 'is_multisite' ) && is_multisite() ) {
			self::activateNetwork();
			return;
		}

		self::activateSite();
	}

	/**
	 * Deactivate EDIS without deleting retained evidence.
	 *
	 * @param bool $network_wide Whether deactivation is network-wide.
	 */
	public static function deactivate( bool $network_wide = false ): void {
		if ( $network_wide && function_exists( 'is_multisite' ) && is_multisite() ) {
			self::forEachSite( static function (): void {
				self::deactivateSite();
			} );
			return;
		}

		self::deactivateSite();
	}

	/** Register hooks required by network-active installations. */
	public static function registerMultisiteHooks(): void {
		add_action( 'wp_initialize_site', array( self::class, 'initializeSite' ), 20, 1 );
	}

	/**
	 * Initialize a newly created site when EDIS is network-active.
	 *
	 * @param \WP_Site $site Created site.
	 */
	public static function initializeSite( \WP_Site $site ): void {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( plugin_basename( EDIS_EVIDENCE_EXPORTER_PATH . 'edis-evidence-exporter.php' ) ) ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );
		try {
			self::activateSite();
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Activate every existing site and roll back sites already changed if one fails.
	 */
	private static function activateNetwork(): void {
		$activation_states = array();
		$site_ids          = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		try {
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				try {
					$activation_states[ (int) $site_id ] = self::captureActivationState();
					self::activateSite();
				} finally {
					restore_current_blog();
				}
			}
		} catch ( \Throwable $exception ) {
			foreach ( array_reverse( array_keys( $activation_states ) ) as $site_id ) {
				switch_to_blog( (int) $site_id );
				try {
					self::restoreActivationState( $activation_states[ $site_id ] );
				} finally {
					restore_current_blog();
				}
			}
			throw $exception;
		}
	}

	/**
	 * Capture the settings and capability changed by activation.
	 *
	 * @return array<string,mixed>
	 */
	private static function captureActivationState(): array {
		$administrator = get_role( 'administrator' );
		$sentinel      = new \stdClass();
		$options       = array();
		foreach ( array( self::OPTION_RETAIN_UNINSTALL, self::OPTION_ACCEPT_NEW_JOBS, self::OPTION_INSTALLED_VERSION ) as $option ) {
			$value              = get_option( $option, $sentinel );
			$options[ $option ] = array(
				'existed' => $value !== $sentinel,
				'value'   => $value !== $sentinel ? $value : null,
			);
		}

		return array(
			'administrator_had_capability' => $administrator instanceof \WP_Role && $administrator->has_cap( 'edis_export_evidence' ),
			'cleanup_was_scheduled'        => false !== wp_next_scheduled( 'edis_cleanup_export_files' ),
			'options'                      => $options,
		);
	}

	/**
	 * Restore a site's exact pre-activation WordPress state.
	 *
	 * @param array<string,mixed> $state Captured activation state.
	 */
	private static function restoreActivationState( array $state ): void {
		$administrator = get_role( 'administrator' );
		if ( $administrator instanceof \WP_Role ) {
			if ( ! empty( $state['administrator_had_capability'] ) ) {
				$administrator->add_cap( 'edis_export_evidence' );
			} else {
				$administrator->remove_cap( 'edis_export_evidence' );
			}
		}

		$options = is_array( $state['options'] ?? null ) ? $state['options'] : array();
		foreach ( $options as $option => $record ) {
			if ( ! is_string( $option ) || ! is_array( $record ) ) {
				continue;
			}
			if ( ! empty( $record['existed'] ) ) {
				update_option( $option, $record['value'] ?? null, false );
			} else {
				delete_option( $option );
			}
		}

		if ( empty( $state['cleanup_was_scheduled'] ) ) {
			wp_clear_scheduled_hook( 'edis_cleanup_export_files' );
		}
	}

	private static function activateSite(): void {
		$administrator = get_role( 'administrator' );
		if ( $administrator instanceof \WP_Role ) {
			$administrator->add_cap( 'edis_export_evidence' );
		}

		add_option( self::OPTION_RETAIN_UNINSTALL, true, '', false );
		update_option( self::OPTION_ACCEPT_NEW_JOBS, true, false );
		update_option( self::OPTION_INSTALLED_VERSION, EDIS_EVIDENCE_EXPORTER_VERSION, false );

		$storage = new PrivateStorage();
		$result  = $storage->selfTest( true );
		if ( ! $storage->acceptsSelfTestResult( $result ) ) {
			// Storage failure is always fail-closed for exports, but it must not prevent
			// WordPress from activating the plugin and exposing diagnostics/recovery.
			update_option( self::OPTION_ACCEPT_NEW_JOBS, false, false );
			return;
		}

		self::scheduleCleanup();
	}

	private static function deactivateSite(): void {
		$administrator = get_role( 'administrator' );
		if ( $administrator instanceof \WP_Role ) {
			$administrator->remove_cap( 'edis_export_evidence' );
		}
		update_option( self::OPTION_ACCEPT_NEW_JOBS, false, false );
		wp_clear_scheduled_hook( 'edis_process_export_job' );
		wp_clear_scheduled_hook( 'edis_cleanup_export_files' );
	}

	private static function scheduleCleanup(): void {
		if ( wp_next_scheduled( 'edis_cleanup_export_files' ) ) {
			return;
		}
		$result = wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'edis_cleanup_export_files', array(), true );
		if ( false === $result || ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) ) {
			throw new \RuntimeException( 'EDIS cleanup recovery event could not be scheduled.' );
		}
	}

	private static function assertRuntime(): void {
		if (
			version_compare( PHP_VERSION, '8.2', '<' )
			|| version_compare( PHP_VERSION, '8.6', '>=' )
			|| PHP_INT_SIZE < 8
			|| ! CanonicalJson::environmentReady()
		) {
			throw new \RuntimeException( 'EDIS requires a supported deterministic 64-bit PHP 8.2-8.5 runtime.' );
		}
	}

	/**
	 * Execute a lifecycle callback in every site without leaking blog context.
	 *
	 * @param callable():void $callback Callback to execute.
	 */
	private static function forEachSite( callable $callback ): void {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			try {
				$callback();
			} finally {
				restore_current_blog();
			}
		}
	}
}
