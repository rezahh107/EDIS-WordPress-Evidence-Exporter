<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Application;

use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\DiagnosticCapacityReachedException;
use EDIS\EvidenceExporter\Infrastructure\Support\DiagnosticRecordStore;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportIntegrityException;
use EDIS\EvidenceExporter\Infrastructure\Support\JsonSchemaValidator;
use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;

final class DiagnosticRecordService
{
    public const FORMAT = 'EDIS-DIAGNOSTIC-1';
    public const SCHEMA_ID = 'urn:edis:schema:diagnostic-record:1.0.0';
    public const SCHEMA_VERSION = '1.0.0';
    public const MEDIA_TYPE = 'application/vnd.edis.diagnostic+json';

    private JsonSchemaValidator $validator;
    private DeterministicFilesystem $filesystem;

    public function __construct(
        private readonly DiagnosticRecordStore $store,
        private readonly JobStore $jobs,
        private readonly string $pluginRoot,
        ?DeterministicFilesystem $filesystem = null,
    ) {
        $this->filesystem = $filesystem ?? new DeterministicFilesystem();
        $this->validator = new JsonSchemaValidator($this->pluginRoot, $this->filesystem);
    }

    /**
     * @param array<string,mixed> $request
     * @param array<string,mixed> $boundary
     * @return array{diagnostic_available:bool,diagnostic_id:?string,diagnostic_persistence_code:?string}
     */
    public function capturePreJobFailure(
        int $ownerId,
        string $operation,
        ?string $route,
        array $request,
        \Throwable $exception,
        array $boundary = [],
        ?string $publicCode = null,
    ): array {
        try {
            $record = $this->buildPreJobRecord($operation, $route, $request, $exception, $boundary, $publicCode);
            return $this->persist($ownerId, $record);
        } catch (DiagnosticCapacityReachedException) {
            return $this->unavailable(DiagnosticCapacityReachedException::CODE);
        } catch (\Throwable) {
            return $this->unavailable();
        }
    }

    /**
     * @return array{diagnostic_available:bool,diagnostic_id:?string,diagnostic_persistence_code:?string}
     */
    public function captureJobFailure(
        int $ownerId,
        string $jobId,
        string $operation,
        ?string $route,
        \Throwable $exception,
        ?string $publicCode = null,
        string $recordType = 'JOB_FAILURE',
    ): array {
        try {
            $job = $this->jobs->get($jobId);
            if (!is_array($job) || (int) ($job['owner_id'] ?? 0) !== $ownerId) {
                return $this->capturePreJobFailure(
                    $ownerId,
                    $operation,
                    $route,
                    [],
                    $exception,
                    [
                        'lifecycle_stage' => 'job_resolution',
                        'subsystem' => 'JobStore',
                        'operation_immediately_attempted' => 'resolve the persisted export Job after failure',
                        'last_successful_state' => null,
                    ],
                    $publicCode,
                );
            }
            $record = $this->buildJobRecord($job, $operation, $route, $exception, $publicCode, $recordType);
            return $this->persist($ownerId, $record);
        } catch (DiagnosticCapacityReachedException) {
            return $this->unavailable(DiagnosticCapacityReachedException::CODE);
        } catch (\Throwable) {
            return $this->unavailable();
        }
    }

    /** @return array{record:array<string,mixed>,bytes:string}|null */
    public function resolveForOwner(int $ownerId, string $diagnosticId, ?callable $documentAuthorizer = null): ?array
    {
        $resolved = $this->store->resolve($ownerId, $diagnosticId);
        if (!is_array($resolved)) {
            return null;
        }
        $jobId = $resolved['record']['operation_identity']['job_id'] ?? null;
        if (is_string($jobId) && $jobId !== '') {
            $job = $this->jobs->get($jobId);
            if (!is_array($job) || (int) ($job['owner_id'] ?? 0) !== $ownerId) {
                return null;
            }
            $documentIds = (array) ($job['document_ids'] ?? $job['config']['document_ids'] ?? []);
            if ($documentIds !== [] && $documentAuthorizer === null) {
                return null;
            }
            foreach ($documentIds as $value) {
                $documentId = (int) $value;
                if ($documentId <= 0 || $documentAuthorizer === null || !$documentAuthorizer($documentId)) {
                    return null;
                }
            }
        }
        return $resolved;
    }

    /** @return list<array<string,mixed>> */
    public function recentForOwner(int $ownerId, ?callable $documentAuthorizer = null, int $limit = 10): array
    {
        $rows = [];
        foreach ($this->store->recent($ownerId, min(25, max(1, $limit * 2))) as $row) {
            $diagnosticId = (string) ($row['diagnostic_id'] ?? '');
            if ($this->resolveForOwner($ownerId, $diagnosticId, $documentAuthorizer) !== null) {
                $rows[] = $row;
            }
            if (count($rows) >= $limit) {
                break;
            }
        }
        return $rows;
    }

    public function cleanupExpired(): void
    {
        $this->store->cleanupExpired();
    }

