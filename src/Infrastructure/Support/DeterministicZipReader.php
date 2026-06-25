<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

final class DeterministicZipReader
{
    private DeterministicFilesystem $filesystem;

    private const UTF8_FLAG = 0x0800;
    private const DOS_DATE_1980_01_01 = 0x0021;
    private const DOS_TIME_MIDNIGHT = 0x0000;
    private const VERSION_NEEDED = 20;
    private const VERSION_MADE_BY_UNIX = 0x0314;
    private const UNIX_FILE_ATTRIBUTES = 0x81A40000;

    public function __construct(?DeterministicFilesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new DeterministicFilesystem();
    }

    public function readStoredEntry(string $archivePath, string $requestedEntry): ?string
    {
        $requestedEntry = $this->safePath($requestedEntry);
        if (is_link($archivePath) || !is_file($archivePath)) {
            throw new \RuntimeException('The deterministic ZIP archive is missing or is a symbolic link.');
        }
        $length = filesize($archivePath);
        if (!is_int($length) || $length < 22) {
            throw new \RuntimeException('The deterministic ZIP archive is truncated.');
        }

        $handle = $this->filesystem->open($archivePath, 'rb');
        try {
            $endOffset = $length - 22;
            $this->seek($handle, $endOffset);
            $end = unpack(
                'Vsignature/vdisk/vcentral_disk/ventries_disk/ventries_total/Vcentral_size/Vcentral_offset/vcomment_length',
                $this->readExact($handle, 22),
            );
            if (
                !is_array($end)
                || ($end['signature'] ?? 0) !== 0x06054b50
                || ($end['disk'] ?? -1) !== 0
                || ($end['central_disk'] ?? -1) !== 0
                || ($end['entries_disk'] ?? -1) !== ($end['entries_total'] ?? -2)
                || ($end['comment_length'] ?? -1) !== 0
            ) {
                throw new \RuntimeException('The archive is not a valid single-disk EDIS-ZIP-1 package.');
            }

            $centralOffset = (int) $end['central_offset'];
            $centralSize = (int) $end['central_size'];
            $entryCount = (int) $end['entries_total'];
            if ($centralOffset < 0 || $centralSize < 0 || $centralOffset + $centralSize !== $endOffset) {
                throw new \RuntimeException('The deterministic ZIP central directory bounds are invalid.');
            }

            $this->seek($handle, $centralOffset);
            $cursor = $centralOffset;
            $centralEnd = $centralOffset + $centralSize;
            $found = null;
            $seen = [];
            for ($index = 0; $index < $entryCount; $index++) {
                if ($cursor + 46 > $centralEnd) {
                    throw new \RuntimeException('The deterministic ZIP central directory is truncated.');
                }
                $header = unpack(
                    'Vsignature/vversion_made/vversion_needed/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed_size/Vuncompressed_size/vname_length/vextra_length/vcomment_length/vdisk_start/vinternal_attributes/Vexternal_attributes/Vlocal_offset',
                    $this->readExact($handle, 46),
                );
                if (!is_array($header) || ($header['signature'] ?? 0) !== 0x02014b50) {
                    throw new \RuntimeException('The deterministic ZIP central directory is invalid.');
                }
                $nameLength = (int) $header['name_length'];
                $extraLength = (int) $header['extra_length'];
                $commentLength = (int) $header['comment_length'];
                $recordLength = 46 + $nameLength + $extraLength + $commentLength;
                if ($recordLength < 46 || $cursor + $recordLength > $centralEnd) {
                    throw new \RuntimeException('The deterministic ZIP central record exceeds its declared bounds.');
                }
                $name = $this->readExact($handle, $nameLength);
                if ($extraLength > 0) {
                    $this->readExact($handle, $extraLength);
                }
                if ($commentLength > 0) {
                    $this->readExact($handle, $commentLength);
                }
                $this->validateCentralRecord($header, $name);
                if (isset($seen[$name])) {
                    throw new \RuntimeException('The deterministic ZIP contains duplicate entry names.');
                }
                $seen[$name] = true;
                if ($name === $requestedEntry) {
                    if ($found !== null) {
                        throw new \RuntimeException('The requested deterministic ZIP entry is duplicated.');
                    }
                    $returnPosition = $cursor + $recordLength;
                    $found = $this->readLocalRecord($handle, $centralOffset, $header, $name);
                    $this->seek($handle, $returnPosition);
                }
                $cursor += $recordLength;
            }
            if ($cursor !== $centralEnd || ftell($handle) !== $centralEnd) {
                throw new \RuntimeException('The deterministic ZIP central directory contains trailing bytes.');
            }
            return $found;
        } finally {
            $this->filesystem->close($handle);
        }
    }

