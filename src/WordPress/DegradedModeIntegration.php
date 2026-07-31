<?php
/**
 * Fail-closed degraded runtime integration.
 *
 * @package EDIS\EvidenceExporter
 */
declare(strict_types=1);

namespace EDIS\EvidenceExporter\WordPress;

use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;

/**
 * Keep WordPress and diagnostics available while unsafe export operations remain disabled.
 */
final class DegradedModeIntegration {
    private const CAPABILITY = 'manage_options';
    private const MENU_SLUG = 'edis-evidence';
    private const DIAGNOSTICS_SLUG = 'edis-evidence-diagnostics';

    /** @param array<string,mixed> $context Privacy-safe diagnostic context. */
    public function __construct(
        private readonly PrivateStorage $storage,
        private readonly string $diagnosticCode,
        private readonly array $context = array(),
    ) {}

    /** Register notices, recovery UI/actions, Site Health and degraded-mode WP-CLI diagnostics. */
    public function register(): void {
        add_action( 'admin_menu', array( $this, 'registerAdminMenu' ) );
        add_action( 'admin_notices', array( $this, 'notice' ) );
        add_action( 'network_admin_notices', array( $this, 'notice' ) );
        add_action( 'admin_post_edis_storage_retest', array( $this, 'retest' ) );
        ( new SiteHealthIntegration( $this->storage, null, $this->diagnosticCode ) )->register();

        if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\\WP_CLI' ) ) {
            \WP_CLI::add_command( 'edis storage paths', array( $this, 'cliPaths' ) );
            \WP_CLI::add_command( 'edis storage self-test', array( $this, 'cliSelfTest' ) );
        }
    }

    /** Register the minimal recovery-only EDIS admin surface. */
    public function registerAdminMenu(): void {
        add_menu_page(
            __( 'EDIS Evidence', 'edis-evidence-exporter' ),
            __( 'EDIS Evidence', 'edis-evidence-exporter' ),
            self::CAPABILITY,
            self::MENU_SLUG,
            array( $this, 'renderRecoveryPage' ),
            'dashicons-media-archive',
            81
        );
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Diagnostics / Recovery', 'edis-evidence-exporter' ),
            __( 'Diagnostics / Recovery', 'edis-evidence-exporter' ),
            self::CAPABILITY,
            self::DIAGNOSTICS_SLUG,
            array( $this, 'renderRecoveryPage' )
        );
    }

    /** Display a capability-protected, actionable failure notice. */
    public function notice(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }

        $facts = $this->recoveryFacts();
        echo '<div class="notice notice-error"><p>' . esc_html( $facts['message'] ) . '</p>';
        $this->renderStorageFacts( $facts );
        $this->renderRetestForm();
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::DIAGNOSTICS_SLUG ) ) . '">' . esc_html__( 'Open EDIS Diagnostics / Recovery', 'edis-evidence-exporter' ) . '</a></p>';
        echo '<p><code>wp edis storage paths</code> &nbsp; <code>wp edis storage self-test</code></p>';
        echo '</div>';
    }

    /** Render the self-contained degraded diagnostics/recovery page. */
    public function renderRecoveryPage(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }

        $facts = $this->recoveryFacts();
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'EDIS Evidence — Diagnostics / Recovery', 'edis-evidence-exporter' ) . '</h1>';
        echo '<div class="notice notice-error inline"><p>' . esc_html__( 'EDIS is in fail-closed recovery mode. Export creation, worker execution, operational REST controls, downloads, and scheduled export execution remain unavailable until the existing runtime, integrity, configuration, and storage gates pass.', 'edis-evidence-exporter' ) . '</p></div>';
        echo '<p><strong>' . esc_html__( 'Active diagnostic:', 'edis-evidence-exporter' ) . '</strong> <code>' . esc_html( $facts['diagnostic_code'] ) . '</code></p>';
        echo '<p>' . esc_html( $facts['message'] ) . '</p>';
        $this->renderStorageFacts( $facts );

        $status = isset( $_GET['edis_storage_test'] ) && is_string( $_GET['edis_storage_test'] )
            ? sanitize_key( wp_unslash( $_GET['edis_storage_test'] ) )
            : '';
        if ( 'passed' === $status ) {
            echo '<div class="notice notice-success inline"><p>' . esc_html__( 'The storage retest passed. Reload the next WordPress request so EDIS can re-evaluate all normal startup gates.', 'edis-evidence-exporter' ) . '</p></div>';
        } elseif ( 'failed' === $status ) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__( 'The storage retest still fails. Review the bounded checks below and correct the storage/runtime condition before retrying.', 'edis-evidence-exporter' ) . '</p></div>';
        }

        echo '<h2>' . esc_html__( 'Recovery action', 'edis-evidence-exporter' ) . '</h2>';
        $this->renderRetestForm();
        echo '<h2>' . esc_html__( 'Command-line diagnostics', 'edis-evidence-exporter' ) . '</h2>';
        echo '<p><code>wp edis storage paths</code><br /><code>wp edis storage self-test</code></p>';
        echo '<p>' . esc_html__( 'This recovery page intentionally provides diagnostics only. Normal EDIS export and operational controls return automatically on a later request only after the existing startup gates pass.', 'edis-evidence-exporter' ) . '</p>';
        echo '</div>';
    }

    /** Re-run storage verification from wp-admin and return to the referring screen. */
    public function retest(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You are not allowed to run the EDIS storage test.', 'edis-evidence-exporter' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'edis_storage_retest' );
        $result = $this->storage->selfTest( true );
        $status = $this->storage->acceptsSelfTestResult( $result ) ? 'passed' : 'failed';
        $target = wp_get_referer();
        if ( ! is_string( $target ) || '' === $target ) {
            $target = admin_url( 'admin.php?page=' . self::DIAGNOSTICS_SLUG );
        }
        wp_safe_redirect( add_query_arg( 'edis_storage_test', $status, $target ) );
        exit;
    }

    /** @param list<string> $args @param array<string,mixed> $assoc_args */
    public function cliPaths( array $args, array $assoc_args ): void {
        $this->printCliJson( $this->storage->diagnosticContext() );
    }

    /** @param list<string> $args @param array<string,mixed> $assoc_args */
    public function cliSelfTest( array $args, array $assoc_args ): void {
        $result = $this->storage->selfTest( true );
        $this->printCliJson(
            array(
                'diagnostic_code'    => $this->diagnosticCode,
                'diagnostic_context' => $this->storage->diagnosticContext(),
                'self_test'          => $result,
            )
        );
        if ( ! $this->storage->acceptsSelfTestResult( $result ) ) {
            \WP_CLI::error( __( 'EDIS storage self-test failed.', 'edis-evidence-exporter' ) );
        }
        \WP_CLI::success( __( 'EDIS storage self-test passed.', 'edis-evidence-exporter' ) );
    }

    /** @return array{diagnostic_code:string,message:string,candidate:string,failed:list<string>} */
    private function recoveryFacts(): array {
        $context    = array_merge( $this->storage->diagnosticContext(), $this->context );
        $candidates = is_array( $context['candidates'] ?? null ) ? $context['candidates'] : array();
        $candidate  = isset( $candidates[0] ) && is_string( $candidates[0] ) ? $candidates[0] : '';
        $self_test  = is_array( $context['storage_self_test'] ?? null )
            ? $context['storage_self_test']
            : $this->storage->selfTest();

        return array(
            'diagnostic_code' => $this->diagnosticCode,
            'message'         => $this->diagnosticMessage(),
            'candidate'       => $candidate,
            'failed'          => $this->failedChecks( $self_test ),
        );
    }

    /** Return the existing privacy-safe degraded diagnostic message. */
    private function diagnosticMessage(): string {
        return sprintf(
            /* translators: 1: diagnostic code, 2: suggested wp-config.php constant. */
            __( 'EDIS Evidence Exporter is active in fail-closed diagnostic mode. Exports are disabled, but WordPress remains available. Diagnostic: %1$s. In Local, EDIS derives a private directory from the documented <site>/app/public layout; otherwise define %2$s to a writable directory outside the public WordPress root.', 'edis-evidence-exporter' ),
            $this->diagnosticCode,
            'EDIS_EVIDENCE_PRIVATE_STORAGE_DIR'
        );
    }

    /** @param array{candidate:string,failed:list<string>} $facts */
    private function renderStorageFacts( array $facts ): void {
        if ( '' !== $facts['candidate'] ) {
            echo '<p><strong>' . esc_html__( 'Preferred private-storage path:', 'edis-evidence-exporter' ) . '</strong> <code>' . esc_html( $facts['candidate'] ) . '</code></p>';
        }
        if ( array() !== $facts['failed'] ) {
            echo '<p><strong>' . esc_html__( 'Failed checks:', 'edis-evidence-exporter' ) . '</strong> <code>' . esc_html( implode( ', ', $facts['failed'] ) ) . '</code></p>';
        }
    }

    /** Render the existing nonce-protected storage retest action. */
    private function renderRetestForm(): void {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="edis_storage_retest" />';
        wp_nonce_field( 'edis_storage_retest' );
        submit_button( __( 'Run EDIS storage test again', 'edis-evidence-exporter' ), 'secondary', 'submit', false );
        echo '</form>';
    }

    /** @param array<string,mixed> $result @return list<string> */
    private function failedChecks( array $result ): array {
        $failed = array();
        foreach ( array( 'security_state', 'writable', 'atomic_write', 'atomic_replace', 'rename', 'fsync', 'lock_exclusion', 'multiprocess_lock_exclusion', 'cleanup' ) as $key ) {
            $value = $result[ $key ] ?? null;
            $passes = match ( $key ) {
                'security_state' => 'OUTSIDE_WEB_ROOT' === $value,
                'multiprocess_lock_exclusion' => 'PASS' === $value,
                default => true === $value,
            };
            if ( ! $passes ) {
                $failed[] = $key;
            }
        }
        return $failed;
    }

    /** @param array<string,mixed> $data */
    private function printCliJson( array $data ): void {
        $encoded = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( ! is_string( $encoded ) ) {
            \WP_CLI::error( __( 'EDIS could not encode the command result.', 'edis-evidence-exporter' ) );
        }
        \WP_CLI::line( $encoded );
    }
}
