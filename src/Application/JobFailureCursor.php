<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Application;

use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;

final class JobFailureCursor
{
    /**
     * @param array<string,mixed> $job
     * @return array{revision:int,signature:string,state:array<string,mixed>}
     */
    public static function capture(array $job): array
    {
        $diagnostics = array_values(array_filter((array) ($job['diagnostics'] ?? []), 'is_array'));
        $latest = $diagnostics === [] ? [] : (array) $diagnostics[array_key_last($diagnostics)];
        $context = is_array($latest['context'] ?? null) ? $latest['context'] : [];
        $state = [
            'status' => self::scalarString($job['status'] ?? null),
            'phase' => self::scalarString($job['phase'] ?? null),
            'current_component' => self::scalarString($job['current_component'] ?? null),
            'last_error_code' => self::scalarString($job['last_error_code'] ?? null),
            'last_error_at' => is_numeric($job['last_error_at'] ?? null) ? (int) $job['last_error_at'] : null,
            'diagnostic_code' => self::scalarString($latest['code'] ?? null),
            'diagnostic_scope' => self::scalarString($latest['scope'] ?? null),
            'diagnostic_context' => self::boundedContext($context),
        ];
        return [
            'revision' => max(0, (int) ($job['revision'] ?? 0)),
            'signature' => 'sha256:' . hash('sha256', CanonicalJson::encode($state)),
            'state' => $state,
        ];
    }

    /** @param array<string,mixed>|null $before @param array<string,mixed> $after */
    public static function changed(?array $before, array $after): bool
    {
        if (!is_array($before)
            || !is_int($before['revision'] ?? null)
            || !is_string($before['signature'] ?? null)) {
            return true;
        }
        $current = self::capture($after);
        return $current['revision'] !== $before['revision']
            || !hash_equals($current['signature'], $before['signature']);
    }

    private static function scalarString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : substr($value, 0, 128);
    }

    /** @param array<string,mixed> $context @return array<string,string|int|bool|null> */
    private static function boundedContext(array $context): array
    {
        $safe = [];
        foreach (array_slice($context, 0, 16, true) as $key => $value) {
            if (!is_string($key) || preg_match('/\\A[a-zA-Z0-9_.:-]{1,64}\\z/D', $key) !== 1) {
                continue;
            }
            if (is_bool($value) || is_int($value) || $value === null) {
                $safe[$key] = $value;
            } elseif (is_string($value)) {
                $safe[$key] = substr($value, 0, 128);
            }
        }
        ksort($safe, SORT_STRING);
        return $safe;
    }
}
