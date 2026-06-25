# Privacy Model

- Local-only export; no AI, telemetry or remote analysis calls.
- No cookies, authorization headers, passwords, nonces, selection tokens or unrestricted dynamic-tag configuration in evidence packages.
- Strict mode excludes original saved documents from the final export package.
- Document titles are omitted except where an authorized Diagnostic contract explicitly allows bounded metadata.
- URL query strings and fragments are excluded from locator identity.
- Download requires capability checks, nonce, owner match, object-level access, expiring token, managed storage path, expected size and SHA-256.

## Private immutable input snapshots

Version 3.7.11 temporarily stores exact selected `_elementor_data` bytes in protected private storage so an in-flight job cannot silently switch to a later saved revision. These files are worker inputs, not automatically public package contents. Strict mode still controls whether original saved document objects are included in the final ZIP.

Snapshots:

- use the same fail-closed private-storage and symlink policy as jobs and artifacts;
- have bounded expiry and scheduled cleanup;
- are owner/job-bound through the job record;
- are verified before collection and resume;
- are not exposed through a direct public URL.

Administrators should configure the shortest retention period compatible with operational recovery and protect server backups according to the sensitivity of saved page source.

## EDIS 3.7.11 additions

The lossless parser changes representation, not collection scope. It does not authorize additional fields. Exact saved source bytes remain private worker input; original source objects enter the final package only when the selected privacy mode and option permit them. Duplicate-key diagnostics record the code/path state, not secret values. Operational IDs remain in instance evidence where required but are excluded from semantic hashes according to the frozen policy.


## WordPress privacy tools in 3.7.11

EDIS registers a personal-data exporter and eraser for owner-bound operational job records. The exporter returns bounded job ID, state and retention timestamps; it does not expose saved Elementor source through the privacy export. The eraser removes that user's jobs, step artifacts, immutable input snapshots, bundles, metadata and latest-job pointer.

Deleting a WordPress user invokes the same cleanup path. Evidence belonging to other users is not removed. Administrators must account for independent server backups separately.

## Deactivation, uninstall and retention

Deactivation disables new jobs and clears scheduled hooks but retains settings and evidence. Uninstall checks `edis_evidence_retain_data_on_uninstall` independently for each site. When retention is enabled, evidence remains and new jobs stay disabled. When disabled, options, capability assignments, operational records and the site's private-storage tree are deleted without following symlinks or deleting paths inside the public web root.

In multisite, operational storage is isolated by network and site IDs. Privacy export/erase operations execute only in the current site context.


## Version 3.7.11 storage and hash notes

The separate-process lock probe receives only the managed lock-file path; it does not read evidence payloads. The child process is the same PHP binary and is launched without a shell. If the probe cannot run, export creation is blocked.

Empty per-job `.lock` sentinel files may remain after job data is erased. They contain no source evidence, user values or tokens and exist solely to preserve lock identity. They disappear when the complete authorized site-storage tree is removed.

Semantic projection no longer drops nested source fields merely because their names resemble operational metadata. This is an evidence-conservation correction, not an expansion of collection scope.
