# EDIS 3.7.16 Quality Gates

A release claim is valid only for commands that actually ran and passed. A workflow definition is not itself verification evidence.

## Local source gates

```bash
composer validate --strict
composer install --no-interaction --prefer-dist
find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
vendor/bin/phpunit --configuration phpunit.xml.dist
vendor/bin/phpcs --standard=phpcs.xml.dist
php -d error_reporting=E_ALL -d display_errors=1 tests/runtime-smoke.php
```

## Required PHP matrix

Run the full suite on 64-bit PHP 8.2, 8.3, 8.4 and 8.5. `serialize_precision`, locale, timezone, collector registration order and independent selection order permutations must not change semantic output. PHP 8.0, 8.1 and 8.6 or later remain outside the verified deterministic runtime range.

## WordPress Plugin Check

The repository workflow pins Plugin Check `2.0.0` and runs both static and runtime checks against the oldest declared WordPress branch (`6.5.8`) and the current tested release (`7.0`). On a disposable WordPress environment:

```bash
wp plugin install plugin-check --version=2.0.0 --activate
wp plugin activate edis-evidence-exporter
wp plugin check edis-evidence-exporter
wp plugin check edis-evidence-exporter --require=wp-content/plugins/plugin-check/cli.php
```

Both checks are release gates. Enable `WP_DEBUG`, `WP_DEBUG_LOG`, `SCRIPT_DEBUG` and `ELEMENTOR_DEBUG` in non-production test environments and require zero EDIS-origin warnings or deprecations.

## Elementor activation and real export smoke

The repository workflow pins WordPress `7.0` and Elementor `4.1.3`, activates both plugins on PHP 8.2 through 8.5, verifies the Elementor load hook, executes the EDIS-CJ-2 runtime gate and requires the private-storage self-test to pass.

On the representative PHP 8.4 lane, `tests/integration/real-export-completion.php` additionally creates a fresh minimal saved Elementor document, composes the production `ExportJobService`, `ExportService`, `CollectorRegistry` and real private stores, executes the current default selectable collector set through its derived REQUIRED dependency plan, and requires the job to reach `completed`, `progress=100`, `validation_state=PASS`. The gate then verifies the deterministic ZIP through the existing `ExportFileStore` authorization/integrity boundary and checks the package manifest remains Bundle Schema `3.3.0` with producer version `3.7.16`. Worker compatibility remains independently pinned to `ExportJobService::IMPLEMENTATION_VERSION = 3.7.15`.

This is a real application-path export-completion integration gate, not an Elementor Editor browser automation matrix. Legacy/editor-interaction, Windows/LocalWP, third-party addon and other browser-observed behaviors remain separate environment gates.

## Degraded admin recovery gate

The WordPress 7.0 integration lane must instantiate the production `DegradedModeIntegration` in an isolated admin-menu fixture and prove:

```text
one EDIS Evidence top-level menu exists
Diagnostics / Recovery is visible to manage_options administrators
normal operational menu slugs are absent
no operational REST or worker hook is added by degraded recovery registration
recovery content exposes bounded diagnostics and no Create Export/worker-test controls
an unauthorized user cannot render the recovery page
```

This gate must not weaken production storage checks or instantiate the normal Admin/Application graph merely to make the degraded UI visible.

## Storage gate

The active private-storage path must pass durable write, atomic replacement/rename, post-write SHA-256 verification, cleanup, two-handle lock exclusion and separate-PHP-process lock exclusion. The self-test field `atomic_replace` must be `true`. Independent-process lock exclusion must pass both the source regression test and the active deployment self-test. Production invokes the current `PHP_BINARY` through `proc_open` without a shell and requires the child lock attempt to be blocked. Shared/NFS/container-cluster storage requires an additional deployment-specific concurrency test. Failure is fail-closed.


## Canonical diagnostic record gate

The regression suite must prove schema validation, required fact/inference boundaries, rejection of unknown fields, 64 KiB and list bounds, visible truncation, secret/path/source redaction, pre-Job persistence, exact Job association, owner and object-authorization denial, expiry, cleanup safety, truthful persistence fallback, browser metadata preservation, exact copy/download bytes, human/canonical agreement, and Safe Worker PASS/IN_PROGRESS/FAIL/ABORTED mapping. Native Windows/LocalWP execution remains a separate environment gate.

## Package gate