    /** @param array<string,mixed> $request @param array<string,mixed> $boundary @return array<string,mixed> */
    private function buildPreJobRecord(
        string $operation,
        ?string $route,
        array $request,
        \Throwable $exception,
        array $boundary,
        ?string $publicCode,
    ): array {
        $diagnosticId = $this->newId();
        $createdAt = time();
        $internalCode = $this->internalCode($exception, 'EDIS_PRE_JOB_FAILURE');
        $scope = $exception instanceof ExportIntegrityException ? 'SEMANTIC' : ($exception instanceof \InvalidArgumentException ? 'SEMANTIC' : 'OPERATIONAL');
        $retryability = $exception instanceof ExportIntegrityException
            ? 'NOT_RETRYABLE'
            : ($exception instanceof \InvalidArgumentException ? 'NEW_JOB_REQUIRED' : 'NOT_PROVEN');
        [$selectedComponents, $truncation] = $this->boundedStrings((array) ($request['collectors'] ?? []), 64, 'operation_identity.selected_components');
        $documentIds = array_values(array_filter((array) ($request['document_ids'] ?? []), static fn (mixed $id): bool => is_numeric($id) && (int) $id > 0));
        $options = is_array($request['options'] ?? null) ? $request['options'] : [];
        $stage = $this->safeIdentifier($boundary['lifecycle_stage'] ?? $this->exceptionContext($exception)['failure_phase'] ?? null);
        $subsystem = $this->safeIdentifier($boundary['subsystem'] ?? null);
        $attempted = $this->safeSentence($boundary['operation_immediately_attempted'] ?? 'complete the export request before durable Job creation', 256);
        $lastSuccessful = $this->safeIdentifier($boundary['last_successful_state'] ?? null);

        $evidence = [
            ['evidence_id' => 'ev-001', 'source_type' => 'RUNTIME_EXCEPTION_CLASS', 'source_locator' => 'caught_throwable_class', 'status' => 'CONFIRMED'],
            ['evidence_id' => 'ev-002', 'source_type' => 'SANITIZED_REQUEST_SUMMARY', 'source_locator' => 'pre_job_request_boundary', 'status' => 'CONFIRMED'],
        ];
        $facts = [
            $this->fact(1, 'EDIS observed a material failure before a durable export Job was confirmed.', ['ev-001']),
            $this->fact(2, 'The attempted operation was ' . $this->safeIdentifier($operation, 'UNKNOWN_OPERATION') . '.', ['ev-002']),
            $this->fact(3, 'The caught exception class was ' . $this->safeExceptionClass($exception::class) . '.', ['ev-001']),
            $this->fact(4, 'The request selected ' . count($selectedComponents) . ' bounded component identifiers and ' . count($documentIds) . ' document records.', ['ev-002']),
        ];
        $classifications = [
            $this->classification(1, 'EDIS_RULE_FAILURE_SCOPE_FROM_EXCEPTION_CONTRACT', 'The failure is classified as ' . $scope . ' from the implemented exception contract.', ['fact-001', 'fact-003']),
            $this->classification(2, 'EDIS_RULE_PRE_JOB_RETRYABILITY', 'The deterministic retryability result is ' . $retryability . '.', ['fact-001', 'fact-003']),
        ];
        $questions = [
            $this->question(1, 'What underlying condition produced the recorded failure boundary?', 'NOT_PROVEN', 'The plugin recorded the boundary and stable code but did not execute an AI root-cause analysis.', 'Inspect only the subsystem named in failure_location and collect the smallest bounded observation required by the stable internal code.'),
        ];
        if ($stage === null) {
            $questions[] = $this->question(2, 'Which exact lifecycle stage failed?', 'UNAVAILABLE', 'Retry and repair choices depend on the point before durable Job creation.', 'Repeat once with this canonical recorder active and preserve the returned diagnostic_id.');
        }
        if ($retryability === 'NOT_PROVEN') {
            $questions[] = $this->question(3, 'Is retry safe?', 'NOT_PROVEN', 'A blind retry could repeat an integrity or storage failure.', 'Resolve the named subsystem state before retrying or creating another Job.');
        }

        return $this->baseRecord(
            $diagnosticId,
            'PRE_JOB_FAILURE',
            $createdAt,
            $this->store->expiration(),
            $operation,
            $route,
            null,
            is_string($options['export_scope'] ?? null) ? $options['export_scope'] : null,
            is_string($request['privacy_mode'] ?? null) ? $request['privacy_mode'] : null,
            $selectedComponents,
            count($documentIds),
            $stage,
            $subsystem,
            $attempted,
            $lastSuccessful,
            'The operation failed before a durable export Job could be used as incident authority.',
            $publicCode,
            $internalCode,
            $exception,
            $scope,
            $retryability,
            'FAILED',
            $facts,
            $classifications,
            [[
                'sequence' => 1,
                'observed_at' => gmdate('Y-m-d\TH:i:s\Z', $createdAt),
                'event_type' => 'PRE_JOB_FAILURE_OBSERVED',
                'before_state' => $lastSuccessful,
                'after_state' => 'FAILED_BEFORE_JOB_CONFIRMATION',
                'operation' => $this->safeIdentifier($operation, 'UNKNOWN_OPERATION'),
                'status' => 'CONFIRMED',
                'evidence_ids' => ['ev-001', 'ev-002'],
            ]],
            [[
                'invariant_id' => 'EDIS_OPERATION_REACHES_DURABLE_JOB_OR_RETURNS_STRUCTURED_BLOCKER',
                'expected' => 'The request either returns a complete structured blocker or reaches durable Job creation.',
                'observed' => 'A material exception escaped the normal structured path with stable code ' . $internalCode . '.',
                'mismatch' => true,
                'status' => 'CONFIRMED',
                'evidence_ids' => ['ev-001', 'ev-002'],
            ]],
            $evidence,
            $this->safeContext($exception, $boundary),
            $questions,
            $this->recoveryGuidance($retryability),
            $truncation,
        );
    }

