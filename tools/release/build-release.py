#!/usr/bin/env python3
"""Build deterministic EDIS install/source ZIPs from authoritative release inventory."""
from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path, PurePosixPath
import re
import shutil
import subprocess
import tempfile
import zipfile

FIXED_TIME = (1980, 1, 1, 0, 0, 0)
SLUG = "edis-evidence-exporter"
GENERATED_OR_DEPENDENCY_ROOTS = {
    ".git",
    ".idea",
    ".mypy_cache",
    ".phpunit.cache",
    ".pytest_cache",
    ".ruff_cache",
    ".vscode",
    "coverage",
    "htmlcov",
    "node_modules",
    "release-build",
    "vendor",
}
GENERATED_OR_DEPENDENCY_FILES = {".coverage", ".phpunit.result.cache"}
SOURCE_ONLY_FILES = ("composer.lock",)


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def safe_relative(value: str) -> str:
    normalized = value.replace("\\", "/")
    path = PurePosixPath(normalized)
    if (
        normalized == ""
        or normalized.startswith("/")
        or "\\" in value
        or path.is_absolute()
        or any(part in {"", ".", ".."} for part in path.parts)
        or (len(normalized) >= 2 and normalized[0].isalpha() and normalized[1] == ":")
    ):
        raise RuntimeError(f"Unsafe release inventory path: {value!r}")
    return path.as_posix()


def read_json(path: Path) -> dict[str, object]:
    try:
        decoded = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        raise RuntimeError(f"Invalid required JSON authority: {path.name}") from exc
    if not isinstance(decoded, dict):
        raise RuntimeError(f"Required JSON authority must be an object: {path.name}")
    return decoded


def required_non_empty_string(value: object, label: str) -> str:
    if not isinstance(value, str) or value.strip() == "":
        raise RuntimeError(f"Missing or empty release version authority: {label}")
    return value


def release_manifest(root: Path) -> dict[str, object]:
    return read_json(root / "plugin.manifest.json")


def authoritative_inventory(root: Path) -> tuple[list[Path], list[Path], list[str]]:
    manifest = release_manifest(root)
    entries = manifest.get("files")
    if not isinstance(entries, list):
        raise RuntimeError("plugin.manifest.json files must be an array.")

    seen: set[str] = set()
    source_paths: list[str] = []
    install_paths: list[str] = []
    for entry in entries:
        if not isinstance(entry, dict):
            raise RuntimeError("plugin.manifest.json contains a non-object file entry.")
        raw_path = entry.get("path")
        if not isinstance(raw_path, str):
            raise RuntimeError("plugin.manifest.json file entry is missing a string path.")
        relative = safe_relative(raw_path)
        if relative in seen:
            raise RuntimeError(f"Duplicate release authority entry: {relative}")
        seen.add(relative)
        source_paths.append(relative)
        if entry.get("installable") is True:
            install_paths.append(relative)
        elif entry.get("installable") is not False:
            raise RuntimeError(f"Release authority entry has non-boolean installable state: {relative}")

    for relative in SOURCE_ONLY_FILES:
        if relative in seen:
            raise RuntimeError(f"Source-only authority duplicates manifest entry: {relative}")
        source_paths.append(relative)

    source_paths = sorted(source_paths, key=lambda value: value.encode("utf-8"))
    install_paths = sorted(install_paths, key=lambda value: value.encode("utf-8"))
    source_files = [root / relative for relative in source_paths]
    install_files = [root / relative for relative in install_paths]

    for relative, path in zip(source_paths, source_files, strict=True):
        if not path.exists():
            raise RuntimeError(f"Declared release authority file is missing: {relative}")
        if path.is_symlink():
            raise RuntimeError(f"Symlink is prohibited in release authority: {relative}")
        if not path.is_file():
            raise RuntimeError(f"Declared release authority path is not a regular file: {relative}")

    audit_workspace(root, set(source_paths))
    return source_files, install_files, source_paths


def audit_workspace(root: Path, authorized: set[str]) -> None:
    for path in root.rglob("*"):
        try:
            relative_path = path.relative_to(root)
        except ValueError as exc:
            raise RuntimeError("Workspace path escaped repository root.") from exc
        parts = relative_path.parts
        if not parts:
            continue
        if parts[0] in GENERATED_OR_DEPENDENCY_ROOTS:
            continue
        relative = PurePosixPath(*parts).as_posix()
        if relative in GENERATED_OR_DEPENDENCY_FILES:
            continue
        if path.is_symlink():
            raise RuntimeError(f"Undeclared or prohibited symlink in workspace: {relative}")
        if path.is_file() and relative not in authorized:
            raise RuntimeError(f"Undeclared ordinary source file in workspace: {relative}")


