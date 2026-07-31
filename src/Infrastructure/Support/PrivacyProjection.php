<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

/**
 * Deterministic, recursive privacy projection for exported source evidence.
 *
 * The private input remains untouched. This class only produces an exported
 * view and bounded suppression metadata; removed values are never retained in
 * diagnostics or projection summaries.
 */
final class PrivacyProjection
{
    /** @var list<string> */
    private const UNIVERSAL_CREDENTIAL_KEYS = [
        'access_token', 'accesstoken', 'api_key', 'apikey', 'api_token', 'apitoken',
        'auth_token', 'authtoken', 'authorization', 'bearer_token', 'client_secret',
        'credential', 'credentials', 'cookie', 'cookies', 'nonce', 'password',
        'passwd', 'private_key', 'refresh_token', 'refreshtoken', 'secret',
        'session_id', 'session_token', 'token',
    ];

    /** @var list<string> */
    private const UNIVERSAL_CREDENTIAL_FRAGMENTS = [
        'access_token', 'api_key', 'api_token', 'auth_token', 'bearer_token',
        'client_secret', 'credential', 'cookie', 'nonce', 'password', 'passwd',
        'private_key', 'refresh_token', 'session_token',
    ];

    /** @var list<string> */
    private const STRICT_DIRECT_PARTS = [
        'custom_code', 'custom_html', 'custom_js', 'embed_code', 'html_code',
        'tracking', 'analytics', 'pixel', 'webhook', 'recipient', 'contact',
    ];

    /** @var list<string> */
    private const STRICT_CONTENT_KEYS = [
        'caption', 'content', 'description', 'editor', 'heading', 'html',
        'message', 'subtitle', 'text', 'title',
    ];

    /** @var list<string> */
    private const STRICT_FORM_VALUE_KEYS = [
        'default', 'default_value', 'label', 'option', 'options', 'placeholder',
        'value', 'values',
    ];

    /**
     * @return array{value:mixed,summary:array{suppressed_count:int,categories:object,path_classes:object}}
     */
    public function projectWithSummary(mixed $value, string $privacyMode): array
    {
        $summary = [
            'suppressed_count' => 0,
            'categories' => [],
            'path_classes' => [],
        ];
        $projected = $this->walk($value, [], $privacyMode === 'Strict', $summary);
        ksort($summary['categories'], SORT_STRING);
        ksort($summary['path_classes'], SORT_STRING);

        return [
            'value' => $projected,
            'summary' => [
                'suppressed_count' => $summary['suppressed_count'],
                'categories' => (object) $summary['categories'],
                'path_classes' => (object) $summary['path_classes'],
            ],
        ];
    }

    public function project(mixed $value, string $privacyMode): mixed
    {
        return $this->projectWithSummary($value, $privacyMode)['value'];
    }

