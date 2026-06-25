<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

final class JobStore
{
    private string $root;
    private DeterministicFilesystem $filesystem;
    /** @var array<string,array{sha256:string,job:array<string,mixed>}> */
    private array $readCache = [];

    public function __construct(?string $root = null, ?DeterministicFilesystem $filesystem = null)
    {
        $this->root = $root ?? (new PrivateStorage())->path('jobs');
        $this->filesystem = $filesystem ?? new DeterministicFilesystem();
    }

    public function rootWritable(): bool
    {
        try { $this->filesystem->ensureDirectory($this->root); }
        catch (\Throwable) { return false; }
        return is_writable($this->root) && !is_link($this->root);
    }

    /** @param array<string,mixed> $state @return array<string,mixed> */
    public function create(array $state): array
    {
        $jobId = isset($state['job_id']) && is_string($state['job_id']) ? $this->safeName($state['job_id']) : Uuid::v4();
        if ($this->get($jobId) !== null) { throw new \RuntimeException('A job with the requested identifier already exists.'); }
        $state['job_id'] = $jobId; $state['revision'] = 1; $this->save($state, 0); return $state;
    }

    /** @return array<string,mixed>|null */
    public function get(string $jobId): ?array
    {
        $path = $this->path($jobId);
        clearstatcache(true, $path);
        if (is_link($this->root) || is_link($path) || !is_file($path)) {
            unset($this->readCache[$jobId]);
            return null;
        }
        try {
            $bytes = $this->filesystem->read($path);
        } catch (\Throwable) {
            unset($this->readCache[$jobId]);
            return null;
        }
        $sha256 = hash('sha256', $bytes);
        $cached = $this->readCache[$jobId] ?? null;
        if (is_array($cached) && hash_equals($cached['sha256'], $sha256)) {
            return $cached['job'];
        }
        try {
            $decoded = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            unset($this->readCache[$jobId]);
            return null;
        }
        if (!is_array($decoded)) {
            unset($this->readCache[$jobId]);
            return null;
        }
        $this->readCache[$jobId] = [
            'sha256' => $sha256,
            'job' => $decoded,
        ];
        return $decoded;
    }

    /** @param array<string,mixed> $job */
    public function save(array &$job, ?int $expectedRevision = null): void
    {
        if (!isset($job['job_id']) || !is_string($job['job_id'])) { throw new \InvalidArgumentException('Job ID is required.'); }
        $current = $this->get($job['job_id']);
        $currentRevision = is_array($current) ? (int) ($current['revision'] ?? 0) : 0;
        if ($expectedRevision !== null && $currentRevision !== $expectedRevision) { throw new \RuntimeException('Job revision conflict.'); }
        $this->filesystem->ensureDirectory($this->root);
        $job['revision'] = max((int) ($job['revision'] ?? 0), $currentRevision) + 1;
        $job['updated_at'] = time();
        $path = $this->path($job['job_id']);
        $bytes = CanonicalJson::encode($job);
        $this->filesystem->writeAtomically($path, $bytes);
        $this->readCache[$job['job_id']] = [
            'sha256' => hash('sha256', $bytes),
            'job' => $job,
        ];
    }

    /** @return array<string,mixed>|null */
    public function latestForUser(int $userId): ?array
    {
        if (!is_dir($this->root)) { return null; }
        $latest = null;
        foreach (glob($this->root . '/*.json') ?: [] as $path) {
            $job = $this->get(basename($path, '.json'));
            if (!is_array($job) || (int) ($job['owner_id'] ?? 0) !== $userId) { continue; }
            if ($latest === null || (int) ($job['updated_at'] ?? 0) > (int) ($latest['updated_at'] ?? 0)) { $latest = $job; }
        }
        return $latest === null ? null : $this->publicView($latest);
    }


    /** @return list<array<string,mixed>> */
    public function jobsForUser(int $userId): array
    {
        if ($userId <= 0 || !is_dir($this->root) || is_link($this->root)) { return []; }
        $jobs = [];
        foreach (glob($this->root . '/*.json') ?: [] as $path) {
            $job = $this->get(basename($path, '.json'));
            if (is_array($job) && (int) ($job['owner_id'] ?? 0) === $userId) { $jobs[] = $this->publicView($job); }
        }
        usort($jobs, static fn (array $a, array $b): int => [(int) ($a['created_at'] ?? 0), (string) ($a['job_id'] ?? '')] <=> [(int) ($b['created_at'] ?? 0), (string) ($b['job_id'] ?? '')]);
        return $jobs;
    }


    /** @return list<array<string,mixed>> */
    public function jobsForDocument(int $documentId): array
    {
        if ($documentId <= 0 || !is_dir($this->root) || is_link($this->root)) { return []; }
        $jobs = [];
        foreach (glob($this->root . '/*.json') ?: [] as $path) {
            $job = $this->get(basename($path, '.json'));
            if (!is_array($job)) { continue; }
            $config = is_array($job['config'] ?? null) ? $job['config'] : [];
            $documents = array_values(array_filter((array) ($config['document_ids'] ?? []), 'is_int'));
            if (in_array($documentId, $documents, true)) { $jobs[] = $this->publicView($job); }
        }
        usort($jobs, static fn (array $a, array $b): int => [(int) ($a['created_at'] ?? 0), (string) ($a['job_id'] ?? '')] <=> [(int) ($b['created_at'] ?? 0), (string) ($b['job_id'] ?? '')]);
        return $jobs;
    }

