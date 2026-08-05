<?php
declare(strict_types=1);

use EDIS\EvidenceExporter\Application\DiagnosticRecordService;
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\DiagnosticRecordStore;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportIntegrityException;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;
use EDIS\EvidenceExporter\Rest\CanonicalDiagnosticResponse;
use EDIS\EvidenceExporter\Rest\CanonicalDiagnosticResponseAdapter;

if (!defined('ABSPATH') || !defined('EDIS_EVIDENCE_EXPORTER_PATH')) {
    fwrite(STDERR, "Installed EDIS WordPress runtime is required.\n");
    exit(2);
}

/** @param mixed $condition */
function edis_rest_assert($condition, string $message, int $code): void
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit($code);
    }
}

/** @return \WP_REST_Response|\WP_Error */
function edis_rest_dispatch(\WP_REST_Server $server, string $method, string $route, array $params = [])
{
    $request = new \WP_REST_Request($method, $route);
    foreach ($params as $key => $value) {
        $request->set_param((string) $key, $value);
    }
    return $server->dispatch($request);
}

function edis_rest_serve(\WP_REST_Server $server, string $method, string $route, array $query = []): string
{
    $oldMethod = $_SERVER['REQUEST_METHOD'] ?? null;
    $oldGet = $_GET;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_GET = $query;
    ob_start();
    $server->serve_request($route);
    $body = (string) ob_get_clean();
    $_GET = $oldGet;
    if ($oldMethod === null) {
        unset($_SERVER['REQUEST_METHOD']);
    } else {
        $_SERVER['REQUEST_METHOD'] = $oldMethod;
    }
    return $body;
}

$installedRoot = realpath((string) EDIS_EVIDENCE_EXPORTER_PATH);
$checkoutRoot = realpath(dirname(__DIR__, 2));
edis_rest_assert(is_string($installedRoot) && $installedRoot !== '', 'Installed plugin root could not be resolved.', 3);
edis_rest_assert(str_ends_with(str_replace('\\', '/', $installedRoot), '/tmp/plugin-build/edis-evidence-exporter'), 'Integration test is not bound to the installed plugin build.', 4);
edis_rest_assert($checkoutRoot === false || $installedRoot !== $checkoutRoot, 'Source checkout was substituted for the installed plugin runtime.', 5);
foreach ([CanonicalDiagnosticResponse::class, CanonicalDiagnosticResponseAdapter::class] as $class) {
    edis_rest_assert(class_exists($class), 'Canonical REST transport class did not autoload: ' . $class, 6);
    $file = realpath((new ReflectionClass($class))->getFileName() ?: '');
    edis_rest_assert(is_string($file) && str_starts_with($file, $installedRoot . DIRECTORY_SEPARATOR), 'Canonical REST class did not load from the installed plugin.', 7);
}

$administrators = get_users(['role' => 'administrator', 'number' => 1]);
edis_rest_assert($administrators !== [], 'Administrator fixture missing.', 8);
$adminId = (int) $administrators[0]->ID;
wp_set_current_user($adminId);

$filesystem = new DeterministicFilesystem();
$privateStorage = new PrivateStorage();
$jobs = new JobStore($privateStorage->path('jobs'), $filesystem);
$diagnosticRoot = $privateStorage->path('diagnostics');
$store = new DiagnosticRecordStore($diagnosticRoot, $filesystem, 3600);
$records = new DiagnosticRecordService($store, $jobs, $installedRoot . DIRECTORY_SEPARATOR, $filesystem);

$result = $records->capturePreJobFailure(
    $adminId,
    'REST_EXACT_BYTES_TEST',
    '/edis-evidence-exporter/v3/diagnostics/{diagnostic_id}',
    [
        'privacy_mode' => 'Strict',
        'collectors' => ['environment', 'plugin'],
        'document_ids' => [],
        'options' => ['export_scope' => 'METADATA_ONLY'],
    ],
    new RuntimeException('not exported'),
    [
        'lifecycle_stage' => 'unicode_آزمون',
        'subsystem' => 'REST/Canonical',
        'operation_immediately_attempted' => 'serve nested canonical bytes / without re-encoding',
    ],
);
edis_rest_assert(($result['diagnostic_available'] ?? false) === true, 'Diagnostic fixture was unavailable.', 9);
$id = (string) $result['diagnostic_id'];
$resolved = $records->resolveForOwner($adminId, $id, static fn (int $documentId): bool => current_user_can('edit_post', $documentId));
edis_rest_assert(is_array($resolved), 'Diagnostic fixture could not resolve.', 10);