    /** @param array<string,mixed> $job @return array<string,mixed> */
    private function buildJobRecord(
        array $job,
        string $operation,
        ?string $route,
        \Throwable $exception,
        ?string $publicCode,
        string $recordType,
    ): array {
        $diagnosticId = $this->newId();
        $createdAt = time();
        $jobId = (string) ($job['job_id'] ?? '');
        $latestDiagnostic = $this->latestDiagnostic($job);
        $diagnosticContext = is_array($latestDiagnostic['context'] ?? null) ? $latestDiagnostic['context'] : [];
        $internalCode = $this->safeCode($job['last_error_code'] ?? $latestDiagnostic['code'] ?? $this->internalCode($exception, 'EDIS_JOB_FAILURE'));
        $scope = in_array(($latestDiagnostic['scope'] ?? null), ['SEMANTIC', 'OPERATIONAL'], true)
            ? (string) $latestDiagnostic['scope']
            : ($exception instanceof ExportIntegrityException ? 'SEMANTIC' : 'OPERATIONAL');
        $status = (string) ($job['status'] ?? 'unknown');
        $terminal = $this->terminalState($status);
        $retryability = $this->jobRetryability($job, $scope, $terminal);
        [$selectedComponents, $truncation] = $this->boundedStrings((array) ($job['selected_components'] ?? []), 64, 'operation_identity.selected_components');
        $config = is_array($job['config'] ?? null) ? $job['config'] : [];
        $stage = $this->safeIdentifier($diagnosticContext['failure_phase'] ?? $job['phase'] ?? null);
        $subsystem = $this->safeIdentifier($job['current_component'] ?? ($stage === 'packaging' ? 'ExportService' : 'ExportJobService'));
        $lastSuccessful = $this->jobLastSuccessfulState($job);
        $jobObservedAt = isset($job['last_error_at']) && is_numeric($job['last_error_at']) ? (int) $job['last_error_at'] : $createdAt;

        $evidence = [
            ['evidence_id' => 'ev-001', 'source_type' => 'PERSISTED_JOB_RECORD', 'source_locator' => 'JobStore:' . $jobId, 'status' => 'CONFIRMED'],
            ['evidence_id' => 'ev-002', 'source_type' => 'RUNTIME_EXCEPTION_CLASS', 'source_locator' => 'caught_throwable_class', 'status' => 'CONFIRMED'],
        ];
        $facts = [
            $this->fact(1, 'EDIS resolved the exact persisted Job ' . $jobId . ' for this incident.', ['ev-001']),
            $this->fact(2, 'The persisted Job status was ' . $this->safeIdentifier($status, 'UNKNOWN') . ' with phase ' . $this->safeIdentifier($job['phase'] ?? null, 'UNKNOWN') . '.', ['ev-001']),
            $this->fact(3, 'The persisted stable failure code was ' . $internalCode . '.', ['ev-001']),
            $this->fact(4, 'The caught exception class was ' . $this->safeExceptionClass($exception::class) . '.', ['ev-002']),
        ];
        $classifications = [
            $this->classification(1, 'EDIS_RULE_JOB_SCOPE_FROM_PERSISTED_DIAGNOSTIC', 'The Job failure scope is ' . $scope . '.', ['fact-002', 'fact-003']),
            $this->classification(2, 'EDIS_RULE_JOB_RETRYABILITY_FROM_PERSISTED_STATE', 'The deterministic retryability result is ' . $retryability . '.', ['fact-002', 'fact-003']),
            $this->classification(3, 'EDIS_RULE_TERMINAL_STATE_FROM_JOB_STATUS', 'The terminal-state result is ' . $terminal . '.', ['fact-002']),
        ];
        $questions = [
            $this->question(1, 'What lower-level condition produced the persisted failure code?', 'NOT_PROVEN', 'The Job record proves the boundary and state but not every external or filesystem cause beneath it.', 'Inspect the subsystem and expected-versus-observed entry named in this artifact before changing unrelated configuration.'),
        ];
        if ($retryability === 'NOT_PROVEN') {
            $questions[] = $this->question(2, 'Is another attempt safe?', 'NOT_PROVEN', 'The persisted state does not prove a safe automatic recovery path.', 'Collect the smallest subsystem check named by the stable code before retrying.');
        }

        $timeline = [];
        $sequence = 1;
        if (isset($job['created_at']) && is_numeric($job['created_at'])) {
            $timeline[] = [
                'sequence' => $sequence++,
                'observed_at' => gmdate('Y-m-d\TH:i:s\Z', (int) $job['created_at']),
                'event_type' => 'JOB_CREATED',
                'before_state' => null,
                'after_state' => 'queued',
                'operation' => 'EXPORT_CREATE',
                'status' => 'CONFIRMED',
                'evidence_ids' => ['ev-001'],
            ];
        }
        if (isset($job['last_successful_step_at']) && is_numeric($job['last_successful_step_at'])) {
            $timeline[] = [
                'sequence' => $sequence++,
                'observed_at' => gmdate('Y-m-d\TH:i:s\Z', (int) $job['last_successful_step_at']),
                'event_type' => 'LAST_SUCCESSFUL_JOB_STEP',
                'before_state' => null,
                'after_state' => $lastSuccessful,
                'operation' => $this->safeIdentifier($operation, 'UNKNOWN_OPERATION'),
                'status' => 'CONFIRMED',
                'evidence_ids' => ['ev-001'],
            ];
        }
        $timeline[] = [
            'sequence' => $sequence,
            'observed_at' => gmdate('Y-m-d\TH:i:s\Z', $jobObservedAt),
            'event_type' => 'JOB_FAILURE_RECORDED',
            'before_state' => $lastSuccessful,
            'after_state' => $status,
            'operation' => $this->safeIdentifier($operation, 'UNKNOWN_OPERATION'),
            'status' => 'CONFIRMED',
            'evidence_ids' => ['ev-001', 'ev-002'],
        ];

        return $this->baseRecord(
            $diagnosticId,
            in_array($recordType, ['JOB_FAILURE', 'WORKER_FAILURE'], true) ? $recordType : 'JOB_FAILURE',
            $createdAt,
            $this->store->expiration(isset($job['expires_at']) && is_numeric($job['expires_at']) ? (int) $job['expires_at'] : null),
            $operation,
            $route,
            $jobId,
            is_string($job['export_scope'] ?? null) ? $job['export_scope'] : (is_string($config['options']['export_scope'] ?? null) ? $config['options']['export_scope'] : null),
            is_string($config['privacy_mode'] ?? null) ? $config['privacy_mode'] : null,
            $selectedComponents,
            isset($job['selected_document_count']) ? (int) $job['selected_document_count'] : count((array) ($config['document_ids'] ?? [])),
            $stage,
            $subsystem,
            'advance or recover the exact persisted export Job',
            $lastSuccessful,
            'The failure boundary is the persisted Job transition identified by lifecycle stage and stable code.',
            $publicCode,
            $internalCode,
            $exception,
            $scope,
            $retryability,
            $terminal,
            $facts,
            $classifications,
            $timeline,
            [[
                'invariant_id' => 'EDIS_JOB_OPERATION_COMPLETES_OR_PERSISTS_TRUTHFUL_FAILURE_STATE',
                'expected' => 'The Job operation completes or persists a bounded truthful failure state.',
                'observed' => 'JobStore persisted status ' . $status . ' and stable code ' . $internalCode . '.',
                'mismatch' => $terminal === 'FAILED',
                'status' => 'CONFIRMED',
                'evidence_ids' => ['ev-001'],
            ]],
            $evidence,
            $this->safeContext($exception, $diagnosticContext + [
                'schedule_state' => $job['schedule_state'] ?? null,
                'schedule_error' => $job['schedule_error'] ?? null,
                'selected_document_count' => $job['selected_document_count'] ?? null,
            ]),
            $questions,
            $this->recoveryGuidance($retryability),
            $truncation,
        );
    }

