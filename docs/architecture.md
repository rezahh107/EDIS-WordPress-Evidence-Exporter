# Runtime Architecture — EDIS 3.7.11

```text
WordPress composition root
    -> frozen/versioned collector definitions
    -> CollectorRegistry
    -> ExportJobService
        -> immutable InputSnapshotStore 2.0.0
        -> JobStore and ArtifactStore
        -> DeterministicFilesystem
        -> bounded execution plan
    -> ExportService
        -> EDIS-CJ-2
        -> dual artifact hashes
        -> schema/package validation
        -> DeterministicZipWriter (EDIS-ZIP-1)
```

## Ownership boundary

WordPress owns saved WordPress/Elementor evidence, provenance, private persistence, deterministic source indexes and package integrity. Browser owns runtime DOM, geometry, computed styles, relationships and preliminary source binding. Python owns schema/provenance validation, deterministic merge, dependency graphs, reference/class/variable/breakpoint resolution, formulas, rules, final correlation, diagnostics and TruthReport. The LLM explains Python output only.

## Lossless source boundary

Raw saved JSON is parsed into typed nodes before conversion to processing arrays. Object/array shape, exact object keys and exact decimal number semantics are preserved for emitted source evidence. Duplicate object keys and invalid UTF-8 fail closed. Index builders may receive a bounded processing view, but they do not replace the preserved source node.

## Hash boundary

```text
semantic_payload_sha256
    schema identity + canonicalization descriptor + semantic projection + semantic diagnostics

artifact_instance_sha256
    complete envelope except artifact_instance_sha256

file sha256
    exact emitted bytes

source_export_root_sha256
    deterministic package-level root over committed source files
```

Operational identity remains available in artifacts and instance/file hashes but is excluded from semantic identity according to policy `1.0.0`.

## Deterministic package boundary

EDIS-ZIP-1 uses uncompressed entries, exact UTF-8 path order, fixed timestamps, fixed Unix attributes and no ZIP64. The archive writer refuses unsafe paths, oversized names, excessive file counts and sizes requiring ZIP64.

## Storage and locking

Critical JSON and ZIP metadata writes use temporary files, complete writes, `fflush`, `fsync`, permission application, atomic replacement/rename and final hash verification. A forced active-path self-test proves exclusion with two local handles and a separate PHP process. A successful proof may be reused through a bounded, integrity-protected attestation bound to the storage path and runtime environment; explicit diagnostics and preflight still force a live proof. Clustered/shared filesystems still require deployment-specific real testing.

## Version boundary

```text
WordPress Exporter: 3.7.11
Bundle Schema: 3.3.0
Shared Envelope: 2.0.0
Selection Snapshot: 1.2.0
Private Job Format: 2.1.0
Private Input Snapshot Format: 2.0.0
Canonical JSON: EDIS-CJ-2
Deterministic ZIP: EDIS-ZIP-1
```

No older Job/Input Snapshot format is resumed. Browser/Python compatibility is not inferred from version similarity and remains `insufficient_evidence` until identical vectors pass.


## WordPress runtime boundary in 3.7.11

Ordinary frontend requests stop after the lightweight bootstrap and do not hydrate collector registries or private stores. Admin, REST, Cron, AJAX and WP-CLI contexts construct only the services required for that request class.

The WordPress integration layer owns lifecycle, capability registration, Site Health, privacy tools, WP-CLI commands, cron wake-ups and multisite routing. It does not acquire any Python-owned resolution responsibility.

### Multisite isolation

A network-active installation initializes existing sites transactionally and initializes new sites through `wp_initialize_site`. Private storage is namespaced by network ID and blog ID. Switching blogs must always be paired with `restore_current_blog()` in a `finally` block.

### Worker lease boundary

Job Format `2.1.0` adds operational lease owner, acquisition and expiry fields. A lease is not semantic evidence. It prevents concurrent workers, is refreshed with heartbeat updates and is cleared on completion/failure. Stale-job repair requeues only expired queued/running jobs under a job lock.

## WordPress Filesystem boundary

`WordPressFilesystemPreflightAdapter` observes the host-selected WordPress transport without using it as a substitute for deterministic storage. The exporter requires unattended direct storage with verified `fsync`, atomic replacement and process locking; unsupported or interactive-only storage remains fail-closed.


## Version 3.7.11 corrective invariants

- Private storage is accepted only when both logical and physical paths are outside `ABSPATH` and no existing ancestor redirects through a symlink-like path.
- The exact active path must pass durable write, atomic rename/replace, local-handle exclusion and separate-process exclusion.
- Semantic operational exclusions are exact-path rules, not recursive bare-key rules.
- A per-job lock pathname is stable for the lifetime of the site-scoped storage tree; normal cleanup never unlinks it.
- Resume/Retry mutation and worker advancement are one lock-owned transition.
