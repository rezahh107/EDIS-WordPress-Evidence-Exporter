# Migration Guide — EDIS 3.7.3 to 3.7.4

source_version: 3.7.3  
target_version: 3.7.4  
contract_change: false  
public_schema_change: false

Version 3.7.4 is a compatible WordPress-hardening release. It does not change the frozen cross-product contract, Bundle Schema `3.3.0`, Shared Artifact Envelope `2.0.0`, Package Manifest `2.1.0`, Selection Snapshot `1.2.0`, EDIS-CJ-2 or EDIS-ZIP-1.

## What changed

- REST routes now declare bounded argument schemas for job IDs, revisions, document search, document inclusion and Elementor Inspector selections.
- REST error responses no longer expose raw exception messages; they return stable public messages and diagnostic IDs.
- The security policy now documents the actual `edis_export_evidence` capability model and reiterates that nonces are not authorization.
- Source packages now include asset lint scripts, CSS/JS gate scripts, split PHPCS rulesets and a PHPStan configuration scaffold.
- The GitHub Actions workflow runs the asset lint gates where Node is available. Composer audit remains enabled when a verified `composer.lock` exists.

## Upgrade procedure

1. Preserve completed 3.7.3 packages as immutable historical artifacts.
2. Stop any running EDIS workers.
3. Delete the complete old plugin directory; do not overlay files.
4. Install the complete `edis-evidence-exporter-3.7.4.zip`.
5. Activate the plugin and open EDIS Evidence → Diagnostics.
6. Run the worker self-test before creating new evidence exports.

## Job compatibility

Completed, self-validating 3.7.3 packages remain valid historical packages. Incomplete jobs should be recreated after upgrade when any runtime, storage or capability state changed.

## Rollback

Rollback does not rewrite 3.7.4 jobs into 3.7.3 jobs. Stop workers, deactivate the plugin, remove the complete plugin directory, restore the complete prior plugin version and create a fresh job if needed.

# راهنمای مهاجرت — EDIS 3.7.3 به 3.7.4

نسخهٔ 3.7.4 قرارداد frozen و Schemaهای عمومی را تغییر نمی‌دهد. تمرکز این نسخه سخت‌سازی وردپرسی است: Schemaهای دقیق‌تر REST، پیام خطای امن‌تر، مستندات امنیتی درست‌تر، و Gateهای اولیهٔ JS/CSS/static-analysis.

برای ارتقا، پوشهٔ افزونهٔ قبلی را کامل حذف کنید و ZIP کامل 3.7.4 را نصب کنید. فایل‌ها را روی نسخهٔ قبلی Overlay نکنید. بسته‌های کامل و معتبر 3.7.3 را به‌عنوان artifact تاریخی نگه دارید و Jobهای نیمه‌تمام را بعد از ارتقا دوباره بسازید.
