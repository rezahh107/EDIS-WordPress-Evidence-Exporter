<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

final class InputSnapshotStore
{
    private const FORMAT_VERSION = '2.0.0';

    private string $root;
    private DeterministicFilesystem $filesystem;
    /** @var array<string,array<string,mixed>|null> */
    private array $manifestCache = [];
    /** @var array<string,array<int,array<string,mixed>|null>> */
    private array $documentCache = [];
    /** @var array<string,string> */
    private array $manifestCacheSignature = [];

    /** @var \Closure(int):array<string,mixed>|null */
    private \Closure $documentReader;

    public function __construct(?string $root = null, ?callable $documentReader = null, ?DeterministicFilesystem $filesystem = null)
    {
        $this->root = $root ?? (new PrivateStorage())->path('inputs');
        $this->filesystem = $filesystem ?? new DeterministicFilesystem();
        $this->documentReader = $documentReader !== null
            ? \Closure::fromCallable($documentReader)
            : static function (int $documentId): ?array {
                if (!function_exists('get_post_meta')) {
                    return null;
                }
                $rawValue = get_post_meta($documentId, '_elementor_data', true);
                if (is_string($rawValue)) {
                    $raw = $rawValue;
                    $representation = 'WORDPRESS_META_STRING';
                } elseif (is_array($rawValue) || $rawValue instanceof \stdClass) {
                    $raw = CanonicalJson::encode($rawValue);
                    $representation = 'WORDPRESS_META_DECODED_VALUE_REENCODED_EDIS_CJ_2';
                } else {
                    $raw = '';
                    $representation = 'UNAVAILABLE';
                }
                if ($raw === '') {
                    return null;
                }

                $post = function_exists('get_post') ? get_post($documentId) : null;
                $templateType = (string) (get_post_meta($documentId, '_elementor_template_type', true)
                    ?: (is_object($post) ? (string) ($post->post_type ?? 'unknown') : 'unknown'));
                $pageSettings = get_post_meta($documentId, '_elementor_page_settings', true);
                if (!is_array($pageSettings) && !$pageSettings instanceof \stdClass) {
                    $pageSettings = [];
                }

                return [
                    'raw_source' => $raw,
                    'raw_source_representation' => $representation,
                    'document_type' => $templateType,
                    'post_type' => is_object($post) ? (string) ($post->post_type ?? '') : null,
                    'post_status' => is_object($post) ? (string) ($post->post_status ?? '') : null,
                    'post_modified_gmt' => is_object($post) ? (string) ($post->post_modified_gmt ?? '') : null,
                    'page_settings' => $pageSettings,
                    'elementor_edit_mode' => (string) get_post_meta($documentId, '_elementor_edit_mode', true),
                    'elementor_version' => (string) get_post_meta($documentId, '_elementor_version', true),
                ];
            };
    }

    public function rootWritable(): bool
    {
        try {
            $this->filesystem->ensureDirectory($this->root);
        } catch (\Throwable) {
            return false;
        }
        return !is_link($this->root) && is_writable($this->root);
    }

