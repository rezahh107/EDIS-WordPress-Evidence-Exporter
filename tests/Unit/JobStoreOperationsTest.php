<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\JobStore;
use PHPUnit\Framework\TestCase;

final class JobStoreOperationsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/edis-job-store-operations-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testUserQueriesRunnableOrderingAndRemovalAreDeterministic(): void
    {
        $store = new JobStore($this->root);
        $now = time();
        $first = $store->create([
            'job_id' => 'job-b',
            'owner_id' => 17,
            'status' => 'queued',
            'created_at' => $now - 20,
            'expires_at' => $now + 3600,
        ]);
        $second = $store->create([
            'job_id' => 'job-a',
            'owner_id' => 17,
            'status' => 'queued',
            'created_at' => $now - 10,
            'expires_at' => $now + 3600,
        ]);
        $store->create([
            'job_id' => 'job-c',
            'owner_id' => 18,
            'status' => 'completed',
            'created_at' => $now,
            'expires_at' => $now + 3600,
        ]);

        self::assertSame('job-b', $first['job_id']);
        self::assertSame('job-a', $second['job_id']);
        self::assertSame(['job-b', 'job-a'], array_column($store->jobsForUser(17), 'job_id'));
        self::assertSame(['job-a', 'job-b'], $store->runnableJobIds(10));

        $store->remove('job-a');
        self::assertNull($store->get('job-a'));
        self::assertSame(['job-b'], $store->runnableJobIds(10));
    }


    public function testReadCacheDoesNotHideSameSizeSameMtimeExternalRewrite(): void
    {
        $store = new JobStore($this->root);
        $store->create([
            'job_id' => 'same-signature-rewrite',
            'owner_id' => 17,
            'status' => 'queued',
            'created_at' => time(),
            'expires_at' => time() + 3600,
        ]);
        self::assertSame('queued', $store->get('same-signature-rewrite')['status']);

        $path = $this->root . '/same-signature-rewrite.json';
        $originalMtime = filemtime($path);
        $originalBytes = (string) file_get_contents($path);
        $rewrittenBytes = str_replace('"queued"', '"failed"', $originalBytes, $count);
        self::assertSame(1, $count);
        self::assertSame(strlen($originalBytes), strlen($rewrittenBytes));
        file_put_contents($path, $rewrittenBytes);
        if (is_int($originalMtime)) {
            touch($path, $originalMtime);
        }
        clearstatcache(true, $path);
        self::assertSame(strlen($originalBytes), filesize($path));
        self::assertSame('failed', $store->get('same-signature-rewrite')['status']);
    }

    public function testRecoveryBatchRepairsAndReturnsRunnableJobsInOnePass(): void
    {
        $store = new JobStore($this->root);
        $now = time();
        $store->create([
            'job_id' => 'job-stale-batch',
            'owner_id' => 17,
            'status' => 'running',
            'created_at' => $now - 600,
            'updated_at' => $now - 600,
            'last_heartbeat' => $now - 600,
            'stale_after' => 30,
            'expires_at' => $now + 3600,
            'diagnostics' => [],
            'lease_owner' => 'old-worker',
            'lease_acquired_at' => $now - 600,
            'lease_expires_at' => $now - 500,
        ]);
        $store->create([
            'job_id' => 'job-ready-batch',
            'owner_id' => 17,
            'status' => 'queued',
            'created_at' => $now,
            'expires_at' => $now + 3600,
        ]);

        $result = $store->recoveryBatch(10);

        self::assertSame(['job-stale-batch'], $result['repaired']);
        self::assertSame(['job-ready-batch', 'job-stale-batch'], $result['runnable']);
        self::assertSame('queued', $store->get('job-stale-batch')['status']);
    }

    public function testStaleJobRepairIsDryRunUnlessExplicitlyApplied(): void
    {
        $store = new JobStore($this->root);
        $now = time();
        $store->create([
            'job_id' => 'job-stale',
            'owner_id' => 17,
            'status' => 'running',
            'phase' => 'collecting',
            'created_at' => $now - 600,
            'updated_at' => $now - 600,
            'last_heartbeat' => $now - 600,
            'stale_after' => 30,
            'expires_at' => $now + 3600,
            'diagnostics' => [],
            'lease_owner' => 'old-worker',
            'lease_acquired_at' => $now - 600,
            'lease_expires_at' => $now - 500,
        ]);

        $dry = $store->repairStaleJobs(false);
        self::assertSame(['job-stale'], $dry['candidates']);
        self::assertSame([], $dry['repaired']);
        self::assertSame('running', $store->get('job-stale')['status']);

        $applied = $store->repairStaleJobs(true);
        self::assertSame(['job-stale'], $applied['repaired']);
        $job = $store->get('job-stale');
        self::assertSame('queued', $job['status']);
        self::assertNull($job['lease_owner']);
        self::assertSame('REPAIRED_EXPIRED_LEASE', $job['schedule_state']);
    }

    public function testRemoveRetainsStableLockSentinel(): void
    {
        $store = new JobStore($this->root);
        $store->create([
            'job_id' => 'removed-job',
            'owner_id' => 17,
            'status' => 'queued',
            'expires_at' => time() + 3600,
        ]);

        $store->remove('removed-job');

        self::assertNull($store->get('removed-job'));
        self::assertFileExists($this->root . '/removed-job.lock');
    }

    public function testCleanupCannotReplaceHeldLockIdentity(): void
    {
        $store = new JobStore($this->root);
        $store->create([
            'job_id' => 'expired-job',
            'owner_id' => 17,
            'status' => 'running',
            'created_at' => time() - 60,
            'expires_at' => time() - 1,
        ]);
        $first = $store->acquireLock('expired-job', 0);
        self::assertTrue(is_resource($first));
        try {
            $store->cleanupExpired();
            self::assertIsArray($store->get('expired-job'));
            $second = $store->acquireLock('expired-job', 0);
            self::assertFalse(is_resource($second));
            $store->releaseLock($second);
        } finally {
            $store->releaseLock($first);
        }
        $store->cleanupExpired();
        self::assertNull($store->get('expired-job'));
        self::assertFileExists($this->root . '/expired-job.lock');
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . '/' . $entry);
            }
        }
        rmdir($path);
    }

    public function testQueuedJobWithoutLeaseIsRunnableAndNotStale(): void
    {
        $root = sys_get_temp_dir() . '/edis-job-store-' . bin2hex(random_bytes(4));
        $store = new JobStore($root);
        $now = time();
        $job = $store->create([
            'job_id' => 'queued-no-lease',
            'owner_id' => 7,
            'status' => 'queued',
            'created_at' => $now - 600,
            'updated_at' => $now - 600,
            'last_heartbeat' => $now - 600,
            'stale_after' => 30,
            'expires_at' => $now + 600,
            'lease_owner' => null,
            'lease_expires_at' => null,
        ]);
        self::assertContains('queued-no-lease', $store->runnableJobIds());
        self::assertCount(0, $store->staleJobs());
        $store->remove('queued-no-lease');
        $this->remove($root);
    }

    public function testActiveLeasePreventsSchedulingAndExpiredRunningLeaseIsRepairable(): void
    {
        $root = sys_get_temp_dir() . '/edis-job-store-' . bin2hex(random_bytes(4));
        $store = new JobStore($root);
        $now = time();
        $job = $store->create([
            'job_id' => 'leased-job',
            'owner_id' => 7,
            'status' => 'queued',
            'created_at' => $now,
            'updated_at' => $now,
            'last_heartbeat' => $now,
            'stale_after' => 30,
            'expires_at' => $now + 600,
            'lease_owner' => 'worker-a',
            'lease_expires_at' => $now + 120,
        ]);
        self::assertCount(0, $store->runnableJobIds());
        self::assertCount(0, $store->staleJobs());
        $job['status'] = 'running';
        $job['lease_expires_at'] = $now - 1;
        $store->save($job);
        self::assertCount(1, $store->staleJobs());
        $result = $store->repairStaleJobs(true);
        self::assertContains('leased-job', $result['repaired']);
        $repaired = $store->get('leased-job');
        self::assertSame('queued', $repaired['status']);
        self::assertSame('REPAIRED_EXPIRED_LEASE', $repaired['schedule_state']);
        $store->remove('leased-job');
        $this->remove($root);
    }


    public function testDocumentQueryIsSiteLocalAndDeterministicallyOrdered(): void
    {
        $root = sys_get_temp_dir() . '/edis-job-store-' . bin2hex(random_bytes(4));
        $store = new JobStore($root);
        $now = time();
        foreach ([['doc-b', $now + 1, [44]], ['doc-a', $now, [44, 45]], ['doc-c', $now + 2, [99]]] as [$id, $created, $documents]) {
            $store->create([
                'job_id' => $id,
                'owner_id' => 7,
                'status' => 'queued',
                'created_at' => $created,
                'updated_at' => $created,
                'expires_at' => $now + 600,
                'config' => ['document_ids' => $documents],
            ]);
        }
        $jobs = $store->jobsForDocument(44);
        self::assertCount(2, $jobs);
        self::assertSame('doc-a', $jobs[0]['job_id']);
        self::assertSame('doc-b', $jobs[1]['job_id']);
        foreach (['doc-a', 'doc-b', 'doc-c'] as $id) { $store->remove($id); }
        $this->remove($root);
    }

}
