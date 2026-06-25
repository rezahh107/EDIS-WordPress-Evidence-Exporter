<?php
/**
 * WordPress Filesystem API preflight for deterministic private storage.
 *
 * @package EDIS\EvidenceExporter
 */
declare(strict_types=1);

namespace EDIS\EvidenceExporter\WordPress;

use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;

/**
 * Report the WordPress transport recommendation without weakening EDIS storage semantics.
 */
final class WordPressFilesystemPreflightAdapter {
	/**
	 * Inspect the WordPress filesystem method for the configured private root.
	 *
	 * The adapter never requests interactive credentials and never replaces the
	 * deterministic direct backend. Background evidence export requires the
	 * stronger lock, durable-write and atomic-rename checks in PrivateStorage.
	 *
	 * @param PrivateStorage $storage Private-storage service.
	 * @return array{wordpress_method:string,edis_backend:string,interactive_credentials_required:bool,background_export_policy:string}
	 */
	public static function report( PrivateStorage $storage ): array {
		$method = 'UNAVAILABLE';
		try {
			if ( ! function_exists( 'get_filesystem_method' ) && defined( 'ABSPATH' ) ) {
				$file = ABSPATH . 'wp-admin/includes/file.php';
				if ( is_file( $file ) ) {
					require_once $file;
				}
			}
			if ( function_exists( 'get_filesystem_method' ) ) {
				$detected = get_filesystem_method( array(), $storage->root(), true );
				if ( is_string( $detected ) && '' !== $detected ) {
					$method = $detected;
				}
			}
		} catch ( \Throwable ) {
			$method = 'ERROR';
		}

		return array(
			'wordpress_method'                => $method,
			'edis_backend'                    => 'DIRECT_DETERMINISTIC',
			'interactive_credentials_required' => ! in_array( $method, array( 'direct', 'UNAVAILABLE', 'ERROR' ), true ),
			'background_export_policy'        => 'ALLOW_ONLY_AFTER_EDIS_STORAGE_SELF_TEST',
		);
	}
}
