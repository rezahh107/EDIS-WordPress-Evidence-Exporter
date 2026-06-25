# Migration Guide — EDIS 3.7.4 to 3.7.6

source_version: 3.7.4  
target_version: 3.7.6  
contract_change: false  
public_schema_change: false

Version 3.7.6 is a compatible gate-execution hardening release. It does not change the frozen cross-product contract, Bundle Schema `3.3.0`, Shared Artifact Envelope `2.0.0`, Package Manifest `2.1.0`, Selection Snapshot `1.2.0`, EDIS-CJ-2 or EDIS-ZIP-1.

## What changed

- The local workflow-reference gate now exits successfully only when every action is either pinned to a full 40-character SHA or listed as a documented non-immutable rolling major reference in `tools/ci/github-actions-policy.json`.
- A strict mode remains available through `npm run lint:workflows:strict`; it fails unless every action is pinned to a full SHA.
- The release report separates `PASS_WITH_DOCUMENTED_ROLLING_REFS` from true immutable SHA pinning.

## Upgrade

1. Preserve completed 3.7.4 packages as immutable historical artifacts.
2. Deactivate the old plugin if active.
3. Remove the old plugin directory completely; do not overlay files.
4. Install the complete `edis-evidence-exporter-3.7.6.zip`.
5. Re-run Diagnostics and the private-storage self-test before creating new exports.

Incomplete jobs should be recreated after upgrade when runtime, storage, capability or workflow policy state changed.

# راهنمای مهاجرت — EDIS 3.7.4 به 3.7.6

نسخهٔ 3.7.6 قرارداد frozen و Schemaهای عمومی را تغییر نمی‌دهد. تمرکز این نسخه اجرای Gateهای محلی و تفکیک شفاف بین pin کامل SHA و rolling major reference مستندشده است.

برای ارتقا، پوشهٔ افزونهٔ قبلی را کامل حذف کنید و ZIP کامل 3.7.6 را نصب کنید. فایل‌ها را روی نسخهٔ قبلی Overlay نکنید. بسته‌های کامل 3.7.4 را artifact تاریخی نگه دارید و Jobهای نیمه‌تمام را در صورت تغییر وضعیت runtime یا storage دوباره بسازید.
