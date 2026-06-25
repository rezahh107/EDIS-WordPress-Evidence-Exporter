# Migration from EDIS 3.3.0 to 3.4.0

1. Back up WordPress files and the database.
2. Deactivate 3.3.0 without uninstalling it.
3. Install and activate 3.4.0 using the same plugin slug.
4. Open **EDIS Evidence → Diagnostics** and run **Safe Worker Test**.
5. Create a new export; incomplete 3.3 jobs are not resumable because the validation and package contracts changed.

## Contract changes

- WordPress Bundle Schema is now `3.0.0`.
- `saved_source_sha256` remains only as a deprecated compatibility alias for `canonical_saved_source_sha256`.
- New explicit fields distinguish raw storage bytes, canonical decoded source and final artifact-file hashes.
- Selection Snapshot, Evidence Conservation, Unknown Structure Ledger, Source Comparison and optional Fixture Metadata are new artifacts.
- Package validation now reports package integrity, contract validation and analysis readiness separately.
- Single/Multiple Document + Required Dependencies excludes unrelated document records.

Python consumers must route Bundle Schema 3.0.0 explicitly and must not assign new evidence to historical bundles.
