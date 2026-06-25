# EDIS Evidence Exporter — Complete English Guide

## 1. Purpose

EDIS prepares source evidence for a deterministic analysis pipeline. The WordPress plugin exports saved WordPress and Elementor facts. The Browser Runtime Collector exports rendered observations. Python verifies and joins both packages, resolves effective values and executes versioned rules. The LLM explains Python results only.

The plugin does not decide whether a design is good or bad.

## 2. First use

1. Open **EDIS Evidence → Diagnostics**.
2. Confirm that the manifest, registry, job storage, artifact storage, immutable input-snapshot storage, bundle storage, JSON support, REST API and ZIP backend are available.
3. Run **Safe worker test**. This creates and advances a minimal real export path.
4. Open **Create Export**.
5. Choose a privacy mode.
6. Select source components.
7. Search for and select the Elementor documents belonging to the analysis.
8. Review effective options and start the export.
9. Keep the page open while the authenticated REST worker advances the job.
10. Download the ZIP after validation reaches `PASS`.

## 3. Why export no longer depends on WP-Cron

Version 3.1 could leave a job in `queued / initializing / 0%` when WP-Cron was disabled. Version 3.5 fixes that design.

The administration page calls:

```text
POST /export-jobs
POST /export-jobs/{job_id}/advance
```

Each advance request performs bounded work and persists its cursor. WP-Cron schedules recovery only; it is not the primary worker. A disabled WP-Cron state is therefore a warning rather than an automatic export blocker.

## 4. Job states and recovery

Important job fields include:

```text
status
phase
progress
revision
cursor
current_component
last_heartbeat
last_successful_step_at
attempt_count
last_error_code
next_retry_at
schedule_state
schedule_error
job_format_version
input_snapshot_format_version
input_snapshot_sha256
completed_step_records
```

Available actions:

- **Resume:** continue a paused or stale job from its committed cursor.
- **Retry:** clear a recoverable error and run the bounded worker again.
- **Cancel:** stop future processing without pretending that a ZIP was produced.
- **Safe worker test:** execute a minimal real collection/package route.

A worker request is idempotent only at verified committed step boundaries. A job-specific lock prevents concurrent advancement. Version 3.7.1 also verifies that completed components are the exact execution-plan prefix and that the snapshot, step-input and artifact-file hashes still match before continuing.

## 5. Privacy modes

### Strict

Use for the smallest source disclosure. Original saved Elementor document objects are forcibly excluded even when the option was selected.

### Standard

Recommended for controlled local analysis. It exports the selected evidence and deterministic indexes without diagnostic expansion.

### Diagnostic

Use only when authorized. It can include more operational metadata to help explain failures. The UI requires explicit confirmation.

## 6. Component types

### Source Collector

Reads an actual WordPress or Elementor source. Examples: active Kit settings, configured breakpoints, saved document source, Variables registry and plugin inventory.

### Index Builder

Creates a lightweight deterministic index from source artifacts. Examples: Element Structure Index, Responsive Declaration Index and Reference Index. An index is not a second source of truth.

### Bundle Processor

Operates on the complete export. Examples: Source Coverage, Bridge Context, bundle diagnostics and estimated size.

## 7. Source truth and availability

These are separate dimensions.

### Source truth state

- `VERIFIED`: the source contract and implementation are backed by current validation evidence.
- `PARTIAL`: the source is real, but some semantics remain version-bounded or fixture-bounded.
- `UNKNOWN`: EDIS cannot establish the source contract safely.
- `UNSUPPORTED`: no valid source contract exists for the current environment.

### Source availability

- `AVAILABLE`: requested evidence was collected.
- `PARTIAL`: usable evidence exists with bounded omissions.
- `INSUFFICIENT`: evidence exists but does not satisfy the declared minimum contract.
- `DISABLED`: the user or configuration disabled it.
- `UNAVAILABLE`: the environment could not provide it.
- `NOT_APPLICABLE`: the evidence has no meaning for this environment or document.
- `ERROR`: collection failed.

A component can be `PARTIAL` truth and `AVAILABLE` data. This is not contradictory.

