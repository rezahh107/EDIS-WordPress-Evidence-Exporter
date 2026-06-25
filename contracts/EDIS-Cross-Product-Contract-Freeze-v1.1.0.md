# EDIS Cross-Product Contract Freeze v1.1.0

**Status:** FROZEN ADDENDUM  
**Supersedes:** only the version-boundary and selection-hardening clauses of v1.0.0  
**Architecture boundaries:** unchanged

## Product versions

```text
WordPress Evidence Exporter: 3.6.0
WordPress Bundle Schema: 3.2.0
Selection Snapshot Schema: 1.2.0
Browser Runtime Collector target: 1.4.0
Python ingestion: version-routed
```

## Frozen hardening clauses

1. Inspector selection is operational input; saved evidence remains sourced from WordPress persistence.
2. Inspector selections use authenticated, owner-bound, document-bound, short-lived tokens rather than JSON query strings.
3. Semantic selection identity excludes timestamps, editor-session state, token values, user IDs, job IDs, and bundle IDs.
4. Selection sets are canonicalized by document ID, source document order, source path, and element ID; click order is operational provenance only.
5. Source projection preserves anonymous or unknown wrapper nodes and descendants. Silent structural deletion is forbidden.
6. Final package validation covers every final JSON artifact and every schema keyword used by bundled schemas.
7. Private evidence storage must be outside the public web root or protected by verified access guards; failure is fail-closed.
8. `edis_export_evidence` plus object-level `edit_post` authorization is required for document operations.
9. EDIS-CJ-1 requires deterministic number serialization and rejects unsupported runtime configuration.
10. EDIS-URL-1 rejects invalid percent escapes; IDN normalization is unavailable when standards-based ASCII conversion is unavailable.

## Ownership

WordPress exports saved source evidence and source indexes. Browser exports runtime evidence and preliminary binding. Python owns final correlation, effective-value resolution, formulas, rules, diagnostics, and TruthReport. LLM only explains Python output.

## Conformance gate

Coordinated compatibility remains conditional until PHP, JavaScript, and Python pass identical EDIS-CJ-1 and EDIS-URL-1 vectors and Browser/Python consume Bundle Schema 3.2.0 and Selection Snapshot 1.2.0.