    /**
     * @param list<string> $selectedComponents
     * @param list<array<string,mixed>> $facts
     * @param list<array<string,mixed>> $classifications
     * @param list<array<string,mixed>> $timeline
     * @param list<array<string,mixed>> $expectedObserved
     * @param list<array<string,mixed>> $evidence
     * @param array<string,scalar|null> $safeContext
     * @param list<array<string,mixed>> $questions
     * @param array<string,mixed> $recovery
     * @param list<array<string,mixed>> $truncation
     * @return array<string,mixed>
     */
    private function baseRecord(
        string $diagnosticId,
        string $recordType,
        int $createdAt,
        int $expiresAt,
        string $operation,
        ?string $route,
        ?string $jobId,
        ?string $exportScope,
        ?string $privacyMode,
        array $selectedComponents,
        int $selectedDocumentCount,
        ?string $stage,
        ?string $subsystem,
        string $attempted,
        ?string $lastSuccessful,
        string $failureBoundary,
        ?string $publicCode,
        string $internalCode,
        \Throwable $exception,
        string $scope,
        string $retryability,
        string $terminalState,
        array $facts,
        array $classifications,
        array $timeline,
        array $expectedObserved,
        array $evidence,
        array $safeContext,
        array $questions,
        array $recovery,
        array $truncation,
    ): array {
        [$systemIdentity, $identityEvidence] = $this->systemIdentity();
        $evidence = array_values(array_merge($evidence, $identityEvidence));
        return [
            'schema' => [
                'format' => self::FORMAT,
                'schema_id' => self::SCHEMA_ID,
                'schema_version' => self::SCHEMA_VERSION,
                'media_type' => self::MEDIA_TYPE,
            ],
            'diagnostic_identity' => [
                'diagnostic_id' => $diagnosticId,
                'record_type' => $recordType,
                'created_at' => gmdate('Y-m-d\TH:i:s\Z', $createdAt),
                'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
            ],
            'completeness_boundary' => [
                'status' => 'CONFIRMED',
                'description' => 'This record contains all bounded allowlisted facts EDIS observed and persisted at this incident boundary; it is not a complete system log or an AI diagnosis.',
                'excluded_evidence' => [
                    'raw exception messages',
                    'stack traces',
                    'credentials, tokens, cookies, nonces, and sessions',
                    'raw request bodies and form values',
                    'absolute paths and raw SQL',
                    'complete Elementor documents and unrestricted source content',
                ],
            ],
            'system_identity' => $systemIdentity,
            'operation_identity' => [
                'operation_type' => $this->safeIdentifier($operation, 'UNKNOWN_OPERATION'),
                'rest_route' => $this->safeRoute($route),
                'job_id' => $jobId !== null && preg_match('/\A[a-f0-9-]{36}\z/D', $jobId) === 1 ? $jobId : null,
                'export_scope' => $this->safeIdentifier($exportScope),
                'privacy_mode' => in_array($privacyMode, ['Strict', 'Standard', 'Diagnostic'], true) ? $privacyMode : null,
                'selected_components' => $selectedComponents,
                'selected_document_count' => max(0, min(250, $selectedDocumentCount)),
            ],
            'failure_location' => [
                'lifecycle_stage' => $stage,
                'subsystem' => $subsystem,
                'operation_immediately_attempted' => $this->safeSentence($attempted, 256),
                'last_successful_state' => $lastSuccessful,
                'failure_boundary' => $this->safeSentence($failureBoundary, 512),
            ],
            'failure_classification' => [
                'public_code' => $publicCode !== null ? $this->safePublicCode($publicCode) : null,
                'internal_code' => $internalCode,
                'exception_class' => $this->safeExceptionClass($exception::class),
                'scope' => in_array($scope, ['SEMANTIC', 'OPERATIONAL'], true) ? $scope : 'UNKNOWN',
                'retryability' => $retryability,
                'terminal_state' => $terminalState,
                'root_cause' => null,
                'root_cause_status' => 'NOT_PROVEN',
            ],
            'recorded_facts' => array_slice($facts, 0, 64),
            'plugin_classification' => array_slice($classifications, 0, 16),
            'causal_timeline' => array_slice($timeline, 0, 32),
            'expected_vs_observed' => array_slice($expectedObserved, 0, 16),
            'evidence_and_provenance' => array_slice($evidence, 0, 32),
            'safe_context' => $safeContext,
            'unresolved_questions' => array_slice($questions, 0, 16),
            'recovery_guidance' => $recovery,
            'supporting_artifact_index' => [],
            'bounds' => [
                'maximum_payload_bytes' => DiagnosticRecordStore::MAX_BYTES,
                'truncated' => $truncation !== [],
                'truncated_fields' => array_slice($truncation, 0, 16),
            ],
            'model_usage' => [
                'fact_policy' => 'Use recorded_facts and evidence_and_provenance as direct facts.',
                'classification_policy' => 'Treat plugin_classification as deterministic derivation, not root-cause certainty.',
                'unknown_policy' => 'Do not treat NOT_PROVEN or UNAVAILABLE as evidence that a condition is absent.',
                'next_action_policy' => 'Prefer the smallest evidence-collection action before recommending repair.',
            ],
            'model_analysis_not_included' => [
                'root_cause_analysis' => false,
                'repair_claim' => false,
                'reason' => 'EDIS records bounded evidence and deterministic classifications; language-model inference is not embedded.',
            ],
        ];
    }