    /**
     * @param list<string> $path
     * @param array{suppressed_count:int,categories:array<string,int>,path_classes:array<string,int>} $summary
     */
    private function walk(mixed $value, array $path, bool $strict, array &$summary): mixed
    {
        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
            $projected = $this->walkMap($value, $path, $strict, $summary);
            return (object) $projected;
        }
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            $rows = [];
            foreach ($value as $index => $child) {
                $rows[] = $this->walk($child, [...$path, (string) $index], $strict, $summary);
            }
            return $rows;
        }
        return $this->walkMap($value, $path, $strict, $summary);
    }

    /**
     * @param array<array-key,mixed> $value
     * @param list<string> $path
     * @param array{suppressed_count:int,categories:array<string,int>,path_classes:array<string,int>} $summary
     * @return array<array-key,mixed>
     */
    private function walkMap(array $value, array $path, bool $strict, array &$summary): array
    {
        $projected = [];
        foreach ($value as $key => $child) {
            $keyString = (string) $key;
            $normalizedKey = $this->normalize($keyString);
            $nextPath = [...$path, $normalizedKey];
            $category = $this->suppressionCategory($normalizedKey, $nextPath, $strict);
            if ($category !== null) {
                $this->recordSuppression($category, $nextPath, $summary);
                continue;
            }
            $projected[$key] = $this->walk($child, $nextPath, $strict, $summary);
        }
        return $projected;
    }

    /** @param list<string> $path */
    private function suppressionCategory(string $key, array $path, bool $strict): ?string
    {
        if ($this->isCredentialKey($key)) {
            return 'CREDENTIAL';
        }
        if (!$strict) {
            return null;
        }
        if ($this->containsPart($key, self::STRICT_DIRECT_PARTS)) {
            return 'STRICT_CUSTOM_OR_CONTACT';
        }
        if (in_array($key, self::STRICT_CONTENT_KEYS, true)) {
            return 'STRICT_CONTENT';
        }
        if (($key === 'url' || str_ends_with($key, '_url') || str_ends_with($key, '_uri')) && $this->pathLooksContentBearing($path)) {
            return 'STRICT_CONTACT_OR_CONTENT_URL';
        }
        if (in_array($key, self::STRICT_FORM_VALUE_KEYS, true) && $this->pathLooksFormBearing($path)) {
            return 'STRICT_FORM_VALUE';
        }
        if ($this->looksLikeContactValueKey($key)) {
            return 'STRICT_CONTACT_VALUE';
        }
        return null;
    }

    private function isCredentialKey(string $key): bool
    {
        if (in_array($key, self::UNIVERSAL_CREDENTIAL_KEYS, true)) {
            return true;
        }
        foreach (self::UNIVERSAL_CREDENTIAL_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }
        if (str_ends_with($key, '_secret') || str_ends_with($key, '_password')) {
            return true;
        }
        return false;
    }

    /** @param list<string> $parts */
    private function containsPart(string $key, array $parts): bool
    {
        foreach ($parts as $part) {
            if ($key === $part || str_contains($key, $part)) {
                return true;
            }
        }
        return false;
    }

    /** @param list<string> $path */
    private function pathLooksFormBearing(array $path): bool
    {
        foreach ($path as $part) {
            if ($this->containsPart($part, ['form', 'field', 'input', 'textarea', 'select', 'contact', 'mail', 'email', 'phone', 'address'])) {
                return true;
            }
        }
        return false;
    }

    /** @param list<string> $path */
    private function pathLooksContentBearing(array $path): bool
    {
        foreach ($path as $part) {
            if ($this->containsPart($part, ['content', 'media', 'image', 'video', 'audio', 'link', 'button', 'form', 'contact', 'social'])) {
                return true;
            }
        }
        return false;
    }

    private function looksLikeContactValueKey(string $key): bool
    {
        return $this->containsPart($key, [
            'email', 'e_mail', 'phone', 'telephone', 'mobile', 'address',
            'recipient', 'reply_to', 'mailto',
        ]);
    }

    private function normalize(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? $key;
        return trim($key, '_');
    }

    /**
     * @param list<string> $path
     * @param array{suppressed_count:int,categories:array<string,int>,path_classes:array<string,int>} $summary
     */
    private function recordSuppression(string $category, array $path, array &$summary): void
    {
        $summary['suppressed_count']++;
        $summary['categories'][$category] = ($summary['categories'][$category] ?? 0) + 1;
        $pathClass = $this->pathClass($path);
        $summary['path_classes'][$pathClass] = ($summary['path_classes'][$pathClass] ?? 0) + 1;
    }

    /** @param list<string> $path */
    private function pathClass(array $path): string
    {
        foreach ($path as $part) {
            if ($this->pathLooksFormBearing([$part])) {
                return 'FORM_OR_CONTACT';
            }
            if ($this->containsPart($part, ['settings', 'style', 'background', 'typography', 'layout'])) {
                return 'SETTINGS_OR_STYLE';
            }
            if ($this->containsPart($part, ['element', 'widget', 'children'])) {
                return 'ELEMENT_TREE';
            }
        }
        return 'OTHER';
    }
}
