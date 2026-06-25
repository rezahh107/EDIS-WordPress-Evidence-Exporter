<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

use EDIS\EvidenceExporter\Admin\Settings\SettingsRepository;

final class ExportFileStore
{
    private string $root;
    private DeterministicFilesystem $filesystem;
    private DeterministicZipWriter $zipWriter;
    private DeterministicZipReader $zipReader;

    public function __construct(
        private readonly SettingsRepository $settings,
        ?string $root = null,
        ?DeterministicFilesystem $filesystem = null,
        ?DeterministicZipWriter $zipWriter = null,
        ?DeterministicZipReader $zipReader = null,
    ) {
        $this->root = $root ?? (new PrivateStorage())->path('bundles');
        $this->filesystem = $filesystem ?? new DeterministicFilesystem();
        $this->zipWriter = $zipWriter ?? new DeterministicZipWriter();
        $this->zipReader = $zipReader ?? new DeterministicZipReader();
    }

    public function rootWritable(): bool
    {
        try { $this->filesystem->ensureDirectory($this->root); }
        catch (\Throwable) { return false; }
        return is_writable($this->root) && !is_link($this->root);
    }

    public function zipBackendAvailable(): bool { return function_exists('pack') && in_array('crc32b', hash_algos(), true); }

    public function readStoredEntry(string $archivePath, string $entryName): ?string
    {
        return $this->zipReader->readStoredEntry($archivePath, $entryName);
    }

    /** @param array<string,string> $files @return array{path:string,sha256:string,size:int,token:string,expires_at:int} */
    public function createBundle(string $jobId, array $files, int $expiresAt): array
    {
        if (!$this->zipBackendAvailable()) { throw new \RuntimeException('Deterministic ZIP backend is unavailable.'); }
        if (!$this->rootWritable()) { throw new \RuntimeException('Bundle directory is not writable.'); }
        $path = $this->bundlePath($jobId);
        $written = $this->zipWriter->writeToFile($path, $files, $this->filesystem);
        $token = bin2hex(random_bytes(24));
        $metadata = [
            'path' => $path,
            'sha256' => $written['sha256'],
            'size' => $written['size'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'zip_profile' => 'EDIS-ZIP-1',
            'compression_method' => 'STORE',
        ];
        try { $this->filesystem->writeAtomically($this->metadataPath($jobId), CanonicalJson::encode($metadata)); }
        catch (\Throwable $exception) { $this->filesystem->removeFileIfExists($path, false); throw $exception; }
        return ['path' => $path, 'sha256' => $metadata['sha256'], 'size' => $metadata['size'], 'token' => $token, 'expires_at' => $expiresAt];
    }

    /** @return array{path:string,sha256:string,size:int,token_hash:string,expires_at:int,zip_profile?:string,compression_method?:string}|null */
    public function metadata(string $jobId): ?array
    {
        $path = $this->metadataPath($jobId);
        if (!is_file($path) || is_link($path)) { return null; }
        try { $decoded = json_decode($this->filesystem->read($path), true, 512, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { return null; }
        if (!is_array($decoded) || (string) ($decoded['path'] ?? '') !== $this->bundlePath($jobId)) { return null; }
        return $decoded;
    }

    public function authorize(string $jobId, string $token): ?string
    {
        $metadata = $this->metadata($jobId);
        if (!is_array($metadata) || (int) ($metadata['expires_at'] ?? 0) < time()) { return null; }
        if (!hash_equals((string) ($metadata['token_hash'] ?? ''), hash('sha256', $token))) { return null; }
        if (($metadata['zip_profile'] ?? null) !== 'EDIS-ZIP-1' || ($metadata['compression_method'] ?? null) !== 'STORE') { return null; }
        $expected = $this->bundlePath($jobId);
        $real = realpath($expected); $root = realpath($this->root);
        if ($real === false || $root === false || $real !== $expected || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real) || is_link($real)) { return null; }
        if ((int) ($metadata['size'] ?? -1) !== (int) filesize($real)) { return null; }
        $hash = hash_file('sha256', $real);
        if (!is_string($hash) || !hash_equals((string) ($metadata['sha256'] ?? ''), 'sha256:' . $hash)) { return null; }
        return $real;
    }

    public function remove(string $jobId): void
    {
        $this->filesystem->removeFileIfExists($this->bundlePath($jobId), false);
        $this->filesystem->removeFileIfExists($this->metadataPath($jobId), false);
    }

    public function cleanupExpired(): void
    {
        if (!$this->settings->cleanupEnabled() || !is_dir($this->root) || is_link($this->root)) { return; }
        foreach (glob($this->root . '/edis-source-evidence-*.zip.json') ?: [] as $metadataPath) {
            if (is_link($metadataPath)) { continue; }
            $name = basename($metadataPath); $prefix = 'edis-source-evidence-'; $suffix = '.zip.json';
            if (!str_starts_with($name, $prefix) || !str_ends_with($name, $suffix)) { continue; }
            $jobId = substr($name, strlen($prefix), -strlen($suffix));
            try {
                $metadata = $this->metadata($jobId);
                if (is_array($metadata) && (int) ($metadata['expires_at'] ?? PHP_INT_MAX) < time()) {
                    $this->filesystem->removeFileIfExists($this->bundlePath($jobId), false);
                    $this->filesystem->removeFileIfExists($this->metadataPath($jobId), false);
                }
            } catch (\InvalidArgumentException) {}
        }
    }

    private function bundlePath(string $jobId): string { return $this->root . '/edis-source-evidence-' . $this->safeName($jobId) . '.zip'; }
    private function metadataPath(string $jobId): string { return $this->bundlePath($jobId) . '.json'; }
    private function safeName(string $value): string
    {
        if ($value === '' || str_contains($value, '..') || preg_match('/^[A-Za-z0-9._-]+$/D', $value) !== 1) { throw new \InvalidArgumentException('Unsafe bundle identifier.'); }
        return $value;
    }
}
