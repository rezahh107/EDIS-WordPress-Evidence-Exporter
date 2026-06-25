<?php
/**
 * WP-CLI operational commands.
 *
 * @package EDIS\EvidenceExporter
 */
declare(strict_types=1);

namespace EDIS\EvidenceExporter\WordPress;

use EDIS\EvidenceExporter\Application\DiagnosticsService;
use EDIS\EvidenceExporter\Application\ExportJobService;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;

/**
 * Register bounded WP-CLI operational and recovery commands.
 */
final class CliCommands {
	/**
	 * @param ExportJobService  $worker      Export worker service.
	 * @param JobStore          $jobs        Job persistence service.
	 * @param DiagnosticsService $diagnostics Diagnostics service.
	 * @param PrivateStorage    $storage     Private-storage service.
	 */
	public function __construct(
		private readonly ExportJobService $worker,
		private readonly JobStore $jobs,
		private readonly DiagnosticsService $diagnostics,
		private readonly PrivateStorage $storage,
	) {}

	/** Register commands when WP-CLI is active. */
	public function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( '\\WP_CLI' ) ) {
			return;
		}
		\WP_CLI::add_command( 'edis status', array( $this, 'status' ) );
		\WP_CLI::add_command( 'edis worker run', array( $this, 'workerRun' ) );
		\WP_CLI::add_command( 'edis worker status', array( $this, 'workerStatus' ) );
		\WP_CLI::add_command( 'edis jobs repair', array( $this, 'jobsRepair' ) );
		\WP_CLI::add_command( 'edis storage self-test', array( $this, 'storageSelfTest' ) );
		\WP_CLI::add_command( 'edis storage paths', array( $this, 'storagePaths' ) );
	}

	/**
	 * Print the bounded diagnostics report.
	 *
	 * @param list<string>        $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 */
	public function status( array $args, array $assoc_args ): void {
		$this->printJson( $this->diagnostics->report() );
	}

	/**
	 * Advance one requested job or a bounded set of eligible jobs.
	 *
	 * @param list<string>        $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 */
	public function workerRun( array $args, array $assoc_args ): void {
		$job_id = isset( $assoc_args['job_id'] ) ? sanitize_text_field( (string) $assoc_args['job_id'] ) : '';
		$ids    = '' !== $job_id ? array( $job_id ) : $this->jobs->runnableJobIds( 10 );
		foreach ( $ids as $id ) {
			$this->worker->process( $id );
		}
		\WP_CLI::success( sprintf( /* translators: %d: processed job count. */ __( 'Processed %d eligible job(s).', 'edis-evidence-exporter' ), count( $ids ) ) );
	}

	/**
	 * Print runnable and stale job state.
	 *
	 * @param list<string>        $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 */
	public function workerStatus( array $args, array $assoc_args ): void {
		$this->printJson( array( 'runnable' => $this->jobs->runnableJobIds( 100 ), 'stale' => $this->jobs->staleJobs() ) );
	}

	/**
	 * Inspect or requeue stale jobs.
	 *
	 * @param list<string>        $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 */
	public function jobsRepair( array $args, array $assoc_args ): void {
		$apply  = isset( $assoc_args['apply'] );
		$result = $this->jobs->repairStaleJobs( $apply );
		$this->printJson( $result );
		if ( ! $apply ) {
			\WP_CLI::warning( __( 'Dry run only. Re-run with --apply to queue repairable stale jobs.', 'edis-evidence-exporter' ) );
		}
	}

	/**
	 * Run and print the full private-storage self-test.
	 *
	 * @param list<string>        $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 */
	public function storageSelfTest( array $args, array $assoc_args ): void {
		$result = $this->storage->selfTest( true );
		$this->printJson(
			array(
				'diagnostic_context' => $this->storage->diagnosticContext(),
				'self_test'          => $result,
			)
		);
		if ( ! $this->storage->acceptsSelfTestResult( $result ) ) {
			\WP_CLI::error( __( 'EDIS storage self-test failed.', 'edis-evidence-exporter' ) );
		}
		\WP_CLI::success( __( 'EDIS storage self-test passed.', 'edis-evidence-exporter' ) );
	}

	/**
	 * Print resolved and candidate private-storage paths.
	 *
	 * @param list<string>        $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 */
	public function storagePaths( array $args, array $assoc_args ): void {
		$this->printJson( $this->storage->diagnosticContext() );
	}

	/**
	 * Print privacy-safe JSON and fail when encoding is unavailable.
	 *
	 * @param array<string,mixed> $data Data to encode.
	 */
	private function printJson( array $data ): void {
		$encoded = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) ) {
			\WP_CLI::error( __( 'EDIS could not encode the command result.', 'edis-evidence-exporter' ) );
		}
		\WP_CLI::line( $encoded );
	}

}
