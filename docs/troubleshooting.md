# Troubleshooting

## Export stays queued

The administration client advances jobs through authenticated REST. Open **Diagnostics**, verify REST and private storage, then run **Safe worker test**. Check the browser network panel, nonce freshness, object authorization, heartbeat and revision conflicts. Disabled WP-Cron alone does not block the normal execution path.

## Immutable input snapshot storage fails

`EDIS_INPUT_SNAPSHOT_STORAGE_UNAVAILABLE` means the protected `inputs` sub-store could not pass the required directory or write checks. Confirm that the configured private storage is outside the public web root, is not a symlink, is writable by the PHP process, and supports temporary-file write plus atomic replacement/rename.

## Source changes while the job is created

`EDIS_SOURCE_CHANGED_DURING_SNAPSHOT` is fail-closed protection, not data repair. The saved Elementor source differed between the two bounded reads used to commit the snapshot. Save the editor, stop concurrent saves or automation touching the post, and create a new job.

## A 3.7.0 or older job will not resume

`EDIS_JOB_FORMAT_INCOMPATIBLE` prevents a pre-3.7.1 job from being continued under the new Job Format 2.1.0 execution semantics. Create a new export. A completed and already validated older ZIP may remain as an immutable historical package; EDIS does not mutate it.

## Snapshot integrity mismatch

`EDIS_RESUME_INPUT_MISMATCH` means the committed input snapshot is missing, corrupted, replaced or does not match the hash bound to the job. Do not recreate files manually. Inspect storage health and create a new export.

## Completed artifact mismatch

`EDIS_RESUME_ARTIFACT_MISMATCH` means a previously completed artifact is absent or its file hash, step input, schema version or implementation version no longer matches its step record. The correct recovery is a new job after filesystem investigation, not overwriting the recorded checksum.

## Breakpoint direction or active state is null

This can be correct evidence. Elementor's public breakpoint manager may expose a breakpoint collection without proving whether each entry is active or whether it is a min-width/max-width boundary. Version 3.7.1 does not infer those facts from IDs such as `widescreen`. Python and Browser evidence must supply the information required for final responsive resolution.

## A legacy responsive suffix is not indexed

Legacy suffix scanning is limited to breakpoint IDs actually observed from the exported Elementor breakpoint registry. Confirm that the custom breakpoint exists in the active site configuration and that the breakpoint collector is available. EDIS does not treat a known suffix name as evidence that the site uses it.

## ZIP creation unavailable

EDIS 3.7.1 creates runtime bundles through its bundled deterministic STORE-only writer; the PHP ZIP extension is not required for package creation. Inspect Diagnostics for filesystem, CRC32B, path-budget, archive-size and private-storage failures. EDIS does not report a successful export without a complete validated package.

## A component is unavailable

Availability describes the current environment, not universal Elementor support. Check Elementor activation, active Kit, selected documents, dependency artifacts and the component diagnostic code.

## Browser correlation is missing

Export `bridge/source-context.json`, import it locally into Browser Runtime Collector 1.4.0 and validate both packages in Python. WordPress does not perform final source/runtime correlation.

## `EDIS_DUPLICATE_JSON_OBJECT_KEY`

The saved JSON contains the same object key more than once. PHP's normal decoder would silently collapse this ambiguity, so EDIS 3.7.1 blocks canonicalization. Preserve the raw source for diagnosis and repair the source through Elementor/WordPress or the responsible addon; do not hand-edit the EDIS snapshot.

## `EDIS_JSON_NUMBER_LIMIT_EXCEEDED`

A source number requires expansion beyond the EDIS-CJ-2 normalization budget. This is a hostile-input/performance guard. The value is not rounded or converted to float.

## Private storage activation fails on file locking

The installable runtime self-test holds a lock and launches a separate `PHP_BINARY` through `proc_open` against the same active-path lock file. Confirm that process functions are enabled and that the private path honors advisory locks and atomic rename. `UNAVAILABLE` is fail-closed. NFS, clustered mounts and container-shared volumes still require a deployment-specific multi-host concurrency test; one-host process exclusion is not proof for a distributed lock domain.

