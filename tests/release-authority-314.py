#!/usr/bin/env python3
from __future__ import annotations

import importlib.util
import json
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

VERSION = "3.7.14"
BASE_PATHS = [
    "config/critical-files.json",
    "edis-evidence-exporter.php",
    "package-lock.json",
    "package.json",
    "plugin.manifest.json",
    "src/Application/ExportJobService.php",
    "src/Application/ExportService.php",
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
        "<?php\n/**\n * Version: 3.7.14\n */\n"
        "define('EDIS_EVIDENCE_EXPORTER_VERSION', '3.7.14');\n"
        "define('EDIS_EVIDENCE_BUILD_PLATFORM_VERSION', '3.7.14');\n",
    )
    write(root / "package.json", json.dumps({"version": VERSION}) + "\n")
    write(
        root / "package-lock.json",
        json.dumps({"version": VERSION, "packages": {"": {"version": VERSION}}}) + "\n",
    )
    write(root / "plugin.manifest.json", json.dumps(manifest(), separators=(",", ":")) + "\n")
    write(root / "config/critical-files.json", json.dumps({"plugin_version": VERSION, "files": {}}) + "\n")
    write(root / "src/Application/ExportService.php", "<?php final class X { private const PRODUCER_VERSION = '3.7.14'; }\n")
    write(root / "src/Application/ExportJobService.php", "<?php final class Y { private const IMPLEMENTATION_VERSION = '3.7.14'; }\n")
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
            (root / "edis-evidence-exporter.php").read_text(encoding="utf-8").replace("Version: 3.7.14", "Version: 3.7.13"),
        ),
        "exporter_constant_version": lambda root: write(
            root / "edis-evidence-exporter.php",
            (root / "edis-evidence-exporter.php").read_text(encoding="utf-8").replace("EDIS_EVIDENCE_EXPORTER_VERSION', '3.7.14", "EDIS_EVIDENCE_EXPORTER_VERSION', '3.7.13"),
        ),
        "platform_constant_version": lambda root: write(
            root / "edis-evidence-exporter.php",
            (root / "edis-evidence-exporter.php").read_text(encoding="utf-8").replace("EDIS_EVIDENCE_BUILD_PLATFORM_VERSION', '3.7.14", "EDIS_EVIDENCE_BUILD_PLATFORM_VERSION', '3.7.13"),
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
        "worker_implementation_version": lambda root: write(
            root / "src/Application/ExportJobService.php",
            "<?php final class Y { private const IMPLEMENTATION_VERSION = '3.7.13'; }\n",
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
        assert identity["worker_implementation_version"] == VERSION

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
        test_t17_package_lock_top_level_version_fails_closed,
        test_t17_package_lock_root_version_fails_closed,
        test_t17_package_lock_missing_empty_and_malformed_fail_closed,
        test_t18_clean_inventory_and_zip_are_deterministic,
    ]
    for test in tests:
        test()
        print(f"PASS {test.__name__}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