## 8. Elementor Breakpoints example

A breakpoint is a configured viewport boundary at which Elementor may save a device-specific declaration. It is not a quality score.

EDIS exports breakpoint identity, label, configured value, unit, manager order and provenance when available. `active` and `direction` remain `null` with an explicit unverified state when Elementor's public manager API does not provide those facts; the exporter does not infer them from names such as `widescreen`. Python uses the observed registry facts to:

- map saved suffixes such as `_tablet` or `_mobile` to the actual site configuration;
- resolve missing declarations without hardcoding standard widths;
- align Elementor source evidence with Browser observations;
- refuse a responsive comparison when the required viewport evidence is missing.

A breakpoint does not prove that the rendered page is responsive or usable. Browser evidence is required for that conclusion.

## 9. Saved and effective values

The WordPress plugin exports saved declarations and references. It does not silently replace a missing mobile value with an inferred desktop value.

Python later produces an effective value with explicit provenance, for example:

```text
saved mobile value: missing
effective mobile value: 40px
inherited from: desktop
```

This separation prevents hidden inference inside the collector.

## 10. Bridge Context

`bridge/source-context.json` contains the bounded source-side matching facts needed by Browser Runtime Collector 1.4.0:

- analysis-set and bundle identifiers;
- source-export root hash;
- site and document fingerprints;
- privacy-safe locator candidates;
- document IDs as strings;
- source element keys;
- real Elementor element IDs;
- duplicate-ID evidence;
- source paths and ancestor chains;
- source truth and availability.

The Browser extension imports this file locally and explicitly. It does not contact WordPress. Browser binding remains preliminary; Python owns final correlation.

## 11. Export ZIP structure

A complete package may contain:

```text
package-manifest.json
checksums.sha256
bridge/source-context.json
environment/
sources/
indexes/
coverage/source-coverage.json
provenance/provenance.json
diagnostics/diagnostics.json
validation/package-validation.json
schemas/
```

Optional files are emitted only when real content exists.

## 12. Diagnostics

Diagnostics reports:

- WordPress and PHP compatibility;
- manifest and registry loading;
- job, artifact, immutable input-snapshot and bundle storage;
- ZIP and JSON support;
- REST availability;
- executor mode;
- WP-Cron recovery state;
- stale queued/running jobs;
- cleanup configuration;
- latest heartbeat and scheduling error.

The copied report is privacy-safe and does not include document content.

## 13. Common export problems

### Job stays at zero

Check whether the administration page can call REST and whether the nonce is current. Refresh the page and use Resume. WP-Cron being disabled alone should not stop version 3.4.

### ZIP creation unavailable

EDIS 3.7.1 uses its bundled deterministic STORE-only ZIP writer and does not require the PHP ZIP extension to create evidence bundles. If ZIP creation is blocked, open Diagnostics and inspect filesystem, CRC32B, path-budget and private-storage checks. EDIS does not report success without a complete validated package.

### Document search returns nothing

Confirm Elementor data exists, the current administrator can edit the documents and the document status/type is included in the bounded query.

### Component is unavailable

Open Data Sources and read both Source Truth and Source Availability. A missing feature in the current Elementor version may be `NOT_APPLICABLE` or `UNAVAILABLE`, not an exporter failure.

### Job is stale

Use Resume only for a current-format job whose snapshot and completed artifacts still verify. The worker continues from the last verified committed cursor and records a new heartbeat.

### Job format is incompatible

Jobs created by 3.6.2 or earlier are never silently resumed under 3.7.1. Create a new export so a current immutable snapshot and current step records are captured. A previously completed, validated bundle remains a historical artifact; it is not rewritten.

### Source changed during snapshot

`EDIS_SOURCE_CHANGED_DURING_SNAPSHOT` means the saved Elementor source changed between the two bounded reads used to commit the job input. Save the editor state, stop concurrent edits and create the export again. No mixed-source job is continued.

### Resume artifact mismatch

