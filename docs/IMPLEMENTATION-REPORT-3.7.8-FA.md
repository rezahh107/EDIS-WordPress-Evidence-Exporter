# گزارش پیاده‌سازی EDIS 3.7.8

## 1. خلاصهٔ اجرایی

```text
source_version: 3.7.7
target_version: 3.7.8
public_schema_change: false
frozen_contract_change: false
repository_ready: true
production_release_status: BLOCKED_EXTERNAL_VALIDATION
```

- یافته‌های رفع‌شده: **13**
- یافته‌های تا حد امن کاهش‌یافته: **3**
- یافته‌های نادیده‌گرفته‌شده: **0**
- تست محلی: **88 پاس، 0 شکست، 0 ردشده**
- PHP lint: **155 فایل پاس**
- Runtime smoke با `E_ALL`: **PASS**
- asset gates: **PASS**
- deterministic install/source rebuild: **PASS**.

بهبودهای اصلی:

- نتیجهٔ self-test کامل storage به attestation نسخه‌دار، محیط‌مقید و integrity-protected تبدیل شد. در benchmark محلی، self-test زنده `132.744 ms` و بازیابی attestation `1.112 ms` بود.
- مسیر UI از preflight تکراری به proof کوتاه‌عمر HMAC، owner-bound، request-bound و source-bound منتقل شد.
- Snapshot و artifactها در یک ownership اجرایی دوباره parse نمی‌شوند؛ هر Collector فقط dependencyهای ثبت‌شدهٔ خود را دریافت می‌کند.
- ZIP با حفظ `EDIS-ZIP-1` مستقیم و atomic روی فایل نوشته می‌شود. benchmark مصنوعی 32 MiB پیک حافظهٔ PHP را به `35,655,680` بایت و RSS را به `71,728 KiB` محدود کرد.
- خواندن Bridge Context از ZIP به‌صورت seek-based انجام می‌شود و کل archive وارد RAM نمی‌شود.
- `ENTIRE_SITE` دیگر در صورت عبور از سقف به‌طور خاموش ناقص صادر نمی‌شود و با diagnostic صریح fail-closed می‌شود.
- endpoint اسناد، namespace هویت سند و test discovery اصلاح شدند.

محدودیت‌های باقی‌مانده:

- JobStore هنوز فایل‌محور است و query بین processها همچنان به اسکن directory نیاز دارد؛ cache request-scoped هزینهٔ parse تکراری را حذف کرده ولی index پایدار اضافه نشده است.
- Package validation هنوز مجموعهٔ JSONهای نهایی را در RAM نگه می‌دارد؛ رشتهٔ ZIP نهایی و envelope/artifact duplication حذف شده‌اند، اما assembly کاملاً file-backed نشده است.
- parser و چند utility عمومی همچنان recursion محدود و budgetدار دارند؛ traversalهای source پرکاربرد iterative شدند، ولی حذف کامل recursion نیازمند تغییر وسیع‌تر و fixtureهای عمقی است.
- Windows/LocalWP واقعی، WordPress/Multisite، Elementor runtime، Composer/PHPCS/PHPStan/Plugin Check و cross-product ingestion در این محیط اجرا نشده‌اند.
- strict SHA-only GitHub Actions gate به‌دلیل 8 rolling reference همچنان FAIL است.

## 2. نگاشت یافته به اصلاح

