# کیت اعتبارسنجی EDIS 3.7.11

این کیت فقط در بستهٔ سورس قرار دارد و دامنه و وضعیت شواهد اعتبارسنجی را ثبت می‌کند. پوشه از ZIP نصب WordPress حذف شده است.

اجرای Gateهای متعلق به مخزن:

```bash
php tools/validation/run-local-validation.php --report=validation/evidence/local-validation.json
```

در Windows PowerShell:

```powershell
./tools/validation/run-local-validation.ps1 --report=validation/evidence/windows-local-validation.json
```

مقدار `summary.local_state=PASS` اکنون فقط وقتی تولید می‌شود که تمام Gateهای محلی الزامی پاس شده باشند. Gate محلی Skipشده یا در دسترس‌نبوده، وضعیت `INCOMPLETE` و exit code غیرصفر ایجاد می‌کند. Gateهای خارجی WordPress، Elementor، Windows/LocalWP، Composer و Python جداگانه در `summary.external_state` خلاصه می‌شوند.

گزینهٔ `--strict-external` علاوه بر Gateهای محلی، پاس‌شدن تمام Gateهای خارجی را نیز الزام می‌کند و وضعیت Gate محلی Skipشده را تغییر نمی‌دهد.

خروجی فرمان‌ها در فایل‌های موقت خصوصی ثبت می‌شود و Evidence فقط SHA-256، تعداد بایت و Tail محدود را نگه می‌دارد. فایل گزارش نیز با جایگزینی اتمیک و بررسی مجدد SHA-256 ثبت می‌شود.

این کیت هیچ Schema، قرارداد frozen، مرز محاسباتی یا رفتار Runtime نصب‌شدهٔ افزونه را تغییر نمی‌دهد.
