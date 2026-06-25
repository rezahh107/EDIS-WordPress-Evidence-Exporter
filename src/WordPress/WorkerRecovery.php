<?php
/**
 * Bounded WordPress Cron recovery scheduling.
 *
 * @package EDIS\EvidenceExporter
 */
declare(strict_types=1);

namespace EDIS\EvidenceExporter\WordPress;

use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;

/**
 * Repair expired worker leases and schedule bounded single-job wake-ups.
 */
final class WorkerRecovery {
	private const BATCH_SIZE = 10;

	/**
	 * @param JobStore $jobs Job persistence service.
	 */
	public function __construct( private readonly JobStore $jobs ) {}

	/**
	 * Repair stale jobs and schedule eligible jobs exactly once.
	 *
	 * @return array{repaired:list<string>,scheduled:list<string>}
	 */
	public function run(): array {
		$recovery  = $this->jobs->recoveryBatch( self::BATCH_SIZE );
		$scheduled = array();
		foreach ( $recovery['runnable'] as $job_id ) {
			$args = array( $job_id );
			if ( false !== wp_next_scheduled( 'edis_process_export_job', $args ) ) {
				continue;
			}
			$result = wp_schedule_single_event( time() + 5, 'edis_process_export_job', $args, true );
			if ( true === $result ) {
				$scheduled[] = $job_id;
			}
		}
		sort( $scheduled, SORT_STRING );
		$repaired = is_array( $recovery['repaired'] ?? null ) ? array_values( array_filter( $recovery['repaired'], 'is_string' ) ) : array();
		sort( $repaired, SORT_STRING );
		return array(
			'repaired'  => $repaired,
			'scheduled' => $scheduled,
		);
	}
}