`EDIS_RESUME_ARTIFACT_MISMATCH` means a completed artifact is missing, modified or no longer matches its recorded SHA-256 or implementation contract. The job fails closed. Do not replace files manually; create a new export after checking private storage and filesystem integrity.

## 14. Component encyclopedia

The integrated Help page and these files provide equal-depth English and Persian entries for every Source Collector, Index Builder and Bundle Processor:

```text
docs/collector-encyclopedia.md
docs/collector-encyclopedia-fa.md
```

Each entry explains what the evidence is, retrieval method, exported fields, Python use, LLM boundary, limitations, privacy and troubleshooting.

## 15. Limits of validation

Package validation proves internal structure, paths, hashes, JSON decoding and declared artifact consistency. It does not prove that every Elementor addon follows a stable undocumented storage contract. Components with bounded evidence remain visibly `PARTIAL` until real fixtures and version-specific adapters justify promotion.

## 16. Version 3.6.0 evidence-safety controls

### Selection Snapshot

Every document-scoped export includes `selection/selection-snapshot.json`. It records the export scope, dependency scope, selected document IDs, selection revision, canonical selected-source hashes, and why each document or shared artifact was included. This lets Python prove which saved source revision the bundle describes.

### Evidence Conservation

`validation/evidence-conservation.json` compares raw-source counts with deterministic indexes. It checks selected elements, Legacy responsive declarations, Atomic style variants and properties, and class bindings. A mismatch produces `EDIS_EVIDENCE_LOSS_DETECTED`; it is not silently treated as an empty successful result.

### Three validation dimensions

The validation page and artifact keep these results separate:

```text
package_integrity
contract_validation
analysis_readiness
```

A structurally intact ZIP may still have partial analysis readiness. One generic PASS is not used to hide that distinction.

### Unknown Structure Ledger

`diagnostics/unknown-structures.json` lists bounded source paths that were preserved in the raw document but are not yet modeled by an index. Unknown Elementor or addon fields are preserved safely where possible instead of being discarded or guessed.

### Strict single-document isolation

`SINGLE_DOCUMENT + REQUIRED_DEPENDENCIES` exports only the selected document records plus required site/Kit context. Unrelated document titles and source trees are excluded. Use Full Site Context only when the analysis explicitly requires the complete inventory.

### Bridge readiness and download

A completed job reports whether the Browser Bridge Context is ready. The **Download Browser Bridge Context** action downloads the exact validated `bridge/source-context.json` entry from the completed bundle; it does not construct a second version of the context.

### Previous-source comparison

When enabled, `comparison/previous-export-diff.json` reports a bounded deterministic source diff against the previous saved EDIS export for the same selected document. It reports source changes only and does not evaluate UX quality.

### Real fixture capture mode

Fixture mode adds `fixture/fixture-metadata.json` with environment facts, selected-source identifiers, verification state, and a template for user-authored expected behavior. Synthetic or unverified fixture metadata is never presented as a verified real Elementor fixture.

### Privacy preview

Preflight states whether original text, media URLs, other document titles, and diagnostic environment metadata are expected in the package. Use Strict privacy mode for minimum disclosure and Diagnostic mode only in a controlled environment.


## 17. Version 3.7.1 resilience controls

### Immutable document input snapshot

Job creation captures the selected documents into protected private storage before collection starts. The snapshot contains exact saved source bytes, bounded document metadata, a manifest, per-file hashes and a semantic snapshot hash. After the snapshot is committed, `elementor_document_source` reads from it instead of reading live `_elementor_data` again.

The snapshot freezes only the selected document source and bounded metadata used by that collector. Other site registries remain separate collectors and must not be described as part of the frozen document snapshot unless their own artifact says so.

### Source-drift gate

Each selected source is read twice during bounded snapshot capture. If the capture record differs, the temporary snapshot is removed and the job is blocked with `EDIS_SOURCE_CHANGED_DURING_SNAPSHOT`. This prevents a selection made against one saved revision from being projected onto another saved revision.

### Resume integrity contract

Job format `2.1.0` records, for every completed component:

```text
component_id
component_schema_version
implementation_version
input_snapshot_sha256
step_input_sha256
artifact_file_sha256
```

