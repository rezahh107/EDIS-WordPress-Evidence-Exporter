# Migration from EDIS 3.4.0 to 3.5.0

1. Back up the WordPress site and database.
2. Deactivate 3.4.0 without uninstalling it.
3. Install and activate 3.5.0 using the same plugin slug.
4. Open **EDIS Evidence → Diagnostics** and run the Safe Worker Test.
5. Open a page in Elementor, right-click a saved element and choose an EDIS Inspector action.
6. Run Preflight before creating the first element- or subtree-scoped export.

## Contract changes

- WordPress Bundle Schema is now `3.1.0`.
- `elementor_kit_settings.settings` is serialized as a JSON object, including the empty-map case.
- `elementor_site_settings_index.source` is a string and `groups` is an explicit JSON object.
- Selection Snapshot schema is `1.1.0` and can contain bounded element selections.
- Element/subtree exports always use the last saved WordPress source. Unsaved Editor state is never merged into source evidence.
- Selected subtree projection preserves original source paths, document order, parents and ancestor IDs.

Incomplete 3.4.0 jobs must be recreated because the selection contract and package schema changed. Existing 3.4.0 ZIPs remain historical evidence and must be routed through their original schema reader.