    /** @param array<string,mixed> $record @return array{diagnostic_available:bool,diagnostic_id:?string,diagnostic_persistence_code:?string} */
    private function persist(int $ownerId, array $record): array
    {
        $record = $this->fitBoundedRecord($record);
        $instance = json_decode(CanonicalJson::encode($record));
        $errors = $this->validator->validate($instance, 'schemas/diagnostic-record.schema.json');
        if ($errors !== []) {
            throw new \RuntimeException('The canonical diagnostic record failed schema validation.');
        }
        $created = $this->store->create($ownerId, $record);
        return [
            'diagnostic_available' => true,
            'diagnostic_id' => $created['diagnostic_id'],
            'diagnostic_persistence_code' => null,
        ];
    }

    /** @param array<string,mixed> $record @return array<string,mixed> */
    private function fitBoundedRecord(array $record): array
    {
        $bytes = CanonicalJson::encode($record);
        if (strlen($bytes) <= DiagnosticRecordStore::MAX_BYTES) {
            return $record;
        }
        foreach (['safe_context', 'expected_vs_observed', 'plugin_classification'] as $field) {
            $original = is_array($record[$field] ?? null) ? count($record[$field]) : 0;
            if ($field === 'safe_context') {
                $record[$field] = [];
            } else {
                $record[$field] = array_slice((array) ($record[$field] ?? []), 0, 4);
            }
            $retained = count((array) $record[$field]);
            $record['bounds']['truncated'] = true;
            $record['bounds']['truncated_fields'][] = ['path' => $field, 'original_count' => $original, 'retained_count' => $retained];
            if (strlen(CanonicalJson::encode($record)) <= DiagnosticRecordStore::MAX_BYTES) {
                return $record;
            }
        }
        throw new \RuntimeException('Core diagnostic fields cannot fit the bounded record contract.');
    }

