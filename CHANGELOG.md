# Changelog

## 3.7.12

- Preserves package contract and final-integrity failures as typed `ExportIntegrityException` diagnostics with stable codes and bounded privacy-safe context.
- Clears `current_component` before packaging so package failures cannot be misattributed to the final collector.
- Recovers compatible failed jobs only when their existing `next_retry_at` is due, through the existing verified `resume()` path; non-retryable integrity failures remain terminal.
- Routes default-enabled Bundle Processors through the same recursive REQUIRED dependency closure as explicit selections.
- Fails `BridgeContextProcessor` closed when required `environment` or `elementor_document_index` input is absent or malformed.
- Rejects explicit unknown, non-selectable and non-executable collector IDs at the application/service boundary without silent filtering or default substitution.
- Adds focused regression coverage and a production-service real Elementor export-completion gate on the representative PHP 8.4 smoke lane.
- Advances the private worker implementation contract to `3.7.12`; persisted `3.7.11` jobs must be recreated rather than migrated in place.
- Preserves Bundle Schema `3.3.0`, frozen evidence schemas, EDIS-CJ-2, EDIS-ZIP-1 and existing authorization architecture.

## 3.7.11

- Corrects validation evidence so skipped or unavailable required local gates produce `INCOMPLETE` rather than a false `PASS`.
- Separates local completion from unresolved external gates and makes `--strict-external` evaluate only external requirements.
- Replaces sequential pipe reads with file-backed stdout/stderr capture to prevent validation commands from deadlocking on large output.
- Adds bounded tails, byte counts and SHA-256 hashes without retaining complete command output in PHP memory.
- Commits validation reports through verified atomic replacement and fails closed when report persistence cannot be proven.
- Runs repository-wide PHP syntax validation in an isolated child process and imports a structured lint report, preventing child-process accumulation from stalling later gates.
- Adds regression tests for evidence-state promotion, external-state separation, large process output and report replacement.
- Adds no WordPress runtime feature and preserves all frozen schemas and cross-product boundaries.

## 3.7.10

- Adds a source-only validation runner that records executed command evidence without promoting unavailable external gates to PASS.
- Adds a PowerShell wrapper for the same validation runner without changing installed WordPress runtime behavior.
- Adds a machine-readable validation plan with explicit `BLOCKED_EXTERNAL`, `NOT_RUN` and `insufficient_evidence` states.
- Adds fail-closed controlled-real-fixture intake for Legacy V3, Container and Atomic/Hybrid V4 Elementor evidence; no synthetic fixture is represented as real.
- Keeps validation tooling and fixture intake out of the WordPress install ZIP.
- Preserves all frozen schemas, EDIS-CJ-2, EDIS-ZIP-1 and the WordPress/Browser/Python computation boundary.

## 3.7.9

- Filters document discovery by object-level `edit_post` authorization before calculating totals and pages, including fail-closed handling for unauthorized `include` selections.
- Replaces the JobStore mtime/size parse cache signature with a SHA-256 content signature so same-size, same-timestamp external rewrites are not hidden.
- Combines stale-lease repair and runnable-job discovery into one deterministic Cron recovery pass, reducing duplicate full-directory scans while preserving the file-backed JobStore contract.
- Synchronizes parent-directory entries after atomic file and snapshot-directory commits on supported POSIX runtimes, while retaining explicit Windows external validation status.
- Pins all GitHub Actions to verified full commit SHAs and verifies the fixed WP-CLI 2.12.0 download with the official SHA-512 checksum.
- Removes the hard-coded 3.7.7 multisite workflow assertion and derives the expected installed version from the active plugin constant.
- Makes Composer dependency locking and `composer audit --locked` fail-closed in CI; `composer.lock` generation remains an external gate until Composer dependency resolution is executed.
- Adds regression coverage for authorization-aware totals, unauthorized includes, same-size/same-mtime JobStore tampering, parent-directory synchronization and supply-chain policy.

## 3.7.8

- Enforces the frozen independent-process lock contract in every environment; local mode no longer accepts unavailable lock proof.
- Adds integrity-protected storage self-test attestations so ordinary application requests avoid repeated process spawning while explicit diagnostics still force a live proof.
- Fixes the document REST query method and `DocumentIdentity` namespace defects.
- Adds signed, owner-bound, short-lived preflight proofs with saved-source drift validation to eliminate duplicate full preflight work.
- Caches verified snapshots and committed artifacts within one request while retaining forced tamper verification at resume boundaries.
- Streams deterministic EDIS-ZIP-1 output to disk and reads individual stored entries by seek instead of loading the full archive.
- Reduces package assembly duplication by processing committed artifacts once in topological order.
- Blocks `ENTIRE_SITE` preflight when the configured inventory bound would truncate eligible documents.
- Uses raw saved-source hashes for low-cost admin row freshness checks with canonical fallback for older records.
- Makes the local test harness fail on undiscovered test files and restores the previously skipped tests.

## 3.7.7

### LocalWP path and Windows storage diagnostics

- Detect Local’s documented `<site>/app/public` WordPress layout and prefer `<site>/edis-private-storage` outside the public document root.
- Retain the 3.7.6 `<site>/app/edis-private-storage` path as a safe fallback for existing local installations.
- Prevent private-storage preflight failures from aborting plugin activation; exports remain fail-closed while diagnostics stay available.
- Expose `wp edis storage paths` and `wp edis storage self-test` even in degraded mode, and add an administrator storage-retest action.
- On Windows, use a sibling CLI `php.exe` when the web runtime reports `PHP_BINARY` as `php-cgi.exe`.
- Preserve an observed child-process exit code when `proc_close()` returns `-1` after status polling.
- Add candidate-path and Local-environment diagnostics without changing frozen schemas or cross-product contracts.

