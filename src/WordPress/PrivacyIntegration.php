<?php
/**
 * WordPress privacy tools integration.
 *
 * @package EDIS\EvidenceExporter
 */
declare(strict_types=1);

namespace EDIS\EvidenceExporter\WordPress;

use EDIS\EvidenceExporter\Infrastructure\Support\ArtifactStore;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportFileStore;
use EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;

/**
 * Integrate bounded EDIS operational data with WordPress privacy tools.
 */
final class PrivacyIntegration {
	private const PAGE_SIZE = 50;
	/**
	 * @param JobStore           $jobs      Job persistence service.
	 * @param ArtifactStore      $artifacts Step artifact persistence service.
	 * @param InputSnapshotStore $inputs    Immutable input snapshot service.
	 * @param ExportFileStore    $files     Completed bundle persistence service.
	 */
	public function __construct(
		private readonly JobStore $jobs,
		private readonly ArtifactStore $artifacts,
		private readonly InputSnapshotStore $inputs,
		private readonly ExportFileStore $files,
	) {}

	/** Register privacy, policy-content and user-deletion hooks. */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'registerExporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'registerEraser' ) );
		add_action( 'admin_init', array( $this, 'addPolicyText' ) );
		add_action( 'delete_user', array( $this, 'deleteUserData' ), 10, 1 );
		add_action( 'before_delete_post', array( $this, 'deleteDocumentJobs' ), 10, 1 );
	}

	/** @param array<string,mixed> $exporters @return array<string,mixed> */
	public function registerExporter( array $exporters ): array {
		$exporters['edis-evidence-exporter'] = array(
			'exporter_friendly_name' => __( 'EDIS Evidence Exporter operational records', 'edis-evidence-exporter' ),
			'callback'               => array( $this, 'exportPersonalData' ),
		);
		return $exporters;
	}

	/** @param array<string,mixed> $erasers @return array<string,mixed> */
	public function registerEraser( array $erasers ): array {
		$erasers['edis-evidence-exporter'] = array(
			'eraser_friendly_name' => __( 'EDIS Evidence Exporter operational records', 'edis-evidence-exporter' ),
			'callback'             => array( $this, 'erasePersonalData' ),
		);
		return $erasers;
	}

	/** Add suggested EDIS text to the WordPress privacy-policy guide. */
	public function addPolicyText(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . esc_html__( 'EDIS Evidence Exporter creates owner-bound operational job records and temporary private evidence files when an authorized user starts an export. These records are retained only for the configured retention period unless an administrator explicitly retains completed evidence. EDIS does not send evidence to an external service.', 'edis-evidence-exporter' ) . '</p>';
		wp_add_privacy_policy_content( __( 'EDIS Evidence Exporter', 'edis-evidence-exporter' ), wp_kses_post( wpautop( $content, false ) ) );
	}

	/**
	 * Export one bounded page of operational records.
	 *
	 * @param string $email_address User email address.
	 * @param int    $page          One-based page number.
	 * @return array<string,mixed>
	 */
	public function exportPersonalData( string $email_address, int $page = 1 ): array {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user instanceof \WP_User ) {
			return array( 'data' => array(), 'done' => true );
		}
		$jobs       = $this->jobs->jobsForUser( (int) $user->ID );
		$page       = max( 1, $page );
		$offset     = ( $page - 1 ) * self::PAGE_SIZE;
		$page_jobs  = array_slice( $jobs, $offset, self::PAGE_SIZE );
		$data       = array();
		foreach ( $page_jobs as $job ) {
			$data[] = array(
				'group_id'    => 'edis-evidence-exporter',
				'group_label' => __( 'EDIS Evidence Exporter', 'edis-evidence-exporter' ),
				'item_id'     => 'edis-job-' . (string) ( $job['job_id'] ?? '' ),
				'data'        => array(
					array( 'name' => __( 'Job ID', 'edis-evidence-exporter' ), 'value' => (string) ( $job['job_id'] ?? '' ) ),
					array( 'name' => __( 'Status', 'edis-evidence-exporter' ), 'value' => (string) ( $job['status'] ?? '' ) ),
					array( 'name' => __( 'Created timestamp', 'edis-evidence-exporter' ), 'value' => (string) ( $job['created_at'] ?? '' ) ),
					array( 'name' => __( 'Expiry timestamp', 'edis-evidence-exporter' ), 'value' => (string) ( $job['expires_at'] ?? '' ) ),
				),
			);
		}
		return array( 'data' => $data, 'done' => $offset + count( $page_jobs ) >= count( $jobs ) );
	}

	/**
	 * Erase one bounded page of operational records.
	 *
	 * @param string $email_address User email address.
	 * @param int    $page          One-based page number supplied by WordPress.
	 * @return array<string,mixed>
	 */
	public function erasePersonalData( string $email_address, int $page = 1 ): array {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user instanceof \WP_User ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		$jobs    = array_slice( $this->jobs->jobsForUser( (int) $user->ID ), 0, self::PAGE_SIZE );
		$removed = $this->deleteJobs( $jobs );
		$done    = count( $this->jobs->jobsForUser( (int) $user->ID ) ) === 0;
		if ( $done ) {
			delete_user_meta( (int) $user->ID, self::latestJobMetaKey() );
		}
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => $done,
		);
	}

	/**
	 * Delete all operational records for a deleted WordPress user.
	 *
	 * @param int $user_id User ID.
	 * @return int Number of deleted jobs.
	 */
	public function deleteUserData( int $user_id ): int {
		$jobs    = $this->jobs->jobsForUser( $user_id );
		$removed = $this->deleteJobs( $jobs );
		delete_user_meta( $user_id, self::latestJobMetaKey() );
		return $removed;
	}


	/**
	 * Delete jobs whose immutable input selection contains a deleted document.
	 *
	 * @param int $post_id Deleted post ID.
	 * @return int Number of deleted jobs.
	 */
	public function deleteDocumentJobs( int $post_id ): int {
		if ( $post_id <= 0 ) {
			return 0;
		}
		return $this->deleteJobs( $this->jobs->jobsForDocument( $post_id ) );
	}

	/** Return the current site's operational user-meta key. */
	private static function latestJobMetaKey(): string {
		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_current_blog_id' ) ) {
			return 'edis_evidence_latest_job_id_' . max( 1, (int) get_current_blog_id() );
		}
		return 'edis_evidence_latest_job_id';
	}

	/**
	 * Delete bounded job records and their private files.
	 *
	 * @param list<array<string,mixed>> $jobs Jobs to delete.
	 */
	private function deleteJobs( array $jobs ): int {
		$removed = 0;
		foreach ( $jobs as $job ) {
			$job_id = (string) ( $job['job_id'] ?? '' );
			if ( '' === $job_id ) {
				continue;
			}
			$this->artifacts->removeJob( $job_id );
			$this->inputs->remove( $job_id );
			$this->files->remove( $job_id );
			$this->jobs->remove( $job_id );
			++$removed;
		}
		return $removed;
	}
}