| Finding ID | وضعیت | علت ریشه‌ای | فایل‌های اصلی | پیاده‌سازی | اثر مورد انتظار |
|---|---|---|---|---|---|
| `EDIS-CONTRACT-001` | FIXED | پذیرش proof تک‌فرایندی در Local | `PrivateStorage.php`, `DegradedModeIntegration.php`, `Bootstrap.php` | فقط `state=PASS` و `multiprocess_lock_exclusion=PASS` پذیرفته می‌شود؛ نبود child-process proof fail-closed است. | حذف concurrency اثبات‌نشده و انطباق با قرارداد frozen. |
| `EDIS-DEFECT-001` | FIXED | نام متد و namespace نادرست | `DocumentController.php`, `DocumentQueryService.php`, `DocumentActions.php` | controller از `query()` استفاده می‌کند؛ alias سازگار `search()` حفظ شد؛ namespace صحیح شد. | بازیابی مسیر جست‌وجو و وضعیت اسناد. |
| `EDIS-PERF-001` | FIXED | self-test کامل در هر request | `PrivateStorage.php` | attestation 24 ساعته، version/runtime/path-bound و hash-protected؛ forced diagnostic همچنان probe کامل دارد. | کاهش self-test معمول از 132.744ms به 1.112ms در benchmark محلی. |
| `EDIS-PERF-002` | FIXED | preflight کامل دوباره در create | `PreflightProof.php`, `ExportJobService.php`, `ExportJobController.php`, `admin.js` | proof کوتاه‌عمر HMAC با owner/request/source binding؛ create فقط attestation و source drift را بازبینی می‌کند. | حذف process probe و parse کامل تکراری در مسیر UI. |
| `EDIS-PERF-003` | FIXED | verify/manifest/document تکراری در یک advance | `InputSnapshotStore.php`, `ExportJobService.php` | manifest/document cache با signature و force-verify در مرز resume؛ validation فقط یک‌بار در ownership lock. | کاهش I/O، parse و hashing تکراری. |
| `EDIS-PERF-004` | FIXED | بارگذاری همهٔ artifactها برای هر component | `ArtifactStore.php`, `ExportJobService.php`, `ExportService.php` | artifact catalog request-scoped؛ dependency-only input؛ package assembly در ترتیب توپولوژیک و یک‌بار برای هر artifact. | حذف replay و parse کل prefix در هر component. |
| `EDIS-PERF-005` | PARTIALLY FIXED | ساخت ZIP و چند نمایش کامل هم‌زمان | `DeterministicZipWriter.php`, `ExportFileStore.php`, `ExportService.php` | streaming ZIP atomic، حذف رشتهٔ ZIP نهایی و کاهش envelope/artifact duplication. | کاهش محسوس peak RAM؛ validation JSON همچنان in-memory است. |
| `EDIS-PERF-006` | FIXED | `file_get_contents` کل ZIP برای یک entry | `DeterministicZipReader.php` | EOCD/central directory seek و خواندن bounded local entry. | RAM متناسب با entry/central directory، نه کل archive. |
| `EDIS-PERF-007` | FIXED | `substr` باقی‌مانده و reread کامل | `DeterministicFilesystem.php` | chunked write، stream writer، incremental metadata و `hash_file` نهایی. | حذف کپی‌های بزرگ و بازخوانی string-based. |
| `EDIS-SCAL-001` | FIXED | سقف خاموش `ENTIRE_SITE` | `ExportJobService.php` | inventory صفحه‌بندی‌شده تا `limit+1` و blocker `EDIS_ENTIRE_SITE_SCOPE_TRUNCATED`. | عدم تولید export ناقص با برچسب Entire Site. |
| `EDIS-PERF-008` | FIXED | canonicalization raw source داخل JSON ثانویه | `InputSnapshotStore.php` | drift identity از raw hash + canonical source hash + metadata hash ساخته می‌شود. | حذف escaping/serialization کامل ثانویه. |
| `EDIS-PERF-009` | PARTIALLY FIXED | اسکن همهٔ فایل‌های JobStore | `JobStore.php` | cache request-scoped بر پایهٔ mtime/size و invalidation در save/remove/cleanup. | حذف read/decode تکراری در scanهای همان request؛ glob بین processها باقی است. |
| `EDIS-TEST-001` | FIXED | discovery وابسته به namespace بدون reconciliation | `tests/run-local.php` و سه Test | همهٔ testها namespace استاندارد دارند؛ هر فایل کشف‌نشده Gate را fail می‌کند. | 88 تست واقعی در برابر 80 تست قبلی. |
| `EDIS-RISK-001` | FIXED | دو parse برای hash و architecture | `DocumentIdentity.php`, `DocumentQueryService.php` | `inspectSource()` یک parse مشترک تولید می‌کند و traversal معماری iterative است. | کاهش parse و traversal جست‌وجوی سند. |
| `EDIS-RISK-002` | FIXED | canonical hash کامل در هر admin row | `DocumentActions.php` | eligibility با `metadata_exists` و freshness با raw hash ذخیره‌شده؛ fallback قدیمی حفظ شد. | کاهش هزینهٔ list table. |
| `EDIS-RISK-003` | PARTIALLY FIXED | traversalهای recursive روی source عمیق | `ExportJobService.php`, `DocumentQueryService.php` | traversalهای preflight، occurrence و architecture iterative شدند؛ parser/bounded utilities باقی‌اند. | کاهش فشار stack در مسیرهای پرکاربرد؛ ریسک عمومی کاملاً حذف نشده است. |

