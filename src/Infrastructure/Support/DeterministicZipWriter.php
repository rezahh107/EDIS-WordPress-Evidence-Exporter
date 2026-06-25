<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

final class DeterministicZipWriter
{
    private const UTF8_FLAG = 0x0800;
    private const DOS_DATE_1980_01_01 = 0x0021;
    private const DOS_TIME_MIDNIGHT = 0x0000;
    private const VERSION_NEEDED = 20;
    private const VERSION_MADE_BY_UNIX = 0x0314;
    private const UNIX_FILE_ATTRIBUTES = 0x81A40000;
    private const MAX_NON_ZIP64_VALUE = 0xfffffffe;
    private const MAX_NON_ZIP64_ENTRIES = 65534;

    /** @param array<string,string> $files */
    public function build(array $files): string
    {
        $handle = fopen('php://temp/maxmemory:2097152', 'w+b');
        if (!is_resource($handle)) {
            throw new \RuntimeException('Unable to create a deterministic ZIP temporary stream.');
        }
        try {
            $this->writeToStream($handle, $files);
            if (fseek($handle, 0) !== 0) {
                throw new \RuntimeException('Unable to rewind the deterministic ZIP temporary stream.');
            }
            $bytes = stream_get_contents($handle);
            if (!is_string($bytes)) {
                throw new \RuntimeException('Unable to read the deterministic ZIP temporary stream.');
            }
            return $bytes;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Write EDIS-ZIP-1 bytes directly to a file through the deterministic
     * filesystem atomic-commit boundary.
     *
     * @param array<string,string> $files
     * @return array{sha256:string,size:int}
     */
    public function writeToFile(string $path, array $files, DeterministicFilesystem $filesystem): array
    {
        return $filesystem->writeStreamAtomically(
            $path,
            fn ($handle): array => $this->writeToStream($handle, $files, $filesystem),
        );
    }

    /**
     * @param resource $handle
     * @param array<string,string> $files
     * @return array{sha256:string,size:int}
     */
    public function writeToStream($handle, array $files, ?DeterministicFilesystem $filesystem = null): array
    {
        if (!is_resource($handle)) {
            throw new \InvalidArgumentException('A writable ZIP output stream is required.');
        }
        if (count($files) > self::MAX_NON_ZIP64_ENTRIES) {
            throw new \LengthException('EDIS-ZIP-1 supports at most 65534 files without ZIP64 sentinels.');
        }

        $entries = [];
        foreach ($files as $path => $contents) {
            if (!is_string($path) || !is_string($contents)) {
                throw new \InvalidArgumentException('ZIP entries must be string paths mapped to string bytes.');
            }
            $name = $this->safePath($path);
            if (array_key_exists($name, $entries)) {
                throw new \InvalidArgumentException('Two input paths normalize to the same ZIP entry.');
            }
            $entries[$name] = $path;
        }
        uksort($entries, static fn (string $a, string $b): int => strcmp($a, $b));

        $archiveHash = hash_init('sha256');
        $archiveSize = 0;
        $offset = 0;
        $centralRecords = [];
        $centralSize = 0;
        foreach ($entries as $name => $sourcePath) {
            $contents = $files[$sourcePath];
            $size = strlen($contents);
            if ($size > self::MAX_NON_ZIP64_VALUE || $offset > self::MAX_NON_ZIP64_VALUE) {
                throw new \LengthException('ZIP64 is intentionally unsupported by EDIS-ZIP-1.');
            }

            $crcBytes = hash('crc32b', $contents, true);
            if (!is_string($crcBytes) || strlen($crcBytes) !== 4) {
                throw new \RuntimeException('CRC32B could not be calculated deterministically.');
            }
            $crcParts = unpack('Ncrc', $crcBytes);
            if (!is_array($crcParts) || !isset($crcParts['crc'])) {
                throw new \RuntimeException('CRC32B could not be decoded deterministically.');
            }
            $crc = (int) $crcParts['crc'];
            $nameLength = strlen($name);
            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                self::VERSION_NEEDED,
                self::UTF8_FLAG,
                0,
                self::DOS_TIME_MIDNIGHT,
                self::DOS_DATE_1980_01_01,
                $crc,
                $size,
                $size,
                $nameLength,
                0,
            );
            $localRecordSize = strlen($localHeader) + $nameLength + $size;
            if ($offset + $localRecordSize > self::MAX_NON_ZIP64_VALUE) {
                throw new \LengthException('ZIP64 is intentionally unsupported by EDIS-ZIP-1.');
            }

            $this->emit($handle, $localHeader, $archiveHash, $archiveSize, $filesystem);
            $this->emit($handle, $name, $archiveHash, $archiveSize, $filesystem);
            $this->emit($handle, $contents, $archiveHash, $archiveSize, $filesystem);

            $central = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                self::VERSION_MADE_BY_UNIX,
                self::VERSION_NEEDED,
                self::UTF8_FLAG,
                0,
                self::DOS_TIME_MIDNIGHT,
                self::DOS_DATE_1980_01_01,
                $crc,
                $size,
                $size,
                $nameLength,
                0,
                0,
                0,
                0,
                self::UNIX_FILE_ATTRIBUTES,
                $offset,
            ) . $name;
            $centralRecords[] = $central;
            $centralSize += strlen($central);
            if ($centralSize > self::MAX_NON_ZIP64_VALUE) {
                throw new \LengthException('ZIP64 is intentionally unsupported by EDIS-ZIP-1.');
            }
            $offset += $localRecordSize;
        }

        $totalSize = $offset + $centralSize + 22;
        if ($offset > self::MAX_NON_ZIP64_VALUE || $totalSize > self::MAX_NON_ZIP64_VALUE) {
            throw new \LengthException('ZIP64 is intentionally unsupported by EDIS-ZIP-1.');
        }
        foreach ($centralRecords as $central) {
            $this->emit($handle, $central, $archiveHash, $archiveSize, $filesystem);
        }
        $count = count($entries);
        $end = pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, $centralSize, $offset, 0);
        $this->emit($handle, $end, $archiveHash, $archiveSize, $filesystem);

