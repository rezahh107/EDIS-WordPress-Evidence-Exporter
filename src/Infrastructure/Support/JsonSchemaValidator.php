<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

/**
 * Deterministic validator for the exact JSON Schema Draft 2020-12 subset used by bundled EDIS schemas.
 * Bundled date-time and UUID formats are assertions. Unsupported keywords must fail the build-time
 * keyword-inventory gate rather than being accepted silently.
 */
final class JsonSchemaValidator
{
    public const FORMAT_POLICY = 'ASSERT_BUNDLED_FORMATS';

    /** @var array<string,array<string,mixed>> */
    private array $cache = [];

    private DeterministicFilesystem $filesystem;

    public function __construct(private readonly string $pluginRoot, ?DeterministicFilesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new DeterministicFilesystem();
    }

    /** @return list<array{path:string,keyword:string,message:string}> */
    public function validate(mixed $instance, string $reference): array
    {
        $errors = [];
        [$schema, $file] = $this->resolveReference($reference, null);
        $this->walk($instance, $schema, '$', $file, $errors);
        return $errors;
    }

    /** @param array<string,mixed> $schema @param list<array{path:string,keyword:string,message:string}> $errors */
    private function walk(mixed $instance, array $schema, string $path, string $schemaFile, array &$errors): void
    {
        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            [$resolved, $file] = $this->resolveReference($schema['$ref'], $schemaFile);
            $this->walk($instance, $resolved, $path, $file, $errors);
            unset($schema['$ref']);
            if ($schema === []) {
                return;
            }
        }
        if (isset($schema['type']) && !$this->matchesType($instance, $schema['type'])) {
            $errors[] = [
                'path' => $path,
                'keyword' => 'type',
                'message' => 'Value does not match the declared JSON type. Expected '
                    . $this->declaredTypeLabel($schema['type'])
                    . '; actual ' . $this->jsonTypeLabel($instance) . '.',
            ];
            return;
        }
        if (array_key_exists('const', $schema) && !$this->same($instance, $schema['const'])) {
            $errors[] = ['path' => $path, 'keyword' => 'const', 'message' => 'Value does not match const.'];
        }
        if (isset($schema['enum']) && is_array($schema['enum'])) {
            $match = false;
            foreach ($schema['enum'] as $candidate) {
                if ($this->same($instance, $candidate)) {
                    $match = true;
                    break;
                }
            }
            if (!$match) {
                $errors[] = ['path' => $path, 'keyword' => 'enum', 'message' => 'Value is not in the declared enum.'];
            }
        }
        if (is_int($instance) || is_float($instance)) {
            if (isset($schema['minimum']) && $instance < (float) $schema['minimum']) {
                $errors[] = ['path' => $path, 'keyword' => 'minimum', 'message' => 'Number is smaller than the declared minimum.'];
            }
            if (isset($schema['maximum']) && $instance > (float) $schema['maximum']) {
                $errors[] = ['path' => $path, 'keyword' => 'maximum', 'message' => 'Number is greater than the declared maximum.'];
            }
            if (isset($schema['exclusiveMinimum']) && $instance <= (float) $schema['exclusiveMinimum']) {
                $errors[] = ['path' => $path, 'keyword' => 'exclusiveMinimum', 'message' => 'Number is not greater than the exclusive minimum.'];
            }
            if (isset($schema['exclusiveMaximum']) && $instance >= (float) $schema['exclusiveMaximum']) {
                $errors[] = ['path' => $path, 'keyword' => 'exclusiveMaximum', 'message' => 'Number is not smaller than the exclusive maximum.'];
            }
        }
        if (is_string($instance)) {
            $length = $this->unicodeLength($instance);
            if ($length === null) {
                $errors[] = ['path' => $path, 'keyword' => 'encoding', 'message' => 'String is not valid UTF-8.'];
                return;
            }
            if (isset($schema['minLength']) && $length < (int) $schema['minLength']) {
                $errors[] = ['path' => $path, 'keyword' => 'minLength', 'message' => 'String is shorter than allowed.'];
            }
            if (isset($schema['maxLength']) && $length > (int) $schema['maxLength']) {
                $errors[] = ['path' => $path, 'keyword' => 'maxLength', 'message' => 'String is longer than allowed.'];
            }
            if (isset($schema['pattern']) && is_string($schema['pattern']) && !$this->matchesDeclaredPattern($instance, $schema['pattern'])) {
                $errors[] = ['path' => $path, 'keyword' => 'pattern', 'message' => 'String does not match the declared pattern.'];
            }
            $format = $schema['format'] ?? null;
            if ($format === 'date-time' && !$this->isRfc3339DateTime($instance)) {
                $errors[] = ['path' => $path, 'keyword' => 'format', 'message' => 'Value is not a valid RFC 3339 date-time.'];
            }
            if ($format === 'uuid' && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $instance) !== 1) {
                $errors[] = ['path' => $path, 'keyword' => 'format', 'message' => 'Value is not a valid lowercase UUID.'];
            }
        }
        if (is_array($instance)) {
            $count = count($instance);
            if (isset($schema['minItems']) && $count < (int) $schema['minItems']) {
                $errors[] = ['path' => $path, 'keyword' => 'minItems', 'message' => 'Array has too few items.'];
            }
            if (isset($schema['maxItems']) && $count > (int) $schema['maxItems']) {
                $errors[] = ['path' => $path, 'keyword' => 'maxItems', 'message' => 'Array has too many items.'];
            }
            if (!empty($schema['uniqueItems'])) {
                $seen = [];
                foreach ($instance as $index => $item) {
                    $key = CanonicalJson::encode($item);
                    if (isset($seen[$key])) {
                        $errors[] = ['path' => $path . '[' . $index . ']', 'keyword' => 'uniqueItems', 'message' => 'Array items must be unique.'];
                        break;
                    }
                    $seen[$key] = true;
                }
            }
            if (isset($schema['items']) && is_array($schema['items'])) {
                foreach ($instance as $index => $item) {
                    $this->walk($item, $schema['items'], $path . '[' . $index . ']', $schemaFile, $errors);
                }
            }
        }
        if (is_object($instance)) {
            $properties = get_object_vars($instance);
            $count = count($properties);
            if (isset($schema['minProperties']) && $count < (int) $schema['minProperties']) {
                $errors[] = ['path' => $path, 'keyword' => 'minProperties', 'message' => 'Object has too few properties.'];
            }
            if (isset($schema['maxProperties']) && $count > (int) $schema['maxProperties']) {
                $errors[] = ['path' => $path, 'keyword' => 'maxProperties', 'message' => 'Object has too many properties.'];
            }
            $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
            foreach ($required as $name) {
                if (is_string($name) && !property_exists($instance, $name)) {
                    $errors[] = ['path' => $path, 'keyword' => 'required', 'message' => 'Missing required property: ' . $name];
                }
            }
            $declared = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
            foreach ($declared as $name => $child) {
                if (is_string($name) && is_array($child) && property_exists($instance, $name)) {
                    $this->walk($instance->{$name}, $child, $path . '.' . $name, $schemaFile, $errors);
                }
            }
            $additional = $schema['additionalProperties'] ?? true;
            if ($additional === false) {
                foreach (array_keys($properties) as $name) {
                    if (!array_key_exists($name, $declared)) {
                        $errors[] = ['path' => $path . '.' . $name, 'keyword' => 'additionalProperties', 'message' => 'Unexpected property.'];
                    }
                }
            } elseif (is_array($additional)) {
                foreach ($properties as $name => $childValue) {
                    if (!array_key_exists($name, $declared)) {
                        $this->walk($childValue, $additional, $path . '.' . $name, $schemaFile, $errors);
                    }
                }
            }
        }
    }

    private function declaredTypeLabel(mixed $declared): string
    {
        $types = is_array($declared) ? $declared : [$declared];
        $labels = [];
        foreach ($types as $type) {
            if (is_string($type) && $type !== '') {
                $labels[] = $type;
            }
        }
        return $labels === [] ? 'unknown' : implode('|', $labels);
    }

    private function jsonTypeLabel(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_object($value) => 'object',
            is_array($value) => 'array',
            is_string($value) => 'string',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_bool($value) => 'boolean',
            default => get_debug_type($value),
        };
    }

    private function matchesType(mixed $value, mixed $declared): bool
    {
        $types = is_array($declared) ? $declared : [$declared];
        foreach ($types as $type) {
            if (!is_string($type)) {
                continue;
            }
            if (match ($type) {
                'object' => is_object($value),
                'array' => is_array($value),
                'string' => is_string($value),
                'integer' => is_int($value),
                'number' => is_int($value) || is_float($value),
                'boolean' => is_bool($value),
                'null' => $value === null,
                default => false,
            }) {
                return true;
            }
        }
        return false;
    }

    private function unicodeLength(string $value): ?int
    {
        if (preg_match('//u', $value) !== 1) {
            return null;
        }
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }
        $count = preg_match_all('/./us', $value, $matches);
        return is_int($count) ? $count : null;
    }

    private function matchesDeclaredPattern(string $value, string $pattern): bool
    {
        if (strlen($pattern) > 512 || str_contains($pattern, "\0")) {
            return false;
        }
        $delimiter = '~';
        $escaped = str_replace($delimiter, '\\' . $delimiter, $pattern);
        $result = preg_match($delimiter . $escaped . $delimiter . 'Du', $value);

        return $result === 1;
    }

    private function isRfc3339DateTime(string $value): bool
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,9})?(?:Z|[+-]\d{2}:\d{2})\z/D', $value) !== 1) {
            return false;
        }
        $normalized = preg_replace('/\.\d{1,9}(?=Z|[+-]\d{2}:\d{2}$)/', '', $value);
        if (!is_string($normalized)) {
            return false;
        }
        $normalized = str_ends_with($normalized, 'Z') ? substr($normalized, 0, -1) . '+00:00' : $normalized;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $normalized);
        $errors = \DateTimeImmutable::getLastErrors();
        return $date !== false && ($errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0));
    }

    private function same(mixed $left, mixed $right): bool
    {
        return CanonicalJson::encode($left) === CanonicalJson::encode($right);
    }

    /** @return array{0:array<string,mixed>,1:string} */
    private function resolveReference(string $reference, ?string $baseFile): array
    {
        [$filePart, $fragment] = array_pad(explode('#', $reference, 2), 2, '');
        if ($filePart === '') {
            if ($baseFile === null) {
                throw new \RuntimeException('Schema reference has no base file.');
            }
            $file = $baseFile;
        } else {
            $candidate = $baseFile !== null && !str_contains($filePart, '/') ? dirname($baseFile) . '/' . $filePart : $filePart;
            $file = $this->safeSchemaPath($candidate);
        }
        $schema = $this->load($file);
        if ($fragment !== '') {
            foreach (explode('/', ltrim($fragment, '/')) as $segment) {
                $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
                if (!is_array($schema) || !array_key_exists($segment, $schema) || !is_array($schema[$segment])) {
                    throw new \RuntimeException('Unresolvable schema fragment: ' . $reference);
                }
                $schema = $schema[$segment];
            }
        }
        return [$schema, $file];
    }

    private function safeSchemaPath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        if (!str_starts_with($normalized, 'schemas/')) {
            $normalized = 'schemas/' . ltrim($normalized, '/');
        }
        $root = realpath($this->pluginRoot . 'schemas');
        $real = realpath($this->pluginRoot . $normalized);
        if ($root === false || $real === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Unsafe or missing schema reference: ' . $path);
        }
        return 'schemas/' . basename($real);
    }

    /** @return array<string,mixed> */
    private function load(string $file): array
    {
        if (isset($this->cache[$file])) {
            return $this->cache[$file];
        }
        try {
            $bytes = $this->filesystem->read($this->pluginRoot . $file);
        } catch (FilesystemException $exception) {
            throw new \RuntimeException('Unable to read schema: ' . $file, 0, $exception);
        }
        $decoded = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Schema root must be an object: ' . $file);
        }
        return $this->cache[$file] = $decoded;
    }
}