    /** @return array{0:array<string,mixed>,1:list<array<string,mixed>>} */
    private function systemIdentity(): array
    {
        global $wp_version;
        $pluginVersion = defined('EDIS_EVIDENCE_EXPORTER_VERSION') ? (string) EDIS_EVIDENCE_EXPORTER_VERSION : null;
        $workerVersion = null;
        try {
            $reflection = new \ReflectionClass(ExportJobService::class);
            $value = $reflection->getConstant('IMPLEMENTATION_VERSION');
            $workerVersion = is_string($value) && $value !== '' ? $value : null;
        } catch (\Throwable) {
        }
        $wordpressVersion = isset($wp_version) && is_string($wp_version) && $wp_version !== '' ? $wp_version : null;
        $elementorVersion = defined('ELEMENTOR_VERSION') ? (string) ELEMENTOR_VERSION : null;
        $pluginManifestHash = $this->boundedFileSha256('plugin.manifest.json');
        $criticalFilesHash = $this->boundedFileSha256('config/critical-files.json');
        $buildIdentity = $pluginManifestHash !== null && $criticalFilesHash !== null
            ? 'plugin_manifest=sha256:' . $pluginManifestHash . ';critical_files=sha256:' . $criticalFilesHash
            : null;

        $evidence = [];
        $evidenceIds = [];
        $append = static function (array &$rows, string $id, string $sourceType, string $locator): array {
            $rows[] = ['evidence_id' => $id, 'source_type' => $sourceType, 'source_locator' => $locator, 'status' => 'CONFIRMED'];
            return [$id];
        };

        $pluginEvidence = $pluginVersion !== null
            ? $append($evidence, 'ev-101', 'RUNTIME_CONSTANT', 'EDIS_EVIDENCE_EXPORTER_VERSION')
            : [];
        $workerEvidence = $workerVersion !== null
            ? $append($evidence, 'ev-102', 'RUNTIME_CLASS_CONSTANT', 'ExportJobService::IMPLEMENTATION_VERSION')
            : [];
        $wordpressEvidence = $wordpressVersion !== null
            ? $append($evidence, 'ev-103', 'WORDPRESS_RUNTIME_GLOBAL', 'global:wp_version')
            : [];
        $phpEvidence = $append($evidence, 'ev-104', 'PHP_RUNTIME_VERSION', 'PHP_VERSION');
        $elementorEvidence = $elementorVersion !== null
            ? $append($evidence, 'ev-105', 'ELEMENTOR_RUNTIME_CONSTANT', 'ELEMENTOR_VERSION')
            : [];
        if ($buildIdentity !== null) {
            $evidenceIds = array_merge(
                $append($evidence, 'ev-106', 'BUILD_IDENTITY_FILE_SHA256', 'plugin.manifest.json#sha256:' . $pluginManifestHash),
                $append($evidence, 'ev-107', 'BUILD_IDENTITY_FILE_SHA256', 'config/critical-files.json#sha256:' . $criticalFilesHash),
            );
        }

        return [[
            'plugin_version' => $this->observation($pluginVersion, $pluginVersion !== null ? 'CONFIRMED' : 'UNAVAILABLE', $pluginEvidence),
            'worker_implementation_version' => $this->observation($workerVersion, $workerVersion !== null ? 'CONFIRMED' : 'UNAVAILABLE', $workerEvidence),
            'wordpress_version' => $this->observation($wordpressVersion, $wordpressVersion !== null ? 'CONFIRMED' : 'UNAVAILABLE', $wordpressEvidence),
            'php_version' => $this->observation(PHP_VERSION, 'CONFIRMED', $phpEvidence),
            'elementor_version' => $this->observation($elementorVersion, $elementorVersion !== null ? 'CONFIRMED' : 'UNAVAILABLE', $elementorEvidence),
            'build_identity' => $this->observation($buildIdentity, $buildIdentity !== null ? 'CONFIRMED' : 'UNAVAILABLE', $evidenceIds),
        ], $evidence];
    }

    private function boundedFileSha256(string $relativePath): ?string
    {
        $path = rtrim($this->pluginRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($path) || is_link($path)) {
            return null;
        }
        $hash = hash_file('sha256', $path);
        return is_string($hash) ? $hash : null;
    }

    /** @return array{value:string|int|bool|null,status:string,evidence_ids:list<string>} */
    private function observation(string|int|bool|null $value, string $status, array $evidenceIds): array
    {
        return ['value' => is_string($value) ? substr($value, 0, 256) : $value, 'status' => $status, 'evidence_ids' => array_slice($evidenceIds, 0, 8)];
    }

    /** @return array<string,mixed> */
    private function fact(int $number, string $statement, array $evidenceIds): array
    {
        return ['fact_id' => sprintf('fact-%03d', $number), 'statement' => $this->safeSentence($statement, 512), 'status' => 'CONFIRMED', 'evidence_ids' => array_slice($evidenceIds, 0, 8)];
    }

    /** @return array<string,mixed> */
    private function classification(int $number, string $ruleId, string $classification, array $factIds): array
    {
        return [
            'classification_id' => sprintf('class-%03d', $number),
            'rule_id' => $this->safeCode($ruleId),
            'rule_version' => '1.0.0',
            'classification' => $this->safeSentence($classification, 512),
            'status' => 'DERIVED',
            'input_fact_ids' => array_slice(array_values(array_filter($factIds, 'is_string')), 0, 16),
        ];
    }

    /** @return array<string,mixed> */
    private function question(int $number, string $question, string $status, string $why, string $action): array
    {
        return [
            'question_id' => sprintf('missing-%03d', $number),
            'question' => $this->safeSentence($question, 256),
            'status' => in_array($status, ['NOT_PROVEN', 'UNAVAILABLE', 'CONTRADICTED'], true) ? $status : 'NOT_PROVEN',
            'why_it_matters' => $this->safeSentence($why, 512),
            'smallest_collection_action' => $this->safeSentence($action, 512),
        ];
    }

