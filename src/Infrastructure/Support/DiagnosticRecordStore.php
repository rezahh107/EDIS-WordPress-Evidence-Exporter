<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

final class DiagnosticRecordStore
{
    public const MAX_BYTES = 65536;

    public function __construct(
        private readonly string $root,
        private readonly DeterministicFilesystem $filesystem = new DeterministicFilesystem(),
        private readonly int $retentionSeconds = 86400,
    ) {
    }

    public function rootWritable(): bool
    {
        try {
            $this->filesystem->ensureDirectory($this->root, 0750, true);
        } catch (\Throwable) {
            return false;
        }
        return !is_link($this->root) && is_writable($this->root);
    }

    public function retentionSeconds(): int
    {
        return max(3600, min(604800, $this->retentionSeconds));
    }

    public function expiration(?int $minimum = null): int
    {
        return max(time() + $this->retentionSeconds(), $minimum ?? 0);
    }

    /** @param array<string,mixed> $record @return array{diagnostic_id:string,bytes:string,path:string} */
    public function create(int $ownerId, array $record): array
    {
        if ($ownerId <= 0) {
            throw new \InvalidArgumentException('A diagnostic record owner is required.');
        }
        $identity = is_array($record['diagnostic_identity'] ?? null) ? $record['diagnostic_identity'] : [];
        $diagnosticId = $this->safeId((string) ($identity['diagnostic_id'] ?? ''));
        $expiresAt = strtotime((string) ($identity['expires_at'] ?? ''));
        if ($expiresAt === false || $expiresAt < time()) {
            throw new \InvalidArgumentException('A future diagnostic expiry is required.');
        }

        $directory = $this->ownerDirectory($ownerId);
        $this->filesystem->ensureDirectory($this->root, 0750, true);
        $this->filesystem->ensureDirectory($directory, 0750, true);
        $path = $directory . '/' . $diagnosticId . '.json';
        if (is_link($directory) || is_link($path) || file_exists($path)) {
            throw new \RuntimeException('The diagnostic record identifier is unavailable.');
        }

        $bytes = CanonicalJson::encode($record);
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new \RuntimeException('The diagnostic record exceeds the bounded size contract.');
        }
        $this->filesystem->writeAtomically($path, $bytes, 0640);

        return ['diagnostic_id' => $diagnosticId, 'bytes' => $bytes, 'path' => $path];
    }

    /** @return array{record:array<string,mixed>,bytes:string}|null */
    public function resolve(int $ownerId, string $diagnosticId): ?array
    {
        if ($ownerId <= 0 || !$this->validId($diagnosticId)) {
            return null;
        }
        $directory = $this->ownerDirectory($ownerId);
        $path = $directory . '/' . $diagnosticId . '.json';
        if (is_link($this->root) || is_link($directory) || is_link($path) || !is_file($path)) {
            return null;
        }
        try {
            $bytes = $this->filesystem->read($path);
            if (strlen($bytes) > self::MAX_BYTES) {
                return null;
            }
            $record = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($record)
            || (string) ($record['diagnostic_identity']['diagnostic_id'] ?? '') !== $diagnosticId
            || (string) ($record['schema']['format'] ?? '') !== 'EDIS-DIAGNOSTIC-1') {
            return null;
        }
        $expiresAt = strtotime((string) ($record['diagnostic_identity']['expires_at'] ?? ''));
        if ($expiresAt === false || $expiresAt < time()) {
            try {
                $this->filesystem->removeFileIfExists($path, false);
            } catch (\Throwable) {
            }
            return null;
        }
        return ['record' => $record, 'bytes' => $bytes];
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $ownerId, int $limit = 10): array
    {
        if ($ownerId <= 0) {
            return [];
        }
        $directory = $this->ownerDirectory($ownerId);
        if (!is_dir($directory) || is_link($directory)) {
            return [];
        }
        $rows = [];
        foreach (glob($directory . '/edis-diag-*.json') ?: [] as $path) {
            $diagnosticId = basename($path, '.json');
            $resolved = $this->resolve($ownerId, $diagnosticId);
            if (!is_array($resolved)) {
                continue;
            }
            $record = $resolved['record'];
            $rows[] = [
                'diagnostic_id' => $diagnosticId,
                'record_type' => (string) ($record['diagnostic_identity']['record_type'] ?? 'UNKNOWN'),
                'created_at' => (string) ($record['diagnostic_identity']['created_at'] ?? ''),
                'operation_type' => (string) ($record['operation_identity']['operation_type'] ?? 'UNKNOWN'),
                'terminal_state' => (string) ($record['failure_classification']['terminal_state'] ?? 'UNKNOWN'),
                'job_id' => is_string($record['operation_identity']['job_id'] ?? null) ? $record['operation_identity']['job_id'] : null,
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));
        return array_slice($rows, 0, max(1, min(25, $limit)));
    }

    public function cleanupExpired(): void
    {
        if (!is_dir($this->root) || is_link($this->root)) {
            return;
        }
        foreach (glob($this->root . '/user-*') ?: [] as $directory) {
            if (!is_dir($directory) || is_link($directory)) {
                continue;
            }
            foreach (glob($directory . '/edis-diag-*.json') ?: [] as $path) {
                if (is_link($path) || !is_file($path)) {
                    continue;
                }
                try {
                    $bytes = $this->filesystem->read($path);
                    $record = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    continue;
                }
                if (!is_array($record)) {
                    continue;
                }
                $expiresAt = strtotime((string) ($record['diagnostic_identity']['expires_at'] ?? ''));
                if ($expiresAt !== false && $expiresAt < time()) {
                    $this->filesystem->removeFileIfExists($path, false);
                }
            }
            $this->filesystem->removeDirectoryIfEmpty($directory, false);
        }
    }

    private function ownerDirectory(int $ownerId): string
    {
        return $this->root . '/user-' . max(1, $ownerId);
    }

    private function safeId(string $diagnosticId): string
    {
        if (!$this->validId($diagnosticId)) {
            throw new \InvalidArgumentException('Invalid diagnostic record identifier.');
        }
        return $diagnosticId;
    }

    private function validId(string $diagnosticId): bool
    {
        return preg_match('/\Aedis-diag-[a-f0-9]{32}\z/D', $diagnosticId) === 1;
    }
}
