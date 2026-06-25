# EDIS Cross-Product Contract Freeze v1.2.0

**Status:** FROZEN ADDENDUM  
**Supersedes:** only the version-boundary, canonical JSON, artifact-hash, and deterministic-package clauses of v1.1.0  
**Architecture boundaries:** unchanged

## Product versions

```text
WordPress Evidence Exporter: 3.7.0
WordPress Bundle Schema: 3.3.0
Selection Snapshot Schema: 1.2.0
Private Job Format: 2.0.0
Private Input Snapshot Format: 2.0.0
Browser Runtime Collector target: version-routed; 1.4.0 is not presumed compatible with Bundle 3.3.0
Python ingestion: version-routed
```

## Frozen deterministic clauses

1. `EDIS-CJ-2` accepts only valid UTF-8 JSON text. Invalid UTF-8 is rejected.
2. JSON object keys are sorted lexicographically by their exact UTF-8 bytes. No Unicode normalization is applied.
3. JSON objects and arrays remain distinct, including empty `{}` versus empty `[]`. Object keys that look numeric remain object keys.
4. Duplicate object keys are rejected before native PHP conversion with diagnostic `EDIS_DUPLICATE_JSON_OBJECT_KEY`.
5. Source JSON numbers are parsed as exact lexical decimal nodes and canonicalized without binary-float conversion. Exponents or expanded values beyond the versioned normalization budget are rejected.
6. Native PHP integers remain limited to the interoperable safe-integer range. Native PHP floats must be finite and use the supported deterministic serialization environment.
7. Every artifact envelope has two hashes:
   - `semantic_payload_sha256`: schema identity, canonicalization descriptor, semantic evidence projection, and semantic diagnostics;
   - `artifact_instance_sha256`: the complete emitted envelope except the self-referential instance-hash field.
8. Operational identifiers and capture/session fields excluded by semantic identity policy `1.0.0` remain present in the artifact instance and file hashes.
9. `EDIS-ZIP-1` uses deterministic uncompressed ZIP entries (`STORE`), fixed entry order, fixed DOS timestamps, UTF-8 filenames, fixed Unix file attributes, no comments, and no ZIP64. A limit violation fails closed.
10. Critical private writes use temporary-file creation, complete writes, `fflush`, `fsync`, permission application where supported, atomic rename, and final SHA-256 verification.
11. Advisory file locking is accepted only after a storage self-test proves exclusion for two independent handles and a separate PHP process in the active storage environment. If an independent-process probe cannot be executed or exclusion fails, export creation is blocked.
12. Jobs created with a private Job or Input Snapshot format older than `2.0.0` are not resumed under 3.7.0.
13. WordPress remains a saved-source evidence exporter. Lossless parsing, indexing, hashing, packaging, and validation do not authorize final resolution, formula evaluation, runtime correlation, or TruthReport generation in WordPress.

## Cross-product vectors

The canonical vectors are `contracts/edis-cj-2-vectors.json`. PHP, Browser JavaScript, and Python must produce identical canonical bytes, semantic hashes, and invalid-input diagnostics before coordinated compatibility can be declared.

## Conformance gate

```text
wordpress_bundle_3_3_0: implemented_by_wordpress_3_7_0
browser_1_4_0_compatibility: insufficient_evidence
python_compatibility: insufficient_evidence
coordinated_cross_product_status: insufficient_evidence
```

Browser and Python must explicitly route Bundle Schema `3.3.0`, Shared Envelope `2.0.0`, `EDIS-CJ-2`, and both artifact hashes. Silent fallback to Bundle Schema `3.2.0` or `EDIS-CJ-1` is forbidden.
