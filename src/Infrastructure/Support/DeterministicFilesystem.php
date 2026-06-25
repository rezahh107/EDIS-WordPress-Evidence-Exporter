<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

final class DeterministicFilesystem
{
    /** @var array{state:string,binary:string|null,sapi:string,exit_code:int|null,stdout:string,stderr:string} */
    private array $lastMultiprocessProbe = [
        'state' => 'NOT_RUN',
        'binary' => null,
        'sapi' => PHP_SAPI,
        'exit_code' => null,
        'stdout' => '',
        'stderr' => '',
    ];

    public function ensureDirectory(string $directory, int $mode = 0750): void
    {
        if (is_link($directory)) {
            throw new FilesystemException('EDIS_FILESYSTEM_SYMLINK_REJECTED', 'mkdir', 'Refusing to use a symbolic-link directory: ' . $directory);
        }
        if (!is_dir($directory)) {
            $created = $this->invoke('mkdir', static function () use ($directory, $mode): bool {
                if (function_exists('wp_mkdir_p')) {
                    return (bool) wp_mkdir_p($directory);
                }
                return mkdir($directory, $mode, true);
            });
            if ($created !== true && !is_dir($directory)) {
                throw new FilesystemException('EDIS_FILESYSTEM_DIRECTORY_CREATE_FAILED', 'mkdir', 'Unable to create directory: ' . $directory);
            }
        }
        if (!is_dir($directory) || is_link($directory)) {
            throw new FilesystemException('EDIS_FILESYSTEM_DIRECTORY_INVALID', 'mkdir', 'Directory validation failed: ' . $directory);
        }
        $this->setPermissions($directory, $mode, true);
    }

    public function writeAtomically(string $path, string $contents, int $mode = 0640): void
    {
        $expectedSha256 = 'sha256:' . hash('sha256', $contents);
        $expectedSize = strlen($contents);
        $this->writeStreamAtomically(
            $path,
            function ($handle) use ($contents, $expectedSha256, $expectedSize): array {
                $offset = 0;
                $chunkSize = 1024 * 1024;
                while ($offset < $expectedSize) {
                    $chunk = substr($contents, $offset, min($chunkSize, $expectedSize - $offset));
                    $this->writeAll($handle, $chunk);
                    $offset += strlen($chunk);
                }
                return ['sha256' => $expectedSha256, 'size' => $expectedSize];
            },
            $mode,
        );
    }

    /**
     * Atomically create a file from a bounded-memory stream writer.
     *
     * The callback must write all bytes to the supplied handle and return the
     * expected SHA-256 and byte size. The committed file is independently
     * verified after the atomic rename.
     *
     * @param callable(resource):array{sha256:string,size:int} $writer
     * @return array{sha256:string,size:int}
     */
    public function writeStreamAtomically(string $path, callable $writer, int $mode = 0640): array
    {
        $directory = dirname($path);
        $this->ensureDirectory($directory);
        if (is_link($path)) {
            throw new FilesystemException('EDIS_FILESYSTEM_SYMLINK_REJECTED', 'write', 'Refusing to replace a symbolic-link file: ' . $path);
        }
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
        $handle = null;
        $committed = false;
        try {
            $handle = $this->invoke('fopen', static fn () => fopen($temporary, 'x+b'));
            if (!is_resource($handle)) {
                throw new FilesystemException('EDIS_FILESYSTEM_OPEN_FAILED', 'fopen', 'Unable to create temporary file: ' . $temporary);
            }
            $result = $writer($handle);
            if (!is_array($result)
                || !is_string($result['sha256'] ?? null)
                || !preg_match('/\\Asha256:[a-f0-9]{64}\\z/D', $result['sha256'])
                || !is_int($result['size'] ?? null)
                || $result['size'] < 0) {
                throw new FilesystemException('EDIS_FILESYSTEM_WRITE_RESULT_INVALID', 'write', 'The stream writer returned invalid verification metadata.');
            }
            if ($this->invoke('fflush', static fn () => fflush($handle)) !== true) {
                throw new FilesystemException('EDIS_FILESYSTEM_FLUSH_FAILED', 'fflush', 'Unable to flush temporary file: ' . $temporary);
            }
            if (!function_exists('fsync') || $this->invoke('fsync', static fn () => fsync($handle)) !== true) {
                throw new FilesystemException('EDIS_FILESYSTEM_SYNC_FAILED', 'fsync', 'Unable to durably synchronize temporary file: ' . $temporary);
            }
            $this->invoke('fclose', static fn () => fclose($handle));
            $handle = null;
            $this->setPermissions($temporary, $mode, false);
            $this->rename($temporary, $path);
            $committed = true;
            $this->synchronizeDirectory($directory);
            $this->setPermissions($path, $mode, false);
            $size = filesize($path);
            $hash = hash_file('sha256', $path);
            if (!is_int($size)
                || $size !== $result['size']
                || !is_string($hash)
                || !hash_equals($result['sha256'], 'sha256:' . $hash)) {
                throw new FilesystemException('EDIS_FILESYSTEM_VERIFY_FAILED', 'verify', 'Atomic stream write verification failed: ' . $path);
            }
            return $result;
        } catch (\Throwable $exception) {
            if (is_resource($handle)) {
                try { $this->invoke('fclose', static fn () => fclose($handle)); } catch (\Throwable) {}
            }
            $this->removeFileIfExists($temporary, false);
            if ($committed) {
                $this->removeFileIfExists($path, false);
            }
            if ($exception instanceof FilesystemException) {
                throw $exception;
            }
            throw new FilesystemException('EDIS_FILESYSTEM_WRITE_FAILED', 'write', 'Atomic stream write failed: ' . $path, $exception);
        }
    }

