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
     * @param array<string,mixed> $normalizedRequest
     * @return array{source_raw_sha256:array<string,string>,expires_at:int}|null
     */
    public function verify(string $token, int $ownerId, array $normalizedRequest): ?array
    {
        if ($ownerId <= 0 || strlen($token) > 65536) {
            return null;
        }
        $parts = explode('.', $token);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }
        [$encoded, $encodedSignature] = $parts;
        $signature = $this->base64UrlDecode($encodedSignature);
        if (!is_string($signature)) {
            return null;
        }
        $expected = hash_hmac('sha256', $encoded, $this->secret, true);
        if (!hash_equals($expected, $signature)) {
            return null;
        }
        $bytes = $this->base64UrlDecode($encoded);
        if (!is_string($bytes)) {
            return null;
        }
        try {
            $payload = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($payload)
            || ($payload['format'] ?? null) !== self::FORMAT
            || (int) ($payload['owner_id'] ?? 0) !== $ownerId
            || (int) ($payload['expires_at'] ?? 0) < time()
            || !is_string($payload['request_sha256'] ?? null)
            || !hash_equals((string) $payload['request_sha256'], $this->requestSha256($normalizedRequest))) {
            return null;
        }
        $decodedHashes = is_array($payload['source_raw_sha256'] ?? null) ? $payload['source_raw_sha256'] : [];
        $sourceHashes = [];
        foreach ($decodedHashes as $documentId => $hash) {
            $normalizedDocumentId = is_int($documentId) ? (string) $documentId : $documentId;
            if (!is_string($normalizedDocumentId)
                || !ctype_digit($normalizedDocumentId)
                || !is_string($hash)
                || preg_match('/\Asha256:[a-f0-9]{64}\z/D', $hash) !== 1) {
                return null;
            }
            $sourceHashes[$normalizedDocumentId] = $hash;
        }
        ksort($sourceHashes, SORT_STRING);
        return [
            'source_raw_sha256' => $sourceHashes,
            'expires_at' => (int) $payload['expires_at'],
        ];
    }

    /** @param array<string,mixed> $normalizedRequest */
    private function requestSha256(array $normalizedRequest): string
    {
        return 'sha256:' . hash('sha256', CanonicalJson::encode($normalizedRequest));
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
