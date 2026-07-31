#!/usr/bin/env python3
from pathlib import Path

path = Path('src/Application/ExportService.php')
text = path.read_text(encoding='utf-8')


def once(old: str, new: str, label: str) -> None:
    global text
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected exactly one match, found {count}')
    text = text.replace(old, new, 1)


once("    private const PRODUCER_VERSION = '3.7.13';", "    private const PRODUCER_VERSION = '3.7.14';", 'producer_version')
once(
    "        int $expiresAt,\n    ): array {\n        $files = [];",
    "        int $expiresAt,\n        ?string $packagingStartedAt = null,\n    ): array {\n        $packageCapturedAt = is_string($packagingStartedAt) && $packagingStartedAt !== ''\n            ? $packagingStartedAt\n            : $context->capturedAt;\n        $files = [];",
    'package_signature',
)
once(
    "            ],\n            $context,\n        );\n        $files['provenance/provenance.json'] = CanonicalJson::encode($provenanceEnvelope);",
    "            ],\n            $context,\n            $packageCapturedAt,\n        );\n        $files['provenance/provenance.json'] = CanonicalJson::encode($provenanceEnvelope);",
    'provenance_timestamp',
)
once(
    "$validationEnvelope = $this->validationEnvelope($validation, $context);",
    "$validationEnvelope = $this->validationEnvelope($validation, $context, $packageCapturedAt);",
    'validation_initial',
)
once(
    "[$files, $manifest] = $this->buildManifestAndChecksums($files, $context, $sourceRoot);",
    "[$files, $manifest] = $this->buildManifestAndChecksums($files, $context, $sourceRoot, $packageCapturedAt);",
    'manifest_initial',
)
once(
    "$files['validation/package-validation.json'] = CanonicalJson::encode($this->validationEnvelope($validation, $context));",
    "$files['validation/package-validation.json'] = CanonicalJson::encode($this->validationEnvelope($validation, $context, $packageCapturedAt));",
    'validation_final',
)
once(
    "[$files, $manifest] = $this->buildManifestAndChecksums($files, $context, $sourceRoot);",
    "[$files, $manifest] = $this->buildManifestAndChecksums($files, $context, $sourceRoot, $packageCapturedAt);",
    'manifest_final',
)
once(
    "    private function validationEnvelope(array $validation, CollectionContext $context): array",
    "    private function validationEnvelope(array $validation, CollectionContext $context, string $capturedAt): array",
    'validation_signature',
)
once(
    "            ],\n            $context,\n        );\n    }\n\n    /**\n     * @param array<string,string> $files\n     * @return array{0:array<string,string>,1:array<string,mixed>}\n     */\n    private function buildManifestAndChecksums(array $files, CollectionContext $context, string $sourceRoot): array",
    "            ],\n            $context,\n            $capturedAt,\n        );\n    }\n\n    /**\n     * @param array<string,string> $files\n     * @return array{0:array<string,string>,1:array<string,mixed>}\n     */\n    private function buildManifestAndChecksums(array $files, CollectionContext $context, string $sourceRoot, string $capturedAt): array",
    'validation_and_manifest_signatures',
)
once("            'captured_at' => $context->capturedAt,", "            'captured_at' => $capturedAt,", 'manifest_captured_at')
once(
    "    private function envelope(string $schemaId, string $schemaVersion, string $artifactType, array $artifact, CollectionContext $context): array",
    "    private function envelope(string $schemaId, string $schemaVersion, string $artifactType, array $artifact, CollectionContext $context, ?string $capturedAt = null): array",
    'envelope_signature',
)
once(
    "            'captured_at' => $context->capturedAt,\n            'canonicalization' => CanonicalJson::canonicalizationDescriptor(),",
    "            'captured_at' => $capturedAt\n                ?? (is_string($artifact['observed_at'] ?? null) && $artifact['observed_at'] !== '' ? $artifact['observed_at'] : $context->capturedAt),\n            'canonicalization' => CanonicalJson::canonicalizationDescriptor(),",
    'envelope_captured_at',
)
path.write_text(text, encoding='utf-8')