    /** @param resource $handle */
    public function writeAll($handle, string $bytes): void
    {
        $length = strlen($bytes);
        $offset = 0;
        while ($offset < $length) {
            $written = $this->invoke('fwrite', static fn () => fwrite($handle, substr($bytes, $offset, min(1024 * 1024, $length - $offset))));
            if (!is_int($written) || $written <= 0) {
                throw new FilesystemException('EDIS_FILESYSTEM_WRITE_FAILED', 'fwrite', 'Unable to write complete stream bytes.');
            }
            $offset += $written;
        }
    }

    public function read(string $path): string
    {
        if (is_link($path) || !is_file($path)) {
            throw new FilesystemException('EDIS_FILESYSTEM_READ_FAILED', 'read', 'File is missing or is a symbolic link: ' . $path);
        }
        $bytes = $this->invoke('file_get_contents', static fn () => file_get_contents($path));
        if (!is_string($bytes)) {
            throw new FilesystemException('EDIS_FILESYSTEM_READ_FAILED', 'read', 'Unable to read file: ' . $path);
        }
        return $bytes;
    }

    /** @return resource */
    public function open(string $path, string $mode)
    {
        $handle = $this->invoke('fopen', static fn () => fopen($path, $mode));
        if (!is_resource($handle)) {
            throw new FilesystemException('EDIS_FILESYSTEM_OPEN_FAILED', 'fopen', 'Unable to open file: ' . $path);
        }
        return $handle;
    }

    /** @param resource $handle */
    public function lock($handle, int $operation): bool
    {
        return $this->invoke('flock', static fn () => flock($handle, $operation)) === true;
    }

    /** @param resource $handle */
    public function close($handle): void
    {
        if ($this->invoke('fclose', static fn () => fclose($handle)) !== true) {
            throw new FilesystemException('EDIS_FILESYSTEM_CLOSE_FAILED', 'fclose', 'Unable to close file handle.');
        }
    }

    public function rename(string $source, string $target): void
    {
        if (is_link($source) || is_link($target)) {
            throw new FilesystemException('EDIS_FILESYSTEM_SYMLINK_REJECTED', 'rename', 'Refusing to rename a symbolic-link path.');
        }
        if ($this->invoke('rename', static fn () => rename($source, $target)) !== true) {
            throw new FilesystemException('EDIS_FILESYSTEM_RENAME_FAILED', 'rename', 'Unable to atomically rename ' . $source . ' to ' . $target . '.');
        }
    }