## 3.7.6

- Added executable workflow-reference policy gate for documented non-immutable rolling action refs, while preserving strict SHA-only mode.
- Added supply-chain gate contract tests for workflow policy and local package scripts.
- Updated release documentation for gate execution status without changing frozen EDIS evidence contracts or public schemas.


## 3.7.4

### WordPress hardening release

- Added bounded REST argument schemas and validation/sanitization callbacks for export jobs, job actions, document search and Elementor Inspector selections.
- Replaced raw REST exception messages with stable public error messages and diagnostic IDs so filesystem paths or internal state-machine details are not returned to clients.
- Corrected `SECURITY.md` to document the actual `edis_export_evidence` capability model, object-level `edit_post` checks and nonce-as-CSRF-only boundary.
- Added JS and CSS asset lint scripts with a committed `package-lock.json` and wired the asset checks into the PHP 8.4 CI path.
- Added split PHPCS rulesets for WordPress boundary code and deterministic core code, plus a PHPStan configuration scaffold for future typed static analysis.
- Added static regression tests for REST schema coverage, error-message hygiene and hardening configuration presence.
- Added migration documentation for 3.7.3 → 3.7.4 and updated release documentation while preserving frozen public schemas and contracts.

## 3.7.2

### Debugging-only corrective release

- Added regression validation for `sources/elementor/kit-settings.json` and `indexes/site-settings-index.json`, including empty-map preservation through canonical JSON and schema validation.
- Improved JSON Schema type diagnostics to report the expected and actual JSON types without exposing evidence values.
- Fixed the Elementor kit-settings provenance shape so malformed source-reference records are no longer silently discarded.
- Added critical-file SHA-256 verification to detect mixed or partially overwritten plugin installations before collectors run.
- Replaced boot-time private-storage exceptions with a fail-closed degraded mode: exports remain disabled, while WordPress, Site Health and a privacy-safe administrator notice remain available.
- Added deterministic candidate fallback outside the WordPress web root for environments where PHP's temporary directory is inside the site root.
- Added Windows-aware, case-insensitive web-root boundary checks and documented an explicit forward-slash path for Local/Windows installations.
- Invalidated incomplete 3.7.1 job-step implementation records; completed validated bundles remain immutable historical artifacts.
- Updated README, English/Persian Help, troubleshooting, migration and quality-gate documentation in parallel.

## 3.7.1

- Preserved Bundle Schema `3.3.0`, Shared Envelope `2.0.0`, Selection Snapshot `1.2.0`, EDIS-CJ-2 and EDIS-ZIP-1. Advanced Package Manifest Schema to `2.1.0` so plugin release versions are validated as SemVer instead of changing a frozen schema constant.
- Added conditional runtime loading so ordinary frontend requests do not construct registries, stores, controllers or worker services.
- Added network-aware activation/deactivation, rollback of partially activated networks, and initialization of newly created multisite sites.
- Namespaced private storage by network and site IDs to prevent cross-site job, snapshot and bundle access.
- Added private Job Format `2.1.0` with explicit worker leases, lease expiry, heartbeat refresh and deterministic stale-job repair.
- Added WordPress Site Health direct and asynchronous tests for deterministic runtime, recovery scheduling and private-storage integrity.
- Added WP-CLI commands for diagnostics, worker execution/status, dry-run or applied stale-job repair, and storage self-test.
- Added WordPress privacy exporter/eraser integration, privacy-policy text and user-deletion cleanup.
- Added deleted-document cleanup, site-scoped multisite operational user meta and a non-interactive WordPress Filesystem preflight adapter.
- Tightened Cron duplicate suppression, terminal-event cleanup, lease-expiry recovery and fail-closed recurring-event scheduling.
- Removed production process spawning from storage self-tests; independent-process lock exclusion remains an executed source/CI test gate and is absent from installable code.
- Separated deactivation from retention-aware uninstall and made network uninstall site-aware without following symlinks.
- Expanded WordPress-Core, WordPress-Extra, WordPress-Docs, PHPCompatibilityWP, Plugin Check package, WordPress multisite and E_ALL quality gates.
- Updated English and Persian operational, migration, privacy, troubleshooting and release documentation.

## 3.7.0

- Added frozen Cross-Product Contract Addendum v1.2.0 and Bundle Schema 3.3.0.
- Added EDIS-CJ-2 lossless typed JSON parsing, exact decimal canonicalization, UTF-8 byte key ordering, duplicate-key rejection and normalization budgets.
- Added separate semantic and artifact-instance hashes to every envelope.
- Added explicit package-manifest semantic identity so operational file inventories and bundle IDs cannot change the semantic package hash.
- Added EDIS-ZIP-1 deterministic uncompressed ZIP generation with fixed headers and limits.
- Added strict EDIS-ZIP-1 reading with fixed-profile validation, unsafe/control-character path rejection, normalized-path collision rejection and CRC32B verification.
- Replaced production filesystem error suppression with deterministic exceptions, durable `fsync` writes, atomic replacement/rename and final SHA-256 verification.
- Routed critical source, manifest, schema and ZIP reads through the same warning-to-exception deterministic filesystem boundary.
- Added local-handle and independent-process locking self-tests.
