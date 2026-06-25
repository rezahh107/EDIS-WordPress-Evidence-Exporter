# Migration Guide — EDIS 3.7.2 to 3.7.3

## Status and scope

```text
source_version: 3.7.2
target_version: 3.7.3
public_contract_change: false
public_schema_change: false
private_job_format_change: false
implementation_compatibility_change: true
```

Version 3.7.3 is a compatible corrective release for the WordPress Evidence Exporter. It does not change the frozen cross-product contract, Bundle Schema `3.3.0`, Shared Artifact Envelope `2.0.0`, Package Manifest `2.1.0`, Selection Snapshot `1.2.0`, EDIS-CJ-2 or EDIS-ZIP-1.

## Required upgrade procedure

1. Stop REST, Cron and WP-CLI workers and wait for any active export request to finish.
2. Back up the current database and the complete EDIS private-storage directory.
3. Deactivate EDIS 3.7.2.
4. Delete the complete old plugin directory. Do not overlay 3.7.3 files onto 3.7.2.
5. Install the complete `edis-evidence-exporter-3.7.3.zip` and activate it.
6. Open **Tools → Site Health** or run `wp edis storage self-test` from the same PHP deployment context used by WordPress.
7. Confirm all storage fields are `PASS`, especially `multiprocess_lock_exclusion`.
8. Create a new bounded test export before resuming normal use.

## Private-storage requirements

`EDIS_EVIDENCE_PRIVATE_STORAGE_DIR` must be an absolute writable path outside `ABSPATH`. The logical path itself must not be inside the web root, and no existing ancestor may be a symbolic link or another redirecting path component. On supported deployments, PHP must be able to launch the current `PHP_BINARY` with `proc_open`; inability to execute the independent lock probe blocks export creation rather than weakening the contract.

Per-job `.lock` files are stable lock sentinels. Version 3.7.3 intentionally retains them after job expiry or ordinary removal. They contain no evidence payload. Do not delete individual lock files while EDIS workers may run. They are removed only when the entire site-scoped private-storage tree is safely removed, such as an authorized uninstall with retention disabled.

## Job and Resume behavior

Incomplete 3.7.2 jobs must not be resumed by 3.7.3 because component implementation records changed. Create new jobs from the saved WordPress/Elementor source. Completed, self-validating 3.7.2 packages remain immutable historical artifacts and are not rewritten.

Resume and Retry now acquire the per-job lock before changing status, revision or diagnostics and retain that same lock through the first worker advancement. A lock conflict therefore produces an error without mutating the job.

## Semantic hash correction

3.7.2 recursively removed any object property whose bare name matched an operational key. That could incorrectly exclude a nested saved-source or addon field such as `evidence.settings.created_at`. Version 3.7.3 applies the unchanged Semantic Identity Policy `1.0.0` only at contract-defined envelope roots.

Do not replace or relabel hashes in historical packages. If an analysis depends on nested source fields with operational-looking names, create a fresh 3.7.3 export and treat the new package as a distinct evidence instance.

## Rollback

Rollback does not convert 3.7.3 jobs into 3.7.2 jobs. Stop workers, deactivate the plugin, remove the complete plugin directory, restore the complete prior plugin version, and create a new job under that implementation. Preserve completed packages as immutable artifacts.

## Verification state

The bundled unit/static/runtime gates verify the corrected behavior on the environments where they are executed. Real WordPress 7.0, multisite, Elementor Editor, Windows junction/UNC and distributed-filesystem gates remain separate release evidence and must not be inferred from a local PHP test pass.

---

# راهنمای مهاجرت — EDIS 3.7.2 به 3.7.3

نسخهٔ 3.7.3 قرارداد frozen و Schemaهای عمومی را تغییر نمی‌دهد، اما پنج نقص تأییدشده در قفل فعال، مسیر Storage، Semantic Hash، Cleanup و Resume را اصلاح می‌کند.

## مراحل الزامی

1. اجرای REST Worker، Cron و WP-CLI Worker را متوقف کنید و اجازه دهید درخواست فعال تمام شود.
2. از دیتابیس و کل پوشهٔ Private Storage نسخهٔ پشتیبان بگیرید.
3. افزونهٔ 3.7.2 را غیرفعال کنید.
4. پوشهٔ افزونه را کامل حذف کنید؛ فایل‌های 3.7.3 را روی پوشهٔ قدیمی Overlay نکنید.
5. ZIP کامل 3.7.3 را نصب و فعال کنید.
6. در Site Health یا با فرمان `wp edis storage self-test`، مقدار `multiprocess_lock_exclusion` و تمام کنترل‌های Storage را بررسی کنید.
7. پیش از استفادهٔ عادی، یک Export کوچک و تازه بسازید.

مسیر Storage باید مطلق، قابل‌نوشتن، خارج از `ABSPATH` و بدون ancestor از نوع symlink یا مسیر redirectشده باشد. در محیط اجرای وردپرس، `proc_open` و `PHP_BINARY` باید برای Probe مستقل قفل در دسترس باشند؛ در غیر این صورت Export به‌صورت fail-closed مسدود می‌شود ولی Diagnostics در دسترس می‌ماند.

فایل‌های `.lock` خالی، sentinel پایدار قفل هستند و در Cleanup عادی حذف نمی‌شوند. هنگام فعال بودن Workerها آن‌ها را دستی پاک نکنید.

Jobهای ناقص 3.7.2 را Resume نکنید؛ Job تازه بسازید. Packageهای کامل قبلی تغییرناپذیر باقی می‌مانند. Hash تاریخی را بازنویسی نکنید؛ اگر nested source field با نامی مانند `created_at` در نتیجه مهم است، Export تازهٔ 3.7.3 تولید کنید.