    /** @param array<string,int> $header */
    private function validateCentralRecord(array $header, string $name): void
    {
        $this->safePath($name);
        if (
            ($header['version_made'] ?? -1) !== self::VERSION_MADE_BY_UNIX
            || ($header['version_needed'] ?? -1) !== self::VERSION_NEEDED
            || ($header['flags'] ?? -1) !== self::UTF8_FLAG
            || ($header['method'] ?? -1) !== 0
            || ($header['time'] ?? -1) !== self::DOS_TIME_MIDNIGHT
            || ($header['date'] ?? -1) !== self::DOS_DATE_1980_01_01
            || ($header['extra_length'] ?? -1) !== 0
            || ($header['comment_length'] ?? -1) !== 0
            || ($header['disk_start'] ?? -1) !== 0
            || ($header['internal_attributes'] ?? -1) !== 0
            || ($header['external_attributes'] ?? -1) !== self::UNIX_FILE_ATTRIBUTES
            || ($header['compressed_size'] ?? -1) !== ($header['uncompressed_size'] ?? -2)
        ) {
            throw new \RuntimeException('The archive entry does not conform to EDIS-ZIP-1.');
        }
    }

    /** @param resource $handle @param array<string,int> $central */
    private function readLocalRecord($handle, int $centralOffset, array $central, string $name): string
    {
        $offset = (int) $central['local_offset'];
        if ($offset < 0 || $offset + 30 > $centralOffset) {
            throw new \RuntimeException('The requested ZIP local record offset is invalid.');
        }
        $this->seek($handle, $offset);
        $local = unpack(
            'Vsignature/vversion_needed/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed_size/Vuncompressed_size/vname_length/vextra_length',
            $this->readExact($handle, 30),
        );
        if (!is_array($local) || ($local['signature'] ?? 0) !== 0x04034b50) {
            throw new \RuntimeException('The requested ZIP local record is invalid.');
        }
        if (
            ($local['version_needed'] ?? -1) !== self::VERSION_NEEDED
            || ($local['time'] ?? -1) !== self::DOS_TIME_MIDNIGHT
            || ($local['date'] ?? -1) !== self::DOS_DATE_1980_01_01
        ) {
            throw new \RuntimeException('The ZIP local record does not conform to EDIS-ZIP-1.');
        }
        foreach (['flags', 'method', 'crc', 'compressed_size', 'uncompressed_size'] as $field) {
            if (($local[$field] ?? null) !== ($central[$field] ?? null)) {
                throw new \RuntimeException('The ZIP local and central records do not match.');
            }
        }
        $nameLength = (int) $local['name_length'];
        $extraLength = (int) $local['extra_length'];
        if ($extraLength !== 0) {
            throw new \RuntimeException('EDIS-ZIP-1 local records cannot contain extra fields.');
        }
        $dataOffset = $offset + 30 + $nameLength;
        $size = (int) $local['compressed_size'];
        if ($dataOffset < 30 || $dataOffset + $size > $centralOffset) {
            throw new \RuntimeException('The requested ZIP entry exceeds its declared bounds.');
        }
        $localName = $this->readExact($handle, $nameLength);
        if ($localName !== $name) {
            throw new \RuntimeException('The ZIP local and central entry names do not match.');
        }
        $contents = $this->readExact($handle, $size);
        $crcBytes = hash('crc32b', $contents, true);
        $crcParts = is_string($crcBytes) ? unpack('Ncrc', $crcBytes) : false;
        if (!is_array($crcParts) || (int) ($crcParts['crc'] ?? -1) !== (int) $local['crc']) {
            throw new \RuntimeException('The requested ZIP entry failed CRC32B validation.');
        }
        return $contents;
    }

    /** @param resource $handle */
    private function seek($handle, int $offset): void
    {
        if (fseek($handle, $offset, SEEK_SET) !== 0) {
            throw new \RuntimeException('The deterministic ZIP archive could not be seeked.');
        }
    }

    /** @param resource $handle */
    private function readExact($handle, int $length): string
    {
        if ($length < 0) {
            throw new \RuntimeException('A negative ZIP read length was requested.');
        }
        $bytes = '';
        while (strlen($bytes) < $length) {
            $chunk = fread($handle, min(1024 * 1024, $length - strlen($bytes)));
            if (!is_string($chunk) || $chunk === '') {
                throw new \RuntimeException('The deterministic ZIP archive ended before the declared boundary.');
            }
            $bytes .= $chunk;
        }
        return $bytes;
    }

    private function safePath(string $path): string
    {
        if (
            $path === ''
            || str_contains($path, '\\')
            || str_starts_with($path, '/')
            || preg_match('//u', $path) !== 1
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            throw new \InvalidArgumentException('Unsafe or invalid UTF-8 ZIP entry path.');
        }
        if (strlen($path) >= 2 && ctype_alpha($path[0]) && $path[1] === ':') {
            throw new \InvalidArgumentException('Absolute drive paths are not permitted in ZIP files.');
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Unsafe ZIP entry path segment.');
            }
        }
        return $path;
    }
}
