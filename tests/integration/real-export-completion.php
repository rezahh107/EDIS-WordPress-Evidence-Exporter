<?php
declare(strict_types=1);

use EDIS\EvidenceExporter\Admin\Settings\SettingsRepository;
use EDIS\EvidenceExporter\Application\ExportJobService;
use EDIS\EvidenceExporter\Application\ExportService;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Support\ArtifactStore;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportFileStore;
use EDIS\EvidenceExporter\Infrastructure\Support\InputSnapshotStore;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use EDIS\EvidenceExporter\Infrastructure\Support\PrivateStorage;

if (!defined('ABSPATH') || !function_exists('wp_insert_post')) {
    fwrite(STDERR, "This harness must run inside WordPress via wp eval-file.\n");
    exit(1);
}
if (!did_action('elementor/loaded')) {
    fwrite(STDERR, "Elementor did not load.\n");
    exit(1);
}

$pluginRoot = dirname(__DIR__, 2) . '/';
$ownerId = 1;
wp_set_current_user($ownerId);
if (!current_user_can('manage_options')) {
    fwrite(STDERR, "The integration harness requires the disposable admin user.\n");
    exit(1);
}

$postId = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'EDIS 3.7.12 Real Export Gate',
    'post_content' => '',
], true);
if (is_wp_error($postId) || !is_int($postId) || $postId <= 0) {
    fwrite(STDERR, "Could not create the disposable Elementor document.\n");
    exit(1);
}

try {
    $elementorData = [[
        'id' => 'edisroot1',
        'elType' => 'container',
        'isInner' => false,
        'settings' => [],
        'elements' => [[
            'id' => 'edishead1',
            'elType' => 'widget',
            'widgetType' => 'heading',
            'settings' => ['title' => 'EDIS integration evidence'],
            'elements' => [],
        ]],
    ]];
    update_post_meta($postId, '_elementor_edit_mode', 'builder');
    update_post_meta($postId, '_elementor_template_type', 'wp-page');
    update_post_meta($postId, '_elementor_version', defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '4.1.3');
    update_post_meta($postId, '_elementor_data', wp_slash(wp_json_encode($elementorData, JSON_UNESCAPED_SLASHES)));

    $privateStorage = new PrivateStorage();
    $filesystem = new DeterministicFilesystem();
    $definitions = require $pluginRoot . 'config/collectors.php';
    $registry = CollectorRegistry::fromDefinitions($definitions);
    $settings = new SettingsRepository();
    $jobs = new JobStore($privateStorage->path('jobs'), $filesystem);
    $artifacts = new ArtifactStore($privateStorage->path('artifacts'), $filesystem);
    $files = new ExportFileStore($settings, $privateStorage->path('bundles'), $filesystem);
    $inputs = new InputSnapshotStore($privateStorage->path('inputs'), null, $filesystem);
    $exporter = new ExportService($registry, $pluginRoot);
    $service = new ExportJobService($registry, $exporter, $jobs, $artifacts, $files, $settings, $inputs, $privateStorage);

    $defaultCollectors = $registry->defaultSelectableIds();
    if ($defaultCollectors === []) {
        throw new RuntimeException('Default selectable collector set is empty.');
    }
    $expectedPlan = $registry->executionPlan($defaultCollectors, 'REQUIRED_DEPENDENCIES');

    $created = $service->create($ownerId, [
        'privacy_mode' => 'Strict',
        'collectors' => $defaultCollectors,
        'document_ids' => [$postId],
        'options' => [
            'include_original_documents' => false,
            'export_scope' => 'SINGLE_DOCUMENT',
            'dependency_scope' => 'REQUIRED_DEPENDENCIES',
            'compare_previous_export' => false,
        ],
    ]);
    $jobId = (string) ($created['job_id'] ?? '');
    if ($jobId === '') {
        throw new RuntimeException('Production service did not return a job ID.');
    }

    $stored = $jobs->get($jobId);
    if (!is_array($stored) || ($stored['selected_components'] ?? null) !== $expectedPlan) {
        throw new RuntimeException('Production job plan does not match the current default selectable dependency plan.');
    }
    if (($stored['implementation_version'] ?? null) !== '3.7.12') {
        throw new RuntimeException('Fresh job implementation_version is not 3.7.12.');
    }

    $terminal = null;
    for ($attempt = 0; $attempt < 25; $attempt++) {
        $terminal = $service->advance($jobId, $ownerId, null, 15000);
        if (in_array((string) ($terminal['status'] ?? ''), ['completed', 'failed', 'cancelled'], true)) {
            break;
        }
    }
    if (!is_array($terminal)) {
        throw new RuntimeException('Production application path returned no terminal job state.');
    }
    if (($terminal['status'] ?? null) !== 'completed'
        || (int) ($terminal['progress'] ?? -1) !== 100
        || ($terminal['validation_state'] ?? null) !== 'PASS') {
        throw new RuntimeException('Fresh real export did not reach completed/progress=100/validation_state=PASS.');
    }

    $stored = $jobs->get($jobId);
    if (!is_array($stored) || ($stored['implementation_version'] ?? null) !== '3.7.12') {
        throw new RuntimeException('Completed job implementation_version is not 3.7.12.');
    }
    $token = is_string($stored['download_token'] ?? null) ? $stored['download_token'] : '';
    $bundlePath = $token === '' ? null : $files->authorize($jobId, $token);
    if (!is_string($bundlePath) || !is_file($bundlePath)) {
        throw new RuntimeException('Deterministic ZIP failed the existing authorization/integrity boundary.');
    }
    $manifestBytes = $files->readStoredEntry($bundlePath, 'package-manifest.json');
    $validationBytes = $files->readStoredEntry($bundlePath, 'validation/package-validation.json');
    if (!is_string($manifestBytes) || !is_string($validationBytes)) {
        throw new RuntimeException('Deterministic ZIP does not expose required verified package entries.');
    }
    $manifest = json_decode($manifestBytes, true, 512, JSON_THROW_ON_ERROR);
    $validation = json_decode($validationBytes, true, 512, JSON_THROW_ON_ERROR);
    if (($manifest['producer']['version'] ?? null) !== '3.7.12'
        || ($manifest['data']['bundle_schema_version'] ?? null) !== '3.3.0'
        || ($validation['data']['evidence']['state'] ?? null) !== 'PASS') {
        throw new RuntimeException('Completed ZIP metadata does not prove the 3.7.12/Schema 3.3.0 PASS contract.');
    }

    fwrite(STDOUT, "EDIS_REAL_EXPORT_COMPLETION_PASS job={$jobId}\n");
} finally {
    wp_delete_post($postId, true);
}
