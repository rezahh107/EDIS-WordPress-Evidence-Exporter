<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicZipReader;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicZipWriter;
use PHPUnit\Framework\TestCase;

final class DeterministicZipWriterTest extends TestCase
{
    public function testOrderDoesNotChangeArchiveBytes(): void
    {
        $writer = new DeterministicZipWriter();
        $first = $writer->build(['b.txt' => 'b', 'a.txt' => 'a']);
        $second = $writer->build(['a.txt' => 'a', 'b.txt' => 'b']);
        self::assertSame($first, $second);
        self::assertStringStartsWith("PK\x03\x04", $first);
        self::assertSame("PK\x05\x06", substr($first, -22, 4));
    }

    public function testStreamingFileOutputMatchesCompatibilityBuild(): void
    {
        $files = [
            'b.txt' => str_repeat('b', 1024 * 1024),
            'a.txt' => 'a',
            'bridge/source-context.json' => '{"ok":true}',
        ];
        $writer = new DeterministicZipWriter();
        $expected = $writer->build($files);
        $path = sys_get_temp_dir() . '/edis-streaming-zip-' . bin2hex(random_bytes(6)) . '.zip';
        try {
            $result = $writer->writeToFile($path, $files, new DeterministicFilesystem());
            self::assertSame('sha256:' . hash('sha256', $expected), $result['sha256']);
            self::assertSame(strlen($expected), $result['size']);
            self::assertSame($expected, file_get_contents($path));
            self::assertSame('{"ok":true}', (new DeterministicZipReader())->readStoredEntry($path, 'bridge/source-context.json'));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testNormalizedPathCollisionsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DeterministicZipWriter())->build(['a\\b.json' => 'one', 'a/b.json' => 'two']);
    }

    public function testUnsafePathsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DeterministicZipWriter())->build(['../escape.json' => '{}']);
    }

    public function testControlCharactersInPathsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DeterministicZipWriter())->build(["unsafe\nname.json" => '{}']);
    }

    public function testNonProfileCentralMetadataIsRejected(): void
    {
        $archive = (new DeterministicZipWriter())->build(['bridge/source-context.json' => '{}']);
        $central = strpos($archive, "PK\x01\x02");
        self::assertTrue(is_int($central));
        $archive[$central + 4] = "\x15"; // change version-made-by from 0x0314 to 0x0315
        $path = sys_get_temp_dir() . '/edis-deterministic-reader-profile-' . bin2hex(random_bytes(6)) . '.zip';
        file_put_contents($path, $archive);
        try {
            $this->expectException(\RuntimeException::class);
            (new DeterministicZipReader())->readStoredEntry($path, 'bridge/source-context.json');
        } finally {
            if (is_file($path)) { unlink($path); }
        }
    }

    public function testStoredEntryCanBeReadWithoutZipArchive(): void
    {
        $archive = (new DeterministicZipWriter())->build([
            'bridge/source-context.json' => '{"ok":true}',
            'manifest.json' => '{}',
        ]);
        $path = sys_get_temp_dir() . '/edis-deterministic-reader-' . bin2hex(random_bytes(6)) . '.zip';
        file_put_contents($path, $archive);
        try {
            $reader = new DeterministicZipReader();
            self::assertSame('{"ok":true}', $reader->readStoredEntry($path, 'bridge/source-context.json'));
            self::assertNull($reader->readStoredEntry($path, 'missing.json'));
        } finally {
            if (is_file($path)) { unlink($path); }
        }
    }

    public function testStoredEntryCrcMismatchIsRejected(): void
    {
        $archive = (new DeterministicZipWriter())->build(['bridge/source-context.json' => '{"ok":true}']);
        $position = strpos($archive, '{"ok":true}');
        self::assertTrue(is_int($position));
        $archive[$position] = '[';
        $path = sys_get_temp_dir() . '/edis-deterministic-reader-corrupt-' . bin2hex(random_bytes(6)) . '.zip';
        file_put_contents($path, $archive);
        try {
            $this->expectException(\RuntimeException::class);
            (new DeterministicZipReader())->readStoredEntry($path, 'bridge/source-context.json');
        } finally {
            if (is_file($path)) { unlink($path); }
        }
    }
}
