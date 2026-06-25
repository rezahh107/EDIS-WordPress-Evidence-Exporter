<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

use EDIS\EvidenceExporter\Infrastructure\Support\Json\LosslessJsonNode;

final class CanonicalJson
{
    public const PROFILE = 'EDIS-CJ-2';
    public const HASH_ALGORITHM = 'sha256';
    public const OBJECT_KEY_ORDER = 'UTF8_BYTES';
    public const UNICODE_NORMALIZATION = 'NONE';
    public const SEMANTIC_IDENTITY_POLICY_VERSION = '1.0.0';

    private const MAX_SAFE_INTEGER = 9007199254740991;
    /**
     * Operational names are excluded only at contract-defined container roots.
     * Identically named fields deeper inside saved source or addon evidence remain semantic evidence.
     *
     * @var array<string,true>
     */
    private const OPERATIONAL_ROOT_KEYS = [
        'operational_provenance' => true,
        'analysis_set_id' => true,
        'wordpress_bundle_id' => true,
        'job_id' => true,
        'bundle_id' => true,
        'owner_id' => true,
        'user_id' => true,
        'token' => true,
        'token_hash' => true,
        'download_token' => true,
        'download_expires_at' => true,
        'captured_at' => true,
        'selected_at' => true,
        'created_at' => true,
        'updated_at' => true,
        'expires_at' => true,
        'last_heartbeat' => true,
        'last_successful_step_at' => true,
        'last_error_at' => true,
        'next_retry_at' => true,
        'attempt' => true,
        'attempt_count' => true,
        'editor_session_state' => true,
        'editor_unsaved_changes_state' => true,
        'editor_unsaved_changes_detected' => true,
        'editor_selection_source' => true,
    ];

    public static function canonicalizationDescriptor(): array
    {
        return [
            'profile' => self::PROFILE,
            'hash_algorithm' => self::HASH_ALGORITHM,
            'object_key_order' => self::OBJECT_KEY_ORDER,
            'unicode_normalization' => self::UNICODE_NORMALIZATION,
            'number_profile' => 'EXACT_JSON_DECIMAL_OR_IEEE754',
            'semantic_identity_policy_version' => self::SEMANTIC_IDENTITY_POLICY_VERSION,
        ];
    }

    public static function environmentReady(): bool
    {
        if (version_compare(PHP_VERSION, '8.2', '<') || version_compare(PHP_VERSION, '8.6', '>=') || PHP_INT_SIZE < 8 || !function_exists('fsync')) {
            return false;
        }
        $previous = ini_get('serialize_precision');
        if (!is_string($previous)) { return false; }
        if (ini_set('serialize_precision', '-1') === false) { return false; }
        $ready = (string) ini_get('serialize_precision') === '-1';
        $restored = ini_set('serialize_precision', $previous) !== false;
        return $ready && $restored;
    }

    public static function encode(mixed $value): string
    {
        return self::encodeValue($value);
    }

    public static function compareObjectKeys(string $left, string $right): int
    {
        self::assertValidUtf8($left);
        self::assertValidUtf8($right);
        return strcmp($left, $right);
    }

    public static function semanticHash(array|\stdClass $envelope): string
    {
        $diagnostics = [];
        foreach ((self::field($envelope, 'diagnostics', [])) as $diagnostic) {
            if ($diagnostic instanceof \JsonSerializable) { $diagnostic = $diagnostic->jsonSerialize(); }
            if ($diagnostic instanceof \stdClass) { $diagnostic = self::stdClassToArray($diagnostic); }
            if (is_array($diagnostic) && ($diagnostic['scope'] ?? null) === 'SEMANTIC') {
                $diagnostics[] = [
                    'code' => (string) ($diagnostic['code'] ?? ''),
                    'severity' => (string) ($diagnostic['severity'] ?? ''),
                    'scope' => 'SEMANTIC',
                    'message_key' => (string) ($diagnostic['message_key'] ?? ''),
                    'context' => self::objectContext($diagnostic['context'] ?? null),
                ];
            }
        }
        $data = self::field($envelope, 'data');
        $explicit = self::explicitSemanticIdentity($data);
        $semanticData = $explicit['found'] ? $explicit['value'] : self::semanticProjection($data);
        $semantic = [
            'schema_id' => self::field($envelope, 'schema_id'),
            'schema_version' => self::field($envelope, 'schema_version'),
            'artifact_type' => self::field($envelope, 'artifact_type'),
            'canonicalization' => self::field($envelope, 'canonicalization', self::canonicalizationDescriptor()),
            'data' => $semanticData,
            'diagnostics' => $diagnostics,
        ];
        return 'sha256:' . hash('sha256', self::encode($semantic));
    }

    public static function instanceHash(array|\stdClass $envelope): string
    {
        if ($envelope instanceof \stdClass) {
            $copy = clone $envelope;
            unset($copy->artifact_instance_sha256);
        } else {
            $copy = $envelope;
            unset($copy['artifact_instance_sha256']);
        }
        return 'sha256:' . hash('sha256', self::encode($copy));
    }

