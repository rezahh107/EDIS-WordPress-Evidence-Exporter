<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

final class PreflightProof
{
    private const FORMAT = 'EDIS-PREFLIGHT-PROOF-1';

    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds = 300,
    ) {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('Preflight proof secret must contain at least 32 bytes.');
        }
    }

    public static function fromWordPress(): ?self
    {
        $secret = '';
        if (function_exists('wp_salt')) {
            try {
                $secret = (string) wp_salt('auth');
            } catch (\Throwable) {
                $secret = '';
            }
        }
        if ($secret === '') {
            foreach (['AUTH_SALT', 'SECURE_AUTH_SALT', 'NONCE_SALT'] as $constant) {
                if (defined($constant) && is_string(constant($constant))) {
                    $secret .= (string) constant($constant);
                }
            }
        }
        return strlen($secret) >= 32 ? new self(hash('sha256', $secret, true)) : null;
    }

    /**
     * @param array<string,mixed> $normalizedRequest
     * @param array<string,string> $sourceHashes
     */
    public function issue(int $ownerId, array $normalizedRequest, array $sourceHashes): string
    {
        if ($ownerId <= 0) {
            throw new \InvalidArgumentException('A positive proof owner is required.');
        }
        ksort($sourceHashes, SORT_STRING);
        $issuedAt = time();
        $payload = [
            'format' => self::FORMAT,
            'owner_id' => $ownerId,
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt + max(30, min(900, $this->ttlSeconds)),
            'request_sha256' => $this->requestSha256($normalizedRequest),
            'source_raw_sha256' => $sourceHashes === [] ? (object) [] : $sourceHashes,
        ];
        $bytes = CanonicalJson::encode($payload);
        $encoded = $this->base64UrlEncode($bytes);
        $signature = hash_hmac('sha256', $encoded, $this->secret, true);
        return $encoded . '.' . $this->base64UrlEncode($signature);
    }

    /**
     * Structured internal proof result. It never returns proof bytes, signatures,
     * tokens, request bodies, document source, or hashes to public consumers.
     *
     * @param array<string,mixed> $normalizedRequest
     * @return array{valid:bool,reason_code:string,lifecycle_stage:string,source_raw_sha256:array<string,string>,expires_at:int,safe_context:array<string,scalar|list<scalar>|null>}
     */
    public function inspect(string $token, int $ownerId, array $normalizedRequest): array
    {
        if ($ownerId <= 0) {
            return $this->failure('EDIS_PREFLIGHT_PROOF_OWNER_INVALID', 'preflight_proof_verification', ['failed_checks' => ['owner']]);
        }
        if ($token === '' || strlen($token) > 65536) {
            return $this->failure('EDIS_PREFLIGHT_PROOF_MALFORMED', 'preflight_proof_verification', ['failed_checks' => ['token_shape']]);
        }
        $parts = explode('.', $token);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return $this->failure('EDIS_PREFLIGHT_PROOF_MALFORMED', 'preflight_proof_verification', ['failed_checks' => ['token_shape']]);
        }
        [$encoded, $encodedSignature] = $parts;
        $signature = $this->base64UrlDecode($encodedSignature);
        if (!is_string($signature)) {
            return $this->failure('EDIS_PREFLIGHT_PROOF_MALFORMED', 'preflight_proof_verification', ['failed_checks' => ['signature_encoding']]);
        }
        $expected = hash_hmac('sha256', $encoded, $this->secret, true);
        if (!hash_equals($expected, $signature)) {
            return $this->failure('EDIS_PREFLIGHT_PROOF_SIGNATURE_INVALID', 'preflight_proof_verification', ['failed_checks' => ['signature']]);
        }
        $bytes = $this->base64UrlDecode($encoded);
        if (!is_string($bytes)) {
            return $this->failure('EDIS_PREFLIGHT_PROOF_PAYLOAD_INVALID', 'preflight_proof_verification', ['failed_checks' => ['payload_encoding']]);
        }
        try {
            $payload = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $this->failure('EDIS_PREFLIGHT_PROOF_PAYLOAD_INVALID', 'preflight_proof_verification', ['failed_checks' => ['payload_json']]);
        }
        if (!is_array($payload) || ($payload['format'] ?? null) !== self::FORMAT) {
            return $this->failure('EDIS_PREFLIGHT_PROOF_PAYLOAD_INVALID', 'preflight_proof_verification', ['failed_checks' => ['format']]);
        }
        if ((int) ($payload['owner_id'] ?? 0) !== $ownerId) {
            return $this->failure('EDIS_PREFLIGHT_PROOF_OWNER_MISMATCH', 'preflight_proof_verification', ['failed_checks' => ['owner_match']]);
        }
        $expiresAt = (int) ($payload['expires_at'] ?? 0);
        if ($expiresAt < time()) {
            return $this->failure('EDIS_PREFLIGHT_PROOF_EXPIRED', 'preflight_proof_verification', ['failed_checks' => ['expiry']]);
        }
        $requestHash = $payload['request_sha256'] ?? null;
        if (!is_string($requestHash) || !hash_equals($requestHash, $this->requestSha256($normalizedRequest))) {
            return $this->failure('EDIS_PREFLIGHT_PROOF_REQUEST_MISMATCH', 'preflight_proof_verification', ['failed_checks' => ['request_match']]);
        }

        $decodedHashes = is_array($payload['source_raw_sha256'] ?? null) ? $payload['source_raw_sha256'] : [];
        $sourceHashes = [];
        foreach ($decodedHashes as $documentId => $hash) {
            $normalizedDocumentId = is_int($documentId) ? (string) $documentId : $documentId;
            if (!is_string($normalizedDocumentId)
                || !ctype_digit($normalizedDocumentId)
                || (int) $normalizedDocumentId <= 0
                || !is_string($hash)
                || preg_match('/\Asha256:[a-f0-9]{64}\z/D', $hash) !== 1) {
                return $this->failure('EDIS_PREFLIGHT_PROOF_SOURCE_SET_INVALID', 'preflight_source_revalidation', ['failed_checks' => ['source_hash_contract']]);
            }
            $sourceHashes[$normalizedDocumentId] = $hash;
        }
        ksort($sourceHashes, SORT_STRING);

        $documents = [];
        foreach ((array) ($normalizedRequest['document_ids'] ?? []) as $value) {
            if (is_int($value) && $value > 0) {
                $documents[] = (string) $value;
            }
        }
        sort($documents, SORT_STRING);
        $proofIds = array_keys($sourceHashes);
        sort($proofIds, SORT_STRING);
        if ($documents !== $proofIds) {
            return $this->failure('EDIS_PREFLIGHT_PROOF_DOCUMENT_SET_MISMATCH', 'preflight_source_revalidation', [
                'failed_checks' => ['document_set'],
                'selected_document_count' => count($documents),
            ]);
        }

        foreach ($sourceHashes as $documentId => $hash) {
            $raw = $this->currentRawSourceBytes((int) $documentId);
            if ($raw === null) {
                return $this->failure('EDIS_PREFLIGHT_SOURCE_MISSING', 'preflight_source_revalidation', [
                    'failed_checks' => ['source_exists'],
                    'selected_document_count' => count($documents),
                ]);
            }
            if (!hash_equals($hash, 'sha256:' . hash('sha256', $raw))) {
                return $this->failure('EDIS_PREFLIGHT_SOURCE_CHANGED', 'preflight_source_revalidation', [
                    'failed_checks' => ['source_hash'],
                    'selected_document_count' => count($documents),
                ]);
            }
        }

        return [
            'valid' => true,
            'reason_code' => 'EDIS_PREFLIGHT_PROOF_VALID',
            'lifecycle_stage' => 'preflight_source_revalidation',
            'source_raw_sha256' => $sourceHashes,
            'expires_at' => $expiresAt,
            'safe_context' => ['selected_document_count' => count($documents)],
        ];
    }

    /**
     * Compatibility adapter used by the existing Job-creation service. Invalid
     * proofs now preserve their source-owned reason and orchestration stage.
     *
     * @param array<string,mixed> $normalizedRequest
     * @return array{source_raw_sha256:array<string,string>,expires_at:int}
     */
    public function verify(string $token, int $ownerId, array $normalizedRequest): array
    {
        $result = $this->inspect($token, $ownerId, $normalizedRequest);
        if ($result['valid'] !== true) {
            $context = $result['safe_context'];
            $context['failure_phase'] = $result['lifecycle_stage'];
            throw new ExportIntegrityException(
                $result['reason_code'],
                'The bounded Preflight proof contract was not satisfied.',
                null,
                $context,
            );
        }
        return [
            'source_raw_sha256' => $result['source_raw_sha256'],
            'expires_at' => $result['expires_at'],
        ];
    }

    /** @param array<string,mixed> $normalizedRequest */
    private function requestSha256(array $normalizedRequest): string
    {
        return 'sha256:' . hash('sha256', CanonicalJson::encode($normalizedRequest));
    }

    private function currentRawSourceBytes(int $documentId): ?string
    {
        if (!function_exists('get_post_meta')) {
            return null;
        }
        $raw = get_post_meta($documentId, '_elementor_data', true);
        if (is_string($raw)) {
            return $raw !== '' ? $raw : null;
        }
        if (is_array($raw) || $raw instanceof \stdClass) {
            return CanonicalJson::encode($raw);
        }
        return null;
    }

    /** @param array<string,scalar|list<scalar>|null> $context */
    private function failure(string $code, string $stage, array $context): array
    {
        return [
            'valid' => false,
            'reason_code' => $code,
            'lifecycle_stage' => $stage,
            'source_raw_sha256' => [],
            'expires_at' => 0,
            'safe_context' => $context + ['validation_stage' => $stage],
        ];
    }

    private function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/\A[A-Za-z0-9_-]+\z/D', $value) !== 1) {
            return null;
        }
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if (!is_string($decoded) || $this->base64UrlEncode($decoded) !== rtrim($value, '=')) {
            return null;
        }
        return $decoded;
    }
}
