<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

final class PrivateStorage
{
    private const ATTESTATION_FORMAT = 'EDIS-STORAGE-ATTESTATION-1';
    private const ATTESTATION_TTL_SECONDS = 86400;
    private ?string $resolvedRoot = null;
    private DeterministicFilesystem $filesystem;
    /** @var array<string,mixed>|null */
    private ?array $cachedSelfTest = null;

    public function __construct(private readonly ?string $configuredRoot = null, ?DeterministicFilesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new DeterministicFilesystem();
    }

    public function root(): string
    {
        if ($this->resolvedRoot !== null) {
            return $this->resolvedRoot;
        }
        $candidates = $this->candidateRoots();
        if ($candidates === []) {
            throw new \RuntimeException('No EDIS private storage candidates are available.');
        }
        return $candidates[0];
    }

    /** @return list<string> */
    public function candidateRoots(): array
    {
        $explicit = $this->configuredRoot;
        if ($explicit === null && defined('EDIS_EVIDENCE_PRIVATE_STORAGE_DIR')) {
            $value = constant('EDIS_EVIDENCE_PRIVATE_STORAGE_DIR');
            if (is_string($value) && trim($value) !== '') {
                $explicit = $value;
            }
        }

        $siteKey = substr(hash('sha256', defined('ABSPATH') ? (string) ABSPATH : __FILE__), 0, 16);
        $bases = [];
        $localConvenience = $this->localConvenienceEnabled();
        if ($explicit !== null) {
            $bases[] = $explicit;
            foreach ($this->localConvenienceRoots() as $localRoot) {
                $bases[] = $localRoot;
            }
        } else {
            if ($localConvenience) {
                foreach ($this->localConvenienceRoots() as $localRoot) {
                    $bases[] = $localRoot;
                }
            }
            if (!$localConvenience && defined('ABSPATH')) {
                $webParent = dirname(rtrim((string) ABSPATH, '/\\'));
                if ($webParent !== '' && $webParent !== '.') {
                    $bases[] = rtrim($webParent, '/\\') . '/edis-private-' . $siteKey;
                }
            }
            if (!$localConvenience && defined('WP_CONTENT_DIR')) {
                $contentParent = dirname(rtrim((string) WP_CONTENT_DIR, '/\\'), 2);
                if ($contentParent !== '' && $contentParent !== '.') {
                    $bases[] = rtrim($contentParent, '/\\') . '/edis-private-' . $siteKey;
                }
            }
        }

        $result = [];
        foreach ($bases as $base) {
            if (!is_string($base)) {
                continue;
            }
            $candidate = rtrim($base, '/\\');
            if ($candidate === '' || str_contains($candidate, "\0") || !$this->isAbsolutePath($candidate) || $this->containsParentTraversal($candidate)) {
                continue;
            }
            $candidate = $this->withSiteNamespace($candidate);
            $key = $this->comparisonPath($candidate);
            if ($key !== '' && !isset($result[$key])) {
                $result[$key] = $candidate;
            }
        }
        return array_values($result);
    }

    public function path(string $child): string
    {
        $child = trim(str_replace('\\', '/', $child), '/');
        if ($child === '' || str_contains($child, '..')) {
            throw new \InvalidArgumentException('Unsafe EDIS private storage child path.');
        }
        return $this->root() . '/' . $child;
    }

    public function ensure(): bool
    {
        $explicit = $this->configuredRoot !== null || defined('EDIS_EVIDENCE_PRIVATE_STORAGE_DIR');
        $candidates = $this->resolvedRoot !== null ? [$this->resolvedRoot] : $this->candidateRoots();
        foreach ($candidates as $candidate) {
            if (!$this->isOutsideWebRoot($candidate)) {
                continue;
            }
            if (!$this->ensureCandidate($candidate)) {
                continue;
            }
            if (!$this->isOutsideWebRoot($candidate)) {
                continue;
            }
            $this->resolvedRoot = $candidate;
            $this->cachedSelfTest = null;
            return true;
        }
        if ($explicit) {
            $this->resolvedRoot = null;
        }
        return false;
    }