    /** @param array<string,mixed> $envelope */
    public static function applyHashes(array &$envelope): void
    {
        unset($envelope['semantic_payload_sha256'], $envelope['artifact_instance_sha256']);
        $envelope['semantic_payload_sha256'] = self::semanticHash($envelope);
        $envelope['artifact_instance_sha256'] = self::instanceHash($envelope);
    }

    private static function encodeValue(mixed $value): string
    {
        if ($value instanceof LosslessJsonNode) { return $value->canonicalJson(); }
        if ($value === null) { return 'null'; }
        if ($value === true) { return 'true'; }
        if ($value === false) { return 'false'; }
        if (is_string($value)) {
            self::assertValidUtf8($value);
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (!is_string($encoded)) { throw new \RuntimeException('String encoding failed.'); }
            return $encoded;
        }
        if (is_int($value)) {
            if ($value > self::MAX_SAFE_INTEGER || $value < -self::MAX_SAFE_INTEGER) {
                throw new \InvalidArgumentException('Integers outside the interoperable range must be represented by an exact JSON number node or string.');
            }
            return (string) $value;
        }
        if (is_float($value)) { return self::encodeFloat($value); }
        if ($value instanceof \JsonSerializable) { return self::encodeValue($value->jsonSerialize()); }
        if ($value instanceof \stdClass) {
            $members = [];
            foreach ($value as $key => $item) { $members[] = [(string) $key, $item]; }
            usort($members, static fn (array $a, array $b): int => self::compareObjectKeys($a[0], $b[0]));
            $parts = [];
            foreach ($members as [$key, $item]) { $parts[] = self::encodeValue($key) . ':' . self::encodeValue($item); }
            return '{' . implode(',', $parts) . '}';
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                $parts = [];
                foreach ($value as $item) { $parts[] = self::encodeValue($item); }
                return '[' . implode(',', $parts) . ']';
            }
            $members = [];
            foreach ($value as $key => $item) { $members[] = [(string) $key, $item]; }
            usort($members, static fn (array $a, array $b): int => self::compareObjectKeys($a[0], $b[0]));
            $parts = [];
            foreach ($members as [$key, $item]) { $parts[] = self::encodeValue($key) . ':' . self::encodeValue($item); }
            return '{' . implode(',', $parts) . '}';
        }
        throw new \InvalidArgumentException('Unsupported value in EDIS-CJ-2 canonical JSON.');
    }

    private static function encodeFloat(float $value): string
    {
        if (!is_finite($value)) { throw new \InvalidArgumentException('Non-finite values are not permitted.'); }
        if ($value == 0.0) { return '0'; }
        $previous = ini_get('serialize_precision');
        if (!is_string($previous) || ini_set('serialize_precision', '-1') === false) {
            throw new \RuntimeException('EDIS-CJ-2 cannot establish deterministic PHP number serialization.');
        }
        try { $encoded = json_encode($value, JSON_THROW_ON_ERROR); }
        finally {
            if (ini_set('serialize_precision', $previous) === false) {
                throw new \RuntimeException('EDIS-CJ-2 could not restore PHP serialize_precision.');
            }
        }
        if (!is_string($encoded)) { throw new \RuntimeException('Float encoding failed.'); }
        $encoded = strtolower($encoded);
        if (!str_contains($encoded, 'e')) {
            if (str_contains($encoded, '.')) { $encoded = rtrim(rtrim($encoded, '0'), '.'); }
            return $encoded === '-0' ? '0' : $encoded;
        }
        return self::expandScientific($encoded);
    }

    private static function expandScientific(string $value): string
    {
        $separator = strpos($value, 'e');
        if ($separator === false) { throw new \RuntimeException('Unexpected scientific number representation.'); }
        $mantissa = substr($value, 0, $separator);
        $exponentText = substr($value, $separator + 1);
        $sign = '';
        if (str_starts_with($mantissa, '-')) { $sign = '-'; $mantissa = substr($mantissa, 1); }
        elseif (str_starts_with($mantissa, '+')) { $mantissa = substr($mantissa, 1); }
        if ($mantissa === '' || $exponentText === '' || $exponentText === '+' || $exponentText === '-') {
            throw new \RuntimeException('Unexpected scientific number representation.');
        }
        [$integer, $fraction] = array_pad(explode('.', $mantissa, 2), 2, '');
        if ($integer === '' || !ctype_digit($integer) || ($fraction !== '' && !ctype_digit($fraction))) {
            throw new \RuntimeException('Unexpected scientific number representation.');
        }
        $normalizedExponent = ltrim($exponentText, '+-');
        if ($normalizedExponent === '' || !ctype_digit($normalizedExponent) || strlen($normalizedExponent) > 6) {
            throw new \RuntimeException('Unexpected scientific number representation.');
        }
        $exponent = (int) $exponentText;
        $digits = $integer . $fraction;
        if (strlen($digits) + abs($exponent) > 100000) { throw new \LengthException('Float expansion exceeds the deterministic budget.'); }
        $decimalPosition = strlen($integer) + $exponent;
        if ($decimalPosition <= 0) { $plain = '0.' . str_repeat('0', -$decimalPosition) . $digits; }
        elseif ($decimalPosition >= strlen($digits)) { $plain = $digits . str_repeat('0', $decimalPosition - strlen($digits)); }
        else { $plain = substr($digits, 0, $decimalPosition) . '.' . substr($digits, $decimalPosition); }
        if (str_contains($plain, '.')) { $plain = rtrim(rtrim($plain, '0'), '.'); }
        [$integerPart, $fractionPart] = array_pad(explode('.', $plain, 2), 2, '');
        $integerPart = ltrim($integerPart, '0');
        if ($integerPart === '') { $integerPart = '0'; }
        $plain = $fractionPart === '' ? $integerPart : $integerPart . '.' . $fractionPart;
        return $sign === '-' && $plain !== '0' ? '-' . $plain : $plain;
    }

    /** @return array{found:bool,value:mixed} */
    private static function explicitSemanticIdentity(mixed $data): array
    {
        if ($data instanceof \stdClass) {
            if (property_exists($data, 'semantic_identity')) { return ['found' => true, 'value' => $data->semantic_identity]; }
            if (property_exists($data, 'evidence')) {
                $evidence = $data->evidence;
                if ($evidence instanceof \stdClass && property_exists($evidence, 'semantic_identity')) { return ['found' => true, 'value' => $evidence->semantic_identity]; }
                if (is_array($evidence) && array_key_exists('semantic_identity', $evidence)) { return ['found' => true, 'value' => $evidence['semantic_identity']]; }
            }
        }
        if (is_array($data)) {
            if (array_key_exists('semantic_identity', $data)) { return ['found' => true, 'value' => $data['semantic_identity']]; }
            $evidence = $data['evidence'] ?? null;
            if (is_array($evidence) && array_key_exists('semantic_identity', $evidence)) { return ['found' => true, 'value' => $evidence['semantic_identity']]; }
            if ($evidence instanceof \stdClass && property_exists($evidence, 'semantic_identity')) { return ['found' => true, 'value' => $evidence->semantic_identity]; }
        }
        return ['found' => false, 'value' => null];
    }

    /** @param list<string> $path */
    private static function semanticProjection(mixed $value, array $path = []): mixed
    {
        if ($value instanceof LosslessJsonNode) { return $value; }
        if ($value instanceof \JsonSerializable) { return self::semanticProjection($value->jsonSerialize(), $path); }
        if ($value instanceof \stdClass) {
            $object = new \stdClass();
            foreach ($value as $key => $item) {
                $key = (string) $key;
                $childPath = [...$path, $key];
                if (self::isOperationalProjectionPath($childPath)) { continue; }
                $object->{$key} = self::semanticProjection($item, $childPath);
            }
            return $object;
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                $result = [];
                foreach ($value as $index => $item) {
                    $result[] = self::semanticProjection($item, [...$path, (string) $index]);
                }
                return $result;
            }
            $result = [];
            foreach ($value as $key => $item) {
                $keyText = (string) $key;
                $childPath = [...$path, $keyText];
                if (self::isOperationalProjectionPath($childPath)) { continue; }
                $result[$key] = self::semanticProjection($item, $childPath);
            }
            return $result;
        }
        return $value;
    }

    /** @param list<string> $path */
    private static function isOperationalProjectionPath(array $path): bool
    {
        $count = count($path);
        if ($count === 1) {
            return isset(self::OPERATIONAL_ROOT_KEYS[$path[0]]);
        }
        return $count === 2
            && $path[0] === 'evidence'
            && isset(self::OPERATIONAL_ROOT_KEYS[$path[1]]);
    }

    private static function assertValidUtf8(string $value): void
    {
        if (preg_match('//u', $value) !== 1) { throw new \InvalidArgumentException('Invalid UTF-8 is not permitted by EDIS-CJ-2.'); }
    }


    private static function field(array|\stdClass $container, string $name, mixed $default = null): mixed
    {
        if ($container instanceof \stdClass) {
            return property_exists($container, $name) ? $container->{$name} : $default;
        }
        return array_key_exists($name, $container) ? $container[$name] : $default;
    }

    /** @return array<string,mixed> */
    private static function stdClassToArray(\stdClass $object): array
    {
        $result = [];
        foreach ($object as $key => $value) { $result[(string) $key] = $value; }
        return $result;
    }

    private static function objectContext(mixed $value): object
    {
        if ($value instanceof \stdClass) { return $value; }
        if (is_array($value) && ($value === [] || !array_is_list($value))) { return (object) $value; }
        return (object) [];
    }
}
