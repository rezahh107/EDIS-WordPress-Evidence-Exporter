<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReleaseAuthority314Test extends TestCase
{
    /** T15 + T16 + T17 + T18 */
    public function testManifestAuthoritativeReleaseMutationSuite(): void
    {
        $root = dirname(__DIR__, 2);
        $python = $this->pythonBinary();
        if ($python === null) {
            self::markTestSkipped('python3 is unavailable; release-authority mutation suite cannot execute.');
        }

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open([$python, 'tests/release-authority-314.py'], $descriptor, $pipes, $root);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        self::assertSame(0, $exit, (string) $stderr . "\n" . (string) $stdout);
        foreach ([
            'PASS test_t15_unknown_ordinary_file_fails',
            'PASS test_t16_missing_unsafe_and_symlink_fail',
            'PASS test_t17_all_version_authorities_fail_closed',
            'PASS test_t18_clean_inventory_and_zip_are_deterministic',
        ] as $marker) {
            self::assertStringContainsString($marker, (string) $stdout);
        }
    }

    private function pythonBinary(): ?string
    {
        foreach (['python3', 'python'] as $candidate) {
            $descriptor = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = @proc_open([$candidate, '--version'], $descriptor, $pipes);
            if (!is_resource($process)) {
                continue;
            }
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) === 0) {
                return $candidate;
            }
        }
        return null;
    }
}