    /** @return array<string,mixed> */
    private function recoveryGuidance(string $retryability): array
    {
        return match ($retryability) {
            'RETRY_SAFE' => [
                'safe_immediate_action' => 'Correct the recorded operational condition, then use the existing Retry action for the exact Job.',
                'retry_action' => 'RETRY_AFTER_RECORDED_CONDITION_IS_CORRECTED',
                'resume_action' => 'USE_EXISTING_JOB_RETRY_PATH',
                'new_job_action' => 'NOT_REQUIRED',
                'developer_investigation' => 'ONLY_IF_THE_SAME_STABLE_CODE_RECURS_AFTER_THE_CONDITION_IS_CORRECTED',
                'stop_conditions' => ['Do not bypass integrity or semantic validation.', 'Stop repeated retries if the same stable code recurs.'],
            ],
            'RESUME_SAFE' => [
                'safe_immediate_action' => 'Resume the exact authorized Job through the existing Resume action.',
                'retry_action' => 'NOT_REQUIRED',
                'resume_action' => 'RESUME_EXACT_JOB',
                'new_job_action' => 'NOT_REQUIRED',
                'developer_investigation' => 'ONLY_IF_RESUME_PERSISTS_A_NEW_FAILURE_RECORD',
                'stop_conditions' => ['Do not create parallel replacement Jobs while the existing Job remains resumable.'],
            ],
            'NEW_JOB_REQUIRED' => [
                'safe_immediate_action' => 'Correct the recorded request or preflight condition and run preflight again before creating a new Job.',
                'retry_action' => 'DO_NOT_RETRY_THE_FAILED_PRE_JOB_REQUEST_UNCHANGED',
                'resume_action' => 'NOT_AVAILABLE_WITHOUT_A_DURABLE_JOB',
                'new_job_action' => 'RUN_PREFLIGHT_THEN_CREATE_NEW_JOB',
                'developer_investigation' => 'REQUIRED_ONLY_IF_THE_CORRECTED_REQUEST_STILL_FAILS_AT_THE_SAME_BOUNDARY',
                'stop_conditions' => ['Do not reuse an invalid or expired preflight proof.', 'Do not bypass source or integrity checks.'],
            ],
            'NOT_RETRYABLE' => [
                'safe_immediate_action' => 'Do not Retry or Resume until the recorded semantic or integrity mismatch is corrected.',
                'retry_action' => 'BLOCKED',
                'resume_action' => 'BLOCKED',
                'new_job_action' => 'ONLY_AFTER_THE_RECORDED_MISMATCH_IS_CORRECTED',
                'developer_investigation' => 'REQUIRED_IF_THE_MISMATCH_CANNOT_BE_CORRECTED_FROM_THE_RECORDED_EVIDENCE',
                'stop_conditions' => ['Do not bypass fail-closed validation.', 'Do not convert the failure into an automatic retry loop.'],
            ],
            default => [
                'safe_immediate_action' => 'Do not infer a root cause or repeat the operation until the smallest unresolved evidence item is collected.',
                'retry_action' => 'NOT_PROVEN',
                'resume_action' => 'NOT_PROVEN',
                'new_job_action' => 'NOT_PROVEN',
                'developer_investigation' => 'REQUIRED_IF_THE_NAMED_BOUNDARY_CANNOT_BE_RESOLVED_WITH_BOUNDED_EVIDENCE',
                'stop_conditions' => ['Do not bypass integrity or semantic validation.', 'Do not repeatedly retry while retryability remains NOT_PROVEN.'],
            ],
        };
    }