def source_inventory_sha256(source_paths: list[str]) -> str:
    payload = json.dumps(source_paths, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
    return hashlib.sha256(payload).hexdigest()


def read_match(path: Path, pattern: str, label: str) -> str:
    text = path.read_text(encoding="utf-8")
    match = re.search(pattern, text, re.MULTILINE)
    if not match:
        raise RuntimeError(f"Could not resolve version authority: {label}")
    return match.group(1)


def release_identity(root: Path, source_paths: list[str]) -> dict[str, str]:
    manifest = release_manifest(root)
    critical = read_json(root / "config/critical-files.json")
    package = read_json(root / "package.json")
    package_lock = read_json(root / "package-lock.json")
    lock_packages = package_lock.get("packages")
    if not isinstance(lock_packages, dict):
        raise RuntimeError('package-lock.json packages must be an object.')
    lock_root = lock_packages.get("")
    if not isinstance(lock_root, dict):
        raise RuntimeError('package-lock.json packages[""] must be an object.')
    plugin = manifest.get("plugin") if isinstance(manifest.get("plugin"), dict) else {}
    build = manifest.get("build") if isinstance(manifest.get("build"), dict) else {}

    authorities = {
        "plugin_header_version": read_match(root / "edis-evidence-exporter.php", r"^\s*\*\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$", "plugin header Version"),
        "exporter_constant_version": read_match(root / "edis-evidence-exporter.php", r"EDIS_EVIDENCE_EXPORTER_VERSION'\s*,\s*'([0-9]+\.[0-9]+\.[0-9]+)'", "EDIS_EVIDENCE_EXPORTER_VERSION"),
        "platform_constant_version": read_match(root / "edis-evidence-exporter.php", r"EDIS_EVIDENCE_BUILD_PLATFORM_VERSION'\s*,\s*'([0-9]+\.[0-9]+\.[0-9]+)'", "EDIS_EVIDENCE_BUILD_PLATFORM_VERSION"),
        "package_json_version": str(package.get("version", "")),
        "package_lock_version": required_non_empty_string(package_lock.get("version"), "package-lock.json version"),
        "package_lock_root_version": required_non_empty_string(lock_root.get("version"), 'package-lock.json packages[""].version'),
        "manifest_plugin_version": str(plugin.get("version", "")),
        "manifest_build_version": str(build.get("version", "")),
        "manifest_build_platform_version": str(build.get("platform_version", "")),
        "manifest_platform_version": str(manifest.get("platform_version", "")),
        "producer_version": read_match(root / "src/Application/ExportService.php", r"PRODUCER_VERSION\s*=\s*'([0-9]+\.[0-9]+\.[0-9]+)'", "ExportService producer version"),
        "critical_files_plugin_version": str(critical.get("plugin_version", "")),
    }
    values = set(authorities.values())
    if "" in values or len(values) != 1:
        raise RuntimeError("Intended-equal release version authorities disagree: " + json.dumps(authorities, sort_keys=True))
    plugin_version = next(iter(values))
    worker_version = read_match(root / "src/Application/ExportJobService.php", r"IMPLEMENTATION_VERSION\s*=\s*'([0-9]+\.[0-9]+\.[0-9]+)'", "worker implementation version")
    if worker_version != plugin_version:
        raise RuntimeError(f"This release requires worker implementation {plugin_version}; found {worker_version}.")

    return {
        "plugin_version": plugin_version,
        "worker_implementation_version": worker_version,
        "plugin_manifest_sha256": sha256(root / "plugin.manifest.json"),
        "critical_files_manifest_sha256": sha256(root / "config/critical-files.json"),
        "source_inventory_sha256": source_inventory_sha256(source_paths),
    }


def zip_files(root: Path, output: Path, files: list[Path]) -> None:
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_STORED, allowZip64=False) as archive:
        for path in files:
            relative = path.relative_to(root).as_posix()
            archive_path = PurePosixPath(SLUG, relative).as_posix()
            info = zipfile.ZipInfo(archive_path, FIXED_TIME)
            info.compress_type = zipfile.ZIP_STORED
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            info.flag_bits |= 0x800
            info.extra = b""
            info.comment = b""
            archive.writestr(info, path.read_bytes())


