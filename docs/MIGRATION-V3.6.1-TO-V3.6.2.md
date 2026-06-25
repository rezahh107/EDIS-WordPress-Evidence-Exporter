# Migration: EDIS Evidence Exporter 3.6.1 to 3.6.2

## Scope

3.6.2 is a resilience-only patch. It does not change WordPress Bundle Schema `3.2.0`, Selection Snapshot Schema `1.2.0`, the REST namespace, or the WordPress/Browser/Python ownership boundary.

## Before upgrade

1. Download any completed bundles that must be retained.
2. Record or cancel queued/running 3.6.1 jobs; they cannot be resumed under 3.6.2.
3. Confirm protected storage has capacity for an additional immutable copy of the selected document source while a job is active.
4. Back up the site using the normal controlled process.

## After upgrade

1. Open **EDIS Evidence → Diagnostics**.
2. Confirm **Immutable input snapshot storage** passes.
3. Run **Safe worker test**.
4. Create a new export rather than resuming a 3.6.1 incomplete job.
5. Validate and download the new package normally.

## Operational changes

- Selected document source is captured before collector execution.
- Concurrent source drift during capture blocks job creation.
- Resume verifies job format, snapshot, plan prefix, step input and artifact SHA-256.
- Breakpoint `active` and `direction` are no longer inferred when unavailable.
- Legacy responsive suffixes are recognized only when their IDs are present in exported breakpoint evidence.

## Diagnostics

```text
EDIS_SOURCE_CHANGED_DURING_SNAPSHOT
EDIS_INPUT_SNAPSHOT_STORAGE_UNAVAILABLE
EDIS_INPUT_SNAPSHOT_INTEGRITY_FAILED
EDIS_JOB_FORMAT_INCOMPATIBLE
EDIS_RESUME_INPUT_MISMATCH
EDIS_RESUME_ARTIFACT_MISMATCH
```

## Rollback

Rolling plugin code back does not convert a 3.6.2 job into a 3.6.1-compatible job. Do not edit private job records. Cancel or allow cleanup, restore code through the normal deployment process, then create a job using the active version. Previously completed packages remain immutable historical artifacts.

---

# راهنمای مهاجرت از 3.6.1 به 3.6.2

نسخه 3.6.2 یک Patch پایداری است و Bundle Schema `3.2.0` و Selection Snapshot Schema `1.2.0` را تغییر نمی‌دهد.

پیش از Upgrade، Bundleهای کامل موردنیاز را دانلود کنید و Jobهای ناقص 3.6.1 را Resumeشدنی فرض نکنید. پس از Upgrade وارد Diagnostics شوید، وضعیت **Immutable input snapshot storage** را بررسی کنید، Safe Worker Test را اجرا کنید و Export تازه بسازید.

Snapshot دقیق Source سند انتخاب‌شده را پیش از Collectorها ثبت می‌کند. اگر Source هنگام Capture تغییر کند، Job با `EDIS_SOURCE_CHANGED_DURING_SNAPSHOT` متوقف می‌شود. Resume نیز فقط وقتی مجاز است که Job Format، Snapshot، ترتیب مراحل، Step Input و SHA-256 Artifactها بدون اختلاف باشند.

Rollback کد، Job نسخه 3.6.2 را به Job سازگار با 3.6.1 تبدیل نمی‌کند. فایل‌های Private Storage را دستی ویرایش نکنید.
