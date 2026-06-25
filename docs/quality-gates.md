# EDIS 3.7.11 Quality Gates

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

Run the full suite on 64-bit PHP 8.2, 8.3, 8.4 and 8.5. `serialize_precision`, locale, timezone, collector registration order and independent selection order permutations must not change semantic output. PHP 8.0, 8.1 and 8.6 or later are outside the 3.7.11 deterministic runtime contract.

## WordPress Plugin Check

The repository workflow pins Plugin Check `2.0.0` and runs both static and runtime checks against the oldest declared WordPress branch (`6.5.8`) and the current tested release (`7.0`). On a disposable WordPress environment:

```bash
wp plugin install plugin-check --version=2.0.0 --activate
wp plugin activate edis-evidence-exporter
wp plugin check edis-evidence-exporter
wp plugin check edis-evidence-exporter --require=wp-content/plugins/plugin-check/cli.php
```

Both checks are release gates. Enable `WP_DEBUG`, `WP_DEBUG_LOG`, `SCRIPT_DEBUG` and `ELEMENTOR_DEBUG` in non-production test environments and require zero EDIS-origin warnings or deprecations.

## Elementor activation smoke

The repository workflow pins WordPress `7.0` and Elementor `4.1.3`, activates both plugins on PHP 8.2 through 8.5, verifies the Elementor load hook, executes the EDIS-CJ-2 runtime gate and requires the private-storage self-test to pass. This is an activation/integration smoke test, not an Elementor Editor end-to-end test.

A release still requires a browser-driven matrix covering Legacy sections/columns/widgets, Flexbox Containers, nested Containers, Atomic elements, Hybrid V3/V4 documents, third-party addon elements, context-menu selection and real export completion.

## Storage gate

The active private-storage path must pass durable write, atomic replacement/rename, post-write SHA-256 verification, cleanup, two-handle lock exclusion and separate-PHP-process lock exclusion. The self-test field `atomic_replace` must be `true`. Independent-process lock exclusion must pass both the source regression test and the active deployment self-test. Production invokes the current `PHP_BINARY` through `proc_open` without a shell and requires the child lock attempt to be blocked. Shared/NFS/container-cluster storage requires an additional deployment-specific concurrency test. Failure is fail-closed.

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


## 3.7.11 regression gates

A 3.7.11 release must execute and retain evidence for:

```text
active-path child process reports BLOCKED while parent holds lock
logical path inside ABSPATH via ancestor symlink is rejected
nested evidence.settings.created_at changes semantic_payload_sha256
cleanup cannot permit a second lock while the first handle is held
Resume lock conflict leaves job bytes/revision/diagnostics unchanged
```

Each gate must fail on the corresponding unmodified 3.7.2 behavior and pass on 3.7.11. Windows junction/UNC, multi-host shared filesystems, real WordPress/Multisite and Elementor Editor remain separate environment gates.

## 3.7.11 WordPress-hardening gates

A 3.7.11 release must retain all 3.7.3 storage/concurrency regression gates and additionally verify:

- REST routes declare bounded argument schemas for job IDs, revisions, document search, included document IDs and Inspector selections.
- REST failure responses do not expose raw exception messages, filesystem paths or state-machine internals.
- JavaScript assets pass the locked local syntax/safety gate and CSS assets pass the locked local structural gate.
- The WordPress boundary and deterministic core PHPCS rulesets remain present and executable when WPCS/PHPCompatibilityWP are installed.
- PHPStan configuration remains present; full PHPStan execution is a CI/environment gate until the dependency set is locked with Composer.
- GitHub Actions are pinned to verified full commit SHAs. The strict SHA-only checker is a required local and CI gate.


## Version 3.7.11 gate status

The local gate set includes PHP lint, `tests/run-local.php`, runtime smoke, JavaScript lint, CSS lint, structured UTF-8/JSON/YAML validation, install ZIP lint, install ZIP integrity and the workflow-reference policy gate. The workflow-reference gate requires immutable full commit SHAs and must pass `npm run lint:workflows:strict`. Composer validation, install and audit require a committed `composer.lock`; absence of that file is a fail-closed release condition.
