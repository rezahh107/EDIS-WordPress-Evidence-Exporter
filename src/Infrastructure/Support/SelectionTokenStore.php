<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

final class SelectionTokenStore
{
    private DeterministicFilesystem $filesystem;

    public function __construct(private readonly string $root, private readonly int $ttlSeconds = 600, ?DeterministicFilesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new DeterministicFilesystem();
    }

    /** @param list<array<string,mixed>> $selection @return array{token:string,expires_at:int} */
    public function issue(int $ownerId, int $documentId, array $selection, string $unsavedState): array
    {
        if ($ownerId <= 0 || $documentId <= 0) { throw new \InvalidArgumentException('Selection owner and document are required.'); }
        $this->filesystem->ensureDirectory($this->root);
        $token = bin2hex(random_bytes(32));
        $expiresAt = time() + max(60, min(1800, $this->ttlSeconds));
        $payload = [
            'owner_id' => $ownerId,
            'document_id' => $documentId,
            'selection' => array_values($selection),
            'editor_unsaved_changes_state' => $unsavedState,
            'created_at' => time(),
            'expires_at' => $expiresAt,
        ];
        $this->filesystem->writeAtomically($this->path($token), CanonicalJson::encode($payload));
        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /** @return array<string,mixed>|null */
    public function consume(string $token, int $ownerId): ?array
    {
        if (!$this->validToken($token) || $ownerId <= 0) { return null; }
        $path = $this->path($token);
        try { $handle = $this->filesystem->open($path, 'rb'); }
        catch (\Throwable) { return null; }
        try {
            if (!$this->filesystem->lock($handle, LOCK_EX)) { return null; }
            clearstatcache(true, $path);
            if (!is_file($path)) { return null; }
            $raw = stream_get_contents($handle);
            if (!is_string($raw)) { $this->filesystem->removeFileIfExists($path, false); return null; }
            try { $payload = json_decode($raw, true, 128, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { $this->filesystem->removeFileIfExists($path, false); return null; }
            if (!is_array($payload) || (int) ($payload['expires_at'] ?? 0) < time()) { $this->filesystem->removeFileIfExists($path, false); return null; }
            if ((int) ($payload['owner_id'] ?? 0) !== $ownerId) { return null; }
            $this->filesystem->removeFileIfExists($path);
            return $payload;
        } finally {
            try { $this->filesystem->lock($handle, LOCK_UN); } catch (\Throwable) {}
            try { $this->filesystem->close($handle); } catch (\Throwable) {}
        }
    }

    public function cleanupExpired(): void
    {
        if (is_link($this->root) || !is_dir($this->root)) { return; }
        foreach (glob(rtrim($this->root, '/\\') . '/*.json') ?: [] as $path) {
            try { $handle = $this->filesystem->open($path, 'rb'); } catch (\Throwable) { continue; }
            try {
                if (!$this->filesystem->lock($handle, LOCK_EX | LOCK_NB)) { continue; }
                $raw = stream_get_contents($handle);
                $payload = is_string($raw) ? json_decode($raw, true) : null;
                if (!is_array($payload) || (int) ($payload['expires_at'] ?? 0) < time()) { $this->filesystem->removeFileIfExists($path, false); }
            } finally {
                try { $this->filesystem->lock($handle, LOCK_UN); } catch (\Throwable) {}
                try { $this->filesystem->close($handle); } catch (\Throwable) {}
            }
        }
    }

    private function path(string $token): string { return rtrim($this->root, '/\\') . '/' . $token . '.json'; }
    private function validToken(string $token): bool { return strlen($token) === 64 && ctype_xdigit($token) && strtolower($token) === $token; }
}