    public function securityState(): string
    {
        if (!$this->ensure()) {
            return 'ERROR';
        }
        if ($this->isOutsideWebRoot($this->root())) {
            return 'OUTSIDE_WEB_ROOT';
        }
        foreach (['index.php', '.htaccess', 'web.config'] as $guard) {
            if (!is_file($this->root() . '/' . $guard)) {
                return 'ERROR';
            }
        }
        return 'ACCESS_GUARDS_PRESENT';
    }

    /** @return array<string,mixed> */
    public function diagnosticContext(): array
    {
        return [
            'configured' => $this->configuredRoot !== null || defined('EDIS_EVIDENCE_PRIVATE_STORAGE_DIR'),
            'resolved_root' => $this->resolvedRoot,
            'candidates' => $this->candidateRoots(),
            'candidate_details' => $this->candidateDetails(),
            'security_state' => $this->securityState(),
            'local_environment' => $this->localEnvironmentContext(),
        ];
    }

    /**
     * Verify active-path storage behavior. A forced run always executes the separate-process lock probe.
     *
     * @return array<string,mixed>
     */
    public function selfTest(bool $force = false): array
    {
        if (!$force && $this->cachedSelfTest !== null) {
            return $this->cachedSelfTest;
        }
        $result = [
            'state' => 'FAIL',
            'security_state' => 'ERROR',
            'root_is_symlink' => false,
            'writable' => false,
            'atomic_write' => false,
            'atomic_replace' => false,
            'rename' => false,
            'cleanup' => false,
            'fsync' => false,
            'lock_exclusion' => false,
            'multiprocess_lock_exclusion' => 'UNAVAILABLE',
            'multiprocess_lock_probe' => [],
            'attestation_cache_hit' => false,
        ];
        if (!$this->ensure()) {
            return $this->cachedSelfTest = $result;
        }
        $root = $this->root();
        $result['root_is_symlink'] = is_link($root) || $this->hasRedirectingAncestor($root);
        if ($result['root_is_symlink']) {
            $this->removeAttestation();
            return $this->cachedSelfTest = $result;
        }
        $result['writable'] = is_writable($root);
        $result['security_state'] = $this->securityState();
        if (!$result['writable'] || $result['security_state'] !== 'OUTSIDE_WEB_ROOT') {
            $this->removeAttestation();
            return $this->cachedSelfTest = $result;
        }

        if (!$force) {
            $attested = $this->loadAttestation();
            if (is_array($attested)) {
                return $this->cachedSelfTest = $attested;
            }
        }

        try {
            $test = $this->filesystem->selfTest($root, true);
            $result['atomic_write'] = $test['durable_write'];
            $result['fsync'] = $test['durable_write'];
            $result['atomic_replace'] = $test['atomic_replace'];
            $result['rename'] = $test['atomic_rename'];
            $result['lock_exclusion'] = $test['lock_exclusion'];
            $result['multiprocess_lock_exclusion'] = $test['multiprocess_lock_exclusion'];
            $result['multiprocess_lock_probe'] = $test['multiprocess_lock_probe'] ?? $this->filesystem->multiprocessProbeContext();
            $result['cleanup'] = $test['cleanup'];
        } catch (\Throwable) {
            $this->removeAttestation();
            return $this->cachedSelfTest = $result;
        }
        if (
            $result['atomic_write']
            && $result['fsync']
            && $result['atomic_replace']
            && $result['rename']
            && $result['lock_exclusion']
            && $result['cleanup']
            && $result['multiprocess_lock_exclusion'] === 'PASS'
        ) {
            $result['state'] = 'PASS';
        }
        if ($this->acceptsSelfTestResult($result)) {
            $this->persistAttestation($result);
        } else {
            $this->removeAttestation();
        }
        return $this->cachedSelfTest = $result;
    }

