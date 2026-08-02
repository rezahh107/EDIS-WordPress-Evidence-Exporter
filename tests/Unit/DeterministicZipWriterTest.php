<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support {
    /** @return array<string,mixed>|null */
    function directoryRaceState(): ?array
    {
        $state = $GLOBALS['edis_directory_race_state'] ?? null;
        return is_array($state) ? $state : null;
    }

    function is_dir(string $directory): bool
    {
        $state = directoryRaceState();
        if (is_array($state) && $directory === ($state['directory'] ?? null)) {
            $result = array_shift($GLOBALS['edis_directory_race_state']['is_dir']);
            return (bool) $result;
        }
        return \is_dir($directory);
    }

    function is_link(string $directory): bool
    {
        $state = directoryRaceState();
        if (is_array($state) && $directory === ($state['directory'] ?? null)) {
            return (bool) ($state['is_link'] ?? false);
        }
        return \is_link($directory);
    }

    function mkdir(string $directory, int $permissions = 0777, bool $recursive = false): bool
    {
        $state = directoryRaceState();
        if (is_array($state) && $directory === ($state['directory'] ?? null)) {
            ++$GLOBALS['edis_directory_race_state']['mkdir_calls'];
            return (bool) ($state['mkdir_result'] ?? false);
        }
        return \mkdir($directory, $permissions, $recursive);
    }

    function chmod(string $path, int $permissions): bool
    {
        $state = directoryRaceState();
        if (is_array($state) && $path === ($state['directory'] ?? null)) {
            ++$GLOBALS['edis_directory_race_state']['chmod_calls'];
            return (bool) ($state['chmod_result'] ?? true);
        }
        return \chmod($path, $permissions);
    }
}

namespace EDIS\EvidenceExporter\Tests\Unit {

use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicZipReader;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicZipWriter;
use PHPUnit\Framework\TestCase;

final class DeterministicZipWriterTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['edis_directory_race_state']);
    }

    public function testConcurrentDirectoryCreationDoesNotTransferOwnership(): void
    {
        $directory = '/simulated/concurrently-created';
        $this->simulateDirectoryCreation($directory, [false, true, true], false);

        (new DeterministicFilesystem())->ensureDirectory($directory);

        self::assertSame(1, $GLOBALS['edis_directory_race_state']['mkdir_calls']);
        self::assertSame(0, $GLOBALS['edis_directory_race_state']['chmod_calls']);
    }

    public function testSuccessfullyCreatedDirectoryReceivesPermissions(): void
    {
        $directory = '/simulated/created-here';
        $this->simulateDirectoryCreation($directory, [false, true], true);

        (new DeterministicFilesystem())->ensureDirectory($directory);

        self::assertSame(1, $GLOBALS['edis_directory_race_state']['mkdir_calls']);
        self::assertSame(1, $GLOBALS['edis_directory_race_state']['chmod_calls']);
    }

    public function testExistingCallerOwnedDirectoryDoesNotReceivePermissions(): void
    {
        $directory = '/simulated/caller-owned';
        $this->simulateDirectoryCreation($directory, [true, true], false);

        (new DeterministicFilesystem())->ensureDirectory($directory);

        self::assertSame(0, $GLOBALS['edis_directory_race_state']['mkdir_calls']);
        self::assertSame(0, $GLOBALS['edis_directory_race_state']['chmod_calls']);
    }

    public function testExplicitlyOwnedDirectoryReceivesEnforcedPermissions(): void
    {
        $directory = '/simulated/edis-owned';
        $this->simulateDirectoryCreation($directory, [true, true], false);

        (new DeterministicFilesystem())->ensureDirectory($directory, 0750, true);

        self::assertSame(0, $GLOBALS['edis_directory_race_state']['mkdir_calls']);
        self::assertSame(1, $GLOBALS['edis_directory_race_state']['chmod_calls']);
    }

    public function testMissingDirectoryAfterFailedCreationRemainsAnError(): void
    {
        $directory = '/simulated/missing';
        $this->simulateDirectoryCreation($directory, [false, false], false);

        $this->expectException(\EDIS\EvidenceExporter\Infrastructure\Support\FilesystemException::class);
        (new DeterministicFilesystem())->ensureDirectory($directory);
    }

    public function testSymlinkDirectoryRemainsRejected(): void
    {
        $directory = '/simulated/symlink';
        $this->simulateDirectoryCreation($directory, [], false, true);

        $this->expectException(\EDIS\EvidenceExporter\Infrastructure\Support\FilesystemException::class);
        (new DeterministicFilesystem())->ensureDirectory($directory);
    }

    /** @param list<bool> $isDirectory */
    private function simulateDirectoryCreation(string $directory, array $isDirectory, bool $mkdirResult, bool $isLink = false): void
    {
        $GLOBALS['edis_directory_race_state'] = [
            'directory' => $directory,
            'is_dir' => $isDirectory,
            'is_link' => $isLink,
            'mkdir_result' => $mkdirResult,
            'mkdir_calls' => 0,
            'chmod_calls' => 0,
            'chmod_result' => true,
        ];
    }

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

    public function testWritingZipDoesNotChmodExistingCallerOwnedParent(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('POSIX directory modes are not available on Windows.');
        }
        $directory = sys_get_temp_dir() . '/edis-caller-owned-' . bin2hex(random_bytes(6));
        mkdir($directory, 0770, true);
        chmod($directory, 0770);
        $path = $directory . '/evidence.zip';
        try {
            (new DeterministicZipWriter())->writeToFile(
                $path,
                ['manifest.json' => '{}'],
                new DeterministicFilesystem(),
            );
            clearstatcache(true, $directory);
            self::assertSame(0770, fileperms($directory) & 0777);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
            if (is_dir($directory)) {
                rmdir($directory);
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
}
