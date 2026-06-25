<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Application;

use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Support\ArtifactStore;
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportFileStore;
use EDIS\EvidenceExporter\Infrastructure\Support\JsonSchemaValidator;

final class ExportService
{
    private DeterministicFilesystem $filesystem;

    public function __construct(
        private readonly CollectorRegistry $registry,
        private readonly string $pluginRoot,
        ?DeterministicFilesystem $filesystem = null,
    ) {
        $this->filesystem = $filesystem ?? new DeterministicFilesystem();
    }

    /**
     * @param list<string> $executionPlan
     * @return array{path:string,sha256:string,size:int,token:string,expires_at:int,validation_state:string,source_export_root_sha256:string}
     */
    public function package(
        string $jobId,
        CollectionContext $context,
        array $executionPlan,
        ArtifactStore $artifactStore,
        ExportFileStore $fileStore,
        int $expiresAt,
    ): array {
        $files = [];
        $provenance = [];
        /** @var array<string,array{artifact_path:string,semantic_payload_sha256:?string}> $exportedIdentities */
        $exportedIdentities = [];

        // Process committed artifacts once, in the deterministic topological order.
        // This avoids retaining raw artifacts, envelopes and encoded files simultaneously.
        foreach ($executionPlan as $componentId) {
            $artifact = $artifactStore->get($jobId, $componentId);
            if (!is_array($artifact)) {
                throw new \RuntimeException('Missing committed component artifact: ' . $componentId);
            }
            $definition = $this->registry->definition($componentId);
            $envelope = $this->envelope(
                $definition->schemaId,
                $definition->schemaVersion,
                $componentId,
                $artifact,
                $context,
            );
            $provenance[$componentId] = $artifact['provenance'] ?? [];

            $references = is_array($envelope['data']['source_references'] ?? null)
                ? $envelope['data']['source_references']
                : [];
            $declared = [];
            foreach ($references as $reference) {
                if (is_array($reference) && is_string($reference['component_id'] ?? null)) {
                    $declared[(string) $reference['component_id']] = $reference;
                }
            }
            foreach ($definition->dependencies as $dependency) {
                $dependencyId = (string) ($dependency['id'] ?? '');
                if ($dependencyId === '' || !isset($exportedIdentities[$dependencyId])) {
                    continue;
                }
                $baseReference = $declared[$dependencyId] ?? [];
                unset($baseReference['artifact_payload_sha256']);
                $declared[$dependencyId] = array_merge($baseReference, [
                    'component_id' => $dependencyId,
                    'dependency_kind' => (string) ($dependency['kind'] ?? 'OPTIONAL'),
                    'source_artifact_path' => $exportedIdentities[$dependencyId]['artifact_path'],
                    'source_semantic_payload_sha256' => $exportedIdentities[$dependencyId]['semantic_payload_sha256'],
                    'source_file_sha256_location' => 'package-manifest.json files[].sha256',
                ]);
            }
            ksort($declared, SORT_STRING);
            $envelope['data']['source_references'] = array_values($declared);
            $envelope['data']['evidence'] = $this->normalizeEvidenceShape(
                $componentId,
                $envelope['data']['evidence'] ?? null,
            );
            CanonicalJson::applyHashes($envelope);
            $files[$definition->artifactPath] = CanonicalJson::encode($envelope);
            $exportedIdentities[$componentId] = [
                'artifact_path' => $definition->artifactPath,
                'semantic_payload_sha256' => is_string($envelope['semantic_payload_sha256'] ?? null)
                    ? $envelope['semantic_payload_sha256']
                    : null,
            ];
            unset($artifact, $envelope);
        }

        $provenanceEnvelope = $this->envelope(
            'urn:edis:schema:wordpress:provenance',
            '1.0.0',
            'source_provenance',
            [
                'component_type' => 'BUNDLE_PROCESSOR',
                'source_truth_state' => 'VERIFIED',
                'source_availability' => 'AVAILABLE',
                'data' => ['components' => $provenance],
                'diagnostics' => [],
                'source_references' => [],
                'provenance' => ['producer' => 'edis-evidence-exporter'],
            ],
            $context,
        );
        $files['provenance/provenance.json'] = CanonicalJson::encode($provenanceEnvelope);

        $sourceRoot = $this->sourceExportRoot($files);
        $bridgePath = $this->registry->definition('bridge_source_context')->artifactPath;
        if (isset($files[$bridgePath])) {
            $bridge = json_decode($files[$bridgePath], true, 512, JSON_THROW_ON_ERROR);
            if (is_array($bridge) && isset($bridge['data']['evidence']) && is_array($bridge['data']['evidence'])) {
                $bridge['data']['evidence']['source_export_root_sha256'] = $sourceRoot;
                CanonicalJson::applyHashes($bridge);
                $files[$bridgePath] = CanonicalJson::encode($bridge);
            }
        }

        foreach ($this->schemaFiles() as $path => $bytes) {
            $files[$path] = $bytes;
        }

        $validation = $this->validateFiles($files, $executionPlan, $context, $sourceRoot);
        $validation['package_integrity'] = 'NOT_RUN';
        $validation['contract_validation'] = $validation['state'];
        $validation['analysis_readiness'] = $this->analysisReadiness($files, $context);
        $validation['state'] = $validation['contract_validation'];
        if ($validation['contract_validation'] !== 'PASS') {
            throw new \RuntimeException(
                'Package contract validation failed: '
                . implode(', ', array_keys(array_filter((array) ($validation['checks'] ?? []), static fn (bool $passed): bool => !$passed)))
                . '; semantic_paths=' . implode('|', (array) ($validation['semantic_hash_failure_paths'] ?? []))
                . '; instance_paths=' . implode('|', (array) ($validation['instance_hash_failure_paths'] ?? []))
                . '; schema_failures=' . json_encode($validation['schema_failure_details'] ?? [], JSON_UNESCAPED_SLASHES)
                . '; producer_version=' . (defined('EDIS_EVIDENCE_EXPORTER_VERSION') ? (string) constant('EDIS_EVIDENCE_EXPORTER_VERSION') : '3.7.11')
                . '; bundle_schema_version=' . (defined('EDIS_EVIDENCE_BUNDLE_SCHEMA_VERSION') ? (string) constant('EDIS_EVIDENCE_BUNDLE_SCHEMA_VERSION') : '3.3.0')
            );
        }

        $validationEnvelope = $this->validationEnvelope($validation, $context);
        $files['validation/package-validation.json'] = CanonicalJson::encode($validationEnvelope);

        [$files, $manifest] = $this->buildManifestAndChecksums($files, $context, $sourceRoot);
        if (!$this->validateFinalPackage($files, $manifest)) {
            throw new \RuntimeException('Final package integrity validation failed.');
        }

        // Report the final gate, then rebuild manifest/checksums once and validate again.
        $validation['package_integrity'] = 'PASS';
        $validation['checks']['final_manifest_entries_match_files'] = true;
        $validation['checks']['final_checksums_cover_files'] = true;
        $validation['checks']['final_file_hashes_and_sizes_match'] = true;
        $validation['state'] = 'PASS';
        $files['validation/package-validation.json'] = CanonicalJson::encode($this->validationEnvelope($validation, $context));
        unset($files['package-manifest.json'], $files['checksums.sha256']);
        [$files, $manifest] = $this->buildManifestAndChecksums($files, $context, $sourceRoot);
        if (!$this->validateFinalPackage($files, $manifest)) {
            throw new \RuntimeException('Final package integrity validation failed after validation report finalization.');
        }

        $bundle = $fileStore->createBundle($jobId, $files, $expiresAt);
        return $bundle + ['validation_state' => 'PASS', 'source_export_root_sha256' => $sourceRoot];
    }


