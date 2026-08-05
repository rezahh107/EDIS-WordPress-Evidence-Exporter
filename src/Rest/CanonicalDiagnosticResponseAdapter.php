<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Rest;

final class CanonicalDiagnosticResponseAdapter
{
    private const REQUEST_ATTRIBUTE = '_edis_canonical_diagnostic_response';

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        add_filter('rest_request_after_callbacks', [self::class, 'restoreAfterCallbacks'], PHP_INT_MAX, 3);
        add_filter('rest_pre_serve_request', [self::class, 'serve'], 10, 4);
    }

    public static function bind(
        \WP_REST_Request $request,
        CanonicalDiagnosticResponse $response,
    ): CanonicalDiagnosticResponse {
        $attributes = $request->get_attributes();
        $attributes[self::REQUEST_ATTRIBUTE] = $response;
        $request->set_attributes($attributes);
        return $response;
    }

    public static function restoreAfterCallbacks(
        mixed $response,
        mixed $handler,
        mixed $request,
    ): mixed {
        if (!$request instanceof \WP_REST_Request
            || !self::isCanonicalRequest($request)
            || is_wp_error($response)
            || ($response instanceof \WP_REST_Response && $response->get_status() >= 400)
        ) {
            return $response;
        }

        return self::boundResponse($request) ?? $response;
    }

    public static function serve(
        bool $served,
        mixed $response,
        mixed $request,
        mixed $server,
    ): bool {
        if ($served
            || !$request instanceof \WP_REST_Request
            || !self::isCanonicalRequest($request)
            || ($response instanceof \WP_REST_Response && $response->get_status() >= 400)
        ) {
            return $served;
        }

        $canonicalResponse = $response instanceof CanonicalDiagnosticResponse
            ? $response
            : self::boundResponse($request);
        if (!$canonicalResponse instanceof CanonicalDiagnosticResponse) {
            return $served;
        }

        if ($server instanceof \WP_REST_Server) {
            foreach ($canonicalResponse->get_headers() as $name => $value) {
                $server->send_header((string) $name, (string) $value);
            }
            $server->set_status($canonicalResponse->get_status());
        }

        if ($request->get_method() === 'GET') {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Persisted EDIS-CJ-2 bytes are the response authority and must not be escaped or re-encoded.
            echo $canonicalResponse->canonicalBytes();
        }
        return true;
    }

    private static function boundResponse(\WP_REST_Request $request): ?CanonicalDiagnosticResponse
    {
        $attributes = $request->get_attributes();
        $response = $attributes[self::REQUEST_ATTRIBUTE] ?? null;
        return $response instanceof CanonicalDiagnosticResponse ? $response : null;
    }

    private static function isCanonicalRequest(\WP_REST_Request $request): bool
    {
        return in_array($request->get_method(), ['GET', 'HEAD'], true)
            && preg_match(
                '~\A/edis-evidence-exporter/v3/diagnostics/edis-diag-[a-f0-9]{32}\z~D',
                $request->get_route(),
            ) === 1;
    }
}
