# Migration from EDIS 3.5.0 to 3.6.0

1. Back up files and database.
2. Deactivate 3.5.0 without uninstalling it.
3. Install 3.6.0 using the same plugin slug.
4. Activate the plugin so the administrator role receives `edis_export_evidence`.
5. Run Diagnostics and Safe Worker Test.
6. Discard incomplete 3.5.0 jobs and create new exports.
7. Re-open Elementor before using Inspector; old query-string selections are not migrated.

## Contract changes

- Bundle Schema: `3.2.0`
- Selection Snapshot: `1.2.0`
- Inspector uses short-lived server-side tokens.
- Selection semantic identity excludes operational fields.
- Private storage is mandatory and fail-closed.
- Anonymous source wrappers are preserved by subtree projection.