$server = rest_get_server();
$route = '/edis-evidence-exporter/v3/diagnostics/' . $id;
$dispatched = edis_rest_dispatch($server, 'GET', $route);
edis_rest_assert($dispatched instanceof CanonicalDiagnosticResponse, 'Authorized GET lost the canonical response type.', 11);
$headers = array_change_key_case($dispatched->get_headers(), CASE_LOWER);
edis_rest_assert(str_starts_with((string) ($headers['content-type'] ?? ''), DiagnosticRecordService::MEDIA_TYPE), 'Canonical media type was not preserved.', 12);
edis_rest_assert((string) ($headers['content-length'] ?? '') === (string) strlen($resolved['bytes']), 'Canonical Content-Length is incorrect.', 13);
edis_rest_assert(str_contains((string) ($headers['cache-control'] ?? ''), 'no-store'), 'Canonical no-store header is missing.', 14);
edis_rest_assert(strtolower((string) ($headers['x-content-type-options'] ?? '')) === 'nosniff', 'Canonical nosniff header is missing.', 15);

add_filter('rest_post_dispatch', static function ($response, $server, $request) use ($route) {
    if ($request->get_route() === $route && $response instanceof \WP_REST_Response) {
        $response->set_data(['mutated' => true]);
    }
    return $response;
}, 99, 3);
add_filter('rest_pre_echo_response', static fn ($data) => ['echo_filter_mutated' => true], 99);
add_filter('rest_json_encode_options', static fn ($options) => $options | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES, 99);

$body = edis_rest_serve($server, 'GET', $route, ['_pretty' => '1', '_embed' => '1']);
edis_rest_assert(hash_equals($resolved['bytes'], $body), 'Canonical REST bytes changed.', 16);
$headBody = edis_rest_serve($server, 'HEAD', $route);
edis_rest_assert($headBody === '', 'HEAD did not terminate with an empty body.', 17);

$envelope = edis_rest_dispatch($server, 'GET', $route, ['_envelope' => '1']);
edis_rest_assert($envelope instanceof \WP_Error && $envelope->get_error_code() === 'edis_diagnostic_envelope_unsupported' && (int) ($envelope->get_error_data()['status'] ?? 0) === 406, 'Authorized canonical _envelope request was not rejected deterministically.', 18);

$ordinaryRoute = '/edis-evidence-exporter/v3/diagnostics';
$ordinaryResponse = edis_rest_dispatch($server, 'GET', $ordinaryRoute);
edis_rest_assert(!$ordinaryResponse instanceof CanonicalDiagnosticResponse, 'Ordinary route used canonical transport.', 19);
$ordinaryBody = edis_rest_serve($server, 'GET', $ordinaryRoute);
edis_rest_assert(str_contains($ordinaryBody, 'echo_filter_mutated'), 'Ordinary route did not retain normal REST filtering and serialization.', 20);

$otherId = wp_create_user(
    'edis-rest-other-' . strtolower(wp_generate_password(6, false)),
    wp_generate_password(20),
    'edis-rest-other-' . strtolower(wp_generate_password(6, false)) . '@example.test',
);
edis_rest_assert(!is_wp_error($otherId), 'Secondary REST user could not be created.', 21);
$other = get_user_by('id', (int) $otherId);
edis_rest_assert($other instanceof \WP_User, 'Secondary REST user could not be loaded.', 22);
wp_set_current_user((int) $otherId);
$forbidden = edis_rest_dispatch($server, 'GET', $route, ['_envelope' => '1']);
edis_rest_assert($forbidden instanceof \WP_Error && (int) ($forbidden->get_error_data()['status'] ?? 0) === 403, 'Capability denial did not precede canonical envelope handling.', 23);