## Bundle is larger in 3.7.1

EDIS-ZIP-1 uses STORE instead of compression to make archive bytes reproducible across supported environments. The size increase is expected. Do not recompress the ZIP when comparing package SHA-256.

## Browser or Python rejects Bundle 3.3.0

This is expected until those products explicitly route Shared Envelope 2.0.0, EDIS-CJ-2 and dual hashes. Do not downgrade the version field or reinterpret the package as 3.2.0. Report `cross_product_status: insufficient_evidence`.


## Site Health reports an EDIS runtime or storage failure

Open **Tools → Site Health**. The direct runtime and scheduling checks are lightweight; the private-storage test is asynchronous because it performs durable-write, atomic-replace and local-handle lock probes. A critical storage result blocks exports. Run `wp edis storage self-test` from the same PHP deployment context for machine-readable details.

## A low-traffic site runs recovery late

WP-Cron timing is request-driven and is not treated as an exact scheduler. Configure a real system scheduler to invoke WordPress Cron or run `wp edis worker run` periodically. Do not bypass job locks or edit job JSON manually.

## A stale job appears after a process crash

Run `wp edis jobs repair` first. This is a dry run. Review the candidate IDs, investigate storage health, then run `wp edis jobs repair --apply` only when the lease is expired and no worker remains active.

## A multisite site cannot see another site's job

This is expected. Version 3.7.1 namespaces private storage by network and blog ID. A Job ID, download token or bundle path from one site is not valid evidence authority on another site.

## Data remains after plugin deactivation

Deactivation stops new jobs and clears scheduled hooks but intentionally retains settings and evidence. Permanent deletion occurs only through uninstall when **Retain evidence on uninstall** is disabled. This separation prevents accidental evidence loss during routine troubleshooting or upgrades.

## WordPress reports an FTP or SSH filesystem method

Run **Tools → Site Health** or `wp edis storage self-test`. EDIS does not request interactive credentials during a background job. Configure a private direct path that passes the EDIS self-test; do not bypass the storage gate.

## Package contract validation fails for Elementor kit/site settings

For 3.7.11 the declared shapes are:

```text
sources/elementor/kit-settings.json      $.evidence.settings → object
indexes/site-settings-index.json         $.evidence.groups   → object
indexes/site-settings-index.json         $.evidence.source   → string|null
```

The diagnostic now includes expected and actual JSON types. A clean 3.7.11 package is regression-tested for both empty and populated map shapes. If the failure includes `EDIS_INSTALLATION_MIXED_VERSION`, delete the complete plugin directory and reinstall the complete ZIP. If installation integrity passes but package validation still fails, retain the failed Job and final artifact bytes and report the exact expected/actual type; do not manually coerce the evidence.

## WordPress previously showed a critical error for private storage

Versions with an uncaught storage preflight could terminate the WordPress request. Version 3.7.11 keeps evidence creation fail-closed but boots a degraded diagnostic integration instead. WordPress, the administrator notice and Site Health remain available.

On Windows/Local, place private evidence outside the public `app/public` directory. A recommended explicit configuration is:

```php
define( 'EDIS_EVIDENCE_PRIVATE_STORAGE_DIR', 'C:/Users/Nestech/Local Sites/nurro/edis-private-storage' );
```

Local documents the public WordPress root as `<site>/app/public`; EDIS therefore prefers `<site>/edis-private-storage`. Create the directory or allow the Local PHP process to create it. Do not use a directory under `app/public`, `wp-content` or uploads. In Local’s Open Site Shell run `wp edis storage paths` and `wp edis storage self-test` for exact candidate and failed-check diagnostics.

## `EDIS_INSTALLATION_MIXED_VERSION`

The installed critical files do not match the bundled SHA-256 manifest or the plugin header version. This commonly results from extracting a ZIP over an existing directory, an interrupted deployment or a local modification. Deactivate the plugin if needed, delete its entire directory, reinstall one complete signed/verified package and create new incomplete Jobs. Do not copy individual PHP or schema files between releases.
