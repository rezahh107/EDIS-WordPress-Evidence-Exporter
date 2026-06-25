<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use PHPUnit\Framework\TestCase;

final class ArtifactHashSeparationTest extends TestCase
{
    public function testOperationalIdentityChangesInstanceButNotSemanticHash(): void
    {
        $first = $this->envelope('analysis-a', 'bundle-a', '2026-06-15T00:00:00Z');
        $second = $this->envelope('analysis-b', 'bundle-b', '2026-06-16T00:00:00Z');
        CanonicalJson::applyHashes($first);
        CanonicalJson::applyHashes($second);
        self::assertSame($first['semantic_payload_sha256'], $second['semantic_payload_sha256']);
        self::assertNotSame($first['artifact_instance_sha256'], $second['artifact_instance_sha256']);
    }

    public function testNestedSavedSourceFieldsWithOperationalNamesRemainSemantic(): void
    {
        foreach (['created_at', 'captured_at', 'owner_id', 'user_id', 'token', 'attempt_count'] as $key) {
            $first = $this->envelope('analysis-a', 'bundle-a', '2026-06-15T00:00:00Z');
            $second = $first;
            $first['data']['evidence']['settings'] = [$key => 'source-a'];
            $second['data']['evidence']['settings'] = [$key => 'source-b'];
            self::assertNotSame(
                CanonicalJson::semanticHash($first),
                CanonicalJson::semanticHash($second),
                'Nested source field must remain semantic: ' . $key,
            );
        }

        $first = $this->envelope('analysis-a', 'bundle-a', '2026-06-15T00:00:00Z');
        $first['data']['evidence']['settings'] = ['created_at' => 'source-a'];
        $third = $first;
        $third['data']['analysis_set_id'] = 'analysis-other';
        $third['data']['wordpress_bundle_id'] = 'bundle-other';
        self::assertSame(CanonicalJson::semanticHash($first), CanonicalJson::semanticHash($third));
    }

    public function testExplicitSemanticIdentityExcludesPackageInstanceFileInventory(): void
    {
        $first = $this->packageEnvelope('analysis-a', 'bundle-a', [['path' => 'a.json', 'sha256' => 'sha256:' . str_repeat('a', 64)]]);
        $second = $this->packageEnvelope('analysis-b', 'bundle-b', [['path' => 'b.json', 'sha256' => 'sha256:' . str_repeat('b', 64)]]);
        CanonicalJson::applyHashes($first);
        CanonicalJson::applyHashes($second);
        self::assertSame($first['semantic_payload_sha256'], $second['semantic_payload_sha256']);
        self::assertNotSame($first['artifact_instance_sha256'], $second['artifact_instance_sha256']);
    }

    /** @param list<array{path:string,sha256:string}> $files @return array<string,mixed> */
    private function packageEnvelope(string $analysisSetId, string $bundleId, array $files): array
    {
        return [
            'schema_id' => 'urn:edis:schema:wordpress:package-manifest',
            'schema_version' => '2.1.0',
            'artifact_type' => 'wordpress_source_evidence_package_manifest',
            'producer' => ['product' => 'edis-evidence-exporter', 'version' => '3.7.11'],
            'captured_at' => '2026-06-15T00:00:00Z',
            'canonicalization' => CanonicalJson::canonicalizationDescriptor(),
            'data' => [
                'analysis_set_id' => $analysisSetId,
                'wordpress_bundle_id' => $bundleId,
                'semantic_identity' => [
                    'source_export_root_sha256' => 'sha256:' . str_repeat('c', 64),
                    'privacy_mode' => 'STRICT',
                    'plugin_version' => '3.7.11',
                    'bundle_schema_version' => '3.3.0',
                    'zip_profile' => 'EDIS-ZIP-1',
                    'compression_method' => 'STORE',
                    'canonicalization_profile' => 'EDIS-CJ-2',
                ],
                'files' => $files,
            ],
            'diagnostics' => [],
        ];
    }

    /** @return array<string,mixed> */
    private function envelope(string $analysisSetId, string $bundleId, string $capturedAt): array
    {
        return [
            'schema_id' => 'urn:edis:schema:test',
            'schema_version' => '1.0.0',
            'artifact_type' => 'test',
            'producer' => ['product' => 'edis-evidence-exporter', 'version' => '3.7.11'],
            'captured_at' => $capturedAt,
            'canonicalization' => CanonicalJson::canonicalizationDescriptor(),
            'data' => ['analysis_set_id' => $analysisSetId, 'wordpress_bundle_id' => $bundleId, 'evidence' => ['value' => 42]],
            'diagnostics' => [],
        ];
    }
}
