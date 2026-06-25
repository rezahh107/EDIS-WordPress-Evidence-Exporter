<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/tools/validation/ValidationSupport.php';

final class ValidationEvidenceHardeningTest extends TestCase
{
    private ?string $temporary = null;

    protected function tearDown(): void
    {
        if ($this->temporary !== null && is_dir($this->temporary)) {
            $this->remove($this->temporary);
        }
    }

    public function testSkippedRequiredLocalGateIsIncompleteNotPass(): void
    {
        $gates = $this->allPassGates();
        $gates['npm_ci'] = ['state' => 'NOT_RUN', 'reason' => 'skipped_by_command_line'];

        $summary = \EdisValidationSummary::summarize($gates);

        self::assertSame('INCOMPLETE', $summary['local_state']);
        self::assertContains('npm_ci', $summary['incomplete_local_gates']);
        self::assertFalse($summary['production_ready_verified']);
    }

    public function testExternalNotRunDoesNotChangeCompletedLocalState(): void
    {
        $gates = $this->allPassGates();
        $gates['plugin_check'] = ['state' => 'NOT_RUN', 'reason' => 'requires_runtime'];

        $summary = \EdisValidationSummary::summarize($gates);

        self::assertSame('PASS', $summary['local_state']);
        self::assertSame('NOT_RUN', $summary['external_state']);
        self::assertContains('plugin_check', $summary['unresolved_external_gates']);
        self::assertFalse($summary['production_ready_verified']);
    }

    public function testAllRequiredGatesPassPromotesProductionReady(): void
    {
        $summary = \EdisValidationSummary::summarize($this->allPassGates());

        self::assertSame('PASS', $summary['local_state']);
        self::assertSame('PASS', $summary['external_state']);
        self::assertTrue($summary['production_ready_verified']);
    }

    public function testLargeStdoutAndStderrAreCapturedWithoutPipeDeadlock(): void
    {
        $result = \EdisValidationProcess::run(
            dirname(__DIR__, 2),
            ['php', '-r', 'fwrite(STDOUT, str_repeat("O", 262144)); fwrite(STDERR, str_repeat("E", 262144));'],
        );

        self::assertTrue($result['started'] ?? false);
        self::assertSame(0, $result['exit_code'] ?? null);
        self::assertSame(262144, $result['stdout_bytes'] ?? null);
        self::assertSame(262144, $result['stderr_bytes'] ?? null);
        self::assertIsString($result['stdout_sha256'] ?? null);
        self::assertIsString($result['stderr_sha256'] ?? null);
    }

    public function testEvidenceWriterAtomicallyReplacesAndVerifiesReport(): void
    {
        $this->temporary = sys_get_temp_dir() . '/edis-validation-writer-' . bin2hex(random_bytes(6));
        mkdir($this->temporary, 0700, true);
        $target = $this->temporary . '/evidence.json';
        file_put_contents($target, "old\n");

        \EdisValidationEvidenceWriter::write($target, "{\"state\":\"PASS\"}\n");

        self::assertSame("{\"state\":\"PASS\"}\n", file_get_contents($target));
        self::assertSame(hash('sha256', "{\"state\":\"PASS\"}\n"), hash_file('sha256', $target));
    }

    /** @return array<string,array<string,string>> */
    private function allPassGates(): array
    {
        $gates = [];
        foreach (array_merge(\EdisValidationSummary::LOCAL_REQUIRED, \EdisValidationSummary::EXTERNAL_REQUIRED) as $id) {
            $gates[$id] = ['state' => 'PASS'];
        }
        return $gates;
    }

    private function remove(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->remove($path . '/' . $entry);
        }
        rmdir($path);
    }
}
