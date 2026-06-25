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
    /** @param array<string,mixed> $context Privacy-safe diagnostic context. */
    public function __construct(
        private readonly PrivateStorage $storage,
        private readonly string $diagnosticCode,
        private readonly array $context = array(),
    ) {}

    /** Register notices, recovery actions, Site Health and degraded-mode WP-CLI diagnostics. */
    public function register(): void {
        add_action( 'admin_notices', array( $this, 'notice' ) );
        add_action( 'network_admin_notices', array( $this, 'notice' ) );
        add_action( 'admin_post_edis_storage_retest', array( $this, 'retest' ) );
        ( new SiteHealthIntegration( $this->storage, null, $this->diagnosticCode ) )->register();

        if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\\WP_CLI' ) ) {
            \WP_CLI::add_command( 'edis storage paths', array( $this, 'cliPaths' ) );
            \WP_CLI::add_command( 'edis storage self-test', array( $this, 'cliSelfTest' ) );
        }
    }

    /** Display a capability-protected, actionable failure notice. */
    public function notice(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $context    = array_merge( $this->storage->diagnosticContext(), $this->context );
        $candidates = is_array( $context['candidates'] ?? null ) ? $context['candidates'] : array();
        $candidate  = isset( $candidates[0] ) && is_string( $candidates[0] ) ? $candidates[0] : '';
        $self_test  = is_array( $context['storage_self_test'] ?? null )
            ? $context['storage_self_test']
            : $this->storage->selfTest();
        $failed     = $this->failedChecks( $self_test );

        $message = sprintf(
            /* translators: 1: diagnostic code, 2: suggested wp-config.php constant. */
            __( 'EDIS Evidence Exporter is active in fail-closed diagnostic mode. Exports are disabled, but WordPress remains available. Diagnostic: %1$s. In Local, EDIS derives a private directory from the documented <site>/app/public layout; otherwise define %2$s to a writable directory outside the public WordPress root.', 'edis-evidence-exporter' ),
            $this->diagnosticCode,
            'EDIS_EVIDENCE_PRIVATE_STORAGE_DIR'
        );

        echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p>';
        if ( '' !== $candidate ) {
            echo '<p><strong>' . esc_html__( 'Preferred private-storage path:', 'edis-evidence-exporter' ) . '</strong> <code>' . esc_html( $candidate ) . '</code></p>';
        }
        if ( array() !== $failed ) {
            echo '<p><strong>' . esc_html__( 'Failed checks:', 'edis-evidence-exporter' ) . '</strong> <code>' . esc_html( implode( ', ', $failed ) ) . '</code></p>';
        }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="edis_storage_retest" />';
        wp_nonce_field( 'edis_storage_retest' );
        submit_button( __( 'Run EDIS storage test again', 'edis-evidence-exporter' ), 'secondary', 'submit', false );
        echo '</form>';
        echo '<p><code>wp edis storage paths</code> &nbsp; <code>wp edis storage self-test</code></p>';
        echo '</div>';
    }

    /** Re-run storage verification from wp-admin and return to the referring screen. */
    public function retest(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to run the EDIS storage test.', 'edis-evidence-exporter' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'edis_storage_retest' );
        $result = $this->storage->selfTest( true );
        $status = $this->storage->acceptsSelfTestResult( $result ) ? 'passed' : 'failed';
        $target = wp_get_referer();
        if ( ! is_string( $target ) || '' === $target ) {
            $target = admin_url( 'plugins.php' );
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
