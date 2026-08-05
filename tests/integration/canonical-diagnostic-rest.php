<?php
declare(strict_types=1);

use EDIS\EvidenceExporter\Application\DiagnosticRecordService;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\DiagnosticRecordStore;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;

if (!defined('ABSPATH')) { fwrite(STDERR, "WordPress is required.\n"); exit(2); }
$admin = get_users(['role' => 'administrator', 'number' => 1]);
if ($admin === []) { fwrite(STDERR, "Administrator fixture missing.\n"); exit(3); }
wp_set_current_user((int) $admin[0]->ID);
$filesystem = new DeterministicFilesystem();
$jobs = new JobStore(null, $filesystem);
$store = new DiagnosticRecordStore(null, $filesystem, 3600);
$records = new DiagnosticRecordService($store, $jobs, dirname(__DIR__, 2) . '/', $filesystem);
$result = $records->capturePreJobFailure(
    get_current_user_id(),
    'REST_EXACT_BYTES_TEST',
    '/edis-evidence-exporter/v3/diagnostics/{diagnostic_id}',
    ['privacy_mode' => 'Strict', 'collectors' => ['environment', 'plugin'], 'document_ids' => [], 'options' => ['export_scope' => 'METADATA_ONLY']],
    new RuntimeException('not exported'),
    ['lifecycle_stage' => 'unicode_آزمون', 'subsystem' => 'REST/Canonical', 'operation_immediately_attempted' => 'serve nested canonical bytes / without re-encoding'],
);
if (($result['diagnostic_available'] ?? false) !== true) { fwrite(STDERR, "Diagnostic fixture was unavailable.\n"); exit(4); }
$id = (string) $result['diagnostic_id'];
$resolved = $records->resolveForOwner(get_current_user_id(), $id, static fn(int $documentId): bool => current_user_can('edit_post', $documentId));
if (!is_array($resolved)) { fwrite(STDERR, "Diagnostic fixture could not resolve.\n"); exit(5); }
$server = rest_get_server();
$route = '/edis-evidence-exporter/v3/diagnostics/' . $id;
add_filter('rest_post_dispatch', static function ($response, $server, $request) use ($route) {
    if ($request->get_route() === $route && $response instanceof WP_REST_Response) { $response->set_data(['mutated' => true]); }
    return $response;
}, 99, 3);
add_filter('rest_pre_echo_response', static fn($data) => ['echo_filter_mutated' => true], 99);
add_filter('rest_json_encode_options', static fn($options) => $options | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES, 99);
$_GET = ['_pretty' => '1', '_embed' => '1', '_envelope' => '1'];
ob_start();
$server->serve_request($route);
$body = (string) ob_get_clean();
if (!hash_equals($resolved['bytes'], $body)) { fwrite(STDERR, "Canonical REST bytes changed.\n"); exit(6); }
$request = new WP_REST_Request('HEAD', $route);
$request->set_param('diagnostic_id', $id);
$response = $server->dispatch($request);
if (!$response instanceof EDIS\EvidenceExporter\Rest\CanonicalDiagnosticResponse) { fwrite(STDERR, "HEAD route lost canonical response type.\n"); exit(7); }
ob_start();
EDIS\EvidenceExporter\Rest\CanonicalDiagnosticResponseAdapter::serve(false, $response, $request, $server);
$headBody = (string) ob_get_clean();
if ($headBody !== '') { fwrite(STDERR, "HEAD emitted a body.\n"); exit(8); }
$ordinary = new WP_REST_Request('GET', '/edis-evidence-exporter/v3/diagnostics');
$ordinaryResponse = $server->dispatch($ordinary);
if ($ordinaryResponse instanceof EDIS\EvidenceExporter\Rest\CanonicalDiagnosticResponse) { fwrite(STDERR, "Ordinary route used canonical transport.\n"); exit(9); }
$other = wp_create_user('edis-rest-other-' . wp_generate_password(6, false), wp_generate_password(20), 'edis-rest-other@example.test');
$user = get_user_by('id', $other);
$user->add_cap('edis_export_evidence');
wp_set_current_user((int) $other);
$denied = $server->dispatch(new WP_REST_Request('GET', $route));
if (!$denied->is_error() || $denied->get_status() !== 404) { fwrite(STDERR, "Wrong owner was not hidden by 404.\n"); exit(10); }
wp_delete_user((int) $other);
echo "EDIS canonical diagnostic REST: PASS\n";
