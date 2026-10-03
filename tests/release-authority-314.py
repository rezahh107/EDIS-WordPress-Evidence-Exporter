#!/usr/bin/env python3
from __future__ import annotations

import importlib.util
import json
import subprocess
from pathlib import Path
import sys
import tempfile

sys.dont_write_bytecode = True

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("edis_build_release", ROOT / "tools/release/build-release.py")
if SPEC is None or SPEC.loader is None:
    raise SystemExit("could not load release builder")
release = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(release)

VERSION = "3.7.16"
BASE_PATHS = [
    "CHANGELOG.md",
    "SECURITY.md",
    "config/critical-files.json",
    "edis-evidence-exporter.php",
    "package-lock.json",
    "package.json",
    "plugin.manifest.json",
    "src/Application/ExportJobService.php",
    "src/Application/ExportService.php",
    "templates/admin/help.php",
]
NON_INSTALLABLE_PATHS = {
    "config/critical-files.json",
    "package-lock.json",
    "package.json",
    "plugin.manifest.json",
}


def write(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")


def manifest(paths: list[str] | None = None) -> dict[str, object]:
    selected = BASE_PATHS if paths is None else paths
    return {
        "format": "EDIS-MANIFEST-1",
        "platform_version": VERSION,
        "plugin": {"version": VERSION},
        "build": {"version": VERSION, "platform_version": VERSION},
        "files": [
            {"path": path, "installable": path not in NON_INSTALLABLE_PATHS}
            for path in selected
        ],
    }


def fixture(root: Path) -> None:
    write(
        root / "edis-evidence-exporter.php",
        "<?php\n/**\n * Version: 3.7.16\n */\n"
        "define('EDIS_EVIDENCE_EXPORTER_VERSION', '3.7.16');\n"
        "define('EDIS_EVIDENCE_BUILD_PLATFORM_VERSION', '3.7.16');\n",
    )
    write(root / "package.json", json.dumps({"version": VERSION}) + "\n")
    write(
        root / "package-lock.json",
        json.dumps({"version": VERSION, "packages": {"": {"version": VERSION}}}) + "\n",
    )
    write(root / "plugin.manifest.json", json.dumps(manifest(), separators=(",", ":")) + "\n")
    write(root / "config/critical-files.json", json.dumps({"plugin_version": VERSION, "files": {}}) + "\n")
    write(root / "src/Application/ExportService.php", "<?php final class X { private const PRODUCER_VERSION = '3.7.16'; }\n")
    write(root / "src/Application/ExportJobService.php", "<?php final class Y { private const IMPLEMENTATION_VERSION = '3.7.15'; }\n")
    write(
        root / "SECURITY.md",
        "# Security Policy\n\n## Supported release\n\n"
        "EDIS Evidence Exporter 3.7.16 is the currently supported degraded-admin recovery release in this package.\n",
    )
    write(
        root / "templates/admin/help.php",
        "<?php $example = ['producer' => ['product' => 'edis-evidence-exporter', 'version' => EDIS_EVIDENCE_EXPORTER_VERSION]];\n",
    )
    write(root / "CHANGELOG.md", "## 3.7.13\n\nHistorical release notes remain valid.\n")
    write(root / "composer.lock", "{}\n")


def expect_failure(callable_, label: str) -> None:
    try:
        callable_()
    except RuntimeError:
        return
    raise AssertionError(f"{label}: expected RuntimeError")


def fresh() -> tuple[tempfile.TemporaryDirectory[str], Path]:
    tmp = tempfile.TemporaryDirectory(prefix="edis-release-authority-")
    root = Path(tmp.name)
    fixture(root)
    return tmp, root


def identity_paths(root: Path) -> list[str]:
    source_files, _install_files, source_paths = release.authoritative_inventory(root)
    if not source_files:
        raise AssertionError("fixture source inventory unexpectedly empty")
    return source_paths


def test_t15_unknown_ordinary_file_fails() -> None:
    tmp, root = fresh()
    try:
        write(root / "extra.bin", "unexpected\n")
        expect_failure(lambda: release.authoritative_inventory(root), "T15 undeclared ordinary file")
    finally:
        tmp.cleanup()


def test_t16_missing_unsafe_and_symlink_fail() -> None:
    tmp, root = fresh()
    try:
        (root / "src/Application/ExportService.php").unlink()
        expect_failure(lambda: release.authoritative_inventory(root), "T16 missing declared file")
    finally:
        tmp.cleanup()

    tmp, root = fresh()
    try:
        bad = manifest(BASE_PATHS + ["../escape.php"])
        write(root / "plugin.manifest.json", json.dumps(bad, separators=(",", ":")) + "\n")
        expect_failure(lambda: release.authoritative_inventory(root), "T16 unsafe path")
    finally:
        tmp.cleanup()

    tmp, root = fresh()
    try:
        link_path = root / "symlink.php"
        link_path.symlink_to(root / "edis-evidence-exporter.php")
        bad = manifest(BASE_PATHS + ["symlink.php"])
        write(root / "plugin.manifest.json", json.dumps(bad, separators=(",", ":")) + "\n")
        expect_failure(lambda: release.authoritative_inventory(root), "T16 prohibited symlink")
    finally:
        tmp.cleanup()


def mutate_json(path: Path, mutator) -> None:
    value = json.loads(path.read_text(encoding="utf-8"))
    mutator(value)
    write(path, json.dumps(value, separators=(",", ":")) + "\n")


def test_t17_all_version_authorities_fail_closed() -> None:
    mutations = {
        "plugin_header_version": lambda root: write(
            root / "edis-evidence-exporter.php",
            (root / "edis-evidence-exporter.php").read_text(encoding="utf-8").replace("Version: 3.7.16", "Version: 3.7.13"),
        ),
        "exporter_constant_version": lambda root: write(
            root / "edis-evidence-exporter.php",
            (root / "edis-evidence-exporter.php").read_text(encoding="utf-8").replace("EDIS_EVIDENCE_EXPORTER_VERSION', '3.7.16", "EDIS_EVIDENCE_EXPORTER_VERSION', '3.7.13"),
        ),
        "platform_constant_version": lambda root: write(
            root / "edis-evidence-exporter.php",
            (root / "edis-evidence-exporter.php").read_text(encoding="utf-8").replace("EDIS_EVIDENCE_BUILD_PLATFORM_VERSION', '3.7.16", "EDIS_EVIDENCE_BUILD_PLATFORM_VERSION', '3.7.13"),
        ),
        "package_json_version": lambda root: mutate_json(root / "package.json", lambda value: value.__setitem__("version", "3.7.13")),
        "manifest_plugin_version": lambda root: mutate_json(root / "plugin.manifest.json", lambda value: value["plugin"].__setitem__("version", "3.7.13")),
        "manifest_build_version": lambda root: mutate_json(root / "plugin.manifest.json", lambda value: value["build"].__setitem__("version", "3.7.13")),
        "manifest_build_platform_version": lambda root: mutate_json(root / "plugin.manifest.json", lambda value: value["build"].__setitem__("platform_version", "3.7.13")),
        "manifest_platform_version": lambda root: mutate_json(root / "plugin.manifest.json", lambda value: value.__setitem__("platform_version", "3.7.13")),
        "producer_version": lambda root: write(
            root / "src/Application/ExportService.php",
            "<?php final class X { private const PRODUCER_VERSION = '3.7.13'; }\n",
        ),
        "critical_files_plugin_version": lambda root: mutate_json(root / "config/critical-files.json", lambda value: value.__setitem__("plugin_version", "3.7.13")),
        "security_supported_release_version": lambda root: write(
            root / "SECURITY.md",
            (root / "SECURITY.md").read_text(encoding="utf-8").replace(
                "EDIS Evidence Exporter 3.7.16 is the currently supported",
                "EDIS Evidence Exporter 3.7.13 is the currently supported",
            ),
        ),
        "help_producer_projection": lambda root: write(
            root / "templates/admin/help.php",
            (root / "templates/admin/help.php").read_text(encoding="utf-8").replace(
                "EDIS_EVIDENCE_EXPORTER_VERSION",
                "'3.7.13'",
            ),
        ),
    }

    for label, mutation in mutations.items():
        tmp, root = fresh()
        try:
            source_paths = identity_paths(root)
            mutation(root)
            expect_failure(lambda: release.release_identity(root, source_paths), f"T17 {label}")
        finally:
            tmp.cleanup()


def test_t17_historical_release_references_remain_valid() -> None:
    tmp, root = fresh()
    try:
        source_paths = identity_paths(root)
        assert "3.7.13" in (root / "CHANGELOG.md").read_text(encoding="utf-8")
        identity = release.release_identity(root, source_paths)
        assert identity["plugin_version"] == VERSION
        assert identity["worker_implementation_version"] == "3.7.15"
    finally:
        tmp.cleanup()


def test_t19_current_help_projection_renders_canonical_version() -> None:
    source_paths = identity_paths(ROOT)
    identity = release.release_identity(ROOT, source_paths)
    expected = identity["plugin_version"]
    assert expected == VERSION

    php_source = r'''<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support {
    final class CanonicalJson
    {
        public static function canonicalizationDescriptor(): array
        {
            return ['id' => 'EDIS-CJ-2'];
        }

        public static function applyHashes(array &$value): void
        {
        }
    }
}

namespace {
    define('EDIS_EVIDENCE_EXPORTER_VERSION', __EXPECTED_VERSION__);

    function esc_html__(string $text, string $domain = ''): string { return $text; }
    function esc_html(string $text): string { return $text; }
    function esc_attr__(string $text, string $domain = ''): string { return $text; }
    function esc_attr(string $text): string { return $text; }
    function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
    {
        return json_encode($value, $flags, $depth);
    }

    $definitions = [
        (object) [
            'id' => 'example',
            'label' => 'Example',
            'labelFa' => 'Example',
            'componentType' => (object) ['value' => 'SYSTEM'],
            'declaredTruthState' => (object) ['value' => 'VERIFIED'],
            'documentation' => ['en' => [], 'fa' => [], 'official_references' => []],
            'description' => '',
            'descriptionFa' => '',
            'schemaId' => 'example.schema',
            'schemaVersion' => '1.0.0',
            'defaultAvailability' => (object) ['value' => 'AVAILABLE'],
            'artifactPath' => 'example.json',
            'dependencies' => [],
        ],
    ];

    ob_start();
    require __HELP_TEMPLATE__;
    $html = (string) ob_get_clean();
    if (!preg_match('/"producer"\s*:\s*\{\s*"product"\s*:\s*"edis-evidence-exporter"\s*,\s*"version"\s*:\s*"([^"]+)"/s', $html, $matches)) {
        fwrite(STDERR, "Help producer JSON projection was not rendered.\n");
        exit(2);
    }
    if (($matches[1] ?? '') !== EDIS_EVIDENCE_EXPORTER_VERSION) {
        fwrite(STDERR, "Help producer version mismatch: " . ($matches[1] ?? '<missing>') . "\n");
        exit(3);
    }
    echo "Help producer version: " . $matches[1] . "\n";
}
'''
    php_source = php_source.replace("__EXPECTED_VERSION__", json.dumps(expected))
    php_source = php_source.replace("__HELP_TEMPLATE__", json.dumps(str(ROOT / "templates/admin/help.php")))

    with tempfile.TemporaryDirectory(prefix="edis-help-projection-") as temp_dir:
        runner = Path(temp_dir) / "render-help.php"
        write(runner, php_source)
        completed = subprocess.run(
            ["php", str(runner)],
            cwd=ROOT,
            check=False,
            capture_output=True,
            text=True,
        )
    if completed.returncode != 0:
        raise AssertionError(
            "Help projection render failed: "
            + completed.stdout
            + completed.stderr
        )
    assert completed.stdout.strip() == f"Help producer version: {expected}"


def test_t17_worker_identity_is_independent_and_fail_closed() -> None:
    tmp, root = fresh()
    try:
        source_paths = identity_paths(root)
        identity = release.release_identity(root, source_paths)
        assert identity["plugin_version"] == VERSION
        assert identity["worker_implementation_version"] == "3.7.15"
        write(root / "src/Application/ExportJobService.php", "<?php final class Y { private const IMPLEMENTATION_VERSION = '3.7.14'; }\n")
        identity = release.release_identity(root, source_paths)
        assert identity["plugin_version"] == VERSION
        assert identity["worker_implementation_version"] == "3.7.14"
    finally:
        tmp.cleanup()

    for label, source in {
        "missing": "<?php final class Y {}\n",
        "empty": "<?php final class Y { private const IMPLEMENTATION_VERSION = ''; }\n",
        "malformed": "<?php final class Y { private const IMPLEMENTATION_VERSION = 'not-a-version'; }\n",
    }.items():
        tmp, root = fresh()
        try:
            source_paths = identity_paths(root)
            write(root / "src/Application/ExportJobService.php", source)
            expect_failure(lambda: release.release_identity(root, source_paths), f"T17 worker {label}")
        finally:
            tmp.cleanup()

def test_t17_package_lock_top_level_version_fails_closed() -> None:
    tmp, root = fresh()
    try:
        source_paths = identity_paths(root)
        mutate_json(root / "package-lock.json", lambda value: value.__setitem__("version", "3.7.13"))
        expect_failure(lambda: release.release_identity(root, source_paths), "T17 package_lock_version")
    finally:
        tmp.cleanup()


def test_t17_package_lock_root_version_fails_closed() -> None:
    tmp, root = fresh()
    try:
        source_paths = identity_paths(root)
        mutate_json(root / "package-lock.json", lambda value: value["packages"][""].__setitem__("version", "3.7.13"))
        expect_failure(lambda: release.release_identity(root, source_paths), "T17 package_lock_root_version")
    finally:
        tmp.cleanup()


def test_t17_package_lock_missing_empty_and_malformed_fail_closed() -> None:
    mutations = {
        "missing_top_level_version": lambda root: mutate_json(root / "package-lock.json", lambda value: value.pop("version")),
        "empty_top_level_version": lambda root: mutate_json(root / "package-lock.json", lambda value: value.__setitem__("version", "")),
        "missing_packages": lambda root: mutate_json(root / "package-lock.json", lambda value: value.pop("packages")),
        "malformed_packages": lambda root: mutate_json(root / "package-lock.json", lambda value: value.__setitem__("packages", [])),
        "missing_root_package": lambda root: mutate_json(root / "package-lock.json", lambda value: value["packages"].pop("")),
        "empty_root_version": lambda root: mutate_json(root / "package-lock.json", lambda value: value["packages"][""].__setitem__("version", "")),
        "malformed_json": lambda root: write(root / "package-lock.json", "{\n"),
    }

    for label, mutation in mutations.items():
        tmp, root = fresh()
        try:
            source_paths = identity_paths(root)
            mutation(root)
            expect_failure(lambda: release.release_identity(root, source_paths), f"T17 package lock {label}")
        finally:
            tmp.cleanup()


def test_t18_clean_inventory_and_zip_are_deterministic() -> None:
    tmp, root = fresh()
    try:
        source_files, install_files, source_paths = release.authoritative_inventory(root)
        expected_source = sorted(BASE_PATHS + ["composer.lock"], key=lambda value: value.encode("utf-8"))
        expected_install = sorted(
            [path for path in BASE_PATHS if path not in NON_INSTALLABLE_PATHS],
            key=lambda value: value.encode("utf-8"),
        )
        assert source_paths == expected_source
        assert [path.relative_to(root).as_posix() for path in install_files] == expected_install
        identity = release.release_identity(root, source_paths)
        assert identity["plugin_version"] == VERSION
        assert identity["worker_implementation_version"] == "3.7.15"

        one = root / "one.zip"
        two = root / "two.zip"
        release.zip_files(root, one, install_files)
        release.zip_files(root, two, install_files)
        assert one.read_bytes() == two.read_bytes()
        report = release.verify_archive(one, expected_install)
        assert report["entry_count"] == len(expected_install)
    finally:
        tmp.cleanup()


def main() -> int:
    tests = [
        test_t15_unknown_ordinary_file_fails,
        test_t16_missing_unsafe_and_symlink_fail,
        test_t17_all_version_authorities_fail_closed,
        test_t17_historical_release_references_remain_valid,
        test_t17_worker_identity_is_independent_and_fail_closed,
        test_t17_package_lock_top_level_version_fails_closed,
        test_t17_package_lock_root_version_fails_closed,
        test_t17_package_lock_missing_empty_and_malformed_fail_closed,
        test_t18_clean_inventory_and_zip_are_deterministic,
        test_t19_current_help_projection_renders_canonical_version,
    ]
    for test in tests:
        test()
        print(f"PASS {test.__name__}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