The runtime ZIP must use `EDIS-ZIP-1`: stored entries only, UTF-8 names, byte-sorted paths, fixed DOS timestamp, fixed Unix mode, no comments, no optional extra fields and no ZIP64. Rebuilding the same package twice must produce identical bytes and the same SHA-256.

## Cross-product gate

PHP, Browser JavaScript and Python must pass `contracts/edis-cj-2-vectors.json`, route Bundle Schema 3.3.0 and verify both the semantic and instance hashes. Until they do:

```text
cross_product_status: insufficient_evidence
```

## WordPress-facing coding standards

The WordPress boundary (`edis-evidence-exporter.php`, `uninstall.php`, `src/Bootstrap.php`, `src/WordPress/` and admin templates) is checked with WordPress-Core, WordPress-Extra and WordPress-Docs. The full source remains subject to security, i18n, deprecation, prepared-SQL, no-silenced-error and PHPCompatibilityWP rules. PSR-4 class/file and camelCase method naming are documented exceptions for the deterministic core and adapters; security exceptions are not allowed.

## Site Health, lifecycle and multisite gates

The WordPress 7.0 multisite lane must prove:

```text
network activation initializes every existing site
activation rollback occurs after a site preflight failure
wp_initialize_site initializes a newly created site
private storage roots differ between sites
network deactivation clears scheduled hooks
Site Health registers runtime, cron and async storage tests
WP-CLI status/worker/repair/storage commands load
```

Activation, deactivation and uninstall must be tested separately. Deactivation retains evidence. Uninstall honors the retention option, removes capabilities and scheduled events, and never follows a symlink or deletes a path within the public web root.

## WP-CLI recovery gates

```bash
wp edis status
wp edis worker status
wp edis worker run
wp edis jobs repair
wp edis jobs repair --apply
wp edis storage self-test
```

`jobs repair` must be dry-run by default. Worker commands must respect the same locks, leases, version checks and hashes as REST/Cron execution.

## 3.7.13 regression gates

A 3.7.13 release must retain all 3.7.12 storage, concurrency, REST-hardening, export-completion and validation-evidence gates and additionally verify:

```text
all three current Bootstrap degraded causes share DegradedModeIntegration and return before AdminModule
degraded recovery uses manage_options while healthy admin remains edis_export_evidence
degraded recovery does not construct the operational Admin/Application graph
degraded recovery does not expose export, worker, download, operational REST, or scheduled-export controls
healthy AdminModule behavior remains unchanged
plugin/build/package release identity is 3.7.13
ExportJobService::IMPLEMENTATION_VERSION remains exactly 3.7.12
critical-file integrity hashes match final bytes
the real WordPress degraded-admin integration check passes
```

## Preserved 3.7.12 regression gates

The 3.7.12 worker/runtime repair remains covered by these historical compatibility gates:

```text
package contract/integrity failures retain stable typed diagnostics without raw error disclosure
packaging sets current_component=null before package execution
retryable operational failures retain bounded phase/class context and a future next_retry_at
due compatible failed jobs recover through Resume; future/null retries do not advance
default Bundle Processor REQUIRED dependencies are transitively closed and precede consumers
Bridge Context fails closed on missing or malformed required input
explicit invalid collector requests fail closed without default substitution
fresh PHP 8.4 WordPress+Elementor export completes and produces a verified deterministic ZIP
persisted 3.7.11 jobs fail the 3.7.12 worker compatibility boundary and must be recreated
```

## WordPress-hardening gates

The release must retain:

- REST routes with bounded argument schemas for job IDs, revisions, document search, included document IDs and Inspector selections.
- REST failure responses that do not expose raw exception messages, filesystem paths or state-machine internals.
- Locked local JavaScript/CSS syntax and safety gates.
- WordPress-boundary and deterministic-core PHPCS rulesets where WPCS/PHPCompatibilityWP are installed.
- PHPStan configuration; full PHPStan execution remains an environment gate until the dependency set is available.
- GitHub Actions pinned to verified full commit SHAs; `npm run lint:workflows:strict` remains required.

## Version 3.7.13 gate status

The local gate set includes PHP lint, `tests/run-local.php`, runtime smoke, JavaScript lint, CSS lint, structured UTF-8/JSON/YAML validation, install ZIP lint, install ZIP integrity and the workflow-reference policy gate. Composer validation, install, audit, official PHPUnit and PHPCS must be treated as unexecuted unless their commands actually run successfully. The real export-completion and degraded-admin checks are STANDARD_CI evidence and do not replace required local validation.
