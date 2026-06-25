<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

final class InstallationIntegrity
{
    /** @return array{state:string,code:string,version:string|null,failures:list<array{path:string,reason:string}>} */
    public static function verify(string $pluginRoot): array
    {
        $path = rtrim($pluginRoot, '/\\') . '/config/critical-files.json';
        if (!is_file($path) || is_link($path)) {
            return self::failure('EDIS_INSTALLATION_INTEGRITY_MANIFEST_MISSING', [['path' => 'config/critical-files.json', 'reason' => 'missing_or_symlink']]);
        }
        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new \RuntimeException('read_failed');
            }
            $manifest = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return self::failure('EDIS_INSTALLATION_INTEGRITY_MANIFEST_INVALID', [['path' => 'config/critical-files.json', 'reason' => 'invalid_json']]);
        }
        $version = is_string($manifest['plugin_version'] ?? null) ? $manifest['plugin_version'] : null;
        $files = is_array($manifest['files'] ?? null) ? $manifest['files'] : null;
        if (($manifest['format'] ?? null) !== 'EDIS-INTEGRITY-1' || $version === null || $files === null) {
            return self::failure('EDIS_INSTALLATION_INTEGRITY_MANIFEST_INVALID', [['path' => 'config/critical-files.json', 'reason' => 'invalid_contract']], $version);
        }
        if (defined('EDIS_EVIDENCE_EXPORTER_VERSION') && constant('EDIS_EVIDENCE_EXPORTER_VERSION') !== $version) {
            return self::failure('EDIS_INSTALLATION_MIXED_VERSION', [['path' => 'edis-evidence-exporter.php', 'reason' => 'version_mismatch']], $version);
        }
        $rootReal = realpath($pluginRoot);
        if (!is_string($rootReal)) {
            return self::failure('EDIS_INSTALLATION_INTEGRITY_ROOT_UNAVAILABLE', [['path' => '.', 'reason' => 'root_unavailable']], $version);
        }
        $failures = [];
        foreach ($files as $relative => $expected) {
            if (!is_string($relative) || !is_string($expected) || preg_match('/\Asha256:[0-9a-f]{64}\z/D', $expected) !== 1) {
                $failures[] = ['path' => is_string($relative) ? $relative : '(invalid)', 'reason' => 'invalid_manifest_entry'];
                continue;
            }
            $normalized = str_replace('\\', '/', $relative);
            if ($normalized === '' || str_starts_with($normalized, '/') || str_contains($normalized, '..') || str_contains($normalized, "\0")) {
                $failures[] = ['path' => $relative, 'reason' => 'unsafe_path'];
                continue;
            }
            $file = realpath($rootReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized));
            if (!is_string($file) || !self::isWithinRoot($file, $rootReal) || !is_file($file) || is_link($file)) {
                $failures[] = ['path' => $relative, 'reason' => 'missing_or_unsafe'];
                continue;
            }
            $actual = hash_file('sha256', $file);
            if (!is_string($actual) || !hash_equals($expected, 'sha256:' . $actual)) {
                $failures[] = ['path' => $relative, 'reason' => 'sha256_mismatch'];
            }
        }
        if ($failures !== []) {
            usort($failures, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));
            return self::failure('EDIS_INSTALLATION_MIXED_VERSION', $failures, $version);
        }
        return ['state' => 'PASS', 'code' => 'EDIS_INSTALLATION_INTEGRITY_PASS', 'version' => $version, 'failures' => []];
    }


    private static function isWithinRoot(string $path, string $root): bool
    {
        $pathNormalized = rtrim(str_replace('\\', '/', $path), '/');
        $rootNormalized = rtrim(str_replace('\\', '/', $root), '/');
        if (PHP_OS_FAMILY === 'Windows' || preg_match('/\A[A-Za-z]:\//', $pathNormalized) === 1 || preg_match('/\A[A-Za-z]:\//', $rootNormalized) === 1) {
            $pathNormalized = strtolower($pathNormalized);
            $rootNormalized = strtolower($rootNormalized);
        }
        return $pathNormalized !== $rootNormalized && str_starts_with($pathNormalized, $rootNormalized . '/');
    }

    /** @param list<array{path:string,reason:string}> $failures @return array{state:string,code:string,version:string|null,failures:list<array{path:string,reason:string}>} */
    private static function failure(string $code, array $failures, ?string $version = null): array
    {
        return ['state' => 'FAIL', 'code' => $code, 'version' => $version, 'failures' => $failures];
    }
}