    /**
     * @param list<int> $documentIds
     * @return array<string,mixed>
     */
    public function capture(string $snapshotId, array $documentIds, int $expiresAt): array
    {
        $snapshotId = $this->safeName($snapshotId);
        if (!$this->rootWritable()) {
            throw new ExportIntegrityException('EDIS_INPUT_SNAPSHOT_STORAGE_UNAVAILABLE', 'Input snapshot storage is unavailable.');
        }
        $ids = array_values(array_unique(array_filter($documentIds, static fn (mixed $id): bool => is_int($id) && $id > 0)));
        sort($ids, SORT_NUMERIC);

        $finalDirectory = $this->snapshotDirectory($snapshotId);
        if (is_link($finalDirectory) || file_exists($finalDirectory)) {
            throw new ExportIntegrityException('EDIS_INPUT_SNAPSHOT_ALREADY_EXISTS', 'An input snapshot already exists for this job.');
        }
        $temporaryDirectory = $this->root . '/.tmp-' . $snapshotId . '-' . bin2hex(random_bytes(6));
        if (!$this->ensureDirectory($temporaryDirectory . '/documents')) {
            throw new ExportIntegrityException('EDIS_INPUT_SNAPSHOT_STORAGE_UNAVAILABLE', 'Unable to create the temporary input snapshot directory.');
        }

        try {
            $records = [];
            foreach ($ids as $documentId) {
                $first = ($this->documentReader)($documentId);
                if (!is_array($first) || !is_string($first['raw_source'] ?? null) || $first['raw_source'] === '') {
                    throw new ExportIntegrityException('EDIS_INPUT_SNAPSHOT_SOURCE_MISSING', 'Saved Elementor source is unavailable for document ' . $documentId . '.');
                }
                $record = $this->writeDocument($temporaryDirectory, $documentId, $first);
                $second = ($this->documentReader)($documentId);
                if (!is_array($second) || !hash_equals($record['capture_record_sha256'], $this->captureRecordHash($documentId, $second))) {
                    throw new ExportIntegrityException('EDIS_SOURCE_CHANGED_DURING_SNAPSHOT', 'Saved Elementor source changed while the immutable input snapshot was being captured.');
                }
                $records[(string) $documentId] = $record;
            }
            ksort($records, SORT_STRING);

            $capturedAt = gmdate('Y-m-d\TH:i:s\Z');
            $manifest = [
                'snapshot_format_version' => self::FORMAT_VERSION,
                'snapshot_id' => $snapshotId,
                'captured_at' => $capturedAt,
                'expires_at' => $expiresAt,
                'document_ids' => array_map('strval', $ids),
                'documents' => $records === [] ? (object) [] : $records,
            ];
            $manifest['snapshot_sha256'] = $this->semanticManifestHash($manifest);
            $this->atomicWrite($temporaryDirectory . '/input-manifest.json', CanonicalJson::encode($manifest));
            try {
                $this->filesystem->rename($temporaryDirectory, $finalDirectory);
                $this->filesystem->synchronizeDirectory(dirname($finalDirectory));
                $this->filesystem->setPermissions($finalDirectory, 0750, true);
                unset($this->manifestCache[$snapshotId], $this->documentCache[$snapshotId]);
                $this->manifestCacheSignature = [];
            } catch (\Throwable $exception) {
                if (is_dir($finalDirectory) && !is_link($finalDirectory)) {
                    try {
                        $this->remove($snapshotId);
                    } catch (\Throwable) {
                        // Preserve the original commit failure; cleanup remains best-effort here.
                    }
                }
                throw new ExportIntegrityException('EDIS_INPUT_SNAPSHOT_COMMIT_FAILED', 'Unable to atomically commit the immutable input snapshot.', $exception);
            }

            if (!$this->verify($snapshotId, (string) $manifest['snapshot_sha256'])) {
                $this->remove($snapshotId);
                throw new ExportIntegrityException('EDIS_INPUT_SNAPSHOT_INTEGRITY_FAILED', 'The committed input snapshot failed integrity verification.');
            }
            return $manifest;
        } catch (\Throwable $exception) {
            $this->removeDirectory($temporaryDirectory);
            if ($exception instanceof ExportIntegrityException) {
                throw $exception;
            }
            throw new ExportIntegrityException('EDIS_INPUT_SNAPSHOT_CAPTURE_FAILED', 'Unable to capture the immutable input snapshot.', $exception);
        }
    }

