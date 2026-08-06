# کیت اعتبارسنجی EDIS 3.7.16

این کیت فقط در بستهٔ سورس قرار دارد و دامنه و وضعیت شواهد اعتبارسنجی را ثبت می‌کند. پوشه از ZIP نصب WordPress حذف شده است.

اجرای Gateهای متعلق به مخزن:

```bash
php tools/validation/run-local-validation.php --report=release-build/validation-evidence/local-validation.json
```

در Windows PowerShell:

```powershell
./tools/validation/run-local-validation.ps1 --report=release-build/validation-evidence/windows-local-validation.json
```

مقدار `summary.local_state=PASS` فقط وقتی تولید می‌شود که تمام Gateهای محلی الزامی پاس شده باشند. Gate محلی Skipشده یا در دسترس‌نبوده، وضعیت `INCOMPLETE` و exit code غیرصفر ایجاد می‌کند. Gateهای خارجی WordPress، Elementor، Windows/LocalWP، Composer و Python جداگانه در `summary.external_state` خلاصه می‌شوند.

گزینهٔ `--strict-external` علاوه بر Gateهای محلی، پاس‌شدن تمام Gateهای خارجی را نیز الزام می‌کند و وضعیت Gate محلی Skipشده را تغییر نمی‌دهد.

خروجی فرمان‌ها در فایل‌های موقت خصوصی ثبت می‌شود و Evidence فقط SHA-256، تعداد بایت و Tail محدود را نگه می‌دارد. شواهد تولیدشدهٔ Validation فقط زیر `release-build/validation-evidence/` نوشته می‌شوند، هیچ‌گاه Source Authority نیستند و به `plugin.manifest.json` اضافه نمی‌شوند. فایل گزارش با جایگزینی اتمیک و بررسی مجدد SHA-256 ثبت می‌شود.

نسخهٔ 3.7.16 رکورد معیار `EDIS-DIAGNOSTIC-1`، ذخیره‌سازی خصوصی و محدود، بازیابی دقیق و مجاز، حریم خصوصی مبتنی بر فهرست مجاز، نمایش صریح عدم‌قطعیت، Projection مشترک مرورگر و مدیریت، fallback صادقانهٔ ذخیره‌سازی و نگاشت وضعیت Safe Worker را اعتبارسنجی می‌کند. هویت محصول، پلتفرم و producer بسته 3.7.16 است؛ سازگاری worker روی 3.7.15 باقی می‌ماند. نسخه‌های عمومی Schema شواهد و بسته تغییر نمی‌کنند.