    /** @return list<string> */
    public function runnableJobIds(int $limit = 10): array
    {
        if (!is_dir($this->root) || is_link($this->root)) { return []; }
        $ids = [];
        $now = time();
        foreach (glob($this->root . '/*.json') ?: [] as $path) {
            $job = $this->get(basename($path, '.json'));
            if (is_array($job) && $this->isRunnableJob($job, $now)) {
                $ids[] = (string) $job['job_id'];
            }
        }
        sort($ids, SORT_STRING);
        return array_slice($ids, 0, max(1, min(100, $limit)));
    }

    /**
     * Repair stale leases and discover runnable jobs in one deterministic directory pass.
     *
     * @return array{repaired:list<string>,runnable:list<string>}
     */
    public function recoveryBatch(int $limit = 10): array
    {
        if (!is_dir($this->root) || is_link($this->root)) {
            return ['repaired' => [], 'runnable' => []];
        }
        $now = time();
        $repaired = [];
        $runnable = [];
        $paths = glob($this->root . '/*.json') ?: [];
        sort($paths, SORT_STRING);
        foreach ($paths as $path) {
            $jobId = basename($path, '.json');
            $job = $this->get($jobId);
            if (!is_array($job)) { continue; }
            if ($this->isStaleJob($job, $now)) {
                $lock = $this->acquireLock($jobId, 0);
                if (is_resource($lock)) {
                    try {
                        $current = $this->get($jobId);
                        if (is_array($current) && $this->isStaleJob($current, $now)) {
                            $job = $this->repairJobState($current);
                            $this->save($job);
                            $repaired[] = $jobId;
                        } elseif (is_array($current)) {
                            $job = $current;
                        }
                    } finally {
                        $this->releaseLock($lock);
                    }
                }
            }
            if ($this->isRunnableJob($job, $now)) {
                $runnable[] = $jobId;
            }
        }
        sort($repaired, SORT_STRING);
        sort($runnable, SORT_STRING);
        return [
            'repaired' => array_values(array_unique($repaired)),
            'runnable' => array_slice(array_values(array_unique($runnable)), 0, max(1, min(100, $limit))),
        ];
    }

    /** @return array{candidates:list<string>,repaired:list<string>,apply:bool} */
    public function repairStaleJobs(bool $apply = false): array
    {
        $candidates = [];
        $repaired = [];
        foreach ($this->staleJobs() as $view) {
            $jobId = (string) ($view['job_id'] ?? '');
            if ($jobId === '') { continue; }
            $candidates[] = $jobId;
            if (!$apply) { continue; }
            $lock = $this->acquireLock($jobId, 0);
            if (!is_resource($lock)) { continue; }
            try {
                $job = $this->get($jobId);
                if (!is_array($job) || !$this->isStaleJob($job, time())) { continue; }
                $job = $this->repairJobState($job);
                $this->save($job);
                $repaired[] = $jobId;
            } finally { $this->releaseLock($lock); }
        }
        sort($candidates, SORT_STRING); sort($repaired, SORT_STRING);
        return ['candidates' => $candidates, 'repaired' => $repaired, 'apply' => $apply];
    }

    public function remove(string $jobId): void
    {
        $lock = $this->acquireLock($jobId, 1);
        if (!is_resource($lock)) {
            throw new \RuntimeException('The job cannot be removed while its lock is held.');
        }
        try {
            $this->filesystem->removeFileIfExists($this->path($jobId), false);
            unset($this->readCache[$jobId]);
        } finally {
            $this->releaseLock($lock);
        }
        // The stable lock pathname is intentionally retained. Unlinking it can split lock identity
        // when another process still has the previous inode open.
    }

    /** @return list<array<string,mixed>> */
    public function staleJobs(?string $status = null): array
    {
        $jobs = [];
        foreach (glob($this->root . '/*.json') ?: [] as $path) {
            $job = $this->get(basename($path, '.json'));
            if (!is_array($job) || ($status !== null && ($job['status'] ?? null) !== $status)) { continue; }
            if ($this->isStaleJob($job, time())) { $jobs[] = $this->publicView($job); }
        }
        return $jobs;
    }

    /** @param array<string,mixed> $job @return array<string,mixed> */
    public function publicView(array $job): array
    {
        $allowed = [
            'job_id','job_format_version','implementation_version','status','phase','progress','analysis_set_id','wordpress_bundle_id','created_at','updated_at','expires_at','executor_mode','revision','cursor','current_component','last_heartbeat','last_successful_step_at','attempt_count','last_error_code','last_error_at','next_retry_at','stale_after','schedule_state','schedule_error','truth_summary','availability_summary','diagnostics','selected_components','selected_document_count','bundle_sha256','bundle_size','download_token','download_expires_at','validation_state','source_export_root_sha256','completed_components','input_snapshot_format_version','input_snapshot_sha256','lease_owner','lease_acquired_at','lease_expires_at',
        ];
        $view = [];
        foreach ($allowed as $key) { if (array_key_exists($key, $job)) { $view[$key] = $job[$key]; } }
        $view['is_stale'] = $this->isStaleJob($job, time());
        return $view;
    }

