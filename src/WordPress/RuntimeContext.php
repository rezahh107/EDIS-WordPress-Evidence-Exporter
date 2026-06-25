<?php
/**
 * Runtime-context detection for conditional plugin bootstrapping.
 *
 * @package EDIS\EvidenceExporter
 */
declare(strict_types=1);

namespace EDIS\EvidenceExporter\WordPress;

/**
 * Detect whether the current request needs the EDIS application runtime.
 */
final class RuntimeContext {
	/** Determine whether application services are required. */
	public function requiresApplicationRuntime(): bool {
		return $this->isAdmin()
			|| $this->isRest()
			|| $this->isCron()
			|| $this->isCli()
			|| $this->isAjax();
	}

	/** Determine whether this is a WordPress administration request. */
	public function isAdmin(): bool {
		return function_exists( 'is_admin' ) && is_admin();
	}

	/** Determine whether this is a REST API request. */
	public function isRest(): bool {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only route detection; no state is changed.
		$route = $this->requestValue( $_GET, 'rest_route' );
		if ( '' !== $route ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- requestValue() normalizes and sanitizes the string.
		$uri    = $this->requestValue( $_SERVER, 'REQUEST_URI', true );
		$prefix = function_exists( 'rest_get_url_prefix' ) ? '/' . trim( rest_get_url_prefix(), '/' ) . '/' : '/wp-json/';
		return '' !== $uri && str_contains( $uri, $prefix );
	}

	/** Determine whether WordPress Cron is running. */
	public function isCron(): bool {
		return function_exists( 'wp_doing_cron' ) ? wp_doing_cron() : ( defined( 'DOING_CRON' ) && DOING_CRON );
	}

	/** Determine whether WordPress AJAX is running. */
	public function isAjax(): bool {
		return function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : ( defined( 'DOING_AJAX' ) && DOING_AJAX );
	}

	/** Determine whether WP-CLI is running. */
	public function isCli(): bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Read a scalar request value without trusting arrays or objects.
	 *
	 * @param array<string,mixed> $source Request source.
	 * @param string              $key    Request key.
	 * @param bool                $url    Whether URL sanitization is required.
	 */
	private function requestValue( array $source, string $key, bool $url = false ): string {
		$value = $source[ $key ] ?? '';
		if ( ! is_string( $value ) ) {
			return '';
		}
		if ( function_exists( 'wp_unslash' ) ) {
			$value = wp_unslash( $value );
		}
		if ( $url && function_exists( 'esc_url_raw' ) ) {
			return (string) esc_url_raw( $value );
		}
		if ( function_exists( 'sanitize_text_field' ) ) {
			return (string) sanitize_text_field( $value );
		}
		return $value;
	}
}
