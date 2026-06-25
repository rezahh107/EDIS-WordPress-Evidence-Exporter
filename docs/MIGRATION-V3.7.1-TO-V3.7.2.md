# Migration: EDIS Evidence Exporter 3.7.1 to 3.7.2

## Scope

Version 3.7.2 is a debugging-only corrective release. It does not add a collector, resolver, rule or product feature. Bundle Schema `3.3.0`, Shared Envelope `2.0.0`, Package Manifest `2.1.0`, Selection Snapshot `1.2.0`, EDIS-CJ-2 and EDIS-ZIP-1 remain unchanged.

## Before upgrade

1. Record any incomplete Job IDs and preserve the exact error text.
2. Keep completed validated packages as historical artifacts.
3. Do not extract 3.7.2 over the existing plugin directory.
4. Delete the previous plugin directory and install the complete 3.7.2 ZIP to avoid mixed files.

## Incomplete jobs

Incomplete 3.7.1 step records use the previous implementation version and are not resumed under 3.7.2. Create a new export. This prevents an artifact created by the defective or ambiguous path from being trusted as a current completed step.

## Private storage on Windows/Local

The PHP temporary directory is only a temporary-file location; it is not proof that the path is outside the WordPress public root. EDIS 3.7.2 tries bounded outside-root candidates and otherwise enters degraded mode instead of crashing WordPress.

For a Local site whose public root is:

```text
C:/Users/Nestech/Local Sites/nurro/app/public
```

use a sibling private path in `wp-config.php`:

```php
define( 'EDIS_EVIDENCE_PRIVATE_STORAGE_DIR', 'C:/Users/Nestech/Local Sites/nurro/app/edis-private' );
```

The PHP account must be able to create, lock, flush, sync, atomically replace and delete files there.

## Diagnostic codes

- `EDIS_PRIVATE_STORAGE_UNAVAILABLE`: exports blocked; WordPress remains available in degraded mode.
- `EDIS_INSTALLATION_MIXED_VERSION`: critical files do not match the bundled integrity manifest; perform a clean reinstall.
- `EDIS_INSTALLATION_INTEGRITY_MANIFEST_MISSING`: package is incomplete; perform a clean reinstall.
- `EDIS_JOB_FORMAT_INCOMPATIBLE` or implementation mismatch: create a new Job.

## Rollback

Rolling back does not convert 3.7.2 implementation records to 3.7.1. Stop new work, retain completed packages, replace the complete plugin directory through the deployment system and create new incomplete Jobs under the active version.

---

# راهنمای مهاجرت EDIS از 3.7.1 به 3.7.2

## دامنه

نسخه 3.7.2 فقط برای خطایابی و اصلاح نقص‌هاست و Collector، Resolver، Rule یا قابلیت محصولی جدید اضافه نمی‌کند. Bundle Schema `3.3.0`، Shared Envelope `2.0.0`، Package Manifest `2.1.0`، Selection Snapshot `1.2.0`، EDIS-CJ-2 و EDIS-ZIP-1 بدون تغییرند.

## پیش از ارتقا

1. شناسه Jobهای ناقص و متن کامل خطا را ثبت کنید.
2. Packageهای کامل و Validateشده را به‌عنوان Artifact تاریخی نگه دارید.
3. فایل‌های 3.7.2 را روی پوشه افزونه قبلی Extract نکنید.
4. پوشه قبلی را کامل حذف و ZIP کامل 3.7.2 را نصب کنید تا فایل‌های نسخه‌ها مخلوط نشوند.

## Jobهای ناقص

Step Record ناقص 3.7.1 با Implementation Version جدید Resume نمی‌شود. Export تازه بسازید. این رفتار مانع اعتماد به Artifactی می‌شود که با مسیر معیوب یا مبهم قبلی تولید شده است.

## Private Storage در Windows/Local

دایرکتوری موقت PHP فقط محل فایل موقت است و اثبات نمی‌کند خارج از Web Root وردپرس قرار دارد. نسخه 3.7.2 Candidateهای محدود خارج از Root را بررسی می‌کند و در صورت شکست، به‌جای Crash کردن وردپرس وارد Degraded Mode می‌شود.

اگر Web Root این است:

```text
C:/Users/Nestech/Local Sites/nurro/app/public
```

در `wp-config.php` مسیر خصوصی هم‌سطح زیر را تنظیم کنید:

```php
define( 'EDIS_EVIDENCE_PRIVATE_STORAGE_DIR', 'C:/Users/Nestech/Local Sites/nurro/app/edis-private' );
```

User اجرای PHP باید اجازه ساخت، Lock، Flush، Sync، جایگزینی اتمیک و حذف فایل را داشته باشد.

## کدهای Diagnostic

- `EDIS_PRIVATE_STORAGE_UNAVAILABLE`: Export مسدود است ولی وردپرس در Degraded Mode در دسترس می‌ماند.
- `EDIS_INSTALLATION_MIXED_VERSION`: فایل حیاتی با Manifest بسته تطبیق ندارد؛ نصب تمیز انجام دهید.
- `EDIS_INSTALLATION_INTEGRITY_MANIFEST_MISSING`: بسته ناقص است؛ نصب تمیز انجام دهید.
- `EDIS_JOB_FORMAT_INCOMPATIBLE` یا Implementation mismatch: Job تازه بسازید.

## Rollback

Rollback، Step Record نسخه 3.7.2 را به 3.7.1 تبدیل نمی‌کند. ساخت Job جدید را متوقف کنید، Packageهای کامل را نگه دارید، کل پوشه افزونه را از طریق Deployment جایگزین و Jobهای ناقص را در نسخه فعال دوباره ایجاد کنید.
