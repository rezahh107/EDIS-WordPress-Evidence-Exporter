<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\PreflightProof;
use PHPUnit\Framework\TestCase;

final class PreflightProofTest extends TestCase
{
    public function testProofIsOwnerRequestAndSourceBound(): void
    {
        $proof = new PreflightProof(str_repeat('s', 32), 300);
        $request = [
            'privacy_mode' => 'Strict',
            'collectors' => ['environment'],
            'document_ids' => [42],
            'options' => ['export_scope' => 'SINGLE_DOCUMENT'],
            'inventory' => ['limit' => 500, 'eligible_count_lower_bound' => 0, 'truncated' => false],
        ];
        $sources = ['42' => 'sha256:' . str_repeat('a', 64)];
        $token = $proof->issue(7, $request, $sources);
        $verified = $proof->verify($token, 7, $request);
        self::assertIsArray($verified);
        self::assertSame($sources, $verified['source_raw_sha256']);
        self::assertNull($proof->verify($token, 8, $request));

        $changed = $request;
        $changed['privacy_mode'] = 'Diagnostic';
        self::assertNull($proof->verify($token, 7, $changed));
        self::assertNull($proof->verify(substr($token, 0, -1) . 'x', 7, $request));
    }
}
