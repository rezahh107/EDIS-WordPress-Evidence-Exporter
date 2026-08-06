<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\ExportIntegrityException;
use EDIS\EvidenceExporter\Infrastructure\Support\PreflightProof;
use PHPUnit\Framework\TestCase;

final class PreflightProofTest extends TestCase
{
    public function testProofIsOwnerAndRequestBoundWithStableReasons(): void
    {
        $proof = new PreflightProof(str_repeat('s', 32), 300);
        $request = [
            'privacy_mode' => 'Strict',
            'collectors' => ['environment'],
            'document_ids' => [],
            'options' => ['export_scope' => 'METADATA_ONLY'],
            'inventory' => ['limit' => 500, 'eligible_count_lower_bound' => 0, 'truncated' => false],
        ];
        $token = $proof->issue(7, $request, []);
        $verified = $proof->verify($token, 7, $request);
        self::assertSame([], $verified['source_raw_sha256']);

        $this->assertReason(
            'EDIS_PREFLIGHT_PROOF_OWNER_MISMATCH',
            static fn (): array => $proof->verify($token, 8, $request),
        );

        $changed = $request;
        $changed['privacy_mode'] = 'Diagnostic';
        $this->assertReason(
            'EDIS_PREFLIGHT_PROOF_REQUEST_MISMATCH',
            static fn (): array => $proof->verify($token, 7, $changed),
        );
        $this->assertReason(
            'EDIS_PREFLIGHT_PROOF_SIGNATURE_INVALID',
            static fn (): array => $proof->verify(substr($token, 0, -1) . 'x', 7, $request),
        );
    }

    public function testMalformedExpiredAndMissingSourceAreDistinguished(): void
    {
        $secret = str_repeat('s', 32);
        $proof = new PreflightProof($secret, 300);
        $request = [
            'privacy_mode' => 'Strict',
            'collectors' => ['environment'],
            'document_ids' => [],
            'options' => ['export_scope' => 'METADATA_ONLY'],
            'inventory' => ['limit' => 500, 'eligible_count_lower_bound' => 0, 'truncated' => false],
        ];
        $this->assertReason(
            'EDIS_PREFLIGHT_PROOF_MALFORMED',
            static fn (): array => $proof->verify('not-a-proof', 7, $request),
        );

        $expired = $this->token($secret, [
            'format' => 'EDIS-PREFLIGHT-PROOF-1',
            'owner_id' => 7,
            'issued_at' => time() - 120,
            'expires_at' => time() - 60,
            'request_sha256' => 'sha256:' . hash('sha256', CanonicalJson::encode($request)),
            'source_raw_sha256' => (object) [],
        ]);
        $this->assertReason(
            'EDIS_PREFLIGHT_PROOF_EXPIRED',
            static fn (): array => $proof->verify($expired, 7, $request),
        );

        $sourceRequest = $request;
        $sourceRequest['document_ids'] = [42];
        $sourceRequest['options']['export_scope'] = 'SINGLE_DOCUMENT';
        $sourceToken = $proof->issue(7, $sourceRequest, ['42' => 'sha256:' . str_repeat('a', 64)]);
        $this->assertReason(
            'EDIS_PREFLIGHT_SOURCE_MISSING',
            static fn (): array => $proof->verify($sourceToken, 7, $sourceRequest),
        );
    }

    public function testProofFailureContextNeverContainsProofBytesOrSignatures(): void
    {
        $proof = new PreflightProof(str_repeat('s', 32), 300);
        $request = [
            'privacy_mode' => 'Strict',
            'collectors' => ['environment'],
            'document_ids' => [],
            'options' => ['export_scope' => 'METADATA_ONLY'],
            'inventory' => ['limit' => 500, 'eligible_count_lower_bound' => 0, 'truncated' => false],
        ];
        try {
            $proof->verify('malformed.proof', 7, $request);
            self::fail('Expected proof failure.');
        } catch (ExportIntegrityException $exception) {
            $encoded = CanonicalJson::encode($exception->diagnosticContext);
            foreach (['malformed.proof', 'signature', 'hmac', 'token', 'request_body'] as $secret) {
                self::assertStringNotContainsString($secret, strtolower($encoded));
            }
            self::assertSame('preflight_proof_verification', $exception->diagnosticContext['failure_phase'] ?? null);
        }
    }

    /** @param callable():array $operation */
    private function assertReason(string $expected, callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected ExportIntegrityException with ' . $expected . '.');
        } catch (ExportIntegrityException $exception) {
            self::assertSame($expected, $exception->diagnosticCode);
        }
    }

    /** @param array<string,mixed> $payload */
    private function token(string $secret, array $payload): string
    {
        $encoded = rtrim(strtr(base64_encode(CanonicalJson::encode($payload)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encoded, $secret, true);
        return $encoded . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }
}
