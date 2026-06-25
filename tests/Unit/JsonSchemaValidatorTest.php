<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

final class JsonSchemaValidatorTest extends TestCase
{
    public function testInspectorPatternAndBounds(): void
    {
        $validator = new JsonSchemaValidator(dirname(__DIR__, 2) . '/');
        self::assertSame([], $validator->validate((object) [
            'document_id' => '1',
            'elementor_element_id' => 'abc_123-def',
            'include_descendants' => true,
            'selection_reason' => 'USER_SELECTED',
        ], 'schemas/selection-snapshot.schema.json#/$defs/selectedElement'));
        self::assertNotSame([], $validator->validate((object) [
            'document_id' => '1',
            'elementor_element_id' => 'bad/id',
            'include_descendants' => true,
            'selection_reason' => 'USER_SELECTED',
        ], 'schemas/selection-snapshot.schema.json#/$defs/selectedElement'));
    }

    public function testStrictDateTimeAndDeclaredFormatPolicy(): void
    {
        $validator = new JsonSchemaValidator(dirname(__DIR__, 2) . '/');
        self::assertSame('ASSERT_BUNDLED_FORMATS', JsonSchemaValidator::FORMAT_POLICY);
        self::assertNotSame([], $validator->validate('tomorrow', 'schemas/shared-artifact-envelope.schema.json#/properties/captured_at'));
    }

    public function testUnicodeLengthUsesCodePointsWithoutMbstring(): void
    {
        $root = dirname(__DIR__, 2) . '/';
        $schemaPath = $root . 'schemas/.edis-unicode-test.schema.json';
        file_put_contents($schemaPath, json_encode([
            'type' => 'string',
            'minLength' => 2,
            'maxLength' => 2,
        ], JSON_THROW_ON_ERROR));
        try {
            $validator = new JsonSchemaValidator($root);
            self::assertSame([], $validator->validate('ای', 'schemas/.edis-unicode-test.schema.json'));
            self::assertNotSame([], $validator->validate('ا', 'schemas/.edis-unicode-test.schema.json'));
        } finally {
            if (is_file($schemaPath)) { unlink($schemaPath); }
        }
    }

    public function testBundledSchemasUseOnlyTheImplementedKeywordSubset(): void
    {
        $allowed = [
            '$schema', '$id', '$defs', '$ref', 'title', 'description', 'type', 'const', 'enum',
            'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'minLength', 'maxLength',
            'pattern', 'format', 'minItems', 'maxItems', 'uniqueItems', 'items', 'minProperties',
            'maxProperties', 'required', 'properties', 'additionalProperties',
        ];
        $walk = function (mixed $node, ?string $container = null) use (&$walk, $allowed): void {
            if (!is_array($node)) { return; }
            foreach ($node as $key => $value) {
                if (!is_string($key)) {
                    $walk($value, $container);
                    continue;
                }
                if (!in_array($container, ['properties', '$defs'], true)) {
                    self::assertContains($key, $allowed, 'Unsupported schema keyword: ' . $key);
                }
                $walk($value, $key);
            }
        };
        foreach (glob(dirname(__DIR__, 2) . '/schemas/*.schema.json') ?: [] as $path) {
            $schema = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $walk($schema);
        }
    }
}
