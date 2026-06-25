<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

final class PackageManifestSchemaTest extends TestCase
{
    public function testCurrentSchemaAcceptsPatchReleaseAndHistoricalSchemaRemainsFrozen(): void
    {
        $root = dirname(__DIR__, 2) . '/';
        $validator = new JsonSchemaValidator($root);
        $current = $this->manifest('2.1.0', '3.7.11');
        $historical = $this->manifest('2.0.0', '3.7.0');

        $currentObject = json_decode(json_encode($current, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        $historicalObject = json_decode(json_encode($historical, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        self::assertSame([], $validator->validate($currentObject, 'schemas/package-manifest.schema.json'));
        self::assertSame([], $validator->validate($historicalObject, 'schemas/package-manifest-2.0.0.schema.json'));
        self::assertNotSame([], $validator->validate($currentObject, 'schemas/package-manifest-2.0.0.schema.json'));
    }

    /** @return array<string,mixed> */
    private function manifest(string $schemaVersion, string $pluginVersion): array
    {
        return [
            'schema_id' => 'urn:edis:schema:wordpress:package-manifest',
            'schema_version' => $schemaVersion,
            'artifact_type' => 'wordpress_source_evidence_package_manifest',
            'producer' => [
                'product' => 'edis-evidence-exporter',
                'version' => $pluginVersion,
            ],
            'data' => [
                'analysis_set_id' => 'analysis-set',
                'wordpress_bundle_id' => 'bundle-id',
                'semantic_identity' => [
                    'source_export_root_sha256' => 'sha256:' . str_repeat('a', 64),
                    'privacy_mode' => 'Standard',
                    'plugin_version' => $pluginVersion,
                    'bundle_schema_version' => '3.3.0',
                    'zip_profile' => 'EDIS-ZIP-1',
                    'compression_method' => 'STORE',
                    'canonicalization_profile' => 'EDIS-CJ-2',
                ],
                'source_export_root_sha256' => 'sha256:' . str_repeat('a', 64),
                'files' => [],
                'file_count' => 0,
                'plugin_version' => $pluginVersion,
                'bundle_schema_version' => '3.3.0',
                'zip_profile' => 'EDIS-ZIP-1',
                'compression_method' => 'STORE',
                'hash_semantics' => new \stdClass(),
            ],
            'diagnostics' => [],
        ];
    }
}