$other->add_cap('edis_export_evidence');
$wrongOwner = edis_rest_dispatch($server, 'GET', $route, ['_envelope' => '1']);
edis_rest_assert($wrongOwner instanceof \WP_Error && (int) ($wrongOwner->get_error_data()['status'] ?? 0) === 404, 'Wrong owner was not hidden by 404 before envelope handling.', 24);

wp_set_current_user($adminId);
$missingRoute = '/edis-evidence-exporter/v3/diagnostics/edis-diag-' . str_repeat('f', 32);
$missing = edis_rest_dispatch($server, 'GET', $missingRoute, ['_envelope' => '1']);
edis_rest_assert($missing instanceof \WP_Error && (int) ($missing->get_error_data()['status'] ?? 0) === 404, 'Nonexistent diagnostic did not retain bounded 404.', 25);

$expiredRecord = $resolved['record'];
$expiredRecord['diagnostic_identity']['expires_at'] = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
$expiredPath = $diagnosticRoot . '/user-' . $adminId . '/' . $id . '.json';
$filesystem->writeAtomically($expiredPath, CanonicalJson::encode($expiredRecord), 0640);
$expired = edis_rest_dispatch($server, 'GET', $route, ['_envelope' => '1']);
edis_rest_assert($expired instanceof \WP_Error && (int) ($expired->get_error_data()['status'] ?? 0) === 404, 'Expired diagnostic did not retain bounded 404.', 26);

$postId = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'draft',
    'post_title' => 'EDIS REST authorization fixture',
    'post_author' => (int) $otherId,
], true);
edis_rest_assert(!is_wp_error($postId) && (int) $postId > 0, 'Authorization fixture post could not be created.', 27);
$jobId = 'rest-auth-' . strtolower(wp_generate_password(12, false));
$jobs->create([
    'job_id' => $jobId,
    'owner_id' => (int) $otherId,
    'status' => 'failed',
    'phase' => 'failed',
    'revision' => 1,
    'created_at' => time() - 10,
    'expires_at' => time() + 3600,
    'last_error_at' => time(),
    'last_error_code' => 'EDIS_EXPORT_ADVANCE_FAILED',
    'selected_components' => ['environment'],
    'selected_document_count' => 1,
    'config' => [
        'privacy_mode' => 'Strict',
        'document_ids' => [(int) $postId],
        'options' => ['export_scope' => 'SINGLE_DOCUMENT'],
    ],
    'diagnostics' => [[
        'code' => 'EDIS_EXPORT_ADVANCE_FAILED',
        'severity' => 'ERROR',
        'scope' => 'OPERATIONAL',
        'message_key' => 'diagnostic.export.advance_failed',
        'context' => ['failure_phase' => 'collecting'],
    ]],
]);
$jobDiagnostic = $records->captureJobFailure(
    (int) $otherId,
    $jobId,
    'EXPORT_ADVANCE',
    '/edis-evidence-exporter/v3/export-jobs/{job_id}/advance',
    new ExportIntegrityException('EDIS_EXPORT_ADVANCE_FAILED', 'not exported'),
);
edis_rest_assert(($jobDiagnostic['diagnostic_available'] ?? false) === true, 'Job-bound authorization fixture was unavailable.', 28);
wp_set_current_user((int) $otherId);
$revokedRoute = '/edis-evidence-exporter/v3/diagnostics/' . (string) $jobDiagnostic['diagnostic_id'];
$revoked = edis_rest_dispatch($server, 'GET', $revokedRoute, ['_envelope' => '1']);
edis_rest_assert($revoked instanceof \WP_Error && (int) ($revoked->get_error_data()['status'] ?? 0) === 404, 'Revoked document access did not retain bounded 404.', 29);

wp_set_current_user($adminId);
wp_delete_post((int) $postId, true);
wp_delete_user((int) $otherId);

echo "EDIS canonical diagnostic REST: PASS\n";
