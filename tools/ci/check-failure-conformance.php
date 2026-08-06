<?php
declare(strict_types=1);

/**
 * Validate the static, test-only EDIS failure-surface conformance authority.
 *
 * Production code must never read config/failure-surfaces.json. This validator
 * is intentionally kept under tools/ci and is executed only by tests or CI.
 *
 * @return list<string>
 */
function edis_failure_conformance_validate(string $root, bool $final = false): array
{
    $errors = [];
    $path = rtrim($root, '/\\') . '/config/failure-surfaces.json';
    if (!is_file($path)) {
        return ['Missing config/failure-surfaces.json.'];
    }

    try {
        $decoded = json_decode((string) file_get_contents($path), true, 128, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        return ['Failure-surface authority is not valid JSON: ' . $exception::class . '.'];
    }
    if (!is_array($decoded)) {
        return ['Failure-surface authority root must be an object.'];
    }

    if (($decoded['schema_version'] ?? null) !== '1.0.0') {
        $errors[] = 'Unknown failure-surface schema_version.';
    }
    if (($decoded['architecture_id'] ?? null) !== 'BOUNDED_HYBRID_SOURCE_FAILURE_CONTRACT_AND_OPERATION_BOUNDARY_POLICY') {
        $errors[] = 'Unexpected failure-conformance architecture_id.';
    }
    if (($decoded['runtime_dependency'] ?? null) !== false) {
        $errors[] = 'Failure-surface authority must be test/CI-only.';
    }

    $statuses = ['CONFORMING', 'NOT_APPLICABLE', 'PARTIALLY_INSTRUMENTED', 'LEGACY_UNCLASSIFIED'];
    $policies = ['NONE', 'PRE_JOB', 'JOB_BOUND', 'WORKER_BOUND', 'UNAVAILABLE_ONLY'];
    $classifications = [
        'EXPECTED_REJECTION', 'STRUCTURED_BLOCKER', 'AUTHORIZATION_DENIAL',
        'MATERIAL_PRE_JOB_INCIDENT', 'MATERIAL_JOB_INCIDENT', 'MATERIAL_WORKER_INCIDENT',
        'INTERNAL_EVIDENCE_FAILURE', 'AUTHORITY_UNAVAILABLE', 'UNEXPECTED_MATERIAL_FAILURE',
        'PROGRAMMER_INVARIANT_FAILURE',
    ];
    $required = [
        'REST_REQUEST_VALIDATION', 'AUTHORIZATION_OWNERSHIP_DENIAL', 'PREFLIGHT_STRUCTURED_BLOCKERS',
        'PREFLIGHT_EXCEPTIONAL_EXECUTION', 'EXPORT_CREATE_NORMALIZATION', 'PREFLIGHT_PROOF_VERIFICATION',
        'PREFLIGHT_SOURCE_REVALIDATION', 'COLLECTOR_SELECTION', 'EXECUTION_PLAN_CONSTRUCTION',
        'JOB_IDENTITY_PREPARATION', 'INPUT_SNAPSHOT_CAPTURE', 'SNAPSHOT_SELECTION_VALIDATION',
        'SELECTION_SNAPSHOT_CONSTRUCTION', 'JOB_PERSISTENCE', 'POST_CREATE_SCHEDULING',
        'JOB_ADVANCE', 'JOB_RESUME', 'JOB_RETRY', 'JOB_CANCEL', 'CRON_WORKER', 'WPCLI_WORKER',
        'SAFE_WORKER_TEST', 'INSPECTOR_SELECTION', 'INSPECTOR_BROWSER_CONSUMER',
        'COMPONENT_EVIDENCE_COLLECTION', 'DIAGNOSTIC_PROJECTION', 'DIAGNOSTIC_PERSISTENCE',
        'DIAGNOSTIC_CAPACITY', 'DIAGNOSTIC_EXPIRY', 'CANONICAL_REST_RETRIEVAL',
        'ADMIN_EXPORT_BROWSER', 'DOWNLOAD_AUTHORIZATION_INTEGRITY', 'UNSUPPORTED_RUNTIME',
        'DEGRADED_STORAGE', 'BOOTSTRAP_FAILURE', 'INSTALLATION_INTEGRITY', 'ACTIVATION',
        'MULTISITE_ACTIVATION_ROLLBACK', 'CLEANUP_RECOVERY_CALLBACKS', 'LEGACY_CONTROLLER_REGISTRATION',
    ];

    $families = $decoded['families'] ?? null;
    if (!is_array($families)) {
        return array_merge($errors, ['families must be an array.']);
    }
    $seen = [];
    foreach ($families as $index => $family) {
        if (!is_array($family)) {
            $errors[] = 'Family at index ' . $index . ' must be an object.';
            continue;
        }
        $id = is_string($family['id'] ?? null) ? $family['id'] : '';
        if ($id === '' || preg_match('/\A[A-Z0-9_]{3,96}\z/D', $id) !== 1) {
            $errors[] = 'Invalid operation-family id at index ' . $index . '.';
            continue;
        }
        if (isset($seen[$id])) {
            $errors[] = 'Duplicate operation-family id: ' . $id . '.';
            continue;
        }
        $seen[$id] = true;

        $status = $family['status'] ?? null;
        if (!is_string($status) || !in_array($status, $statuses, true)) {
            $errors[] = $id . ': unknown status.';
        }
        $policy = $family['creation_policy'] ?? null;
        if (!is_string($policy) || !in_array($policy, $policies, true)) {
            $errors[] = $id . ': unknown creation policy.';
        }
        $classification = $family['classification'] ?? null;
        if (!is_string($classification) || !in_array($classification, $classifications, true)) {
            $errors[] = $id . ': unknown classification.';
        }
        if (!is_string($family['classification_owner'] ?? null) || trim((string) $family['classification_owner']) === '') {
            $errors[] = $id . ': missing classification owner.';
        }
        $entrypoints = $family['production_entrypoints'] ?? null;
        $tests = $family['required_tests'] ?? null;
        if (!is_array($entrypoints) || $entrypoints === []) {
            $errors[] = $id . ': missing production entrypoints.';
        }
        if (!is_array($tests) || $tests === []) {
            $errors[] = $id . ': missing required tests.';
        }
        foreach (array_merge(is_array($entrypoints) ? $entrypoints : [], is_array($tests) ? $tests : []) as $evidencePath) {
            if (!is_string($evidencePath) || $evidencePath === '' || str_contains($evidencePath, '..')) {
                $errors[] = $id . ': invalid evidence path.';
                continue;
            }
            if (!file_exists(rtrim($root, '/\\') . '/' . $evidencePath)) {
                $errors[] = $id . ': missing evidence path ' . $evidencePath . '.';
            }
        }
        if ($status === 'NOT_APPLICABLE' && (!is_string($family['justification'] ?? null) || trim((string) $family['justification']) === '')) {
            $errors[] = $id . ': NOT_APPLICABLE requires a justification.';
        }
        if ($status === 'CONFORMING' && (!is_array($entrypoints) || $entrypoints === [] || !is_array($tests) || $tests === [])) {
            $errors[] = $id . ': CONFORMING requires executable evidence.';
        }
        if ($final && !in_array($status, ['CONFORMING', 'NOT_APPLICABLE'], true)) {
            $errors[] = $id . ': final conformance status is ' . (string) $status . '.';
        }
    }

    foreach ($required as $id) {
        if (!isset($seen[$id])) {
            $errors[] = 'Missing required operation family: ' . $id . '.';
        }
    }

    $runtimeRoots = ['src', 'assets', 'templates', 'edis-evidence-exporter.php', 'autoload.php'];
    foreach ($runtimeRoots as $relative) {
        $candidate = rtrim($root, '/\\') . '/' . $relative;
        $files = is_dir($candidate)
            ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($candidate, FilesystemIterator::SKIP_DOTS))
            : (is_file($candidate) ? [new SplFileInfo($candidate)] : []);
        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            $bytes = (string) file_get_contents($file->getPathname());
            if (str_contains($bytes, 'failure-surfaces.json')) {
                $errors[] = 'Production runtime reads the static conformance artifact: ' . str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($root, '/\\')) + 1)) . '.';
            }
        }
    }

    if ($final) {
        $inspector = (string) @file_get_contents(rtrim($root, '/\\') . '/src/Rest/InspectorSelectionController.php');
        if (preg_match('/diagnostic_id[^\n]+hash\s*\(/', $inspector) === 1 || str_contains($inspector, "'edis-' . substr(hash")) {
            $errors[] = 'Inspector still produces a fake Diagnostic ID.';
        }
        $cli = (string) @file_get_contents(rtrim($root, '/\\') . '/src/WordPress/CliCommands.php');
        if (!str_contains($cli, 'DiagnosticWorkerRunner')) {
            $errors[] = 'WP-CLI Worker bypasses DiagnosticWorkerRunner.';
        }
        $bootstrap = (string) @file_get_contents(rtrim($root, '/\\') . '/src/Bootstrap.php');
        if (!str_contains($bootstrap, 'DiagnosticExportJobController') || !str_contains($bootstrap, 'DiagnosticWorkerRunner')) {
            $errors[] = 'Canonical REST or Worker boundary is not registered.';
        }
    }

    return array_values(array_unique($errors));
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $root = dirname(__DIR__, 2);
    $final = in_array('--final', $argv ?? [], true);
    $errors = edis_failure_conformance_validate($root, $final);
    if ($errors !== []) {
        foreach ($errors as $error) {
            fwrite(STDERR, $error . PHP_EOL);
        }
        exit(1);
    }
    fwrite(STDOUT, 'EDIS failure-surface conformance: PASS' . PHP_EOL);
}
