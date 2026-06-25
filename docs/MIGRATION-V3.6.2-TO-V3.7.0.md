# Migration: EDIS Evidence Exporter 3.6.2 to 3.7.0

## Breaking compatibility boundary

3.7.0 changes coordinated evidence contracts:

```text
Bundle Schema: 3.2.0 -> 3.3.0
Shared Artifact Envelope: 1.x -> 2.0.0
Canonical JSON: EDIS-CJ-1 -> EDIS-CJ-2
Private Job Format: 1.1.0 -> 2.0.0
Private Input Snapshot Format: 1.0.0 -> 2.0.0
```

Selection Snapshot Schema remains `1.2.0`.

## Before upgrade

1. Download any completed 3.6.2 packages that must be retained.
2. Cancel or record queued/running jobs. They cannot resume under 3.7.0.
3. Confirm PHP is 64-bit PHP 8.2–8.5 and `fsync`, `proc_open`, `pack`, `hash` and JSON are available.
4. Configure `EDIS_EVIDENCE_PRIVATE_STORAGE_DIR` outside the public web root.
5. Confirm Browser and Python do not silently consume Bundle 3.3.0 as if it were 3.2.0.

## Upgrade

1. Deactivate 3.6.2 without uninstalling it.
2. Replace the plugin directory with the 3.7.0 install package.
3. Activate the plugin. Activation runs protected-storage, durable-write and independent-process lock tests.
4. Open **EDIS Evidence → Diagnostics** and run the Safe worker test.
5. Create a new export. Do not edit private job/snapshot files to bypass compatibility checks.

## Browser and Python routing

Browser and Python must explicitly support Bundle 3.3.0, Shared Envelope 2.0.0, EDIS-CJ-2, both artifact hashes and invalid-vector diagnostics. Until this is verified:

```text
cross_product_status: insufficient_evidence
```

## Rollback

Rolling code back does not convert a 3.7.0 Job, Input Snapshot or Bundle into 3.6.2 format. Preserve completed packages as immutable historical artifacts. Remove/cancel incompatible incomplete jobs through normal cleanup, restore 3.6.2 code, and create new jobs under the active version. Never hand-edit hashes or format versions.

---

# راهنمای مهاجرت از EDIS 3.6.2 به 3.7.0

## مرز ناسازگاری

نسخه 3.7.0 Bundle Schema را به `3.3.0`، Envelope را به `2.0.0`، Canonical JSON را به EDIS-CJ-2 و فرمت‌های خصوصی Job/Snapshot را به `2.0.0` ارتقا می‌دهد. Selection Snapshot روی `1.2.0` باقی می‌ماند.

## پیش از ارتقا

1. Bundleهای کامل موردنیاز را دانلود کنید.
2. Jobهای در حال اجرا را ثبت یا لغو کنید؛ Resume نمی‌شوند.
3. PHP هشت‌و‌دو تا هشت‌و‌پنج 64-bit و دسترسی به `fsync` و `proc_open` را بررسی کنید.
4. Storage خصوصی را خارج از Web Root تنظیم کنید.
5. مطمئن شوید Browser و Python نسخه 3.3.0 را به‌اشتباه 3.2.0 تفسیر نمی‌کنند.

## پس از نصب

افزونه را فعال کنید، Diagnostics و Safe Worker Test را اجرا کنید و Export تازه بسازید. فایل‌های Private Storage یا Hashها را دستی تغییر ندهید.

## Rollback

Rollback کد، Job یا Bundle نسخه 3.7.0 را به فرمت 3.6.2 تبدیل نمی‌کند. Artifactهای کامل تاریخی را تغییر ندهید؛ Job ناقص ناسازگار را Cleanup کنید و زیر نسخه فعال Job تازه بسازید.
