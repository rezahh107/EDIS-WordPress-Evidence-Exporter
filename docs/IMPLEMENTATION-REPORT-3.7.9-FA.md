# گزارش پیاده‌سازی EDIS 3.7.9

## وضعیت

```text
base_version: 3.7.8
target_version: 3.7.9
public_contract_change: false
public_schema_change: false
production_ready_verified: false
```

## خلاصه

3.7.9 یافته‌های مستقل مربوط به مجوز سطح سند، cache یکپارچگی JobStore، نسخهٔ hard-coded در Multisite CI و referenceهای متحرک GitHub Actions را اصلاح می‌کند. ریسک دوام parent-directory در POSIX کاهش یافته و Cron recovery از دو پیمایش کامل به یک پیمایش تبدیل شده است. Missing Composer lock به‌دلیل نبود Composer و dependency resolution معتبر در محیط ساخت، جعل یا دستی تولید نشده و همچنان Gate بیرونی مسدودکننده است.

## نگاشت یافته‌ها

| Finding | وضعیت | اصلاح |
|---|---|---|
| `EDIS-AUDIT-SEC-001` | FIXED | authorization سطح `edit_post` پیش از totals و pagination؛ `include` غیرمجاز fail-closed |
| `EDIS-AUDIT-INT-001` | FIXED | cache JobStore با SHA-256 bytes به‌جای mtime/size |
| `EDIS-AUDIT-SC-001` | FIXED | هر هشت GitHub Action با SHA کامل ثابت شدند؛ strict gate پاس شد |
| `EDIS-AUDIT-SC-002` | PARTIALLY FIXED | CI بدون `composer.lock` متوقف می‌شود و audit همیشه locked است؛ lock واقعی هنوز نیازمند Composer resolution است |
| `EDIS-AUDIT-CI-001` | FIXED | انتظار 3.7.7 حذف و نسخه از constant فعال خوانده می‌شود |
| `EDIS-AUDIT-FS-001` | PARTIALLY FIXED | directory fsync روی POSIX افزوده شد؛ رفتار Windows نیازمند runtime validation است |
| `EDIS-AUDIT-PERF-001` | PARTIALLY FIXED | Cron repair/discovery یک‌پاس شد؛ Queryهای عمومی JobStore همچنان O(n) هستند |
| `EDIS-AUDIT-ELEM-001` | INSUFFICIENT_EVIDENCE | نیازمند Export و runtime واقعی Elementor است؛ رفتار دامنه‌ای حدس زده نشد |

## فایل‌های هستهٔ اصلاح‌شده

- `src/Application/DocumentQueryService.php`
- `src/Rest/DocumentController.php`
- `src/Infrastructure/Support/JobStore.php`
- `src/WordPress/WorkerRecovery.php`
- `src/Infrastructure/Support/DeterministicFilesystem.php`
- `src/Infrastructure/Support/InputSnapshotStore.php`
- `.github/workflows/quality.yml`
- `tools/ci/github-actions-policy.json`

تست‌ها، version declarations، critical-file hashes، README، Help، Changelog و مستندات مهاجرت نیز همگام شدند.

## شواهد محلی

```text
PHP lint: 155/155 PASS
local tests: 93 passed, 0 failed, 0 skipped
runtime smoke E_ALL: PASS
npm ci: PASS
JS/CSS gates: PASS
strict workflow SHA gate: PASS (8/8 pinned)
npm audit: 0 vulnerabilities
JSON validation: 25 files PASS
YAML validation: 1 file PASS
internal imports: 108 classes, 0 missing
```

Benchmark همین محیط:

```text
ZIP payload: 32 MiB
PHP peak memory: 35,655,680 bytes
process max RSS: 71,372 KiB

Storage live proof: 86.825 ms
Storage attestation reuse: 1.401 ms
```

اعداد benchmark وابسته به محیط هستند و فقط شاهد این اجرای محلی‌اند.

## Gateهای اجرا‌نشده

- Composer validate/install/audit و lock generation
- PHPUnit رسمی، PHPCS/WPCS/PHPCompatibilityWP، PHPStan
- PHP 8.2، 8.3 و 8.5
- WordPress/Multisite/Plugin Check واقعی
- Elementor runtime و Editor E2E
- Windows/LocalWP، Junction، UNC و Controlled Folder Access
- Browser/Python cross-product ingestion

این موارد `NOT_RUN` هستند.