    /** @return array<string,mixed>|null */
    public function manifest(string $snapshotId): ?array
    {
        $snapshotId = $this->safeName($snapshotId);
        $directory = $this->snapshotDirectory($snapshotId);
        $path = $directory . '/input-manifest.json';
        if (is_link($this->root) || is_link($directory) || is_link($path) || !is_file($path)) {
            unset($this->manifestCache[$snapshotId], $this->manifestCacheSignature[$snapshotId]);
            return null;
        }
        clearstatcache(true, $path);
        $signature = $this->fileSignature($path);
        if ($signature !== null
            && ($this->manifestCacheSignature[$snapshotId] ?? null) === $signature
            && array_key_exists($snapshotId, $this->manifestCache)) {
            return $this->manifestCache[$snapshotId];
        }
        try {
            $raw = $this->filesystem->read($path);
            $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            unset($this->manifestCache[$snapshotId], $this->manifestCacheSignature[$snapshotId]);
            return null;
        }
        $value = is_array($manifest) ? $manifest : null;
        $this->manifestCache[$snapshotId] = $value;
        $this->manifestCacheSignature[$snapshotId] = $signature ?? '';
        return $value;
    }

    /** @return array<string,mixed>|null */
    public function document(string $snapshotId, int $documentId, bool $forceVerify = false): ?array
    {
        $snapshotId = $this->safeName($snapshotId);
        if ($forceVerify) {
            unset($this->documentCache[$snapshotId][$documentId]);
        } elseif (array_key_exists($documentId, $this->documentCache[$snapshotId] ?? [])) {
            return $this->documentCache[$snapshotId][$documentId];
        }
        $manifest = $this->manifest($snapshotId);
        if (!is_array($manifest) || !$this->manifestContractMatches($snapshotId, $manifest)) {
            return null;
        }
        $records = is_array($manifest['documents'] ?? null) ? $manifest['documents'] : [];
        $record = is_array($records[(string) $documentId] ?? null) ? $records[(string) $documentId] : null;
        if (!is_array($record)) {
            return null;
        }
        $directory = $this->snapshotDirectory($snapshotId);
        try {
            $sourcePath = $directory . '/' . $this->relativePath((string) ($record['source_path'] ?? ''));
            $metadataPath = $directory . '/' . $this->relativePath((string) ($record['metadata_path'] ?? ''));
        } catch (\InvalidArgumentException) {
            return null;
        }
        if (is_link($sourcePath) || is_link($metadataPath) || !is_file($sourcePath) || !is_file($metadataPath)) {
            return null;
        }
        try {
            $raw = $this->filesystem->read($sourcePath);
            $metadataBytes = $this->filesystem->read($metadataPath);
        } catch (\Throwable) {
            return null;
        }
        if (!hash_equals((string) ($record['raw_storage_bytes_sha256'] ?? ''), 'sha256:' . hash('sha256', $raw))) {
            return null;
        }
        if (!hash_equals((string) ($record['metadata_file_sha256'] ?? ''), 'sha256:' . hash('sha256', $metadataBytes))) {
            return null;
        }
        try {
            $metadata = json_decode($metadataBytes, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($metadata)) {
            return null;
        }
        $hashes = DocumentIdentity::sourceHashes($raw);
        if (!is_string($hashes['canonical_saved_source_sha256'])
            || !hash_equals((string) ($record['canonical_saved_source_sha256'] ?? ''), $hashes['canonical_saved_source_sha256'])) {
            return null;
        }
        $document = $metadata + [
            'raw_source' => $raw,
            'snapshot_id' => $snapshotId,
            'snapshot_sha256' => (string) ($manifest['snapshot_sha256'] ?? ''),
            'snapshot_captured_at' => (string) ($manifest['captured_at'] ?? ''),
        ];
        $this->documentCache[$snapshotId][$documentId] = $document;
        return $document;
    }

    public function verify(string $snapshotId, string $expectedSnapshotSha256): bool
    {
        $snapshotId = $this->safeName($snapshotId);
        $manifest = $this->manifest($snapshotId);
        if (!is_array($manifest)
            || !hash_equals($expectedSnapshotSha256, (string) ($manifest['snapshot_sha256'] ?? ''))
            || !$this->manifestContractMatches($snapshotId, $manifest)) {
            return false;
        }
        $records = is_array($manifest['documents'] ?? null) ? $manifest['documents'] : [];
        $manifestDocumentIds = is_array($manifest['document_ids'] ?? null) ? $manifest['document_ids'] : [];
        $normalizedManifestIds = [];
        foreach ($manifestDocumentIds as $documentId) {
            if (!is_string($documentId) || !ctype_digit($documentId) || (int) $documentId <= 0) {
                return false;
            }
            $normalizedManifestIds[] = $documentId;
        }
        $recordIds = array_map('strval', array_keys($records));
        sort($normalizedManifestIds, SORT_STRING);
        sort($recordIds, SORT_STRING);
        if ($normalizedManifestIds !== $recordIds || count($normalizedManifestIds) !== count(array_unique($normalizedManifestIds))) {
            return false;
        }
        foreach ($records as $documentId => $record) {
            if (!is_array($record) || (string) ($record['document_id'] ?? '') !== (string) $documentId) {
                return false;
            }
            if (!ctype_digit((string) $documentId) || $this->document($snapshotId, (int) $documentId, true) === null) {
                return false;
            }
        }
        return true;
    }

    public function remove(string $snapshotId): void
    {
        $snapshotId = $this->safeName($snapshotId);
        unset($this->manifestCache[$snapshotId], $this->documentCache[$snapshotId]);
        $this->manifestCacheSignature = [];
        $this->removeDirectory($this->snapshotDirectory($snapshotId));
    }

    public function cleanupExpired(): void
    {
        if (is_link($this->root) || !is_dir($this->root)) {
            return;
        }
        foreach (scandir($this->root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.tmp-')) {
                continue;
            }
            $directory = $this->root . '/' . $entry;
            if (!is_dir($directory) || is_link($directory)) {
                continue;
            }
            $manifest = $this->manifest($entry);
            if (!is_array($manifest) || (int) ($manifest['expires_at'] ?? 0) < time()) {
                $this->remove($entry);
            }
        }
        foreach (glob($this->root . '/.tmp-*') ?: [] as $temporary) {
            if (is_dir($temporary) && !is_link($temporary) && (int) (filemtime($temporary) ?: 0) + 3600 < time()) {
                $this->removeDirectory($temporary);
            }
        }
    }

    /** @param array<string,mixed> $captured @return array<string,mixed> */
    private function writeDocument(string $temporaryDirectory, int $documentId, array $captured): array
    {
        $raw = (string) $captured['raw_source'];
        $hashes = DocumentIdentity::sourceHashes($raw);
        if (!is_string($hashes['canonical_saved_source_sha256'])) {
            $diagnosticCode = is_string($hashes['json_validation_error_code'] ?? null)
                ? $hashes['json_validation_error_code']
                : 'EDIS_INPUT_SNAPSHOT_SOURCE_INVALID';
            throw new ExportIntegrityException($diagnosticCode, 'Saved Elementor source is not valid lossless JSON for document ' . $documentId . '.');
        }
        $metadata = $captured;
        unset($metadata['raw_source']);
        $metadata['document_id'] = (string) $documentId;
        $metadataBytes = CanonicalJson::encode($metadata);
        $sourceRelative = 'documents/' . $documentId . '.source.json';
        $metadataRelative = 'documents/' . $documentId . '.metadata.json';
        $this->atomicWrite($temporaryDirectory . '/' . $sourceRelative, $raw);
        $this->atomicWrite($temporaryDirectory . '/' . $metadataRelative, $metadataBytes);

        return [
            'document_id' => (string) $documentId,
            'source_path' => $sourceRelative,
            'metadata_path' => $metadataRelative,
            'raw_storage_bytes_sha256' => $hashes['raw_storage_bytes_sha256'],
            'canonical_saved_source_sha256' => $hashes['canonical_saved_source_sha256'],
            'canonicalization_profile' => CanonicalJson::PROFILE,
            'duplicate_json_keys_rejected' => true,
            'json_object_array_shape_preserved' => true,
            'metadata_file_sha256' => 'sha256:' . hash('sha256', $metadataBytes),
            'capture_record_sha256' => $this->captureRecordHashFromParts(
                $documentId,
                (string) $hashes['raw_storage_bytes_sha256'],
                'sha256:' . hash('sha256', $metadataBytes),
            ),
        ];
    }

    /** @param array<string,mixed> $captured */
    private function captureRecordHash(int $documentId, array $captured): string
    {
        $raw = is_string($captured['raw_source'] ?? null) ? $captured['raw_source'] : '';
        $metadata = $captured;
        unset($metadata['raw_source']);
        $metadata['document_id'] = (string) $documentId;
        return $this->captureRecordHashFromParts(
            $documentId,
            'sha256:' . hash('sha256', $raw),
            'sha256:' . hash('sha256', CanonicalJson::encode($metadata)),
        );
    }

    private function captureRecordHashFromParts(int $documentId, string $rawSha256, string $metadataSha256): string
    {
        return 'sha256:' . hash('sha256', CanonicalJson::encode([
            'document_id' => (string) $documentId,
            'raw_storage_bytes_sha256' => $rawSha256,
            'metadata_file_sha256' => $metadataSha256,
        ]));
    }

    /** @param array<string,mixed> $manifest */
    private function semanticManifestHash(array $manifest): string
    {
        unset($manifest['snapshot_sha256'], $manifest['captured_at'], $manifest['expires_at']);
        if (array_key_exists('documents', $manifest) && is_array($manifest['documents']) && $manifest['documents'] === []) {
            $manifest['documents'] = (object) [];
        }
        return 'sha256:' . hash('sha256', CanonicalJson::encode($manifest));
    }

    /** @param array<string,mixed> $manifest */
    private function manifestContractMatches(string $snapshotId, array $manifest): bool
    {
        return ($manifest['snapshot_format_version'] ?? null) === self::FORMAT_VERSION
            && ($manifest['snapshot_id'] ?? null) === $this->safeName($snapshotId)
            && $this->manifestHashMatches($manifest);
    }

    /** @param array<string,mixed> $manifest */
    private function manifestHashMatches(array $manifest): bool
    {
        $stored = (string) ($manifest['snapshot_sha256'] ?? '');
        return $stored !== '' && hash_equals($stored, $this->semanticManifestHash($manifest));
    }

    private function fileSignature(string $path): ?string
    {
        $stat = stat($path);
        if (!is_array($stat)) {
            return null;
        }
        return implode(':', [
            (string) ($stat['dev'] ?? 0),
            (string) ($stat['ino'] ?? 0),
            (string) ($stat['size'] ?? 0),
            (string) ($stat['mtime'] ?? 0),
            (string) ($stat['ctime'] ?? 0),
        ]);
    }

    private function snapshotDirectory(string $snapshotId): string
    {
        return $this->root . '/' . $this->safeName($snapshotId);
    }

    private function relativePath(string $path): string
    {
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '\\')) {
            throw new \InvalidArgumentException('Unsafe input snapshot path.');
        }
        return $path;
    }

    private function safeName(string $value): string
    {
        if ($value === '' || str_contains($value, '..')) {
            throw new \InvalidArgumentException('Unsafe input snapshot identifier.');
        }
        $length = strlen($value);
        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];
            $code = ord($character);
            $allowed = ($code >= 48 && $code <= 57)
                || ($code >= 65 && $code <= 90)
                || ($code >= 97 && $code <= 122)
                || $character === '_'
                || $character === '-';
            if (!$allowed) {
                throw new \InvalidArgumentException('Unsafe input snapshot identifier.');
            }
        }
        return $value;
    }

    private function ensureDirectory(string $directory): bool
    {
        if (is_link($this->root) || is_link($directory)) {
            return false;
        }
        try {
            $this->filesystem->ensureDirectory($directory);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function atomicWrite(string $path, string $contents): void
    {
        $this->filesystem->writeAtomically($path, $contents);
    }

    private function removeDirectory(string $directory): void
    {
        if (is_link($directory) || !is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } elseif (!is_link($path)) {
                $this->filesystem->removeFileIfExists($path, false);
            }
        }
        $this->filesystem->removeDirectoryIfEmpty($directory, false);
    }
}