موارد DOM، `getComputedStyle`، geometry، IndexedDB و UI rendering runtime برای این مخزن `not_applicable` هستند؛ این محصول WordPress Evidence Exporter است و Browser Runtime Collector در این repository وجود ندارد.

## 3. تغییرات کامل مخزن

### فایل‌های اصلاح‌شده (54)

- `.distignore`
- `CHANGELOG.md`
- `Help.md`
- `Help_FA.md`
- `README.md`
- `SECURITY.md`
- `assets/js/admin.js`
- `composer.json`
- `config/critical-files.json`
- `docs/architecture.md`
- `docs/collector-encyclopedia-fa.md`
- `docs/collector-encyclopedia.md`
- `docs/export-workflow.md`
- `docs/privacy.md`
- `docs/quality-gates.md`
- `docs/troubleshooting.md`
- `edis-evidence-exporter.php`
- `languages/edis-evidence-exporter-fa_IR.po`
- `languages/edis-evidence-exporter.pot`
- `package-lock.json`
- `package.json`
- `plugin.manifest.json`
- `readme.txt`
- `schemas/data-dictionary.json`
- `src/Admin/DocumentActions.php`
- `src/Application/DocumentQueryService.php`
- `src/Application/ExportJobService.php`
- `src/Application/ExportService.php`
- `src/Bootstrap.php`
- `src/Infrastructure/Support/ArtifactStore.php`
- `src/Infrastructure/Support/DeterministicFilesystem.php`
- `src/Infrastructure/Support/DeterministicZipReader.php`
- `src/Infrastructure/Support/DeterministicZipWriter.php`
- `src/Infrastructure/Support/DocumentIdentity.php`
- `src/Infrastructure/Support/ExportFileStore.php`
- `src/Infrastructure/Support/InputSnapshotStore.php`
- `src/Infrastructure/Support/JobStore.php`
- `src/Infrastructure/Support/PrivateStorage.php`
- `src/Rest/DocumentController.php`
- `src/Rest/ExportJobController.php`
- `src/WordPress/DegradedModeIntegration.php`
- `templates/admin/help.php`
- `tests/Unit/ArtifactHashSeparationTest.php`
- `tests/Unit/DeterministicZipWriterTest.php`
- `tests/Unit/DocumentActionsTest.php`
- `tests/Unit/ElementorInspectorContractTest.php`
- `tests/Unit/ExecutionPlanTest.php`
- `tests/Unit/ExportJobIntegrityTest.php`
- `tests/Unit/InstallationIntegrityTest.php`
- `tests/Unit/PackageManifestSchemaTest.php`
- `tests/Unit/PrivateStorageTest.php`
- `tests/Unit/SupplyChainGateContractTest.php`
- `tests/Unit/ZLocalStorageConvenienceTest.php`
- `tests/run-local.php`

### فایل‌های جدید (6)

- `docs/IMPLEMENTATION-REPORT-3.7.8-FA.md`
- `docs/MIGRATION-V3.7.7-TO-V3.7.8.md`
- `src/Infrastructure/Support/PreflightProof.php`
- `tests/Unit/PreflightProofTest.php`
- `tools/benchmarks/performance-smoke.php`
- `tools/release/build-release.py`

### فایل‌های حذف‌شده (0)

- هیچ‌کدام

### تغییرات پیکربندی و مهاجرت

