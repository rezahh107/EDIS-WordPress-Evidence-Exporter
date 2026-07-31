=== EDIS WordPress Evidence Exporter ===
Contributors: edis
Tags: elementor, evidence, export, diagnostics, deterministic
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 3.7.13
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Exports saved WordPress and Elementor evidence for a deterministic Python analysis pipeline without performing UX analysis or final value resolution in WordPress.

== Description ==

EDIS exports saved source evidence, registries, references, provenance and lightweight source indexes. Browser runtime evidence and final Python resolution remain separate products.

Version 3.7.13 is a bounded degraded-admin recovery patch. When runtime, installation-integrity, configuration, or private-storage gates fail closed, administrators retain a minimal EDIS Evidence Diagnostics / Recovery surface without initializing export services. Healthy-mode behavior and frozen public evidence contracts are unchanged, and worker implementation compatibility remains 3.7.12.

Public evidence versions:

* Bundle Schema 3.3.0
* Shared Artifact Envelope 2.0.0
* Package Manifest Schema 2.1.0
* Selection Snapshot Schema 1.2.0

Browser and Python must explicitly route the new schema and pass the shared EDIS-CJ-2 vectors. Until then coordinated compatibility is insufficient evidence.

== Installation ==

1. Confirm a 64-bit PHP 8.2–8.5 runtime.
2. Define `EDIS_EVIDENCE_PRIVATE_STORAGE_DIR` to a writable directory outside the public WordPress web root when the system temporary directory is unsuitable.
3. Upload and activate the plugin.
4. Open EDIS Evidence → Diagnostics.
5. Run the Safe worker test before creating evidence exports.

Activation and export preflight fail closed unless durable writes, atomic replacement/rename, local-handle locking and separate-PHP-process lock exclusion all pass on the exact active private-storage path. `proc_open` and the current PHP binary must therefore be available in the deployment context. Diagnostics remain available in degraded mode through a recovery-only admin surface; export and worker controls remain unavailable until the normal startup gates pass.

== Frequently Asked Questions ==

= Does EDIS analyze UX in WordPress? =

No. WordPress only exports saved evidence and lightweight indexes. Python owns final resolution, formulas, rules, correlation and TruthReport.

= Why are ZIP files uncompressed? =

EDIS-ZIP-1 uses STORE with fixed headers so identical package inputs produce identical archive bytes without depending on zlib/libzip compression behavior.

= Can old jobs resume after upgrading? =

Jobs are compatible by worker implementation identity, not merely plugin release number. A persisted job created by worker implementation 3.7.11 must be recreated under 3.7.12. Release 3.7.13 intentionally keeps worker implementation compatibility at 3.7.12, so otherwise-compatible persisted 3.7.12 jobs are not invalidated by this admin-only patch.

== Changelog ==

= 3.7.13 =

* Restores a visible EDIS Evidence Diagnostics / Recovery admin surface in every existing fail-closed degraded Bootstrap path.
* Keeps degraded recovery self-contained and protected by `manage_options`; normal healthy EDIS authorization remains `edis_export_evidence`.
* Keeps export creation, workers, operational REST controls, downloads and scheduled exports unavailable while degraded.
* Preserves the healthy AdminModule and automatically removes the degraded shell on the next request once existing startup gates pass.
* Advances plugin/build release identity to 3.7.13 while preserving `ExportJobService` worker implementation compatibility at 3.7.12.
* Preserves Bundle Schema 3.3.0, frozen evidence schemas, EDIS-CJ-2 and EDIS-ZIP-1.

= 3.7.12 =

* Preserves package-specific contract and final-integrity failures as typed, privacy-safe diagnostics and clears collector attribution before packaging.
* Recovers compatible failed jobs only when their existing retry deadline is due, through the existing Resume verification path.
* Closes transitive REQUIRED dependencies for default bundle processors and fails Bridge Context closed on absent or malformed required inputs.
* Rejects explicit unknown, non-selectable or non-executable collector IDs at the application/service boundary without default substitution.
* Adds a production-service real Elementor export-completion gate on the representative PHP 8.4 smoke lane.
* Preserves Bundle Schema 3.3.0, frozen evidence schemas, EDIS-CJ-2 and EDIS-ZIP-1.

= 3.7.11 =

* Marks skipped or unavailable required local gates as `INCOMPLETE`, never `PASS`.
* Separates local completion from unresolved external validation gates.
* Captures large stdout and stderr through bounded file-backed evidence without pipe deadlock.
* Writes validation reports through verified atomic replacement.
* Adds regression tests for evidence-state promotion, large process output and report replacement.
* Preserves frozen schemas, EDIS-CJ-2, EDIS-ZIP-1 and installed runtime behavior.

= 3.7.10 =

* Adds a source-only validation runner and explicit evidence states for external gates.
* Adds controlled real Elementor fixture intake without representing synthetic data as real evidence.
* Keeps all validation tooling outside the WordPress installation ZIP.
* Preserves frozen schemas, EDIS-CJ-2, EDIS-ZIP-1 and architectural boundaries.

= 3.7.9 =

* Applies object-level `edit_post` authorization before document-list items, totals and pages are calculated.
* Prevents unauthorized `include` selections from falling back to an unrestricted listing.
* Detects same-size, same-timestamp external JobStore rewrites using SHA-256 content signatures.
* Combines stale-job repair and runnable-job discovery into one deterministic recovery scan.
* Synchronizes parent-directory entries after atomic commits on supported POSIX runtimes.
* Pins GitHub Actions to reviewed full commit SHAs and verifies the fixed WP-CLI 2.12.0 download checksum.
* Replaces the stale multisite 3.7.7 assertion with the active plugin version constant.
* Makes missing Composer dependency locking fail closed in CI; Composer execution remains an external release gate.

= 3.7.2 =

* Added final-artifact regression coverage for Elementor kit settings and site-settings index JSON types.
* Added expected/actual JSON type details to schema diagnostics.
* Added SHA-256 critical-file integrity checks for mixed installations.
* Prevented private-storage preflight failures from crashing the WordPress request; exports remain fail-closed in degraded mode.
* Added safer outside-web-root storage candidates and Windows/Local setup guidance.
= 3.7.1 =

* Preserved Bundle Schema 3.3.0, Shared Envelope 2.0.0, Selection Snapshot 1.2.0, EDIS-CJ-2 and EDIS-ZIP-1.
* Added conditional loading so ordinary frontend requests do not initialize the export application.
* Added network-aware activation/deactivation with rollback and initialization of newly created multisite sites.
* Added per-site private-storage namespaces in multisite.
* Added WordPress Site Health tests for deterministic runtime, recovery scheduling and private storage.
* Added WordPress privacy exporter/eraser integration and documented retention-aware uninstall behavior.
* Added deleted-document cleanup, multisite-scoped operational user meta and WordPress Filesystem transport preflight.
* Tightened lease-expiry recovery, duplicate Cron suppression and fail-closed cleanup scheduling.
* Added WP-CLI status, worker, repair and storage self-test commands.
* Added private Job Format 2.1.0 with explicit leases and stale-worker recovery.
* Expanded WordPress-Core, WordPress-Extra, WordPress-Docs, PHPCompatibilityWP and Plugin Check gates.
* Updated English and Persian help, migration, privacy, troubleshooting and operations documentation.