    /** @param array<string,mixed> $context @return array<string,scalar|null> */
    private function safeContext(\Throwable $exception, array $context): array
    {
        $context = $this->exceptionContext($exception) + $context;
        $context['exception_class'] = $exception::class;
        $allowed = [
            'failure_phase', 'validation_stage', 'failed_checks', 'component_id', 'filesystem_operation', 'path_role',
            'artifact_sha256', 'artifact_size', 'document_count', 'selected_document_count', 'schedule_state', 'schedule_error',
            'external_trigger_required', 'manual_retry_available', 'exception_class', 'source_kind', 'store_check',
            'expected_revision', 'actual_revision',
        ];
        $safe = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $context) || count($safe) >= 32) {
                continue;
            }
            $value = $context[$key];
            if ($key === 'exception_class') {
                $safe[$key] = $this->safeExceptionClass(is_string($value) ? $value : $exception::class);
                continue;
            }
            if (is_bool($value) || is_int($value) || $value === null) {
                $safe[$key] = $value;
                continue;
            }
            if (is_array($value)) {
                $items = [];
                foreach (array_slice($value, 0, 16) as $item) {
                    $identifier = $this->safeIdentifier($item);
                    if ($identifier !== null) {
                        $items[] = $identifier;
                    }
                }
                $safe[$key] = substr(implode(',', $items), 0, 256);
                continue;
            }
            if (!is_string($value)) {
                continue;
            }
            if (in_array($key, ['artifact_sha256'], true) && preg_match('/\Asha256:[a-f0-9]{64}\z/D', $value) === 1) {
                $safe[$key] = $value;
                continue;
            }
            $identifier = $this->safeIdentifier($value);
            if ($identifier !== null) {
                $safe[$key] = $identifier;
            }
        }
        ksort($safe, SORT_STRING);
        return $safe;
    }

    /** @return array<string,mixed> */
    private function exceptionContext(\Throwable $exception): array
    {
        return $exception instanceof ExportIntegrityException && is_array($exception->diagnosticContext)
            ? $exception->diagnosticContext
            : [];
    }

    /** @param array<string,mixed> $job @return array<string,mixed> */
    private function latestDiagnostic(array $job): array
    {
        $diagnostics = is_array($job['diagnostics'] ?? null) ? $job['diagnostics'] : [];
        $latest = end($diagnostics);
        return is_array($latest) ? $latest : [];
    }

    /** @param array<string,mixed> $job */
    private function jobLastSuccessfulState(array $job): ?string
    {
        $completed = array_values(array_filter((array) ($job['completed_components'] ?? []), 'is_string'));
        if ($completed !== []) {
            return 'COMPONENT_COMMITTED_' . strtoupper((string) end($completed));
        }
        if (isset($job['last_successful_step_at']) && is_numeric($job['last_successful_step_at'])) {
            return 'JOB_STEP_PERSISTED';
        }
        return isset($job['created_at']) ? 'JOB_CREATED' : null;
    }

    /** @param array<string,mixed> $job */
    private function jobRetryability(array $job, string $scope, string $terminal): string
    {
        if ($terminal === 'IN_PROGRESS') {
            return 'RESUME_SAFE';
        }
        if ($terminal !== 'FAILED') {
            return 'NOT_PROVEN';
        }
        if ($scope === 'SEMANTIC' || (($job['schedule_error'] ?? null) === 'NON_RETRYABLE_INTEGRITY_FAILURE')) {
            return 'NOT_RETRYABLE';
        }
        return isset($job['next_retry_at']) && is_numeric($job['next_retry_at']) && (int) $job['next_retry_at'] > 0
            ? 'RETRY_SAFE'
            : 'NOT_PROVEN';
    }

    private function terminalState(string $status): string
    {
        return match ($status) {
            'queued', 'running' => 'IN_PROGRESS',
            'failed' => 'FAILED',
            'cancelled' => 'CANCELLED',
            'completed' => 'COMPLETED',
            default => 'UNKNOWN',
        };
    }

    private function internalCode(\Throwable $exception, string $fallback): string
    {
        return $exception instanceof ExportIntegrityException
            ? $this->safeCode($exception->diagnosticCode)
            : $this->safeCode($fallback);
    }

    private function newId(): string
    {
        return 'edis-diag-' . bin2hex(random_bytes(16));
    }

    /** @return array{0:list<string>,1:list<array<string,mixed>>} */
    private function boundedStrings(array $values, int $limit, string $path): array
    {
        $safe = [];
        foreach ($values as $value) {
            $identifier = $this->safeIdentifier($value);
            if ($identifier !== null) {
                $safe[$identifier] = true;
            }
        }
        $safeValues = array_keys($safe);
        sort($safeValues, SORT_STRING);
        $original = count($safeValues);
        $retained = array_slice($safeValues, 0, $limit);
        return [$retained, $original > count($retained) ? [['path' => $path, 'original_count' => $original, 'retained_count' => count($retained)]] : []];
    }

    private function safeRoute(?string $route): ?string
    {
        if ($route === null || $route === '' || strlen($route) > 256 || str_contains($route, '?') || str_contains($route, '#')) {
            return null;
        }
        return preg_match('~\A/[A-Za-z0-9_./{}:-]+\z~D', $route) === 1 ? $route : null;
    }

    private function safeIdentifier(mixed $value, ?string $fallback = null): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return $fallback;
        }
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > 128 || preg_match('/\A[A-Za-z0-9_.:-]+\z/D', $value) !== 1) {
            return $fallback;
        }
        return $value;
    }

    private function safeCode(mixed $value): string
    {
        $value = is_string($value) ? strtoupper(trim($value)) : 'EDIS_DIAGNOSTIC_UNKNOWN';
        return preg_match('/\A[A-Z0-9_:-]{1,128}\z/D', $value) === 1 ? $value : 'EDIS_DIAGNOSTIC_UNKNOWN';
    }

    private function safePublicCode(string $value): string
    {
        return preg_match('/\A[a-z0-9_:-]{1,128}\z/D', $value) === 1 ? $value : 'edis_diagnostic_failure';
    }

    private function safeExceptionClass(string $value): string
    {
        return preg_match('/\A[A-Za-z_][A-Za-z0-9_\\\\]{0,255}\z/D', $value) === 1 ? $value : 'Throwable';
    }

    private function safeSentence(mixed $value, int $limit): string
    {
        $value = is_string($value) ? trim($value) : '';
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        return substr($value !== '' ? $value : 'No additional bounded statement was recorded.', 0, $limit);
    }

    /** @return array{diagnostic_available:false,diagnostic_id:null,diagnostic_persistence_code:string} */
    private function unavailable(string $code = 'EDIS_DIAGNOSTIC_PERSISTENCE_FAILED'): array
    {
        return [
            'diagnostic_available' => false,
            'diagnostic_id' => null,
            'diagnostic_persistence_code' => $code,
        ];
    }
}
