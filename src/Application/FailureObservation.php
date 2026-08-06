<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Application;

use EDIS\EvidenceExporter\Infrastructure\Support\ExportIntegrityException;

/**
 * Immutable, bounded observation passed from source/orchestration boundaries
 * to the sole canonical Diagnostic projection authority.
 */
final readonly class FailureObservation
{
    private const CLASSIFICATIONS = [
        'MATERIAL_PRE_JOB_INCIDENT',
        'MATERIAL_JOB_INCIDENT',
        'MATERIAL_WORKER_INCIDENT',
        'INTERNAL_EVIDENCE_FAILURE',
        'AUTHORITY_UNAVAILABLE',
        'UNEXPECTED_MATERIAL_FAILURE',
        'PROGRAMMER_INVARIANT_FAILURE',
    ];
    private const POLICIES = ['PRE_JOB', 'JOB_BOUND', 'WORKER_BOUND', 'UNAVAILABLE_ONLY'];
    private const RETRYABILITY = ['NEW_JOB_REQUIRED', 'RETRY_ALLOWED', 'NOT_RETRYABLE', 'NOT_PROVEN'];
    private const CONTEXT_KEYS = [
        'validation_stage', 'failed_checks', 'failure_phase', 'component_id', 'source_kind',
        'document_count', 'selected_document_count', 'filesystem_operation', 'path_role',
        'store_check', 'expected_revision', 'actual_revision', 'schedule_state', 'schedule_error',
        'exception_class',
    ];

    /** @param array<string,scalar|list<scalar>|null> $safeContext */
    public function __construct(
        public string $reasonCode,
        public ?string $lifecycleStage,
        public string $classification,
        public string $creationPolicy,
        public string $retryability,
        public array $safeContext = [],
    ) {
        if (preg_match('/\AEDIS_[A-Z0-9_]{3,120}\z/D', $reasonCode) !== 1) {
            throw new \InvalidArgumentException('Invalid stable failure reason code.');
        }
        if ($lifecycleStage !== null && preg_match('/\A[a-z][a-z0-9_]{2,95}\z/D', $lifecycleStage) !== 1) {
            throw new \InvalidArgumentException('Invalid lifecycle stage.');
        }
        if (!in_array($classification, self::CLASSIFICATIONS, true)) {
            throw new \InvalidArgumentException('Invalid failure classification.');
        }
        if (!in_array($creationPolicy, self::POLICIES, true)) {
            throw new \InvalidArgumentException('Invalid Diagnostic creation policy.');
        }
        if (!in_array($retryability, self::RETRYABILITY, true)) {
            throw new \InvalidArgumentException('Invalid retryability.');
        }
        if (count($safeContext) > 16) {
            throw new \InvalidArgumentException('Failure context exceeds the bounded property limit.');
        }
        foreach ($safeContext as $key => $value) {
            if (!is_string($key) || !in_array($key, self::CONTEXT_KEYS, true)) {
                throw new \InvalidArgumentException('Failure context contains a non-allowlisted key.');
            }
            self::assertBoundedValue($value);
        }
    }

    public function asIntegrityException(?\Throwable $previous = null): ExportIntegrityException
    {
        $context = $this->safeContext;
        if ($this->lifecycleStage !== null) {
            $context['failure_phase'] = $this->lifecycleStage;
        }
        return new ExportIntegrityException(
            $this->reasonCode,
            'EDIS observed a bounded operation failure.',
            $previous,
            $context,
        );
    }

    private static function assertBoundedValue(mixed $value): void
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return;
        }
        if (is_string($value)) {
            if (strlen($value) > 256 || str_contains($value, "\0")) {
                throw new \InvalidArgumentException('Failure context string exceeds the bounded limit.');
            }
            return;
        }
        if (is_array($value) && array_is_list($value) && count($value) <= 16) {
            foreach ($value as $item) {
                if (!is_scalar($item) || (is_string($item) && strlen($item) > 128)) {
                    throw new \InvalidArgumentException('Failure context array contains an unsafe value.');
                }
            }
            return;
        }
        throw new \InvalidArgumentException('Failure context contains an unsupported value.');
    }
}
