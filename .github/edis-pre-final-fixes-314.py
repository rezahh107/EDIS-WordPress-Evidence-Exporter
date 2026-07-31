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

# T09 must inspect the actual serialized/exported contract, where diagnostics are arrays.
replace(
    "tests/Unit/EnvironmentObservation314Test.php",
    "$serialized = $result->jsonSerialize();",
    "$serialized = json_decode(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);",
)

# Existing integrity tests must not unwrap a correctly typed ExportIntegrityException to its causal previous exception.
replace(
    "tests/Unit/ExportJobIntegrityTest.php",
    "$actual = $exception instanceof \\ReflectionException ? $exception : ($exception->getPrevious() ?? $exception);",
    "$actual = $exception instanceof ExportIntegrityException ? $exception : ($exception->getPrevious() ?? $exception);",
)
replace(
    "tests/Unit/ExportJobIntegrityTest.php",
    "$actual = $exception->getPrevious() ?? $exception;",
    "$actual = $exception instanceof ExportIntegrityException ? $exception : ($exception->getPrevious() ?? $exception);",
)

# The tamper test's pre-tamper fixture must itself satisfy the active 3.7.14 resume contract.
# Keep the separate resume-atomic legacy fixture at 3.7.12 so compatibility rejection remains covered.
replace(
    "tests/Unit/ExportJobIntegrityTest.php",
    "            'job_id' => 'job-integrity',\n            'job_format_version' => '2.1.0',\n            'implementation_version' => '3.7.12',",
    "            'job_id' => 'job-integrity',\n            'job_format_version' => '2.1.0',\n            'implementation_version' => '3.7.14',",
)
replace(
    "tests/Unit/ExportJobIntegrityTest.php",
    "                'component_id' => 'environment',\n                'component_schema_version' => '1.0.0',\n                'implementation_version' => '3.7.12',\n                'input_snapshot_sha256' => $manifest['snapshot_sha256'],",
    "                'component_id' => 'environment',\n                'component_schema_version' => '1.0.0',\n                'implementation_version' => '3.7.14',\n                'observed_at' => '2026-07-30T00:00:00Z',\n                'input_snapshot_sha256' => $manifest['snapshot_sha256'],",
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

# Preserve the degraded-recovery boundary explicitly in current 3.7.14 release documentation.
old_intro = 'new_intro = "Version 3.7.14 is the bounded correctness-closure release for exported-source privacy projection, truthful failed-observation semantics, per-artifact temporal provenance, manifest-authoritative release inventory, generated-validation output isolation, machine-checked collector documentation, truthful recovery scheduling state, and exact final-build qualification. Frozen public evidence contracts remain unchanged; worker implementation compatibility advances to `3.7.14`, so incomplete jobs created under 3.7.12 must be recreated."'
new_intro = 'new_intro = "Version 3.7.14 is the bounded correctness-closure release for exported-source privacy projection, truthful failed-observation semantics, per-artifact temporal provenance, manifest-authoritative release inventory, generated-validation output isolation, machine-checked collector documentation, truthful recovery scheduling state, and exact final-build qualification. Unsupported deterministic PHP runtime is still handled before `Bootstrap` by `edis_evidence_exporter_runtime_notice`. Once a supported runtime reaches Bootstrap, installation-integrity failure, invalid configuration, and private-storage failure continue to route through `DegradedModeIntegration`; Create Export, job operations, downloads, worker tests, and operational REST controllers remain unavailable while degraded. Frozen public evidence contracts remain unchanged; worker implementation compatibility advances to `3.7.14`, so incomplete jobs created under 3.7.12 must be recreated."'
replace(".github/edis-finalize-314.py", old_intro, new_intro)

old_desc = 'new_desc = "Version 3.7.14 closes eight bounded correctness defects without changing frozen public evidence schemas: shared pre-commit privacy projection, failure-vs-empty observation truth, orchestrator-stamped provenance, manifest-authoritative release inventory and build fingerprinting, generated-validation output isolation, machine-checked collector documentation, truthful recovery scheduling state, and exact final-build runtime qualification. Worker implementation compatibility advances to 3.7.14."'
new_desc = 'new_desc = "Version 3.7.14 closes eight bounded correctness defects without changing frozen public evidence schemas: shared pre-commit privacy projection, failure-vs-empty observation truth, orchestrator-stamped provenance, manifest-authoritative release inventory and build fingerprinting, generated-validation output isolation, machine-checked collector documentation, truthful recovery scheduling state, and exact final-build runtime qualification. Unsupported deterministic PHP runtime is still handled before `Bootstrap` by `edis_evidence_exporter_runtime_notice`. After a supported runtime reaches Bootstrap, installation-integrity failure, invalid configuration, and private-storage failure continue through `DegradedModeIntegration`; export, worker, download, and operational REST controls remain unavailable while degraded. Worker implementation compatibility advances to 3.7.14."'
replace(".github/edis-finalize-314.py", old_desc, new_desc)
