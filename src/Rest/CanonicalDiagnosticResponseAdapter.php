<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Rest;

final class CanonicalDiagnosticResponseAdapter
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        add_filter('rest_pre_serve_request', [self::class, 'serve'], 10, 4);
    }

    public static function serve(
        bool $served,
        mixed $response,
        mixed $request,
        mixed $server,
    ): bool {
        if ($served
            || !$response instanceof CanonicalDiagnosticResponse
            || !$request instanceof \WP_REST_Request
            || !in_array($request->get_method(), ['GET', 'HEAD'], true)
            || preg_match('~\A/edis-evidence-exporter/v3/diagnostics/edis-diag-[a-f0-9]{32}\z~D', $request->get_route()) !== 1
        ) {
            return $served;
        }

        if ($request->get_method() === 'GET') {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Persisted EDIS-CJ-2 bytes are the response authority and must not be escaped or re-encoded.
            echo $response->canonicalBytes();
        }
        return true;
    }
}
