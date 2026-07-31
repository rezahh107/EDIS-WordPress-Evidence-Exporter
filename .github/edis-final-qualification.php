<?php
declare(strict_types=1);

use EDIS\EvidenceExporter\Admin\Settings\SettingsRepository;
use EDIS\EvidenceExporter\Application\DiagnosticsService;
use EDIS\EvidenceExporter\Application\ExportJobService;
use EDIS\EvidenceExporter\Application\ExportService;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Support\ArtifactStore;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportFileStore;
use EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;

if (!defined('ABSPATH') || !defined('EDIS_EVIDENCE_EXPORTER_PATH')) {
    throw new RuntimeException('EDIS exact plugin root unavailable.');
}
if (!did_action('elementor/loaded')) {
    throw new RuntimeException('Elementor did not load.');
}

$ownerId = 1;
wp_set_current_user($ownerId);
$postIds = [];

try {
    foreach ([1, 2] as $index) {
        $postId = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => 'EDIS qualification ' . $index,
            'post_content' => '',
        ], true);
        if (is_wp_error($postId) || !is_int($postId) || $postId <= 0) {
            throw new RuntimeException('Could not create qualification document.');
        }
        $postIds[] = $postId;
        $data = [[
            'id' => 'root' . $index . 'aaaa',
            'elType' => 'container',
            'isInner' => false,
            'settings' => [],
            'elements' => [[
                'id' => 'head' . $index . 'aaaa',
                'elType' => 'widget',
                'widgetType' => 'heading',
                'settings' => ['title' => 'Qualification ' . $index],
                'elements' => [],
            ]],
        ]];
        update_post_meta($postId, '_elementor_edit_mode', 'builder');
        update_post_meta($postId, '_elementor_template_type', 'wp-page');
        update_post_meta($postId, '_elementor_version', defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '4.1.3');
        update_post_meta($postId, '_elementor_data', wp_slash(wp_json_encode($data, JSON_UNESCAPED_SLASHES)));
    }

    $pluginRoot = EDIS_EVIDENCE_EXPORTER_PATH;
    $privateStorage = new PrivateStorage();
    $filesystem = new DeterministicFilesystem();
    $registry = CollectorRegistry::fromDefinitions(require $pluginRoot . 'config/collectors.php');
    $settings = new SettingsRepository();
    $jobs = new JobStore($privateStorage->path('jobs'), $filesystem);
    $artifacts = new ArtifactStore($privateStorage->path('artifacts'), $filesystem);
    $files = new ExportFileStore($settings, $privateStorage->path('bundles'), $filesystem);
    $inputs = new InputSnapshotStore($privateStorage->path('inputs'), null, $filesystem);
    $exporter = new ExportService($registry, $pluginRoot);
    $worker = new ExportJobService($registry, $exporter, $jobs, $artifacts, $files, $settings, $inputs, $privateStorage);

    $request = [
        'privacy_mode' => 'Strict',
        'collectors' => $registry->defaultSelectableIds(),
        'document_ids' => $postIds,
        'options' => [
            'include_original_documents' => false,
            'export_scope' => 'MULTIPLE_DOCUMENTS',
            'dependency_scope' => 'REQUIRED_DEPENDENCIES',
            'compare_previous_export' => false,
        ],
    ];

    $created = $worker->create($ownerId, $request);
    $jobId = (string) ($created['job_id'] ?? '');
    if ($jobId === '') {
        throw new RuntimeException('No qualification job ID.');
    }

    $terminal = null;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $terminal = $worker->advance($jobId, $ownerId, null, 15000);
        if (in_array((string) ($terminal['status'] ?? ''), ['completed', 'failed', 'cancelled'], true)) {
            break;
        }
    }
    if (!is_array($terminal)
        || ($terminal['status'] ?? null) !== 'completed'
        || ($terminal['phase'] ?? null) !== 'completed'
        || (int) ($terminal['progress'] ?? -1) !== 100
        || ($terminal['validation_state'] ?? null) !== 'PASS') {
        throw new RuntimeException('Exact final multi-document export did not complete with PASS.');
    }

    $stored = $jobs->get($jobId);
    if (!is_array($stored)
        || ($stored['implementation_version'] ?? null) !== '3.7.14'
        || ($stored['completed_components'] ?? null) !== ($stored['selected_components'] ?? null)) {
        throw new RuntimeException('Selected components were not fully accounted for under worker 3.7.14.');
    }

    $token = is_string($stored['download_token'] ?? null) ? $stored['download_token'] : '';
    $bundlePath = $token === '' ? null : $files->authorize($jobId, $token);
    if (!is_string($bundlePath) || !is_file($bundlePath)) {
        throw new RuntimeException('Download authorization/integrity verification failed.');
    }

    $manifestBytes = $files->readStoredEntry($bundlePath, 'package-manifest.json');
    $validationBytes = $files->readStoredEntry($bundlePath, 'validation/package-validation.json');
    if (!is_string($manifestBytes) || !is_string($validationBytes)) {
        throw new RuntimeException('Required package entries unavailable.');
    }
    $manifest = json_decode($manifestBytes, true, 512, JSON_THROW_ON_ERROR);
    $validation = json_decode($validationBytes, true, 512, JSON_THROW_ON_ERROR);
    if (($manifest['producer']['version'] ?? null) !== '3.7.14'
        || ($manifest['data']['bundle_schema_version'] ?? null) !== '3.3.0'
        || ($validation['data']['evidence']['state'] ?? null) !== 'PASS') {
        throw new RuntimeException('Final package identity/validation mismatch.');
    }

    $diagnostics = new DiagnosticsService(
        $registry,
        $jobs,
        $artifacts,
        $files,
        $settings,
        $inputs,
        $worker,
        $pluginRoot,
        $filesystem,
    );
    $identity = $diagnostics->report()['build_identity'] ?? null;
    if (!is_array($identity)) {
        throw new RuntimeException('Runtime build identity unavailable.');
    }
    file_put_contents(
        '/tmp/edis-runtime-build-identity.json',
        json_encode($identity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    );
    fwrite(STDOUT, "EDIS_FINAL_MULTI_DOCUMENT_QUALIFICATION_PASS job={$jobId}\n");
} finally {
    foreach ($postIds as $postId) {
        wp_delete_post($postId, true);
    }
}