Resume is rejected when the job format is old, the snapshot is missing or changed, completed steps are not the exact plan prefix, an implementation/schema version changed, a prior step input changed, or an artifact checksum differs. These failures are integrity failures, not retryable transient errors.

### Breakpoint evidence discipline

Legacy responsive suffix discovery uses only IDs actually exported by the Elementor breakpoint manager. A familiar suffix is not treated as present merely because EDIS knows its name. Active state and direction are not guessed from the ID. Python may resolve responsive behavior only after validating the exported registry and any required runtime evidence.

### Retention and cleanup

Input snapshots live under the same protected-storage policy as jobs and artifacts. Scheduled cleanup removes expired snapshots. Symlink and managed-path checks remain fail-closed. Cancelled or failed jobs do not turn incomplete snapshots or artifacts into downloadable bundles.

### Upgrade behavior

An incomplete job created by 3.6.2 or earlier must be recreated after upgrade. A previously completed package is not mutated. Version 3.7.1 upgrades the WordPress Bundle Schema from `3.2.0` to `3.3.0`; Selection Snapshot Schema remains `1.2.0`. Browser and Python consumers must route Bundle Schema `3.3.0` explicitly before coordinated compatibility is claimed.

## 18. Version 3.7.1 deterministic contract

### Lossless saved JSON

Selected saved document JSON is parsed before native PHP conversion. EDIS preserves object versus array shape, exact numeric-looking object keys and exact decimal number semantics. Invalid UTF-8, duplicate object keys and values exceeding the deterministic normalization budget block the job with an explicit diagnostic. No silent repair is performed.

### EDIS-CJ-2 ordering

Object keys are ordered by exact UTF-8 bytes. Unicode normalization is `NONE`, so composed and decomposed text remain distinct evidence. The same vectors must pass in PHP, Browser JavaScript and Python before coordinated compatibility is claimed.

### Semantic and instance hashes

Every final artifact has two hashes. `semantic_payload_sha256` excludes fields defined as operational by policy `1.0.0`. `artifact_instance_sha256` covers the complete envelope except its own field. The package manifest also records exact file hashes. A semantic match does not excuse an instance or file mismatch.

### Deterministic ZIP

The EDIS-ZIP-1 profile uses STORE rather than environment-selected compression. Paths are UTF-8 byte sorted; timestamps, attributes, flags and comments are fixed. ZIP64 is intentionally unsupported. If a package exceeds the profile limits, EDIS fails with a limit diagnostic instead of producing a different archive format.

### Durable storage and process locking

Critical writes use complete write loops, `fflush`, `fsync`, permission application, atomic replacement/rename and final SHA-256 verification. The installable private-storage self-test proves local-handle exclusion and then launches a separate `PHP_BINARY` through `proc_open` against the same active-path lock file. A blocked child lock is required before exports are enabled. Shared, clustered or network storage still requires a deployment-specific multi-host concurrency test before background exports are trusted.

### Upgrade and cross-product behavior

Jobs created with Job Format older than `2.1.0` or Input Snapshot Format older than `2.0.0` must be recreated. Completed historical ZIPs are immutable. Bundle Schema `3.3.0`, Shared Envelope `2.0.0`, EDIS-CJ-2 and dual hashes require explicit Browser and Python version routing; until both products pass the shared vectors, coordinated compatibility is `insufficient_evidence`.

### Developer verification

The source package contains GitHub Actions for PHP 8.2–8.5, PHPUnit, security-focused WordPress Coding Standards, PHPCompatibilityWP, E_ALL smoke tests and WordPress Plugin Check. A configured workflow is not evidence of a passing workflow; consult the actual CI run and release verification report.


The package manifest uses an explicit `semantic_identity` based on the source-export root and versioned packaging policy. File inventory and per-run package identifiers remain instance evidence.



## WordPress runtime and operations in 3.7.1

### Site Health

Open **Tools → Site Health** to review EDIS deterministic runtime, recovery scheduling and asynchronous private-storage integrity. The storage test is intentionally asynchronous because it performs durable-write and process-lock probes. A critical result blocks evidence creation.

