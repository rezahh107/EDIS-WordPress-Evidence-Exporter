# مهاجرت از EDIS 3.7.7 به 3.7.8

## وضعیت قراردادها

این ارتقا یک Patch سازگار است. نسخه‌های عمومی زیر تغییر نکرده‌اند:

```text
bundle_schema: 3.3.0
shared_artifact_envelope: 2.0.0
package_manifest: 2.1.0
selection_snapshot: 1.2.0
private_job_format: 2.1.0
private_input_snapshot_format: 2.0.0
canonical_json: EDIS-CJ-2
deterministic_zip: EDIS-ZIP-1
```

## تغییر مسدودکنندهٔ امنیتی

نسخهٔ 3.7.8 دیگر `PASS_LOCAL_SINGLE_PROCESS` یا `LOCAL_UNAVAILABLE_ACCEPTED` را قبول نمی‌کند. در LocalWP نیز فقط نتیجهٔ زیر Export را فعال می‌کند:

```text
state=PASS
multiprocess_lock_exclusion=PASS
```

اگر `proc_open` یا PHP مستقل در دسترس نباشد، WordPress فعال می‌ماند اما Export در حالت fail-closed مسدود می‌شود و Diagnostics قابل دسترسی است.

## Jobهای در حال اجرا

Jobهای 3.7.7 به‌طور خاموش Resume نمی‌شوند. پس از نصب 3.7.8، Job ناقص جدید بسازید. Bundleهای کامل قبلی تا پایان retention خود قابل دانلود می‌مانند، مشروط به اینکه فایل و token معتبر باشند.

## نصب جایگزین

1. از Bundleهای لازم نسخهٔ پشتیبان بگیرید.
2. پوشهٔ افزونهٔ 3.7.7 را کامل حذف کنید؛ Source را overlay نکنید.
3. Install ZIP نسخهٔ 3.7.8 را نصب و فعال کنید.
4. این فرمان‌ها را اجرا کنید:

```text
wp plugin list --fields=name,status,version
wp edis storage paths
wp edis storage self-test
```

5. فقط `PASS/PASS` را نتیجهٔ قابل قبول بدانید.
6. یک Export کوچک بسازید و download hash و package validation را بررسی کنید.

## Storage attestation

پس از یک self-test کامل موفق، فایل خصوصی `.edis-storage-attestation.json` در ریشهٔ Storage ساخته می‌شود. این فایل evidence export نیست و نباید دستی ویرایش شود. تغییر مسیر Storage، نسخهٔ افزونه، PHP/SAPI/binary یا environment آن را نامعتبر می‌کند.

## Preflight proof

Admin UI در 3.7.8 یک proof کوتاه‌عمر به Job creation می‌فرستد. proof به owner، درخواست نرمال‌شده و hash خام sourceهای ذخیره‌شده متصل است. تغییر سند یا تنظیمات باعث رد proof و الزام اجرای دوبارهٔ Preflight می‌شود.

## بازگشت به نسخه قبل

Downgrade توصیه نمی‌شود، چون 3.7.7 با قرارداد frozen دربارهٔ lock proof تعارض دارد. در صورت اجبار، ابتدا Jobهای 3.7.8 را لغو و پوشهٔ افزونه را کامل جایگزین کنید؛ هیچ Job بین دو implementation version قابل Resume نیست.