    /** @param array<string,mixed> $job */
    private function isRunnableJob(array $job, int $now): bool
    {
        $status = (string) ($job['status'] ?? '');
        $nextRetry = (int) ($job['next_retry_at'] ?? 0);
        $leaseExpiresAt = (int) ($job['lease_expires_at'] ?? 0);
        $leaseActive = $leaseExpiresAt > $now && is_string($job['lease_owner'] ?? null) && $job['lease_owner'] !== '';
        return !$leaseActive
            && ($status === 'queued' || ($status === 'failed' && $nextRetry > 0 && $nextRetry <= $now))
            && (int) ($job['expires_at'] ?? 0) >= $now;
    }

    /** @param array<string,mixed> $job @return array<string,mixed> */
    private function repairJobState(array $job): array
    {
        $job['status'] = 'queued';
        $job['phase'] = (string) ($job['phase'] ?? 'collecting');
        $job['lease_owner'] = null;
        $job['lease_acquired_at'] = null;
        $job['lease_expires_at'] = null;
        $job['schedule_state'] = 'REPAIRED_EXPIRED_LEASE';
        $diagnostics = is_array($job['diagnostics'] ?? null) ? $job['diagnostics'] : [];
        $diagnostics[] = ['code' => 'EDIS_STALE_JOB_REPAIRED', 'severity' => 'WARNING', 'scope' => 'OPERATIONAL', 'message_key' => 'diagnostic.worker.stale_job_repaired', 'context' => []];
        $job['diagnostics'] = $diagnostics;
        return $job;
    }

    /** @param array<string,mixed> $job */
    private function isStaleJob(array $job, int $now): bool
    {
        $status = (string) ($job['status'] ?? '');
        $leaseOwner = is_string($job['lease_owner'] ?? null) ? $job['lease_owner'] : '';
        $leaseExpiresAt = (int) ($job['lease_expires_at'] ?? 0);
        if ($status === 'queued') {
            return $leaseOwner !== '' && $leaseExpiresAt > 0 && $leaseExpiresAt < $now;
        }
        if ($status !== 'running') { return false; }
        if ($leaseExpiresAt > 0) { return $leaseExpiresAt < $now; }
        $heartbeat = (int) ($job['last_heartbeat'] ?? $job['updated_at'] ?? 0);
        return $heartbeat + max(30, (int) ($job['stale_after'] ?? 120)) < $now;
    }

    /** @return resource|null */
    public function acquireLock(string $jobId, int $timeoutSeconds = 1)
    {
        try {
            $this->filesystem->ensureDirectory($this->root);
            $handle = $this->filesystem->open($this->root . '/' . $this->safeName($jobId) . '.lock', 'c+b');
        } catch (\Throwable) { return null; }
        $deadline = microtime(true) + max(0, $timeoutSeconds);
        do {
            try { if ($this->filesystem->lock($handle, LOCK_EX | LOCK_NB)) { return $handle; } }
            catch (\Throwable) { break; }
            usleep(25000);
        } while (microtime(true) < $deadline);
        try { $this->filesystem->close($handle); } catch (\Throwable) {}
        return null;
    }

    /** @param resource|null $lock */
    public function releaseLock($lock): void
    {
        if (!is_resource($lock)) { return; }
        try { $this->filesystem->lock($lock, LOCK_UN); } finally { try { $this->filesystem->close($lock); } catch (\Throwable) {} }
    }

    public function cleanupExpired(): void
    {
        if (is_link($this->root)) { return; }
        foreach (glob($this->root . '/*.json') ?: [] as $path) {
            $jobId = basename($path, '.json');
            $lock = $this->acquireLock($jobId, 0);
            if (!is_resource($lock)) {
                continue;
            }
            try {
                $job = $this->get($jobId);
                if (is_array($job) && (int) ($job['expires_at'] ?? PHP_INT_MAX) < time()) {
                    $this->filesystem->removeFileIfExists($path, false);
                    unset($this->readCache[$jobId]);
                }
            } finally {
                $this->releaseLock($lock);
            }
            // Never unlink a per-job lock pathname during normal cleanup. A process may already
            // hold an open handle to that inode even after the job JSON becomes expired.
        }
    }

    private function path(string $jobId): string { return $this->root . '/' . $this->safeName($jobId) . '.json'; }
    private function safeName(string $value): string
    {
        if ($value === '' || str_contains($value, '..') || preg_match('/^[A-Za-z0-9._-]+$/D', $value) !== 1) { throw new \InvalidArgumentException('Unsafe job identifier.'); }
        return $value;
    }
}
