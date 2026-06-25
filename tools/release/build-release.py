#!/usr/bin/env python3
"""Build deterministic EDIS install/source ZIPs and a machine-readable report."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import subprocess
import tempfile
import zipfile

FIXED_TIME = (1980, 1, 1, 0, 0, 0)
SLUG = "edis-evidence-exporter"


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def version(root: Path) -> str:
    text = (root / "edis-evidence-exporter.php").read_text(encoding="utf-8")
    match = re.search(r"Version:\s*([0-9]+\.[0-9]+\.[0-9]+)", text)
    if not match:
        raise RuntimeError("Plugin version could not be read.")
    return match.group(1)


def ignore_rules(root: Path) -> list[str]:
    rules = []
    for raw in (root / ".distignore").read_text(encoding="utf-8").splitlines():
        value = raw.strip().replace("\\", "/")
        if value and not value.startswith("#"):
            rules.append(value.lstrip("/"))
    return rules


def ignored(relative: str, rules: list[str]) -> bool:
    for rule in rules:
        if relative == rule or relative.startswith(rule.rstrip("/") + "/"):
            return True
    return False


def repository_files(root: Path) -> list[Path]:
    excluded_roots = {".git", "node_modules", "vendor", "release-build"}
    files = []
    for path in root.rglob("*"):
        if not path.is_file() or path.is_symlink():
            continue
        relative = path.relative_to(root)
        if any(part in excluded_roots for part in relative.parts):
            continue
        files.append(path)
    return sorted(files, key=lambda p: p.relative_to(root).as_posix().encode("utf-8"))


def zip_files(root: Path, output: Path, files: list[Path]) -> None:
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_STORED, allowZip64=False) as archive:
        for path in files:
            relative = path.relative_to(root).as_posix()
            archive_path = PurePosixPath(SLUG, relative).as_posix()
            info = zipfile.ZipInfo(archive_path, FIXED_TIME)
            info.compress_type = zipfile.ZIP_STORED
            info.create_system = 3
            info.external_attr = (0o100644 << 16)
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
        root = directory / SLUG
        failures = []
        for php_file in sorted(root.rglob("*.php")):
            result = subprocess.run(["php", "-l", str(php_file)], capture_output=True, text=True)
            if result.returncode != 0:
                failures.append({"path": php_file.relative_to(root).as_posix(), "stderr": result.stderr.strip()})
        return {"php_file_count": len(list(root.rglob("*.php"))), "state": "PASS" if not failures else "FAIL", "failures": failures}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", type=Path, default=Path.cwd())
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    root = args.root.resolve()
    output = args.output.resolve()
    output.mkdir(parents=True, exist_ok=True)
    release_version = version(root)
    rules = ignore_rules(root)
    source_files = repository_files(root)
    install_files = [p for p in source_files if not ignored(p.relative_to(root).as_posix(), rules)]

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

    install_rel = [p.relative_to(root).as_posix() for p in install_files]
    source_rel = [p.relative_to(root).as_posix() for p in source_files]
    report = {
        "format": "EDIS-RELEASE-BUILD-1",
        "version": release_version,
        "archive_root": SLUG,
        "zip_profile": "EDIS-ZIP-1",
        "compression": "STORE",
        "zip64": "FORBIDDEN",
        "timestamp": "1980-01-01T00:00:00Z",
        "deterministic_rebuild": "PASS",
        "install": verify_archive(install, install_rel),
        "source": verify_archive(source, source_rel),
        "install_validation": lint_install(install),
    }
    report_path = output / f"{SLUG}-{release_version}-build-report.json"
    report_path.write_text(json.dumps(report, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n", encoding="utf-8")
    deliverables = [install, source, report_path]
    sums = "".join(f"{sha256(path)}  {path.name}\n" for path in deliverables)
    (output / "SHA256SUMS").write_text(sums, encoding="utf-8")
    if report["install_validation"]["state"] != "PASS":
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