    /** @param array<string,mixed> $validation @return array<string,mixed> */
    private function validationEnvelope(array $validation, CollectionContext $context): array
    {
        return $this->envelope(
            'urn:edis:schema:wordpress:package-validation',
            '1.3.0',
            'package_validation',
            [
                'component_type' => 'BUNDLE_PROCESSOR',
                'source_truth_state' => 'VERIFIED',
                'source_availability' => ($validation['contract_validation'] ?? $validation['state'] ?? 'FAIL') === 'PASS' ? 'AVAILABLE' : 'ERROR',
                'data' => $validation,
                'diagnostics' => $validation['diagnostics'] ?? [],
                'source_references' => [],
                'provenance' => ['validator_version' => '1.3.0'],
            ],
            $context,
        );
    }

    /**
     * @param array<string,string> $files
     * @return array{0:array<string,string>,1:array<string,mixed>}
     */
    private function buildManifestAndChecksums(array $files, CollectionContext $context, string $sourceRoot): array
    {
        unset($files['package-manifest.json'], $files['checksums.sha256']);
        ksort($files, SORT_STRING);
        $manifestEntries = [];
        foreach ($files as $path => $bytes) {
            $manifestEntries[] = ['path' => $path, 'sha256' => 'sha256:' . hash('sha256', $bytes), 'size' => strlen($bytes)];
        }
        $manifest = [
            'schema_id' => 'urn:edis:schema:wordpress:package-manifest',
            'schema_version' => '2.1.0',
            'artifact_type' => 'wordpress_source_evidence_package_manifest',
            'producer' => ['product' => 'edis-evidence-exporter', 'version' => '3.7.11'],
            'captured_at' => $context->capturedAt,
            'canonicalization' => CanonicalJson::canonicalizationDescriptor(),
            'data' => [
                'analysis_set_id' => $context->analysisSetId,
                'wordpress_bundle_id' => $context->wordpressBundleId,
                'semantic_identity' => [
                    'source_export_root_sha256' => $sourceRoot,
                    'privacy_mode' => $context->privacyMode,
                    'plugin_version' => '3.7.11',
                    'bundle_schema_version' => '3.3.0',
                    'zip_profile' => 'EDIS-ZIP-1',
                    'compression_method' => 'STORE',
                    'canonicalization_profile' => CanonicalJson::PROFILE,
                ],
                'source_export_root_sha256' => $sourceRoot,
                'files' => $manifestEntries,
                'file_count' => count($manifestEntries),
                'privacy_mode' => $context->privacyMode,
                'plugin_version' => '3.7.11',
                'bundle_schema_version' => '3.3.0',
                'zip_profile' => 'EDIS-ZIP-1',
                'compression_method' => 'STORE',
                'source_export_root_scope' => [
                    'included_prefixes' => ['environment/', 'sources/', 'indexes/', 'provenance/'],
                    'excluded_paths' => ['package-manifest.json', 'checksums.sha256', 'validation/package-validation.json', 'bridge/source-context.json'],
                    'algorithm' => 'sha256',
                    'canonicalization' => 'EDIS-CJ-2',
                ],
                'hash_semantics' => [
                    'raw_storage_bytes_sha256' => 'exact_storage_bytes',
                    'canonical_saved_source_sha256' => 'edis_cj_2_lossless_source',
                    'semantic_payload_sha256' => 'semantic_projection_excluding_operational_identity',
                    'artifact_instance_sha256' => 'complete_envelope_except_self_hash',
                    'artifact_file_hash_location' => 'files[].sha256',
                ],
            ],
            'diagnostics' => [],
        ];
        CanonicalJson::applyHashes($manifest);
        $files['package-manifest.json'] = CanonicalJson::encode($manifest);

        ksort($files, SORT_STRING);
        $checksumLines = [];
        foreach ($files as $path => $bytes) {
            $checksumLines[] = hash('sha256', $bytes) . '  ' . $path;
        }
        $files['checksums.sha256'] = implode("\n", $checksumLines) . "\n";
        ksort($files, SORT_STRING);
        return [$files, $manifest];
    }