def verify_archive(path: Path, expected_files: list[str]) -> dict[str, object]:
    with zipfile.ZipFile(path, "r") as archive:
        infos = archive.infolist()
        names = [info.filename for info in infos]
        expected_names = [PurePosixPath(SLUG, name).as_posix() for name in expected_files]
        if names != expected_names:
            raise RuntimeError(f"Archive order/content mismatch: {path.name}")
        for info in infos:
            if info.compress_type != zipfile.ZIP_STORED or info.date_time != FIXED_TIME:
                raise RuntimeError(f"Archive profile mismatch: {info.filename}")
            if info.extra or info.comment:
                raise RuntimeError(f"Archive metadata mismatch: {info.filename}")
            if info.file_size > 0xFFFFFFFE or info.header_offset > 0xFFFFFFFE:
                raise RuntimeError("ZIP64 boundary exceeded.")
        return {"entry_count": len(infos), "size": path.stat().st_size, "sha256": sha256(path)}


def lint_install(path: Path) -> dict[str, object]:
    with tempfile.TemporaryDirectory(prefix="edis-install-validate-") as tmp:
        directory = Path(tmp)
        with zipfile.ZipFile(path, "r") as archive:
            archive.extractall(directory)
        install_root = directory / SLUG
        failures = []
        php_files = sorted(install_root.rglob("*.php"))
        for php_file in php_files:
            result = subprocess.run(["php", "-l", str(php_file)], capture_output=True, text=True, check=False)
            if result.returncode != 0:
                failures.append({"path": php_file.relative_to(install_root).as_posix(), "stderr": result.stderr.strip()})
        return {"php_file_count": len(php_files), "state": "PASS" if not failures else "FAIL", "failures": failures}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", type=Path, default=Path.cwd())
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    root = args.root.resolve()
    output = args.output.resolve()
    output.mkdir(parents=True, exist_ok=True)

    source_files, install_files, source_paths = authoritative_inventory(root)
    identity = release_identity(root, source_paths)
    release_version = identity["plugin_version"]
    install_rel = [path.relative_to(root).as_posix() for path in install_files]
    source_rel = [path.relative_to(root).as_posix() for path in source_files]

    install = output / f"{SLUG}-{release_version}.zip"
    source = output / f"{SLUG}-{release_version}-source.zip"
    with tempfile.TemporaryDirectory(prefix="edis-rebuild-") as tmp:
        tmp_path = Path(tmp)
        first_install = tmp_path / "install-1.zip"
        second_install = tmp_path / "install-2.zip"
        first_source = tmp_path / "source-1.zip"
        second_source = tmp_path / "source-2.zip"
        zip_files(root, first_install, install_files)
        zip_files(root, second_install, install_files)
        zip_files(root, first_source, source_files)
        zip_files(root, second_source, source_files)
        if first_install.read_bytes() != second_install.read_bytes() or first_source.read_bytes() != second_source.read_bytes():
            raise RuntimeError("Deterministic rebuild comparison failed.")
        shutil.copyfile(first_install, install)
        shutil.copyfile(first_source, source)

    report = {
        "format": "EDIS-RELEASE-BUILD-2",
        "version": release_version,
        "archive_root": SLUG,
        "zip_profile": "EDIS-ZIP-1",
        "compression": "STORE",
        "zip64": "FORBIDDEN",
        "timestamp": "1980-01-01T00:00:00Z",
        "deterministic_rebuild": "PASS",
        "release_authority": "plugin.manifest.json",
        "install_authority": "manifest.files[installable=true]",
        "source_authority": "manifest.files + composer.lock",
        "build_identity": identity,
        "plugin_manifest_sha256": identity["plugin_manifest_sha256"],
        "critical_files_manifest_sha256": identity["critical_files_manifest_sha256"],
        "source_inventory_sha256": identity["source_inventory_sha256"],
        "plugin_version": identity["plugin_version"],
        "worker_implementation_version": identity["worker_implementation_version"],
        "install": verify_archive(install, install_rel),
        "source": verify_archive(source, source_rel),
        "install_validation": lint_install(install),
    }
    report_path = output / f"{SLUG}-{release_version}-build-report.json"
    report_path.write_text(json.dumps(report, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n", encoding="utf-8")
    deliverables = [install, source, report_path]
    sums = "".join(f"{sha256(path)}  {path.name}\n" for path in deliverables)
    (output / "SHA256SUMS").write_text(sums, encoding="utf-8")
    return 0 if report["install_validation"]["state"] == "PASS" else 1


if __name__ == "__main__":
    raise SystemExit(main())
