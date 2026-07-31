#!/usr/bin/env python3
from pathlib import Path

path = Path('src/Application/ExportJobService.php')
text = path.read_text(encoding='utf-8')


def once(old: str, new: str, label: str) -> None:
    global text
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected exactly one match, found {count}')
    text = text.replace(old, new, 1)


once("private const IMPLEMENTATION_VERSION = '3.7.12';", "private const IMPLEMENTATION_VERSION = '3.7.14';", 'worker_version')
once("                'next_retry_at' => null,\n                'stale_after' => 120,", "                'next_retry_at' => null,\n                'packaging_started_at' => null,\n                'stale_after' => 120,", 'packaging_field')
once("            if ($cursor >= count($plan)) {\n                $job['phase'] = 'packaging';\n                $job['progress'] = 88;\n                $job['current_component'] = null;\n                return false;\n            }", "            if ($cursor >= count($plan)) {\n                $job['phase'] = 'packaging';\n                $job['progress'] = 88;\n                $job['current_component'] = null;\n                if (!is_string($job['packaging_started_at'] ?? null) || $job['packaging_started_at'] === '') {\n                    $job['packaging_started_at'] = gmdate('Y-m-d\\TH:i:s\\Z');\n                }\n                return false;\n            }", 'packaging_transition')
once("            $stepInputSha256 = $this->stepInputSha256($componentId, $job, $records);\n            try {\n                $result = $this->registry->execute($componentId, $context, $committed);", "            $stepInputSha256 = $this->stepInputSha256($componentId, $job, $records);\n            $executionStartedAt = gmdate('Y-m-d\\TH:i:s\\Z');\n            try {\n                $result = $this->registry->execute($componentId, $context, $committed);", 'execution_start')
once("            $artifact = $result->jsonSerialize();\n            $this->artifacts->put((string) $job['job_id'], $componentId, $artifact);", "            $artifact = $result->jsonSerialize();\n            $config = is_array($job['config'] ?? null) ? $job['config'] : [];\n            $artifact['observed_at'] = $componentId === 'elementor_document_source'\n                ? (string) ($config['captured_at'] ?? $executionStartedAt)\n                : $executionStartedAt;\n            $this->artifacts->put((string) $job['job_id'], $componentId, $artifact);", 'artifact_observed_at')
once("                'step_input_sha256' => $stepInputSha256,\n                'artifact_file_sha256' => $artifactSha256,", "                'step_input_sha256' => $stepInputSha256,\n                'artifact_file_sha256' => $artifactSha256,\n                'observed_at' => (string) $artifact['observed_at'],", 'step_observed_at')
once("        if ($phase === 'packaging') {\n            $plan = array_values(array_filter((array) ($job['selected_components'] ?? []), 'is_string'));\n            $context = $this->context($job);\n            $bundle = $this->exporter->package((string) $job['job_id'], $context, $plan, $this->artifacts, $this->files, (int) $job['expires_at']);", "        if ($phase === 'packaging') {\n            $plan = array_values(array_filter((array) ($job['selected_components'] ?? []), 'is_string'));\n            if (!is_string($job['packaging_started_at'] ?? null) || $job['packaging_started_at'] === '') {\n                $job['packaging_started_at'] = gmdate('Y-m-d\\TH:i:s\\Z');\n                $this->jobs->save($job);\n            }\n            $context = $this->context($job);\n            $bundle = $this->exporter->package((string) $job['job_id'], $context, $plan, $this->artifacts, $this->files, (int) $job['expires_at'], (string) $job['packaging_started_at']);", 'package_call')
once("                && ($record['input_snapshot_sha256'] ?? null) === ($job['input_snapshot_sha256'] ?? null)\n                && $this->artifacts->verifyFileSha256((string) $job['job_id'], $componentId, $artifactSha256)", "                && ($record['input_snapshot_sha256'] ?? null) === ($job['input_snapshot_sha256'] ?? null)\n                && is_string($record['observed_at'] ?? null)\n                && $record['observed_at'] !== ''\n                && $this->artifacts->verifyFileSha256((string) $job['job_id'], $componentId, $artifactSha256)", 'resume_observed_at')
old_failure = """                $job['status'] = 'failed';
                $job['phase'] = 'failed';
                $job['last_error_code'] = $errorCode;
                $job['last_error_at'] = time();
                $job['next_retry_at'] = $exception instanceof ExportIntegrityException ? null : time() + 5;
                $job['lease_owner'] = null;
                $job['lease_acquired_at'] = null;
                $job['lease_expires_at'] = null;
                $job['diagnostics'][] = [
                    'code' => $errorCode,
                    'severity' => 'ERROR',
                    'scope' => $exception instanceof ExportIntegrityException ? 'SEMANTIC' : 'OPERATIONAL',
                    'message_key' => 'diagnostic.export.advance_failed',
                    'context' => $diagnosticContext,
                ];
                $this->jobs->save($job);"""