    /** @return array<string,mixed>|null */
    private function loadAttestation(): ?array
    {
        $path = $this->attestationPath();
        if (is_link($path) || !is_file($path)) {
            return null;
        }
        try {
            $attestation = json_decode($this->filesystem->read($path), true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($attestation) || ($attestation['format'] ?? null) !== self::ATTESTATION_FORMAT) {
            return null;
        }
        $storedHash = is_string($attestation['attestation_sha256'] ?? null) ? $attestation['attestation_sha256'] : '';
        $hashInput = $attestation;
        unset($hashInput['attestation_sha256']);
        $actualHash = 'sha256:' . hash('sha256', CanonicalJson::encode($hashInput));
        if ($storedHash === '' || !hash_equals($storedHash, $actualHash)) {
            return null;
        }
        $storedEnvironment = is_array($attestation['environment'] ?? null) ? $attestation['environment'] : null;
        if ((int) ($attestation['expires_at'] ?? 0) < time()
            || $storedEnvironment === null
            || CanonicalJson::encode($storedEnvironment) !== CanonicalJson::encode($this->attestationEnvironment())) {
            return null;
        }
        $result = is_array($attestation['result'] ?? null) ? $attestation['result'] : null;
        if (!is_array($result) || !$this->acceptsSelfTestResult($result)) {
            return null;
        }
        $result['attestation_cache_hit'] = true;
        $result['attestation_generated_at'] = (int) ($attestation['generated_at'] ?? 0);
        $result['attestation_expires_at'] = (int) ($attestation['expires_at'] ?? 0);
        return $result;
    }

    /** @param array<string,mixed> $result */
    private function persistAttestation(array $result): void
    {
        $generatedAt = time();
        $persistedResult = $result;
        $persistedResult['attestation_cache_hit'] = false;
        unset($persistedResult['attestation_generated_at'], $persistedResult['attestation_expires_at']);
        $attestation = [
            'format' => self::ATTESTATION_FORMAT,
            'generated_at' => $generatedAt,
            'expires_at' => $generatedAt + self::ATTESTATION_TTL_SECONDS,
            'environment' => $this->attestationEnvironment(),
            'result' => $persistedResult,
        ];
        $attestation['attestation_sha256'] = 'sha256:' . hash('sha256', CanonicalJson::encode($attestation));
        try {
            $this->filesystem->writeAtomically($this->attestationPath(), CanonicalJson::encode($attestation));
        } catch (\Throwable) {
            // A failed cache write cannot turn a passing live proof into a failure.
        }
    }

    private function removeAttestation(): void
    {
        if ($this->resolvedRoot === null) {
            return;
        }
        try {
            $this->filesystem->removeFileIfExists($this->attestationPath(), false);
        } catch (\Throwable) {
        }
    }

    private function attestationPath(): string
    {
        return $this->root() . '/.edis-storage-attestation.json';
    }

    /** @return array<string,string> */
    private function attestationEnvironment(): array
    {
        return [
            'root' => $this->comparisonPath($this->root()),
            'plugin_version' => defined('EDIS_EVIDENCE_EXPORTER_VERSION') ? (string) constant('EDIS_EVIDENCE_EXPORTER_VERSION') : '3.7.11',
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'php_binary' => PHP_BINARY,
            'environment_type' => function_exists('wp_get_environment_type')
                ? (string) wp_get_environment_type()
                : (defined('WP_ENVIRONMENT_TYPE') ? (string) constant('WP_ENVIRONMENT_TYPE') : 'production'),
        ];
    }

    private function ensureCandidate(string $root): bool
    {
        try {
            if (!$this->isAbsolutePath($root) || $this->containsParentTraversal($root) || !$this->isOutsideWebRoot($root) || $this->hasRedirectingAncestor($root)) {
                return false;
            }
            $this->filesystem->ensureDirectory($root);
            if (!is_dir($root) || !is_writable($root) || $this->hasRedirectingAncestor($root) || !$this->isOutsideWebRoot($root)) {
                return false;
            }
            $guards = [
                'index.php' => "<?php\nhttp_response_code(404);\nexit;\n",
                '.htaccess' => "Require all denied\nDeny from all\n",
                'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
            ];
            foreach ($guards as $name => $contents) {
                $path = $root . '/' . $name;
                if (is_link($path)) {
                    return false;
                }
                if (!is_file($path)) {
                    $this->filesystem->writeAtomically($path, $contents);
                }
            }
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Determine whether local auto-provisioning may suggest a safe sibling directory. */
    public function localConvenienceEnabled(): bool
    {
        if (defined('EDIS_DISABLE_LOCAL_AUTOPROVISIONED_PRIVATE_STORAGE') && constant('EDIS_DISABLE_LOCAL_AUTOPROVISIONED_PRIVATE_STORAGE') === true) {
            return false;
        }
        if (defined('EDIS_ALLOW_LOCAL_AUTOPROVISIONED_PRIVATE_STORAGE') && constant('EDIS_ALLOW_LOCAL_AUTOPROVISIONED_PRIVATE_STORAGE') === true) {
            return true;
        }
        if (function_exists('wp_get_environment_type')) {
            try {
                return wp_get_environment_type() === 'local';
            } catch (\Throwable) {
                return false;
            }
        }
        return defined('WP_ENVIRONMENT_TYPE') && constant('WP_ENVIRONMENT_TYPE') === 'local';
    }

    /** @return list<string> */
    private function localConvenienceRoots(): array
    {
        if (!$this->localConvenienceEnabled()) {
            return [];
        }

        $roots = [];
        if (defined('ABSPATH')) {
            $publicRoot = rtrim((string) ABSPATH, '/\\');
            $localWpSiteRoot = self::detectLocalWpSiteRoot($publicRoot);
            if ($localWpSiteRoot !== null) {
                // Local documents the WordPress document root as <site>/app/public.
                // Keep private evidence beside the managed app/logs tree, not in public.
                $roots[] = rtrim($localWpSiteRoot, '/\\') . '/edis-private-storage';

                // Backward-compatible fallback used by 3.7.11. It remains outside public.
                $localWpAppRoot = dirname($publicRoot);
                if ($localWpAppRoot !== '' && $localWpAppRoot !== '.') {
                    $roots[] = rtrim($localWpAppRoot, '/\\') . '/edis-private-storage';
                }
            } else {
                $webParent = dirname($publicRoot);
                if ($webParent !== '' && $webParent !== '.') {
                    $roots[] = rtrim($webParent, '/\\') . '/edis-private-storage';
                }
            }
        }
        if (defined('WP_CONTENT_DIR')) {
            $contentParent = dirname(rtrim((string) WP_CONTENT_DIR, '/\\'), 2);
            if ($contentParent !== '' && $contentParent !== '.') {
                $roots[] = rtrim($contentParent, '/\\') . '/edis-private-storage';
            }
        }
        return $roots;
    }

    /**
     * Detect Local's documented <site>/app/public WordPress layout.
     *
     * @return string|null Absolute Local site folder, or null when the layout is not Local-compatible.
     */
    public static function detectLocalWpSiteRoot(string $publicRoot): ?string
    {
        $publicRoot = rtrim(str_replace('\\', '/', $publicRoot), '/');
        if ($publicRoot === '' || strcasecmp(basename($publicRoot), 'public') !== 0) {
            return null;
        }
        $appRoot = str_replace('\\', '/', dirname($publicRoot));
        if ($appRoot === '' || $appRoot === '.' || strcasecmp(basename($appRoot), 'app') !== 0) {
            return null;
        }
        $siteRoot = str_replace('\\', '/', dirname($appRoot));
        return $siteRoot !== '' && $siteRoot !== '.' ? $siteRoot : null;
    }

    /** @return array{environment_type:string,platform_hint:string,localwp_site_root:string|null,localwp_public_root:string|null} */
    public function localEnvironmentContext(): array
    {
        $environmentType = function_exists('wp_get_environment_type')
            ? (string) wp_get_environment_type()
            : (defined('WP_ENVIRONMENT_TYPE') ? (string) constant('WP_ENVIRONMENT_TYPE') : 'production');
        $publicRoot = defined('ABSPATH') ? rtrim((string) ABSPATH, '/\\') : null;
        $siteRoot = is_string($publicRoot) ? self::detectLocalWpSiteRoot($publicRoot) : null;
        return [
            'environment_type' => $environmentType,
            'platform_hint' => $siteRoot !== null ? 'localwp' : 'generic',
            'localwp_site_root' => $siteRoot,
            'localwp_public_root' => $publicRoot,
        ];
    }

    /** @return list<array{path:string,exists:bool,is_directory:bool,is_writable:bool,outside_web_root:bool,redirecting_ancestor:bool}> */
    public function candidateDetails(): array
    {
        $details = [];
        foreach ($this->candidateRoots() as $candidate) {
            $details[] = [
                'path' => $candidate,
                'exists' => file_exists($candidate),
                'is_directory' => is_dir($candidate),
                'is_writable' => is_dir($candidate) && is_writable($candidate),
                'outside_web_root' => $this->isOutsideWebRoot($candidate),
                'redirecting_ancestor' => $this->hasRedirectingAncestor($candidate),
            ];
        }
        return $details;
    }

    /**
     * Decide whether a storage self-test result is acceptable in the current environment.
     *
     * @param array<string,mixed> $result
     */
    public function acceptsSelfTestResult(array $result): bool
    {
        return ($result['state'] ?? 'FAIL') === 'PASS'
            && ($result['multiprocess_lock_exclusion'] ?? null) === 'PASS';
    }

    private function withSiteNamespace(string $candidate): string
    {
        if (function_exists('is_multisite') && is_multisite() && function_exists('get_current_blog_id')) {
            $networkId = function_exists('get_current_network_id') ? (int) get_current_network_id() : 0;
            $blogId = (int) get_current_blog_id();
            return $candidate . '/site-' . max(0, $networkId) . '-' . max(1, $blogId);
        }
        return $candidate;
    }

    private function isOutsideWebRoot(string $candidate): bool
    {
        if ($this->containsParentTraversal($candidate) || !$this->isAbsolutePath($candidate) || $this->hasRedirectingAncestor($candidate)) {
            return false;
        }
        if (!defined('ABSPATH')) {
            return true;
        }

        $logicalRoot = $this->comparisonPath($candidate);
        $logicalWeb = $this->comparisonPath((string) ABSPATH);
        if ($logicalWeb !== '' && ($logicalRoot === $logicalWeb || str_starts_with($logicalRoot, $logicalWeb . '/'))) {
            return false;
        }

        $rootReal = realpath($candidate);
        $webReal = realpath((string) ABSPATH);
        $physicalRoot = $this->comparisonPath(is_string($rootReal) ? $rootReal : $candidate);
        $physicalWeb = $this->comparisonPath(is_string($webReal) ? $webReal : (string) ABSPATH);
        if ($physicalWeb === '') {
            return true;
        }
        return $physicalRoot !== $physicalWeb && !str_starts_with($physicalRoot, $physicalWeb . '/');
    }

    private function hasRedirectingAncestor(string $path): bool
    {
        $current = rtrim(str_replace('\\', '/', $path), '/');
        if ($current === '') {
            return true;
        }
        while ($current !== '' && $current !== '.' && $current !== '/') {
            if (file_exists($current) || is_link($current)) {
                if (is_link($current)) {
                    return true;
                }
                $real = realpath($current);
                if (is_string($real) && $this->comparisonPath($real) !== $this->comparisonPath($current)) {
                    return true;
                }
            }
            $parent = str_replace('\\', '/', dirname($current));
            if ($parent === $current) {
                break;
            }
            $current = rtrim($parent, '/');
            if (preg_match('/\A[A-Za-z]:\z/', $current) === 1) {
                break;
            }
        }
        return false;
    }

    private function containsParentTraversal(string $path): bool
    {
        return preg_match('~(?:\A|[\\/])\.\.(?:[\\/]|\z)~', $path) === 1;
    }

    private function isAbsolutePath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);
        return str_starts_with($normalized, '/')
            || str_starts_with($normalized, '//')
            || preg_match('/\A[A-Za-z]:\//', $normalized) === 1;
    }

    private function comparisonPath(string $path): string
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($normalized, '//?/UNC/')) {
            $normalized = '//' . substr($normalized, 8);
        } elseif (str_starts_with($normalized, '//?/')) {
            $normalized = substr($normalized, 4);
        }
        $isUnc = str_starts_with($normalized, '//');
        $normalized = preg_replace('~/+~', '/', $normalized) ?? $normalized;
        if ($isUnc) {
            $normalized = '//' . ltrim($normalized, '/');
        }
        if (PHP_OS_FAMILY === 'Windows' || preg_match('/\A[A-Za-z]:\//', $normalized) === 1 || $isUnc) {
            $normalized = strtolower($normalized);
        }
        return $normalized;
    }
}