### WP-CLI

```bash
wp edis status
wp edis worker status
wp edis worker run
wp edis jobs repair
wp edis jobs repair --apply
wp edis storage self-test
```

The repair command is a dry run unless `--apply` is supplied. It cannot override current leases, schema versions, immutable snapshot hashes or completed-step checks.

### Multisite

Network activation initializes every current site and newly created sites. Each site has an independent capability, settings, cron schedule and private-storage namespace. Jobs, tokens and bundles are not shared between sites. If one existing site fails storage preflight during network activation, sites already changed by that activation attempt are deactivated before the failure is returned.

### Deactivation and uninstall

Deactivation stops new jobs and removes scheduled events while retaining settings and evidence. Uninstall is separate. The **Retain evidence on uninstall** setting decides whether private evidence remains. Disabling retention before uninstall removes site options, capability grants, jobs, snapshots, artifacts and bundles through the fail-closed private-storage deletion path.

### Privacy tools

WordPress personal-data export includes only bounded operational job records. Personal-data erase and user deletion remove the user's jobs and associated private files in the current site. Saved source is not exposed as a privacy-export item.

### Conditional loading

Ordinary frontend requests do not construct the EDIS collector registry, stores, REST controllers or worker. Application services load only for admin, REST, Cron, AJAX or WP-CLI contexts.

### Private job version

Version 3.7.1 uses Job Format `2.1.0` and Input Snapshot Format `2.0.0`. Incomplete jobs created by 3.7.0 use Job Format `2.0.0` and must be recreated. Public Bundle Schema `3.3.0` and Selection Snapshot `1.2.0` are unchanged. Package Manifest Schema is `2.1.0`; archived `2.0.0` remains bundled for historical validation.

### WordPress Filesystem compatibility

EDIS reports the WordPress-selected filesystem method but does not request interactive FTP/SSH credentials from a background worker. Export remains enabled only when the direct private backend passes EDIS durable-write, atomic-replace and process-lock tests. A non-direct WordPress method is therefore evidence for the administrator, not permission to weaken storage guarantees.

## 19. Version 3.7.2 debugging and recovery controls

Version 3.7.2 was deliberately limited to defect diagnosis and corrective hardening. Public Bundle Schema `3.3.0`, Shared Envelope `2.0.0`, Package Manifest `2.1.0`, Selection Snapshot `1.2.0`, EDIS-CJ-2 and EDIS-ZIP-1 are unchanged.

### Elementor payload type regression

The final package now has dedicated regression coverage for these artifacts:

```text
sources/elementor/kit-settings.json      $.evidence.settings → JSON object
indexes/site-settings-index.json         $.evidence.groups   → JSON object
indexes/site-settings-index.json         $.evidence.source   → string|null
```

A schema type failure reports both the declared and observed JSON type, for example `Expected string|null; actual array`. Evidence values are not included in the error message.

If the exact validation error returns after installing 3.7.2, preserve the failed Job and inspect the final artifact bytes. Do not hand-edit the ZIP. A repeated failure with the new expected/actual type message is new evidence and should be attached to the defect report.

### Mixed-install detection

Before collectors load, EDIS verifies SHA-256 values for critical runtime, schema and configuration files. `EDIS_INSTALLATION_MIXED_VERSION` means files from different plugin builds are present or a critical file changed after packaging. Delete the entire plugin directory and install the complete 3.7.2 ZIP; do not copy the new version over an old directory.

### Protected-storage degraded mode

An unavailable private directory no longer produces an uncaught boot exception. EDIS enters fail-closed degraded mode: WordPress stays available, exports are disabled, and Site Health plus the administrator notice expose a privacy-safe diagnostic code.

EDIS tries bounded outside-web-root candidates. For Windows/Local installations, the recommended deterministic configuration is an explicit forward-slash path in `wp-config.php`, outside the `app/public` directory:

```php
define( 'EDIS_EVIDENCE_PRIVATE_STORAGE_DIR', 'C:/Users/Nestech/Local Sites/nurro/app/edis-private' );
```