        if ($archiveSize !== $totalSize) {
            throw new \RuntimeException('Deterministic ZIP byte count does not match the declared classic ZIP bounds.');
        }
        return ['sha256' => 'sha256:' . hash_final($archiveHash), 'size' => $archiveSize];
    }

    /** @param resource $handle */
    private function emit($handle, string $bytes, \HashContext $hash, int &$size, ?DeterministicFilesystem $filesystem): void
    {
        if ($filesystem instanceof DeterministicFilesystem) {
            $filesystem->writeAll($handle, $bytes);
        } else {
            $length = strlen($bytes);
            $offset = 0;
            while ($offset < $length) {
                $written = fwrite($handle, substr($bytes, $offset, min(1024 * 1024, $length - $offset)));
                if (!is_int($written) || $written <= 0) {
                    throw new \RuntimeException('Unable to write deterministic ZIP bytes.');
                }
                $offset += $written;
            }
        }
        hash_update($hash, $bytes);
        $size += strlen($bytes);
    }

    private function safePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (
            $path === ''
            || str_starts_with($path, '/')
            || preg_match('//u', $path) !== 1
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            throw new \InvalidArgumentException('Unsafe or invalid UTF-8 ZIP path.');
        }
        if (strlen($path) >= 2 && ctype_alpha($path[0]) && $path[1] === ':') {
            throw new \InvalidArgumentException('Absolute drive paths are not permitted in ZIP files.');
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Unsafe ZIP path segment.');
            }
        }
        if (strlen($path) > 65535) {
            throw new \LengthException('ZIP entry path is too long.');
        }
        return $path;
    }
}
