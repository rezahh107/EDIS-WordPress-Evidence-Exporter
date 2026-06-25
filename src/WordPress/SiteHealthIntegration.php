<?php
/**
 * WordPress Site Health integration.
 *
 * @package EDIS\EvidenceExporter
 */
declare(strict_types=1);

namespace EDIS\EvidenceExporter\WordPress;

use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;

/**
 * Register direct and asynchronous EDIS Site Health tests.
 */
final class SiteHealthIntegration {
	/**
	 * @param PrivateStorage $storage Private-storage service.
	 * @param JobStore       $jobs    Job persistence service.
	 */
	public function __construct(
		private readonly PrivateStorage $storage,
		private readonly ?JobStore $jobs,
		private readonly ?string $bootDiagnosticCode = null,
	) {}

	/** Register Site Health filters and the protected asynchronous route. */
	public function register(): void {
		add_filter( 'site_status_tests', array( $this, 'registerTests' ) );
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	/**
	 * Register direct and asynchronous Site Health tests.
	 *
	 * @param array<string,mixed> $tests Existing tests.
	 * @return array<string,mixed>
	 */
	public function registerTests( array $tests ): array {
		$tests['direct']['edis_evidence_runtime'] = array(
			'label'     => __( 'EDIS deterministic runtime', 'edis-evidence-exporter' ),
			'test'      => array( $this, 'runtimeTest' ),
			'skip_cron' => false,
		);
		$tests['direct']['edis_evidence_cron'] = array(
			'label'     => __( 'EDIS worker recovery schedule', 'edis-evidence-exporter' ),
			'test'      => array( $this, 'cronTest' ),
			'skip_cron' => false,
		);
		$tests['async']['edis_evidence_storage'] = array(
			'label'             => __( 'EDIS private storage integrity', 'edis-evidence-exporter' ),
			'test'              => rest_url( 'edis-evidence-exporter/v3/site-health/storage' ),
			'has_rest'          => true,
			'skip_cron'         => true,
			'async_direct_test' => array( $this, 'storageTest' ),
		);
		return $tests;
	}

	/** Register the protected asynchronous storage-check route. */
	public function registerRoutes(): void {
		register_rest_route(
			'edis-evidence-exporter/v3',
			'/site-health/storage',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'storageRestResponse' ),
				'permission_callback' => static fn (): bool => current_user_can( 'view_site_health_checks' ) || current_user_can( 'manage_options' ),
			)
		);
	}

	/** Return the asynchronous storage test as a REST response. */
	public function storageRestResponse(): \WP_REST_Response {
		return rest_ensure_response( $this->storageTest() );
	}

	/** Run the deterministic runtime test.
	 *
	 * @return array<string,mixed>
	 */
	public function runtimeTest(): array {
		$ready = CanonicalJson::environmentReady();
		return $this->result(
			'edis_evidence_runtime',
			$ready ? 'good' : 'critical',
			$ready ? __( 'EDIS deterministic runtime is available', 'edis-evidence-exporter' ) : __( 'EDIS deterministic runtime is unavailable', 'edis-evidence-exporter' ),
			$ready
				? __( 'The active PHP runtime satisfies the EDIS-CJ-2 integer, JSON, precision and durable-write requirements.', 'edis-evidence-exporter' )
				: __( 'Exports are blocked because the current PHP runtime cannot satisfy the deterministic runtime contract.', 'edis-evidence-exporter' )
		);
	}

	/** Run the recovery-schedule test.
	 *
	 * @return array<string,mixed>
	 */
	public function cronTest(): array {
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$next     = wp_next_scheduled( 'edis_cleanup_export_files' );
		$stale    = $this->jobs instanceof JobStore ? count( $this->jobs->staleJobs() ) : 0;
		$overdue  = false !== $next && (int) $next < time() - 300;
		$status   = ( $this->jobs === null || $disabled || false === $next || $overdue || $stale > 0 ) ? 'recommended' : 'good';
		$detail = sprintf(
			/* translators: 1: next scheduled timestamp or none, 2: stale job count. */
			__( 'Next cleanup: %1$s. Stale queued/running jobs: %2$d. WP-Cron is a recovery trigger and exact execution time is not assumed.', 'edis-evidence-exporter' ),
			false === $next ? __( 'not scheduled', 'edis-evidence-exporter' ) : gmdate( 'Y-m-d H:i:s', (int) $next ) . ' UTC',
			$stale
		);
		return $this->result(
			'edis_evidence_cron',
			$status,
			'good' === $status ? __( 'EDIS recovery scheduling is healthy', 'edis-evidence-exporter' ) : __( 'EDIS recovery scheduling needs attention', 'edis-evidence-exporter' ),
			$detail
		);
	}

	/** Run the full private-storage integrity test.
	 *
	 * @return array<string,mixed>
	 */
	public function storageTest(): array {
		$result      = $this->storage->selfTest( true );
		$compatibility = WordPressFilesystemPreflightAdapter::report( $this->storage );
		$pass        = ( $result['state'] ?? 'FAIL' ) === 'PASS';
		$method      = sanitize_key( (string) ( $compatibility['wordpress_method'] ?? 'UNAVAILABLE' ) );
		$failure_detail = $this->bootDiagnosticCode !== null
			? sprintf(
				/* translators: %s: stable EDIS diagnostic code. */
				__( 'Background exports are blocked. Diagnostic code: %s.', 'edis-evidence-exporter' ),
				$this->bootDiagnosticCode
			)
			: __( 'Background exports are unsafe on the current storage backend and remain blocked.', 'edis-evidence-exporter' );
		return $this->result(
			'edis_evidence_storage',
			$pass ? 'good' : 'critical',
			$pass ? __( 'EDIS private storage passed integrity checks', 'edis-evidence-exporter' ) : __( 'EDIS private storage failed integrity checks', 'edis-evidence-exporter' ),
			$pass
				? sprintf(
					/* translators: %s: WordPress filesystem method. */
					__( 'Protected storage, durable writes, atomic replacement, local lock exclusion and separate-process lock exclusion passed on the active storage path. WordPress filesystem method: %s. EDIS retains its verified direct deterministic backend for background jobs.', 'edis-evidence-exporter' ),
					$method
				)
				: $failure_detail
		);
	}

	/** @return array<string,mixed> */
	private function result( string $test, string $status, string $label, string $description ): array {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'EDIS Evidence', 'edis-evidence-exporter' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html( $description ) . '</p>',
			'actions'     => '',
			'test'        => $test,
		);
	}
}
