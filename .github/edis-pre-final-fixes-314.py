#!/usr/bin/env python3
from pathlib import Path

ROOT = Path.cwd()


def replace(path: str, old: str, new: str, count: int = 1) -> None:
    target = ROOT / path
    text = target.read_text(encoding="utf-8")
    if old not in text:
        raise SystemExit(f"missing expected pre-final text in {path}: {old[:120]!r}")
    target.write_text(text.replace(old, new, count), encoding="utf-8")


# M04/T15-T18/T21: mutation tests must not generate undeclared Python bytecode in source authority.
replace(
    "tests/release-authority-314.py",
    "import importlib.util\nimport json\nfrom pathlib import Path\nimport tempfile\n",
    "import importlib.util\nimport json\nfrom pathlib import Path\nimport sys\nimport tempfile\n\nsys.dont_write_bytecode = True\n",
)

# M06: plugin.manifest.json names the active Technical ID with the existing `id` field.
replace(
    "tools/validation/run-local-validation.php",
    "if (!is_array($collector) || !is_string($collector['technical_id'] ?? null) || !is_string($collector['schema_version'] ?? null)) {\n                continue;\n            }\n            $expected[$collector['technical_id']] = $collector['schema_version'];",
    "if (!is_array($collector) || !is_string($collector['id'] ?? null) || !is_string($collector['schema_version'] ?? null)) {\n                continue;\n            }\n            $expected[$collector['id']] = $collector['schema_version'];",
)

# Current-release assertions must advance with the explicit 3.7.14 worker/product lock.
replace("tests/Unit/InstallationIntegrityTest.php", "self::assertSame('3.7.13', $result['version']);", "self::assertSame('3.7.14', $result['version']);")
replace("tests/Unit/SupplyChainGateContractTest.php", "self::assertSame('3.7.13', $package['version'] ?? null);", "self::assertSame('3.7.14', $package['version'] ?? null);")
replace("tests/Unit/ValidationKitContractTest.php", "self::assertSame('3.7.13', $plan['plugin_version'] ?? null);", "self::assertSame('3.7.14', $plan['plugin_version'] ?? null);")
replace("tests/Unit/ValidationKitContractTest.php", "self::assertSame('degraded_admin_recovery_patch', $plan['scope'] ?? null);", "self::assertSame('correctness_closure_v3_7_14', $plan['scope'] ?? null);")
replace(
    "tests/Unit/WordPressIntegrationContractTest.php",
    "public function testPluginPatchVersionDoesNotAdvanceWorkerImplementationCompatibility(): void\n    {\n        $plugin = $this->read('edis-evidence-exporter.php');\n        $worker = $this->read('src/Application/ExportJobService.php');\n\n        self::assertStringContainsString(\"Version: 3.7.13\", $plugin);\n        self::assertStringContainsString(\"EDIS_EVIDENCE_EXPORTER_VERSION', '3.7.13'\", $plugin);\n        self::assertStringContainsString(\"private const IMPLEMENTATION_VERSION = '3.7.12';\", $worker);\n        self::assertStringNotContainsString(\"private const IMPLEMENTATION_VERSION = '3.7.13';\", $worker);\n    }",
    "public function testCorrectnessClosureAdvancesProductAndWorkerTogether(): void\n    {\n        $plugin = $this->read('edis-evidence-exporter.php');\n        $worker = $this->read('src/Application/ExportJobService.php');\n\n        self::assertStringContainsString(\"Version: 3.7.14\", $plugin);\n        self::assertStringContainsString(\"EDIS_EVIDENCE_EXPORTER_VERSION', '3.7.14'\", $plugin);\n        self::assertStringContainsString(\"private const IMPLEMENTATION_VERSION = '3.7.14';\", $worker);\n        self::assertStringNotContainsString(\"private const IMPLEMENTATION_VERSION = '3.7.12';\", $worker);\n    }",
)

# Generated/cache files are not manifest-owned repository source.
replace(
    "tests/Unit/SupplyChainGateContractTest.php",
    "if (str_starts_with($relative, 'vendor/')\n                || str_starts_with($relative, 'node_modules/')\n                || str_starts_with($relative, '.git/')) {",
    "if (str_starts_with($relative, 'vendor/')\n                || str_starts_with($relative, 'node_modules/')\n                || str_starts_with($relative, '.git/')\n                || str_starts_with($relative, '.phpunit.cache/')\n                || str_starts_with($relative, 'release-build/')\n                || str_starts_with($relative, '.pytest_cache/')\n                || str_contains($relative, '/__pycache__/')\n                || str_ends_with($relative, '.pyc')) {",
)

# Translation catalogs are current-release authority for their own version header.
for catalog in ["languages/edis-evidence-exporter.pot", "languages/edis-evidence-exporter-fa_IR.po"]:
    replace(catalog, "Project-Id-Version: EDIS WordPress Evidence Exporter 3.7.13\\n", "Project-Id-Version: EDIS WordPress Evidence Exporter 3.7.14\\n")
