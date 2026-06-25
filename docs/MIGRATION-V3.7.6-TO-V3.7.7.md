# Migration Guide — EDIS 3.7.6 to 3.7.7

source_version: 3.7.6  
target_version: 3.7.7  
contract_change: none  
public_schema_change: none

Version 3.7.7 is a compatible LocalWP and Windows storage-diagnostics release. Frozen EDIS contracts, Bundle Schema `3.3.0`, Shared Artifact Envelope `2.0.0`, Package Manifest `2.1.0`, Selection Snapshot `1.2.0`, EDIS-CJ-2 and EDIS-ZIP-1 remain unchanged.

## LocalWP path behavior

Local documents the WordPress document root as `<site>/app/public`. In `WP_ENVIRONMENT_TYPE=local`, EDIS now derives the preferred private directory as:

```text
<site>/edis-private-storage
```

For the example site:

```text
C:/Users/Nestech/Local Sites/nurro/app/public
```

the preferred storage path is:

```text
C:/Users/Nestech/Local Sites/nurro/edis-private-storage
```

The 3.7.6 candidate `<site>/app/edis-private-storage` remains a secondary safe fallback. Neither path is inside the public WordPress root.

## wp-config.php

An explicit constant is optional in local mode. When used, it remains authoritative:

```php
define(
    'EDIS_EVIDENCE_PRIVATE_STORAGE_DIR',
    'C:/Users/Nestech/Local Sites/nurro/edis-private-storage'
);
```

Remove stale constants that point to another Local site or drive. Never point the constant at `app/public`, `wp-content`, uploads, or a symlink/junction into a public directory.

## Activation and diagnostics

A failed storage preflight no longer aborts plugin activation. The plugin activates in fail-closed diagnostic mode, disables new exports, and exposes:

```text
wp edis storage paths
wp edis storage self-test
```

The administrator notice also includes a storage-retest action and the exact failed check names.

## Windows process-lock probe

When WordPress runs through `php-cgi.exe`, EDIS looks for the sibling CLI binary `php.exe` for the independent-process lock test. If no CLI binary exists, local mode may accept only the explicit `UNAVAILABLE` state; a real lock failure is never accepted.

## Upgrade

1. Stop active export workers.
2. Delete the complete 3.7.6 plugin directory; do not overlay files.
3. Install `edis-evidence-exporter-3.7.7.zip`.
4. Confirm the plugin directory is `wp-content/plugins/edis-evidence-exporter`.
5. Activate the plugin.
6. Run `wp edis storage paths` and then `wp edis storage self-test` from Local’s **Open Site Shell**.
7. Create a new export job. Incomplete jobs from another implementation version are not silently resumed.

## Rollback

Stop workers, deactivate the plugin, remove the complete 3.7.7 directory, restore the complete 3.7.6 package, and create a fresh job. Do not rewrite 3.7.7 job records into older private formats.

---

# راهنمای مهاجرت — EDIS 3.7.6 به 3.7.7

نسخهٔ 3.7.7 هیچ قرارداد frozen یا Schema عمومی را تغییر نمی‌دهد. در LocalWP که روت وردپرس به‌شکل `<site>/app/public` است، مسیر ترجیحی جدید `<site>/edis-private-storage` خواهد بود. شکست Storage دیگر مانع فعال‌شدن افزونه نمی‌شود؛ افزونه فعال می‌ماند اما Exportها تا پاس‌شدن آزمون‌ها Fail-closed هستند. دستورهای `wp edis storage paths` و `wp edis storage self-test` حتی در degraded mode در دسترس‌اند. روی ویندوز، اگر Runtime وب از `php-cgi.exe` استفاده کند، آزمون قفل فرایند مستقل از `php.exe` هم‌سطح آن استفاده می‌کند.