- نسخهٔ افزونه، manifest، package metadata، i18n و documentation به `3.7.8` ارتقا یافت.
- Schemaهای عمومی، Bundle `3.3.0`، Shared Envelope `2.0.0`، Package Manifest `2.1.0`، Selection Snapshot `1.2.0`، `EDIS-CJ-2` و `EDIS-ZIP-1` تغییر نکردند.
- Job format عمومی `2.1.0` باقی ماند، ولی `implementation_version=3.7.8` مانع Resume خاموش jobهای قدیمی می‌شود.
- راهنمای مهاجرت: `docs/MIGRATION-V3.7.7-TO-V3.7.8.md`.
- overlay install مجاز نیست؛ پوشهٔ قبلی باید کامل جایگزین شود.

## 4. به‌روزرسانی اعتبارسنجی

### تست‌های جدید یا گسترش‌یافته

- `PreflightProofTest`: owner/request/source binding و tamper rejection.
- `PrivateStorageTest`: reuse واقعی storage attestation.
- `DeterministicZipWriterTest`: برابری byte-for-byte خروجی streaming و API سازگار قبلی.
- test discovery: fail روی هر فایل `*Test.php` کشف‌نشده.
- تست‌های موجود lock، tamper، snapshot، resume و ZIP reader دوباره اجرا شدند.

### نتایج اجراشده

```text
php tests/run-local.php                         PASS — 88/88
PHP 8.4 lint                                   PASS — 155 files
php -d error_reporting=E_ALL tests/runtime...  PASS
npm ci                                         PASS — 0 vulnerabilities
npm run quality:assets                         PASS
npm run lint:workflows:strict                  FAIL — 8 rolling refs
extracted install integrity                    PASS
storage benchmark                              PASS — live 132.744ms, cached 1.112ms
ZIP 32 MiB benchmark                           PASS — peak PHP 35,655,680 bytes; RSS 71,728 KiB
```

### مراحل پیشنهادی release validation

1. `composer validate --strict`, `composer install`, `composer audit --locked` پس از تولید و review کردن `composer.lock`.
2. PHPUnit رسمی، سه ruleset PHPCS/WPCS/PHPCompatibilityWP و PHPStan.
3. PHP 8.2، 8.3، 8.4 و 8.5.
4. WordPress 6.5.8 و 7.0، Multisite و Plugin Check.
5. Elementor 4.1.3 و Editor E2E.
6. Windows/LocalWP واقعی شامل `php-cgi.exe → php.exe`, junction, UNC و Defender/CFA.
7. Browser/Python cross-product vectors.
8. pin کردن GitHub Actions به SHAهای ممیزی‌شده.

## 5. ریسک‌های باقی‌مانده

### JobStore بدون index پایدار

- دلیل: افزودن index persisted بدون migration و crash-consistency contract، تغییر معماری پرریسک بود.
- اثر: request جدید همچنان برای queryهای global فایل‌های Job را stat می‌کند.
- رفع آینده: index نسخه‌دار، atomic و reconstructable یا sharding تاریخ‌محور همراه fault-injection.

### Package file set در حافظه

- دلیل: Schema/package validation فعلی API مبتنی بر `array<string,string>` دارد.
- اثر: peak RAM هنوز با مجموع JSONهای package رشد می‌کند، هرچند ZIP duplication حذف شده است.
- رفع آینده: file-backed `PackageEntry` catalog و validator streaming/versioned.

### recursion محدود در parser و utilityها

- دلیل: تبدیل کامل parser به iterative machine یک بازنویسی contract-sensitive است.
- اثر: input بسیار عمیق می‌تواند CPU/stack را تحت فشار قرار دهد؛ depth/node budgets همچنان fail-closed هستند.
- رفع آینده: fixtureهای عمق 128/256/512 و parser iterative در یک تغییر جداگانه.

### Gateهای محیطی و supply-chain

- دلیل: ابزارها و runtimeهای لازم در محیط جاری موجود نبودند و action SHAهای دقیق نباید حدس زده شوند.
- اثر: نسخه آمادهٔ validation است ولی production-ready تأییدشده نیست.
- رفع آینده: اجرای ماتریس رسمی و ثبت command/output/SHA؛ pin فقط به SHAهای مستند و ممیزی‌شده.
