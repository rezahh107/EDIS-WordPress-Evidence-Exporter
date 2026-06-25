# راهنمای مهاجرت EDIS از 3.7.8 به 3.7.9

## دامنه

نسخهٔ 3.7.9 یک Patch سازگار با قرارداد است. هیچ Schema عمومی، قرارداد frozen، شناسهٔ Collector یا پروفایل `EDIS-CJ-2` / `EDIS-ZIP-1` تغییر نکرده است.

## تغییرات رفتاری مهم

- فهرست اسناد فقط سندهایی را برمی‌گرداند که کاربر جاری برای آن‌ها `edit_post` دارد. `total` و `total_pages` نیز فقط از همان مجموعهٔ مجاز محاسبه می‌شوند.
- `include` غیرمجاز به‌صورت fail-closed نتیجهٔ خالی می‌دهد و باعث fallback به فهرست عمومی نمی‌شود.
- cache خواندن Job بر اساس SHA-256 محتوا اعتبارسنجی می‌شود؛ بازنویسی خارجی هم‌اندازه و هم‌زمان دیگر پنهان نمی‌ماند.
- Cron، تعمیر stale lease و کشف Job قابل اجرا را در یک پیمایش قطعی انجام می‌دهد.
- در POSIX، پس از commit اتمیک فایل یا دایرکتوری Snapshot، parent directory نیز در صورت پشتیبانی runtime همگام می‌شود.
- Workflowها Actionها را با SHA کامل ثابت اجرا می‌کنند و WP-CLI 2.12.0 را با SHA-512 ثابت بررسی می‌کنند.

## نصب

1. از بستهٔ نصب `edis-evidence-exporter-3.7.9.zip` استفاده کنید.
2. پوشهٔ نسخهٔ قبلی را با Source ZIP یا فایل‌های پراکنده Overlay نکنید.
3. افزونه را فعال کنید.
4. در Site Shell اجرا کنید:

```text
wp plugin list --fields=name,status,version
wp edis status
wp edis storage paths
wp edis storage self-test
```

نتیجهٔ قابل قبول Storage طبق قرارداد frozen فقط این است:

```text
state=PASS
multiprocess_lock_exclusion=PASS
```

## Jobهای قدیمی

Job ناقص ساخته‌شده با implementation version قبلی silently resume نمی‌شود. Bundleهای کامل و اعتبارسنجی‌شدهٔ قبلی به‌عنوان artifact تاریخی باقی می‌مانند. Export ناقص را با 3.7.9 دوباره ایجاد کنید.

## تغییر مجوزها

اگر capability سفارشی `edis_export_evidence` به Editor یا نقش سفارشی داده شده باشد، کاربر فقط metadata سندهایی را می‌بیند که `current_user_can('edit_post', $document_id)` برای آن‌ها موفق است. این تغییر ممکن است total و pagination UI را برای نقش‌های محدود کاهش دهد؛ این رفتار اصلاح امنیتی مورد انتظار است.

## توسعه و CI

Source 3.7.9، نبود `composer.lock` را fail-closed می‌داند. برای ادعای release نهایی توسعه‌ای، در محیط دارای Composer معتبر اجرا کنید:

```text
composer validate --strict
composer update --no-interaction --prefer-dist
composer install --no-interaction --prefer-dist
composer audit --locked
vendor/bin/phpunit --configuration phpunit.xml.dist
vendor/bin/phpcs --standard=phpcs.xml.dist
vendor/bin/phpstan analyse -c phpstan.neon.dist
```

`composer.lock` تولیدشده باید review و commit شود. فایل lock نباید دستی ساخته یا از پروژهٔ دیگری کپی شود.

## وضعیت Gateهای بیرونی

اجرای واقعی WordPress/Multisite، Elementor، Windows/LocalWP، Composer و cross-product ingestion همچنان به محیط خارجی نیاز دارد. هیچ Gate اجرا‌نشده‌ای در این نسخه PASS اعلام نشده است.
