<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use PHPUnit\Framework\TestCase;

final class CrossProcessLockTest extends TestCase
{
    public function testIndependentPhpProcessCannotAcquireHeldLock(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('proc_open is unavailable in this test environment.');
        }
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        if (in_array('proc_open', $disabled, true)) {
            self::markTestSkipped('proc_open is disabled in this test environment.');
        }
        $filesystem = new DeterministicFilesystem();
        $root = sys_get_temp_dir() . '/edis-cross-process-' . bin2hex(random_bytes(4));
        $filesystem->ensureDirectory($root);
        $path = $root . '/probe.lock';
        $parent = $filesystem->open($path, 'c+b');
        self::assertTrue($filesystem->lock($parent, LOCK_EX | LOCK_NB));

        $script = '$h=fopen($argv[1],"c+b");if(!is_resource($h)){exit(2);}';
        $script .= '$locked=flock($h,LOCK_EX|LOCK_NB);fwrite(STDOUT,$locked?"ACQUIRED":"BLOCKED");';
        $script .= 'if($locked){flock($h,LOCK_UN);}fclose($h);';
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-r', $script, $path],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertTrue(is_resource($process));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        $filesystem->lock($parent, LOCK_UN);
        $filesystem->close($parent);
        $filesystem->removeFileIfExists($path);
        $filesystem->removeDirectoryIfEmpty($root);

        self::assertSame(0, $exit, is_string($stderr) ? $stderr : '');
        self::assertSame('BLOCKED', trim((string) $stdout));
    }
}