new_failure = """                $nonRetryable = $exception instanceof ExportIntegrityException;
                $job['status'] = 'failed';
                $job['phase'] = 'failed';
                $job['last_error_code'] = $errorCode;
                $job['last_error_at'] = time();
                $job['next_retry_at'] = $nonRetryable ? null : time() + 5;
                $job['lease_owner'] = null;
                $job['lease_acquired_at'] = null;
                $job['lease_expires_at'] = null;
                $job['schedule_state'] = 'NOT_SCHEDULED';
                $job['schedule_error'] = $nonRetryable ? 'NON_RETRYABLE_INTEGRITY_FAILURE' : null;
                $job['diagnostics'][] = [
                    'code' => $errorCode,
                    'severity' => 'ERROR',
                    'scope' => $nonRetryable ? 'SEMANTIC' : 'OPERATIONAL',
                    'message_key' => 'diagnostic.export.advance_failed',
                    'context' => $diagnosticContext,
                ];
                $this->jobs->save($job);
                if ($nonRetryable) {
                    $this->clearRecoverySchedule((string) ($job['job_id'] ?? ''));
                } else {
                    try {
                        $this->scheduleRecovery($job);
                    } catch (\\Throwable $scheduleException) {
                        $job['schedule_state'] = 'ERROR';
                        $job['schedule_error'] = 'RECOVERY_SCHEDULING_EXCEPTION';
                        $job['diagnostics'][] = [
                            'code' => 'EDIS_RECOVERY_SCHEDULING_FAILED',
                            'severity' => 'WARNING',
                            'scope' => 'OPERATIONAL',
                            'message_key' => 'diagnostic.export.recovery_scheduling_failed',
                            'context' => ['exception_class' => get_class($scheduleException)],
                        ];
                        $this->jobs->save($job);
                    }
                }"""
once(old_failure, new_failure, 'failure_normalization')
once("    private function scheduleRecovery(array &$job): void\n    {\n        if (!function_exists('wp_schedule_single_event')) {", "    private function scheduleRecovery(array &$job): void\n    {\n        $this->recordCronTriggerTruth($job);\n        if (!function_exists('wp_schedule_single_event')) {", 'cron_truth_hook')
once("        $result = wp_schedule_single_event(time() + 15, 'edis_process_export_job', $args, true);", "        $retryEligibility = (int) ($job['next_retry_at'] ?? 0);\n        $scheduledAt = max(time() + 15, $retryEligibility);\n        $result = wp_schedule_single_event($scheduledAt, 'edis_process_export_job', $args, true);", 'retry_eligibility')
once("    private function clearRecoverySchedule(string $jobId): void\n    {", "    /** @param array<string,mixed> $job */\n    private function recordCronTriggerTruth(array &$job): void\n    {\n        if (!defined('DISABLE_WP_CRON') || constant('DISABLE_WP_CRON') !== true) {\n            return;\n        }\n        foreach ((array) ($job['diagnostics'] ?? []) as $diagnostic) {\n            if (is_array($diagnostic) && ($diagnostic['code'] ?? null) === 'EDIS_WP_CRON_INTERNAL_TRIGGER_DISABLED') {\n                return;\n            }\n        }\n        $job['diagnostics'][] = [\n            'code' => 'EDIS_WP_CRON_INTERNAL_TRIGGER_DISABLED',\n            'severity' => 'WARNING',\n            'scope' => 'OPERATIONAL',\n            'message_key' => 'diagnostic.export.wp_cron_internal_trigger_disabled',\n            'context' => [\n                'external_trigger_required' => true,\n                'manual_retry_available' => true,\n            ],\n        ];\n    }\n\n    private function clearRecoverySchedule(string $jobId): void\n    {", 'cron_truth_method')

path.write_text(text, encoding='utf-8')
