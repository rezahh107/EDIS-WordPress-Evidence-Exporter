<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

use EDIS\EvidenceExporter\Infrastructure\Support\Json\LosslessJsonArrayNode;
use EDIS\EvidenceExporter\Infrastructure\Support\Json\LosslessJsonNode;
use EDIS\EvidenceExporter\Infrastructure\Support\Json\LosslessJsonNumberNode;
use EDIS\EvidenceExporter\Infrastructure\Support\Json\LosslessJsonObjectNode;
use EDIS\EvidenceExporter\Infrastructure\Support\Json\LosslessJsonParser;

final class ArtifactStore
{
    private string $root;
    private DeterministicFilesystem $filesystem;
    /** @var array<string,array<string,array<string,mixed>>> */
    private array $artifactCache = [];
    /** @var array<string,array<string,string>> */
    private array $sha256Cache = [];

    public function __construct(?string $root = null, ?DeterministicFilesystem $filesystem = null)
    {
        $this->root = $root ?? (new PrivateStorage())->path('artifacts');
        $this->filesystem = $filesystem ?? new DeterministicFilesystem();
    }

    public function rootWritable(): bool
    {
        try { $this->filesystem->ensureDirectory($this->root); }
        catch (\Throwable) { return false; }
        return !is_link($this->root) && is_writable($this->root);
    }

    /** @param array<string,mixed> $artifact */
    public function put(string $jobId, string $componentId, array $artifact): void
    {
        $jobId = $this->safeName($jobId);
        $componentId = $this->safeName($componentId);
        $directory = $this->jobDirectory($jobId);
        $this->filesystem->ensureDirectory($directory);
        $bytes = CanonicalJson::encode($artifact);
        $this->filesystem->writeAtomically($directory . '/' . $componentId . '.json', $bytes);
        $this->artifactCache[$jobId][$componentId] = $artifact;
        $this->sha256Cache[$jobId][$componentId] = 'sha256:' . hash('sha256', $bytes);
    }

    public function fileSha256(string $jobId, string $componentId): ?string
    {
        $jobId = $this->safeName($jobId);
        $componentId = $this->safeName($componentId);
        if (isset($this->sha256Cache[$jobId][$componentId])) {
            return $this->sha256Cache[$jobId][$componentId];
        }
        $path = $this->jobDirectory($jobId) . '/' . $componentId . '.json';
        if (is_link($this->root) || is_link($this->jobDirectory($jobId)) || is_link($path) || !is_file($path)) { return null; }
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) { return null; }
        return $this->sha256Cache[$jobId][$componentId] = 'sha256:' . $hash;
    }

    public function verifyFileSha256(string $jobId, string $componentId, string $expected): bool
    {
        $jobId = $this->safeName($jobId);
        $componentId = $this->safeName($componentId);
        $path = $this->jobDirectory($jobId) . '/' . $componentId . '.json';
        if ($expected === '' || is_link($this->root) || is_link($this->jobDirectory($jobId)) || is_link($path) || !is_file($path)) {
            return false;
        }
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            return false;
        }
        $actual = 'sha256:' . $hash;
        if (!hash_equals($expected, $actual)) {
            unset($this->artifactCache[$jobId][$componentId], $this->sha256Cache[$jobId][$componentId]);
            return false;
        }
        $this->sha256Cache[$jobId][$componentId] = $actual;
        return true;
    }

    /** @return array<string,mixed>|null */
    public function get(string $jobId, string $componentId): ?array
    {
        $jobId = $this->safeName($jobId);
        $componentId = $this->safeName($componentId);
        if (isset($this->artifactCache[$jobId][$componentId])) {
            return $this->artifactCache[$jobId][$componentId];
        }
        $path = $this->jobDirectory($jobId) . '/' . $componentId . '.json';
        if (is_link($this->root) || is_link($this->jobDirectory($jobId)) || is_link($path) || !is_file($path)) { return null; }
        try {
            $node = (new LosslessJsonParser())->parse($this->filesystem->read($path));
            $decoded = $this->rehydrate($node, null);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($decoded) || array_is_list($decoded)) { return null; }
        return $this->artifactCache[$jobId][$componentId] = $decoded;
    }

    /** @param list<string> $componentIds @return array<string,array<string,mixed>> */
    public function all(string $jobId, array $componentIds): array
    {
        $result = [];
        foreach ($componentIds as $componentId) {
            $artifact = $this->get($jobId, $componentId);
            if (is_array($artifact)) { $result[$componentId] = $artifact; }
        }
        uksort($result, [CanonicalJson::class, 'compareObjectKeys']);
        return $result;
    }

    public function removeJob(string $jobId): void
    {
        $jobId = $this->safeName($jobId);
        $directory = $this->jobDirectory($jobId);
        unset($this->artifactCache[$jobId], $this->sha256Cache[$jobId]);
        if (is_link($this->root) || is_link($directory) || !is_dir($directory)) { return; }
        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') { continue; }
            $path = $directory . '/' . $item;
            if (is_file($path) && !is_link($path)) { $this->filesystem->removeFileIfExists($path, false); }
        }
        $this->filesystem->removeDirectoryIfEmpty($directory, false);
    }


    private function rehydrate(LosslessJsonNode $node, ?string $propertyName): mixed
    {
        if ($propertyName === 'original_saved_document') {
            return $node;
        }
        if ($node instanceof LosslessJsonArrayNode) {
            $items = [];
            foreach ($node->items() as $item) { $items[] = $this->rehydrate($item, null); }
            return $items;
        }
        if ($node instanceof LosslessJsonObjectNode) {
            $members = $node->members();
            if ($members === []) { return new \stdClass(); }
            foreach ($members as [$key]) {
                if (preg_match('/^(?:0|[1-9][0-9]*)$/D', $key) === 1) { return $node; }
            }
            $object = [];
            foreach ($members as [$key, $value]) { $object[$key] = $this->rehydrate($value, $key); }
            return $object;
        }
        if ($node instanceof LosslessJsonNumberNode) { return $node->toProcessingValue(); }
        return $node->toProcessingValue();
    }

    private function jobDirectory(string $jobId): string { return $this->root . '/' . $this->safeName($jobId); }
    private function safeName(string $value): string
    {
        if ($value === '' || str_contains($value, '..') || preg_match('/^[A-Za-z0-9._-]+$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Unsafe artifact identifier.');
        }
        return $value;
    }
}