    /**
     * Synchronize a directory entry after an atomic rename where the runtime exposes
     * a POSIX-compatible directory stream. PHP does not expose an equivalent
     * portable directory flush on Windows, so Windows remains covered by the
     * existing atomic rename/readback proof and external runtime validation.
     */
    public function synchronizeDirectory(string $directory): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return false;
        }
        if (!function_exists('fsync') || !is_dir($directory) || is_link($directory)) {
            throw new FilesystemException('EDIS_FILESYSTEM_DIRECTORY_SYNC_UNAVAILABLE', 'fsync_directory', 'Unable to safely open the parent directory for synchronization: ' . $directory);
        }
        $handle = $this->invoke('fopen_directory', static fn () => fopen($directory, 'rb'));
        if (!is_resource($handle)) {
            throw new FilesystemException('EDIS_FILESYSTEM_DIRECTORY_SYNC_UNAVAILABLE', 'fopen_directory', 'Unable to open the parent directory for synchronization: ' . $directory);
        }
        try {
            if ($this->invoke('fsync_directory', static fn () => fsync($handle)) !== true) {
                throw new FilesystemException('EDIS_FILESYSTEM_DIRECTORY_SYNC_FAILED', 'fsync_directory', 'Unable to synchronize the parent directory: ' . $directory);
            }
        } finally {
            $this->invoke('fclose_directory', static fn () => fclose($handle));
        }
        return true;
    }

    public function removeFileIfExists(string $path, bool $strict = true): void
    {
        if (!file_exists($path) && !is_link($path)) {
            return;
        }
        if (is_link($path)) {
            if ($strict) {
                throw new FilesystemException('EDIS_FILESYSTEM_SYMLINK_REJECTED', 'unlink', 'Refusing to remove a symbolic link: ' . $path);
            }
            return;
        }
        try {
            if ($this->invoke('unlink', static fn () => unlink($path)) !== true && $strict) {
                throw new FilesystemException('EDIS_FILESYSTEM_REMOVE_FAILED', 'unlink', 'Unable to remove file: ' . $path);
            }
        } catch (\Throwable $exception) {
            if ($strict) {
                if ($exception instanceof FilesystemException) { throw $exception; }
                throw new FilesystemException('EDIS_FILESYSTEM_REMOVE_FAILED', 'unlink', 'Unable to remove file: ' . $path, $exception);
            }
        }
    }

    public function removeDirectoryIfEmpty(string $directory, bool $strict = true): void
    {
        if (!is_dir($directory) || is_link($directory)) { return; }
        try {
            if ($this->invoke('rmdir', static fn () => rmdir($directory)) !== true && $strict) {
                throw new FilesystemException('EDIS_FILESYSTEM_REMOVE_DIRECTORY_FAILED', 'rmdir', 'Unable to remove directory: ' . $directory);
            }
        } catch (\Throwable $exception) {
            if ($strict) {
                if ($exception instanceof FilesystemException) { throw $exception; }
                throw new FilesystemException('EDIS_FILESYSTEM_REMOVE_DIRECTORY_FAILED', 'rmdir', 'Unable to remove directory: ' . $directory, $exception);
            }
        }
    }

    public function setPermissions(string $path, int $mode, bool $directory): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return;
        }
        if ($this->invoke('chmod', static fn () => chmod($path, $mode)) !== true) {
            throw new FilesystemException(
                $directory ? 'EDIS_FILESYSTEM_DIRECTORY_PERMISSION_FAILED' : 'EDIS_FILESYSTEM_PERMISSION_FAILED',
                'chmod',
                'Unable to apply required permissions to: ' . $path,
            );
        }
    }

    /**
     * Exercise durable writes, atomic replacement, and advisory-lock exclusion in the supplied directory.
     *
     * @return array{lock_exclusion:bool,multiprocess_lock_exclusion:string,multiprocess_lock_probe:array<string,mixed>,atomic_rename:bool,atomic_replace:bool,durable_write:bool,cleanup:bool}
     */
    public function selfTest(string $directory, bool $probeIndependentProcess = true): array
    {
        $this->ensureDirectory($directory);
        $base = rtrim($directory, '/\\') . '/.edis-fs-test-' . bin2hex(random_bytes(8));
        $target = $base . '.renamed';
        $lockPath = $base . '.lock';
        $result = [
            'lock_exclusion' => false,
            'multiprocess_lock_exclusion' => $probeIndependentProcess ? 'UNAVAILABLE' : 'NOT_RUN',
            'multiprocess_lock_probe' => $this->lastMultiprocessProbe,
            'atomic_rename' => false,
            'atomic_replace' => false,
            'durable_write' => false,
            'cleanup' => false,
        ];
        $first = null;
        $second = null;
        try {
            $bytes = random_bytes(64);
            $this->writeAtomically($base, $bytes);
            $result['durable_write'] = hash_equals(hash('sha256', $bytes), hash('sha256', $this->read($base)));
            $this->rename($base, $target);
            $result['atomic_rename'] = is_file($target) && !is_file($base);
            $replacement = random_bytes(64);
            $this->writeAtomically($target, $replacement);
            $result['atomic_replace'] = hash_equals(hash('sha256', $replacement), hash('sha256', $this->read($target)));
            $first = $this->open($lockPath, 'c+b');
            $second = $this->open($lockPath, 'c+b');
            $firstLocked = $this->lock($first, LOCK_EX | LOCK_NB);
            $secondLocked = $this->lock($second, LOCK_EX | LOCK_NB);
            $result['lock_exclusion'] = $firstLocked && !$secondLocked;
            if ($probeIndependentProcess && $firstLocked) {
                $result['multiprocess_lock_exclusion'] = $this->probeIndependentProcessLock($lockPath);
                $result['multiprocess_lock_probe'] = $this->lastMultiprocessProbe;
            }
            if ($firstLocked) { $this->lock($first, LOCK_UN); }
            if ($secondLocked) { $this->lock($second, LOCK_UN); }
        } finally {
            if (is_resource($first)) { try { $this->close($first); } catch (\Throwable) {} }
            if (is_resource($second)) { try { $this->close($second); } catch (\Throwable) {} }
            $this->removeFileIfExists($base, false);
            $this->removeFileIfExists($target, false);
            $this->removeFileIfExists($lockPath, false);
            $result['cleanup'] = !file_exists($base) && !file_exists($target) && !file_exists($lockPath);
        }
        return $result;
    }

    /**
     * Prove that a separate PHP process cannot acquire an already-held lock in the active storage directory.
     *
     * @return 'PASS'|'FAIL'|'UNAVAILABLE'
     */
    public function probeIndependentProcessLock(string $lockPath): string
    {
        $this->lastMultiprocessProbe = [
            'state' => 'UNAVAILABLE',
            'binary' => null,
            'sapi' => PHP_SAPI,
            'exit_code' => null,
            'stdout' => '',
            'stderr' => '',
        ];
        if (!function_exists('proc_open') || !function_exists('proc_get_status') || !function_exists('proc_terminate') || !function_exists('proc_close')) {
            return 'UNAVAILABLE';
        }
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        foreach (['proc_open', 'proc_get_status', 'proc_terminate', 'proc_close'] as $function) {
            if (in_array($function, $disabled, true)) {
                return 'UNAVAILABLE';
            }
        }

        $binary = $this->independentPhpBinary();
        $this->lastMultiprocessProbe['binary'] = $binary;
        if ($binary === null) {
            return 'UNAVAILABLE';
        }

        $script = '$h=fopen($argv[1],"c+b");if(!is_resource($h)){fwrite(STDOUT,"OPEN_FAILED");exit(2);}';
        $script .= '$locked=flock($h,LOCK_EX|LOCK_NB);fwrite(STDOUT,$locked?"ACQUIRED":"BLOCKED");';
        $script .= 'if($locked){flock($h,LOCK_UN);}fclose($h);exit(0);';
        $pipes = [];
        $process = proc_open(
            [$binary, '-d', 'display_errors=0', '-d', 'log_errors=0', '-r', $script, $lockPath],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true],
        );
        if (!is_resource($process)) {
            return 'UNAVAILABLE';
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + 5.0;
        $timedOut = false;
        $observedExit = null;
        do {
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!is_array($status) || empty($status['running'])) {
                if (is_array($status) && isset($status['exitcode']) && is_int($status['exitcode']) && $status['exitcode'] >= 0) {
                    $observedExit = $status['exitcode'];
                }
                break;
            }
            if (microtime(true) >= $deadline) {
                $timedOut = true;
                proc_terminate($process);
                break;
            }
            usleep(10000);
        } while (true);
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closedExit = proc_close($process);
        $exit = $observedExit ?? ($closedExit >= 0 ? $closedExit : null);

        $this->lastMultiprocessProbe = [
            'state' => $timedOut ? 'TIMEOUT' : 'COMPLETED',
            'binary' => $binary,
            'sapi' => PHP_SAPI,
            'exit_code' => $exit,
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
        ];

        if ($timedOut || $exit !== 0 || trim($stderr) !== '') {
            $this->lastMultiprocessProbe['state'] = 'FAIL';
            return 'FAIL';
        }
        $passed = trim($stdout) === 'BLOCKED';
        $this->lastMultiprocessProbe['state'] = $passed ? 'PASS' : 'FAIL';
        return $passed ? 'PASS' : 'FAIL';
    }

    /** @return array{state:string,binary:string|null,sapi:string,exit_code:int|null,stdout:string,stderr:string} */
    public function multiprocessProbeContext(): array
    {
        return $this->lastMultiprocessProbe;
    }

    private function independentPhpBinary(): ?string
    {
        $candidates = [];
        if (PHP_BINARY !== '') {
            if (PHP_OS_FAMILY === 'Windows') {
                $directory = dirname(PHP_BINARY);
                $basename = strtolower(basename(PHP_BINARY));
                if ($basename === 'php-cgi.exe' || $basename === 'php-cgi') {
                    $candidates[] = $directory . DIRECTORY_SEPARATOR . 'php.exe';
                }
            }
            $candidates[] = PHP_BINARY;
        }

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '' || !is_file($candidate)) {
                continue;
            }
            if (PHP_OS_FAMILY === 'Windows' && str_starts_with(strtolower(basename($candidate)), 'php-cgi')) {
                continue;
            }
            return $candidate;
        }
        return null;
    }

    private function invoke(string $operation, callable $callback): mixed
    {
        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;
            return true;
        });
        try {
            $result = $callback();
        } catch (\Throwable $exception) {
            throw new FilesystemException('EDIS_FILESYSTEM_OPERATION_FAILED', $operation, $operation . ' failed: ' . $exception->getMessage(), $exception);
        } finally {
            restore_error_handler();
        }
        if (is_string($warning) && $warning !== '') {
            throw new FilesystemException('EDIS_FILESYSTEM_OPERATION_FAILED', $operation, $operation . ' warning: ' . $warning);
        }
        return $result;
    }
}
