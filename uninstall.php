<?php
/**
 * EDIS uninstall routine.
 *
 * @package EDIS\EvidenceExporter
 */
declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/autoload.php';

/**
 * Remove one site's EDIS data when retention is disabled.
 */
function edis_evidence_exporter_uninstall_site(): void {
	wp_clear_scheduled_hook( 'edis_process_export_job' );
	wp_clear_scheduled_hook( 'edis_cleanup_export_files' );

	$administrator = get_role( 'administrator' );
	if ( $administrator instanceof WP_Role ) {
		$administrator->remove_cap( 'edis_export_evidence' );
	}

	$retain = (bool) get_option( 'edis_evidence_retain_data_on_uninstall', true );
	if ( $retain ) {
		update_option( 'edis_evidence_accept_new_jobs', false, false );
		return;
	}

	$options = array(
		'edis_evidence_default_privacy_mode',
		'edis_evidence_default_collectors',
		'edis_evidence_retention_hours',
		'edis_evidence_include_diagnostic_metadata',
		'edis_evidence_include_original_documents',
		'edis_evidence_cleanup_enabled',
		'edis_evidence_retain_data_on_uninstall',
		'edis_evidence_accept_new_jobs',
		'edis_evidence_installed_version',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	$user_meta_key = ( function_exists( 'is_multisite' ) && is_multisite() )
		? 'edis_evidence_latest_job_id_' . max( 1, (int) get_current_blog_id() )
		: 'edis_evidence_latest_job_id';
	delete_metadata( 'user', 0, $user_meta_key, '', true );

	$storage = new EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage();
	edis_evidence_exporter_remove_private_tree( $storage->root() );
}


/**
 * Execute a filesystem deletion without emitting an unhandled PHP warning.
 *
 * @param callable():bool $operation Filesystem operation.
 * @return bool Whether the operation completed successfully.
 */
function edis_evidence_exporter_safe_filesystem_delete( callable $operation ): bool {
	set_error_handler(
		static function ( int $severity, string $message ): never {
			throw new ErrorException( $message, 0, $severity );
		}
	);
	try {
		return true === $operation();
	} catch ( Throwable ) {
		return false;
	} finally {
		restore_error_handler();
	}
}

/**
 * Remove an EDIS private-storage tree without following symbolic links.
 *
 * @param string $root Private storage root.
 */
function edis_evidence_exporter_remove_private_tree( string $root ): void {
	if ( '' === $root || is_link( $root ) || ! is_dir( $root ) ) {
		return;
	}
	$real_root = realpath( $root );
	$web_root  = realpath( ABSPATH );
	if ( false === $real_root || false === $web_root ) {
		return;
	}
	$normalized_root = rtrim( str_replace( '\\', '/', $real_root ), '/' );
	$normalized_web  = rtrim( str_replace( '\\', '/', $web_root ), '/' );
	if ( $normalized_root === $normalized_web || str_starts_with( $normalized_root, $normalized_web . '/' ) ) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $real_root, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $item ) {
		$path = $item->getPathname();
		if ( is_link( $path ) ) {
			continue;
		}
		if ( $item->isDir() ) {
			edis_evidence_exporter_safe_filesystem_delete( static fn (): bool => rmdir( $path ) );
		} elseif ( $item->isFile() ) {
			edis_evidence_exporter_safe_filesystem_delete( static fn (): bool => unlink( $path ) );
		}
	}
	edis_evidence_exporter_safe_filesystem_delete( static fn (): bool => rmdir( $real_root ) );
}

if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		try {
			edis_evidence_exporter_uninstall_site();
		} finally {
			restore_current_blog();
		}
	}
} else {
	edis_evidence_exporter_uninstall_site();
}