The PHP process must be able to create, lock, sync, atomically replace and delete files in that directory. Do not point the constant at `wp-content`, uploads or another public path.

### Upgrade handling

Incomplete 3.7.1 jobs are not resumed with 3.7.2 implementation records. Create a new Job after installation. Completed packages that already passed their own validation remain immutable historical artifacts.


## 20. Version 3.7.6 forensic corrective controls

Version 3.7.6 preserves every frozen public evidence contract and fixes five reproduced implementation defects.

### Active-path process lock proof

Activation, application bootstrap, Site Health, WP-CLI storage diagnostics and export preflight run the storage checks on the exact active path. EDIS holds a lock in the WordPress PHP process, launches a separate `PHP_BINARY` through `proc_open`, and requires the child to report that the same lock is blocked. `UNAVAILABLE` and `FAIL` are blockers, not warnings. WordPress remains available through degraded-mode diagnostics when the gate cannot pass.

### Logical and physical path safety

A configured path is rejected if its logical spelling lies inside `ABSPATH`, even when a parent symlink resolves outside it. Existing ancestor components that redirect to another physical path are also rejected. Configure a direct absolute path outside the web root; do not use a symlink, junction or web-root alias as the storage route.

### Semantic identity correction

Operational exclusions are path-scoped. They apply only to the versioned envelope roots defined by Semantic Identity Policy `1.0.0`. Nested saved-source and addon properties are not removed merely because a property name equals `created_at`, `user_id`, `token` or another operational key. Historical package hashes are not rewritten; create a new 3.7.6 export when the corrected source field matters.

### Stable job-lock identity

Resume/Retry acquires the per-job lock before changing the Job and holds the same lock through worker advancement. Cleanup and ordinary removal delete the Job JSON while retaining the empty `.lock` sentinel. This prevents another process from creating a new lock inode while a prior process still owns an open handle. Do not manually delete lock sentinels while workers can run.

See `docs/MIGRATION-V3.7.2-TO-V3.7.6.md` for the complete upgrade and rollback procedure.

## 21. Version 3.7.9 LocalWP storage behavior

Local documents a site’s WordPress document root as `<site>/app/public`. In local environment mode, EDIS derives the preferred private evidence directory from that structure:

```text
<site>/edis-private-storage
```

For `C:/Users/Nestech/Local Sites/nurro/app/public`, the preferred path is `C:/Users/Nestech/Local Sites/nurro/edis-private-storage`. The prior `<site>/app/edis-private-storage` candidate remains a secondary fallback.

A private-storage failure does not abort activation. EDIS activates in fail-closed diagnostic mode, rejects new exports, and keeps wp-admin, Site Health, the administrator retest action, `wp edis storage paths`, and `wp edis storage self-test` available.

On Windows, a web request can report `PHP_BINARY` as `php-cgi.exe`. EDIS 3.7.9 uses the sibling `php.exe` for the separate-process lock proof when available and records the probe binary, exit code, stdout and stderr in the private diagnostic result.

See the 3.7.8 → 3.7.9 migration guide included in the complete release package.

## 22. Version 3.7.10 validation kit

Version 3.7.10 adds source-only validation tooling. It does not add a new WordPress Admin feature or change frozen EDIS evidence contracts. Run `php tools/validation/run-local-validation.php --report=validation/evidence/local-validation.json` from the source repository. External WordPress, Elementor, Windows, Composer and Python gates remain unverified until real execution evidence is attached.


## 23. Version 3.7.11 validation evidence correctness

Version 3.7.11 changes only source-side validation tooling. A required local gate that is skipped or unavailable now makes `summary.local_state` equal to `INCOMPLETE`; it can no longer be reported as `PASS`. External gates are summarized separately, and `--strict-external` evaluates only those external requirements. Command output is captured through bounded file-backed evidence to avoid pipe deadlock, and report files are atomically replaced and SHA-256 verified. The WordPress installation ZIP and frozen EDIS evidence contracts are unchanged.