    /** @param array<string,string> $files */
    private function analysisReadiness(array $files, CollectionContext $context): string
    {
        if ($context->exportScope() === 'METADATA_ONLY') { return 'NOT_APPLICABLE'; }
        $conservationPath = $this->registry->definition('evidence_conservation')->artifactPath;
        $bridgePath = $this->registry->definition('bridge_source_context')->artifactPath;
        $conservation = isset($files[$conservationPath]) ? json_decode($files[$conservationPath], true) : null;
        $bridge = isset($files[$bridgePath]) ? json_decode($files[$bridgePath], true) : null;
        $conservationState = is_array($conservation) ? ($conservation['data']['evidence']['state'] ?? null) : null;
        $bridgeState = is_array($bridge) ? ($bridge['data']['evidence']['bridge_readiness'] ?? null) : null;
        if ($conservationState === 'FAIL') { return 'FAIL'; }
        if ($bridgeState !== 'READY') { return 'INSUFFICIENT'; }
        return $conservationState === 'PASS' ? 'PASS' : 'PARTIAL';
    }

    private function normalizeEvidenceShape(string $artifactType, mixed $value): mixed
    {
        $value = $this->normalizeDiagnosticContexts($value);
        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) { return $value; }
        $mapFields = match ($artifactType) {
            'selection_snapshot' => ['selected_source_hashes'],
            'evidence_conservation' => ['checks'],
            'source_coverage' => ['components', 'truth_summary', 'availability_summary'],
            'elementor_architecture_index' => ['totals', 'documents'],
            'elementor_dynamic_references' => ['kind_counts'],
            'elementor_kit_settings' => ['settings'],
            'elementor_site_settings_index' => ['groups'],
            'fixture_capture' => ['environment_notes'],
            default => [],
        };
        foreach ($mapFields as $field) {
            if (!array_key_exists($field, $value)) {
                continue;
            }
            $value[$field] = $this->normalizeDeclaredObject($value[$field]);
        }
        if ($artifactType === 'selection_snapshot' && is_array($value['semantic_identity'] ?? null) && is_array($value['semantic_identity']['selected_source_hashes'] ?? null)) {
            $value['semantic_identity']['selected_source_hashes'] = (object) $value['semantic_identity']['selected_source_hashes'];
        }
        if ($artifactType === 'elementor_document_source' && is_array($value['documents'] ?? null)) {
            foreach ($value['documents'] as &$document) {
                if (is_array($document) && is_array($document['page_settings'] ?? null)) {
                    $document['page_settings'] = (object) $document['page_settings'];
                }
            }
            unset($document);
        }
        if ($artifactType === 'export_comparison' && is_array($value['documents'] ?? null)) {
            foreach ($value['documents'] as &$document) {
                if (!is_array($document)) { continue; }
                foreach (['current_summary', 'previous_summary', 'deltas'] as $field) {
                    if (is_array($document[$field] ?? null)) { $document[$field] = (object) $document[$field]; }
                }
            }
            unset($document);
        }
        return $value;
    }

    private function normalizeDeclaredObject(mixed $value): mixed
    {
        if ($value === null || $value instanceof \stdClass) {
            return $value;
        }
        if (is_array($value)) {
            return (object) $value;
        }
        return $value;
    }

    private function normalizeDiagnosticContexts(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $properties = get_object_vars($value);
            foreach ($properties as $key => $child) {
                $properties[$key] = $key === 'context'
                    ? $this->contextObject($child)
                    : $this->normalizeDiagnosticContexts($child);
            }
            return (object) $properties;
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                $rows = [];
                foreach ($value as $child) { $rows[] = $this->normalizeDiagnosticContexts($child); }
                return $rows;
            }
            foreach ($value as $key => $child) {
                $value[$key] = $key === 'context'
                    ? $this->contextObject($child)
                    : $this->normalizeDiagnosticContexts($child);
            }
            return $value;
        }
        return $value;
    }

    private function contextObject(mixed $value): object
    {
        if ($value instanceof \stdClass) { return $value; }
        if (is_array($value) && !array_is_list($value)) { return (object) $value; }
        return (object) [];
    }

    private function allDiagnosticContextsAreObjects(mixed $value): bool
    {
        if (is_object($value)) {
            foreach (get_object_vars($value) as $key => $child) {
                if ($key === 'context' && !is_object($child)) { return false; }
                if (!$this->allDiagnosticContextsAreObjects($child)) { return false; }
            }
        } elseif (is_array($value)) {
            foreach ($value as $child) {
                if (!$this->allDiagnosticContextsAreObjects($child)) { return false; }
            }
        }
        return true;
    }


    /**
     * Validate the finalized in-memory package after manifest and checksum creation.
     *
     * @param array<string, string> $files
     * @param array<string, mixed> $manifest
     */
    private function validateFinalPackage(array $files, array $manifest): bool
    {
        if (!isset($files['package-manifest.json'], $files['checksums.sha256'])) {
            return false;
        }

        $manifestData = $manifest['data'] ?? null;
        $manifestEntries = is_array($manifestData) ? ($manifestData['files'] ?? null) : null;
        if (!is_array($manifestEntries)) {
            return false;
        }

        $listedPaths = [];
        foreach ($manifestEntries as $entry) {
            if (!is_array($entry)) {
                return false;
            }
            $path = $entry['path'] ?? null;
            $sha256 = $entry['sha256'] ?? null;
            $size = $entry['size'] ?? null;
            if (!is_string($path) || !$this->isSafeRelativePath($path) || isset($listedPaths[$path])) {
                return false;
            }
            if (!is_string($sha256) || !$this->isSha256Digest($sha256) || !is_int($size) || $size < 0) {
                return false;
            }
            if (!isset($files[$path])) {
                return false;
            }
            if ('sha256:' . hash('sha256', $files[$path]) !== $sha256 || strlen($files[$path]) !== $size) {
                return false;
            }
            $listedPaths[$path] = true;
        }

        $expectedManifestPaths = [];
        foreach ($files as $path => $_bytes) {
            if ($path === 'package-manifest.json' || $path === 'checksums.sha256') {
                continue;
            }
            $expectedManifestPaths[$path] = true;
        }
        ksort($listedPaths, SORT_STRING);
        ksort($expectedManifestPaths, SORT_STRING);
        if (array_keys($listedPaths) !== array_keys($expectedManifestPaths)) {
            return false;
        }
        if (($manifestData['file_count'] ?? null) !== count($manifestEntries)) {
            return false;
        }

        $checksumPaths = [];
        $checksumBytes = rtrim($files['checksums.sha256'], "\n");
        $lines = $checksumBytes === '' ? [] : explode("\n", $checksumBytes);
        foreach ($lines as $line) {
            if (strlen($line) < 67 || substr($line, 64, 2) !== '  ') {
                return false;
            }
            $digest = substr($line, 0, 64);
            $path = substr($line, 66);
            if (!$this->isSha256Digest('sha256:' . $digest) || !$this->isSafeRelativePath($path) || isset($checksumPaths[$path])) {
                return false;
            }
            if (!isset($files[$path]) || hash('sha256', $files[$path]) !== $digest) {
                return false;
            }
            $checksumPaths[$path] = true;
        }

        $expectedChecksumPaths = [];
        foreach ($files as $path => $_bytes) {
            if ($path === 'checksums.sha256') {
                continue;
            }
            $expectedChecksumPaths[$path] = true;
        }
        ksort($checksumPaths, SORT_STRING);
        ksort($expectedChecksumPaths, SORT_STRING);
        if (array_keys($checksumPaths) !== array_keys($expectedChecksumPaths)) { return false; }

        // Final pass: every JSON file must decode, and every finalized artifact envelope
        // must satisfy the shared envelope schema after all post-processing mutations.
        $validator = new JsonSchemaValidator($this->pluginRoot);
        foreach ($files as $path => $bytes) {
            if (!str_ends_with($path, '.json')) { continue; }
            try {
                $object = json_decode($bytes, false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return false;
            }
            if (!is_object($object)) { return false; }
            if (property_exists($object, 'artifact_type')) {
                try {
                    if ($validator->validate($object, 'schemas/shared-artifact-envelope.schema.json') !== []) { return false; }
                } catch (\Throwable) {
                    return false;
                }
                if (!$this->allDiagnosticContextsAreObjects($object)) { return false; }
                if (!property_exists($object, 'semantic_payload_sha256') || CanonicalJson::semanticHash($object) !== $object->semantic_payload_sha256) { return false; }
                if (!property_exists($object, 'artifact_instance_sha256') || CanonicalJson::instanceHash($object) !== $object->artifact_instance_sha256) { return false; }
            }
            try {
                if ($path === 'package-manifest.json' && $validator->validate($object, 'schemas/package-manifest.schema.json') !== []) { return false; }
                if ($path === 'validation/package-validation.json') {
                    $payload = $object->data->evidence ?? null;
                    if (!is_object($payload) || $validator->validate($payload, 'schemas/package-validation.schema.json') !== []) { return false; }
                }
            } catch (\Throwable) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string, mixed> $artifact @return array<string, mixed> */
    private function envelope(string $schemaId, string $schemaVersion, string $artifactType, array $artifact, CollectionContext $context): array
    {
        $diagnostics = [];
        foreach (($artifact['diagnostics'] ?? []) as $diagnostic) {
            if ($diagnostic instanceof \JsonSerializable) {
                $diagnostic = $diagnostic->jsonSerialize();
            }
            if (is_array($diagnostic)) {
                $diagnostic['context'] = $this->contextObject($diagnostic['context'] ?? null);
                $diagnostics[] = $diagnostic;
            }
        }
        $envelope = [
            'schema_id' => $schemaId,
            'schema_version' => $schemaVersion,
            'artifact_type' => $artifactType,
            'producer' => ['product' => 'edis-evidence-exporter', 'version' => '3.7.11'],
            'captured_at' => $context->capturedAt,
            'canonicalization' => CanonicalJson::canonicalizationDescriptor(),
            'data' => [
                'component_id' => $artifact['component_id'] ?? $artifactType,
                'component_type' => $artifact['component_type'] ?? null,
                'source_truth_state' => $artifact['source_truth_state'] ?? 'UNKNOWN',
                'source_availability' => $artifact['source_availability'] ?? 'ERROR',
                'evidence' => $this->normalizeEvidenceShape($artifactType, $artifact['data'] ?? null),
                'source_references' => $artifact['source_references'] ?? [],
                'provenance' => (($artifact['provenance'] ?? []) === [] ? (object) [] : ($artifact['provenance'] ?? (object) [])),
                'evidence_scope' => $this->evidenceScope($artifactType, $context),
            ],
            'diagnostics' => $diagnostics,
        ];
        CanonicalJson::applyHashes($envelope);
        return $envelope;
    }

    /** @param array<string, string> $files */
    private function sourceExportRoot(array $files): string
    {
        $records = [];
        foreach ($files as $path => $bytes) {
            $included = false;
            foreach (['environment/', 'sources/', 'indexes/', 'provenance/'] as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    $included = true;
                    break;
                }
            }
            if (!$included) {
                continue;
            }
            $records[] = ['path' => $path, 'sha256' => 'sha256:' . hash('sha256', $bytes), 'size' => strlen($bytes)];
        }
        usort($records, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));
        return 'sha256:' . hash('sha256', CanonicalJson::encode($records));
    }

    /** @return array<string, string> */
    private function schemaFiles(): array
    {
        $files = [];
        foreach (glob($this->pluginRoot . 'schemas/*.json') ?: [] as $path) {
            $real = realpath($path);
            $root = realpath($this->pluginRoot . 'schemas');
            if ($real === false || $root === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                continue;
            }
            try {
                $bytes = $this->filesystem->read($real);
            } catch (\Throwable) {
                continue;
            }
            $files['schemas/' . basename($real)] = rtrim(str_replace("\r\n", "\n", $bytes), "\n") . "\n";
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    /**
     * @param array<string, string> $files
     * @param list<string> $executionPlan
     * @return array{state:string,checks:array<string,bool>,diagnostics:list<array<string,mixed>>,source_export_root_sha256:string}
     */
    private function validateFiles(array $files, array $executionPlan, CollectionContext $context, string $sourceRoot): array
    {
        $semanticFailurePaths = [];
        $instanceFailurePaths = [];
        $schemaFailureDetails = [];
        $checks = [
            'all_paths_safe' => true,
            'all_json_decodes' => true,
            'required_artifacts_present' => true,
            'source_root_format_valid' => $this->isSha256Digest($sourceRoot),
            'analysis_set_id_present' => $context->analysisSetId !== '',
            'wordpress_bundle_id_present' => $context->wordpressBundleId !== '',
            'schema_index_present' => isset($files['schemas/schema-index.json']),
            'semantic_hashes_valid' => true,
            'artifact_instance_hashes_valid' => true,
            'schema_routes_resolve' => true,
            'json_schema_validation' => true,
            'diagnostic_context_objects' => true,
            'bridge_context_consistent' => true,
            'bundle_diagnostics_consistent' => true,
            'source_coverage_consistent' => true,
            'evidence_conservation_passed' => true,
            'selection_snapshot_consistent' => true,
            'strict_single_document_isolation' => true,
            'document_hash_semantics_consistent' => true,
            'source_element_keys_reproducible' => true,
        ];
        foreach ($files as $path => $bytes) {
            if (!$this->isSafeRelativePath($path)) {
                $checks['all_paths_safe'] = false;
            }
            if (str_ends_with($path, '.json')) {
                try {
                    $decoded = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($decoded)) {
                        $checks['all_json_decodes'] = false;
                    }
                    $decodedObject=json_decode($bytes,false,512,JSON_THROW_ON_ERROR);
                    if (is_array($decoded) && isset($decoded['schema_id'], $decoded['schema_version'], $decoded['semantic_payload_sha256'], $decoded['artifact_instance_sha256']) && str_starts_with($path, 'schemas/') === false) {
                        if (is_object($decodedObject) && CanonicalJson::semanticHash($decodedObject) !== ($decodedObject->semantic_payload_sha256 ?? null)) {
                            $checks['semantic_hashes_valid'] = false;
                            $semanticFailurePaths[] = $path;
                        }
                        if (is_object($decodedObject) && CanonicalJson::instanceHash($decodedObject) !== ($decodedObject->artifact_instance_sha256 ?? null)) {
                            $checks['artifact_instance_hashes_valid'] = false;
                            $instanceFailurePaths[] = $path;
                        }
                        if (!$this->allDiagnosticContextsAreObjects($decodedObject)) { $checks['diagnostic_context_objects'] = false; }
                    }
                } catch (\JsonException) {
                    $checks['all_json_decodes'] = false;
                }
            }
        }
        $schemaIndex = isset($files['schemas/schema-index.json']) ? json_decode($files['schemas/schema-index.json'], true) : null;
        $routes = is_array($schemaIndex) && is_array($schemaIndex['entries'] ?? null) ? $schemaIndex['entries'] : [];
        foreach ($executionPlan as $id) {
            $definition = $this->registry->definition($id); $routeKey = $definition->schemaId . '@' . $definition->schemaVersion;
            if (!isset($routes[$routeKey])) { $checks['schema_routes_resolve'] = false; }
        }
        if ($checks['schema_routes_resolve']) {
            $validator=new JsonSchemaValidator($this->pluginRoot);
            foreach($executionPlan as $id){$definition=$this->registry->definition($id);$route=$routes[$definition->schemaId.'@'.$definition->schemaVersion]??null;$bytes=$files[$definition->artifactPath]??null;if(!is_array($route)||!is_string($bytes)){$checks['json_schema_validation']=false;continue;}try{$object=json_decode($bytes,false,512,JSON_THROW_ON_ERROR);$errors=$validator->validate($object,(string)$route['envelope_schema']);if(is_object($object)&&property_exists($object,'data')){$errors=array_merge($errors,$validator->validate($object->data,(string)$route['payload_schema']));if(isset($route['evidence_schema'])&&is_string($route['evidence_schema'])&&is_object($object->data)&&property_exists($object->data,'evidence')){$errors=array_merge($errors,$validator->validate($object->data->evidence,$route['evidence_schema']));}}if($errors!==[]){$checks['json_schema_validation']=false;$schemaFailureDetails[$definition->artifactPath]=$errors;}}catch(\Throwable $exception){$checks['json_schema_validation']=false;$schemaFailureDetails[$definition->artifactPath]=[['path'=>'$','keyword'=>'validator','message'=>get_class($exception).': '.$exception->getMessage()]];}}
        }
        $bridgePath = $this->registry->definition('bridge_source_context')->artifactPath;
        if (isset($files[$bridgePath])) { $bridge = json_decode($files[$bridgePath], true); $evidence = $bridge['data']['evidence'] ?? null; if (!is_array($evidence) || ($evidence['wordpress_bundle_id'] ?? null) !== $context->wordpressBundleId || ($evidence['analysis_set_id'] ?? null) !== $context->analysisSetId) { $checks['bridge_context_consistent'] = false; } }
        $diagnosticCount = 0; foreach ($files as $path => $bytes) { if (!str_ends_with($path,'.json') || str_starts_with($path,'schemas/')) { continue; } $decoded=json_decode($bytes,true); foreach ((array)($decoded['diagnostics']??[]) as $_d) { $diagnosticCount++; } }
        $bundlePath = $this->registry->definition('bundle_diagnostics')->artifactPath; if (isset($files[$bundlePath])) { $bundle=json_decode($files[$bundlePath],true); $reported=(int)($bundle['data']['evidence']['count']??-1); $own=count((array)($bundle['diagnostics']??[])); if ($reported < max(0,$diagnosticCount-$own)) { $checks['bundle_diagnostics_consistent']=false; } }
        $coveragePath = $this->registry->definition('source_coverage')->artifactPath; if (isset($files[$coveragePath])) { $coverage=json_decode($files[$coveragePath],true); $count=(int)($coverage['data']['evidence']['source_component_count']??-1); if ($count !== count($executionPlan)) { $checks['source_coverage_consistent']=false; } }

        $conservationPath = $this->registry->definition('evidence_conservation')->artifactPath;
        if (isset($files[$conservationPath])) {
            $conservation = json_decode($files[$conservationPath], true);
            $state = $conservation['data']['evidence']['state'] ?? null;
            if (!in_array($state, ['PASS', 'NOT_APPLICABLE'], true)) { $checks['evidence_conservation_passed'] = false; }
        }

        $selectionPath = $this->registry->definition('selection_snapshot')->artifactPath;
        if (isset($files[$selectionPath])) {
            $selection = json_decode($files[$selectionPath], true);
            $evidence = $selection['data']['evidence'] ?? null;
            $expectedSelected = array_values(array_unique(array_map('strval', $context->selectedDocumentIds)));
            sort($expectedSelected, SORT_STRING);
            $actualSelected = is_array($evidence['selected_document_ids'] ?? null) ? array_values($evidence['selected_document_ids']) : [];
            sort($actualSelected, SORT_STRING);
            if ($actualSelected !== $expectedSelected || ($evidence['export_scope'] ?? null) !== $context->exportScope() || ($evidence['dependency_scope'] ?? null) !== $context->dependencyScope()) {
                $checks['selection_snapshot_consistent'] = false;
            }
        }

        if (in_array($context->exportScope(), ['SINGLE_DOCUMENT', 'MULTIPLE_DOCUMENTS'], true) && $context->dependencyScope() !== 'FULL_SITE_CONTEXT') {
            $documentIndexPath = $this->registry->definition('elementor_document_index')->artifactPath;
            $bridgePathForScope = $this->registry->definition('bridge_source_context')->artifactPath;
            foreach ([$documentIndexPath, $bridgePathForScope] as $scopePath) {
                if (!isset($files[$scopePath])) { continue; }
                $scopeArtifact = json_decode($files[$scopePath], true);
                $records = $scopeArtifact['data']['evidence']['documents'] ?? [];
                foreach (is_array($records) ? $records : [] as $record) {
                    $id = is_array($record) ? (string) ($record['document_id'] ?? '') : '';
                    if ($id === '' || !in_array((int) $id, $context->selectedDocumentIds, true)) {
                        $checks['strict_single_document_isolation'] = false;
                    }
                }
            }
        }

        $inventoryPath = $this->registry->definition('elementor_document_inventory')->artifactPath;
        $sourcePath = $this->registry->definition('elementor_document_source')->artifactPath;
        if (isset($files[$inventoryPath], $files[$sourcePath])) {
            $inventoryArtifact = json_decode($files[$inventoryPath], true);
            $sourceArtifact = json_decode($files[$sourcePath], true);
            $inventoryMap = [];
            foreach ((array) ($inventoryArtifact['data']['evidence']['documents'] ?? []) as $record) {
                if (is_array($record)) { $inventoryMap[(string) ($record['document_id'] ?? '')] = $record; }
            }
            foreach ((array) ($sourceArtifact['data']['evidence']['documents'] ?? []) as $record) {
                if (!is_array($record)) { continue; }
                $id = (string) ($record['document_id'] ?? '');
                $expected = $inventoryMap[$id]['canonical_saved_source_sha256'] ?? null;
                if ($expected !== ($record['canonical_saved_source_sha256'] ?? null) || ($record['saved_source_sha256'] ?? null) !== ($record['canonical_saved_source_sha256'] ?? null)) {
                    $checks['document_hash_semantics_consistent'] = false;
                }
            }
        }

        $structurePath = $this->registry->definition('elementor_element_structure_index')->artifactPath;
        if (isset($files[$structurePath])) {
            $structureArtifact = json_decode($files[$structurePath], true);
            foreach ((array) ($structureArtifact['data']['evidence']['elements'] ?? []) as $record) {
                if (!is_array($record)) { continue; }
                $keyInput = [
                    'architecture_kind' => $record['architecture_kind'] ?? null,
                    'document_fingerprint' => $record['document_fingerprint'] ?? null,
                    'document_order' => $record['document_order'] ?? null,
                    'elementor_element_id' => $record['elementor_element_id'] ?? null,
                    'source_path' => $record['source_path'] ?? null,
                ];
                $expectedKey = 'sha256:' . hash('sha256', CanonicalJson::encode($keyInput));
                if (($record['source_element_key'] ?? null) !== $expectedKey) { $checks['source_element_keys_reproducible'] = false; }
                $hashInput = $record; unset($hashInput['source_record_sha256']);
                $expectedRecordHash = 'sha256:' . hash('sha256', CanonicalJson::encode($hashInput));
                if (($record['source_record_sha256'] ?? null) !== $expectedRecordHash) { $checks['source_element_keys_reproducible'] = false; }
            }
        }

        foreach ($executionPlan as $id) {
            $path = $this->registry->definition($id)->artifactPath;
            if (!isset($files[$path])) {
                $checks['required_artifacts_present'] = false;
            }
        }
        $diagnostics = [];
        foreach ($checks as $id => $passed) {
            if (!$passed) {
                $diagnostics[] = ['code' => 'EDIS_PACKAGE_' . strtoupper($id), 'severity' => 'ERROR', 'scope' => 'SEMANTIC', 'message_key' => 'diagnostic.package.' . $id, 'context' => (object) []];
            }
        }
        return ['state' => in_array(false, $checks, true) ? 'FAIL' : 'PASS', 'checks' => $checks, 'diagnostics' => $diagnostics, 'source_export_root_sha256' => $sourceRoot, 'semantic_hash_failure_paths'=>$semanticFailurePaths, 'instance_hash_failure_paths'=>$instanceFailurePaths, 'schema_failure_details'=>$schemaFailureDetails];
    }

    /** @return array<string,mixed> */
    private function evidenceScope(string $artifactType, CollectionContext $context): array
    {
        $site = ['environment','plugin','theme','elementor_installation','elementor_feature_flags','elementor_breakpoints','elementor_registered_widgets','elementor_registered_document_types','elementor_document_inventory','elementor_performance_configuration','elementor_capability_evidence'];
        $kit = ['elementor_kit_metadata','elementor_kit_settings','elementor_variables_registry','elementor_global_classes_registry','elementor_global_classes_order','elementor_legacy_global_styles','elementor_site_settings_index'];
        $scope = in_array($artifactType,$site,true) ? 'SITE' : (in_array($artifactType,$kit,true) ? 'KIT' : (in_array($artifactType,['source_coverage','bridge_source_context','bundle_diagnostics','estimated_export_size','selection_snapshot','evidence_conservation','export_comparison','fixture_capture'],true) ? 'BUNDLE' : (str_contains($artifactType,'element_structure')||str_contains($artifactType,'responsive')||str_contains($artifactType,'reference')||str_contains($artifactType,'architecture')||str_contains($artifactType,'usage') ? 'ELEMENT' : 'DOCUMENT')));
        $selected=array_map('strval',$context->selectedDocumentIds);
        return ['scope_kind'=>$scope,'selected_document_ids'=>$selected,'applies_to_selected_documents'=>$selected,'required_by'=>array_map(static fn(string $id):array=>['scope_kind'=>'DOCUMENT','document_id'=>$id],$selected),'inclusion_reason'=>$scope==='SITE'?'SHARED_SITE_CONTEXT':($scope==='KIT'?'REQUIRED_OR_SELECTED_DESIGN_SYSTEM_CONTEXT':($scope==='BUNDLE'?'BUNDLE_INTEGRITY_AND_COORDINATION':'SELECTED_DOCUMENT_EVIDENCE')),'export_scope'=>$context->exportScope(),'dependency_scope'=>$context->dependencyScope()];
    }

    private function isSha256Digest(string $value): bool
    {
        if (!str_starts_with($value, 'sha256:') || strlen($value) !== 71) {
            return false;
        }
        $digest = substr($value, 7);
        for ($index = 0; $index < 64; $index++) {
            $code = ord($digest[$index]);
            $isDigit = $code >= 48 && $code <= 57;
            $isLowerHex = $code >= 97 && $code <= 102;
            if (!$isDigit && !$isLowerHex) {
                return false;
            }
        }
        return true;
    }

    private function isSafeRelativePath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);
        if ($normalized === '' || str_starts_with($normalized, '/')) {
            return false;
        }
        if (strlen($normalized) >= 2) {
            $first = ord($normalized[0]);
            $isAsciiLetter = ($first >= 65 && $first <= 90) || ($first >= 97 && $first <= 122);
            if ($isAsciiLetter && $normalized[1] === ':') {
                return false;
            }
        }
        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }
        return true;
    }
}
