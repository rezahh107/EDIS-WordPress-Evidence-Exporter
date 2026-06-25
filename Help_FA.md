# راهنمای کامل EDIS Evidence Exporter

## ۱. هدف افزونه

EDIS برای یک زنجیره تحلیل دترمنیستیک خوراک منبع آماده می‌کند. افزونه وردپرس واقعیت‌های ذخیره‌شده WordPress و Elementor را صادر می‌کند. افزونه مرورگر واقعیت‌های رندرشده را می‌گیرد. Python هر دو بسته را اعتبارسنجی و به هم متصل می‌کند، مقدار مؤثر را Resolve می‌کند و Ruleهای نسخه‌بندی‌شده را اجرا می‌کند. مدل زبانی فقط نتیجه Python را توضیح می‌دهد.

این افزونه درباره خوب یا بد بودن طراحی تصمیم نمی‌گیرد.

## ۲. شروع کار

1. وارد **EDIS Evidence → Diagnostics** شوید.
2. سالم‌بودن Manifest، Registry، محل ذخیره Job، Artifact و Bundle، JSON، REST و ZIP را بررسی کنید.
3. دکمه **Run safe worker test** را بزنید. این دکمه یک مسیر واقعی و کوچک Export را اجرا می‌کند.
4. وارد **Create Export** شوید.
5. حالت حریم خصوصی را انتخاب کنید.
6. Source Componentها را برگزینید.
7. اسناد Elementor مربوط به تحلیل را جست‌وجو و انتخاب کنید.
8. گزینه‌های مؤثر را مرور و Export را شروع کنید.
9. تا پایان کار، صفحه را باز نگه دارید تا REST Worker احراز هویت‌شده Job را جلو ببرد.
10. پس از رسیدن Validation به `PASS` فایل ZIP را دانلود کنید.

## ۳. چرا Export دیگر به WP-Cron وابسته نیست؟

در نسخه 3.1 ممکن بود با غیرفعال‌بودن WP-Cron، Job برای همیشه در حالت زیر بماند:

```text
queued / initializing / 0%
```

نسخه 3.2 این نقص معماری را اصلاح کرده است. صفحه مدیریت ابتدا Job را می‌سازد و سپس درخواست‌های زیر را ارسال می‌کند:

```text
POST /export-jobs
POST /export-jobs/{job_id}/advance
```

هر درخواست Advance در یک بازه زمانی محدود چند مرحله امن را اجرا می‌کند، Cursor را ذخیره می‌کند، Heartbeat را به‌روز می‌کند و Progress واقعی را برمی‌گرداند. WP-Cron فقط برای Recovery است و Worker اصلی محسوب نمی‌شود. بنابراین غیرفعال‌بودن WP-Cron به‌تنهایی نباید Export نسخه 3.2 را متوقف کند.

## ۴. وضعیت Job و بازیابی

فیلدهای مهم Job:

```text
status
phase
progress
revision
cursor
current_component
last_heartbeat
last_successful_step_at
attempt_count
last_error_code
next_retry_at
schedule_state
schedule_error
job_format_version
input_snapshot_format_version
input_snapshot_sha256
completed_step_records
```

عملیات موجود:

- **Resume:** ادامه از آخرین مرحله Commitشده.
- **Retry:** پاک‌کردن خطای قابل‌بازیابی و اجرای دوباره Worker.
- **Cancel:** توقف پردازش آینده بدون ادعای ساخت ZIP.
- **Safe worker test:** اجرای واقعی یک مسیر کوچک Collection و Packaging.

Lock اختصاصی Job مانع اجرای هم‌زمان دو Worker می‌شود. در نسخه 3.7.1 ادامه فقط در مرز مرحله‌ای مجاز است که Snapshot، ورودی مرحله و SHA-256 فایل Artifact آن دوباره تأیید شود و Componentهای کامل‌شده دقیقاً Prefix برنامه اجرا باشند.

## ۵. حالت‌های حریم خصوصی

### Strict

کمترین افشای Source را دارد. حتی اگر گزینه سند خام انتخاب شده باشد، Original Elementor Document به‌اجبار حذف می‌شود.

### Standard

برای بیشتر تحلیل‌های محلی و کنترل‌شده مناسب است. شواهد و Indexهای لازم را بدون گسترش Diagnostic صادر می‌کند.

### Diagnostic

فقط با مجوز استفاده شود. ممکن است فراداده عملیاتی بیشتری برای عیب‌یابی صادر کند و در UI نیازمند تأیید صریح است.

## ۶. انواع Component

### Source Collector

داده واقعی ذخیره‌شده را از WordPress یا Elementor می‌خواند؛ مانند Breakpoint، Active Kit، Variables، Global Classes و سند ذخیره‌شده.

### Index Builder

از Artifactهای منبع، یک Index سبک و دترمنیستیک می‌سازد؛ مانند Element Structure Index یا Responsive Declaration Index. Index منبع حقیقت جدیدی نیست.

### Bundle Processor

روی مجموعه Export کار می‌کند؛ مانند Source Coverage، Bridge Context، Diagnostics، تخمین اندازه و Validation.

به همین دلیل یک Index پیاده‌سازی‌نشده دیگر به‌اشتباه «Elementor Collector پشتیبانی‌نشده» نمایش داده نمی‌شود.

## ۷. تفاوت Source Truth و Source Availability

این دو مفهوم مستقل هستند.

### Source Truth State

- `VERIFIED`: قرارداد و پیاده‌سازی منبع با شواهد فعلی تأیید شده است.
- `PARTIAL`: داده واقعی است، اما بخشی از معنا به نسخه، Fixture یا API داخلی وابسته است.
- `UNKNOWN`: منبع با اطمینان کافی قابل اثبات نیست.
- `UNSUPPORTED`: در محیط فعلی قرارداد معتبر منبع وجود ندارد.

### Source Availability

- `AVAILABLE`: داده درخواست‌شده جمع‌آوری شده است.
- `PARTIAL`: داده معتبر وجود دارد ولی بخشی از پوشش محدود است.
- `INSUFFICIENT`: داده هست اما حداقل قرارداد لازم را تأمین نمی‌کند.
- `DISABLED`: کاربر یا تنظیمات آن را غیرفعال کرده است.
- `UNAVAILABLE`: محیط امکان ارائه داده را نداشته است.
- `NOT_APPLICABLE`: این داده برای سند یا محیط حاضر معنا ندارد.
- `ERROR`: Collection شکست خورده است.

مثلاً `PARTIAL + AVAILABLE` یعنی داده واقعی موجود است، ولی هنوز نباید معنای آن را در همه نسخه‌های Elementor قطعی دانست.

## ۸. مثال آموزشی Breakpoint المنتور

Breakpoint یک مرز عرض صفحه است که Elementor می‌تواند برای آن مقدار جداگانه ذخیره کند. این داده امتیاز کیفیت Responsive نیست.

EDIS در صورت دسترسی، شناسه فنی، عنوان، مقدار، واحد، ترتیب Manager و Provenance را صادر می‌کند. اگر API عمومی Manager مقدار `active` یا `direction` را ندهد، این فیلدها `null` و وضعیت آن‌ها صریحاً `UNVERIFIED` باقی می‌ماند؛ جهت از نام‌هایی مانند `widescreen` حدس زده نمی‌شود. Python از شواهد Registry استفاده می‌کند تا:

- پسوندهایی مانند `_tablet` و `_mobile` را به تنظیم واقعی همان سایت وصل کند؛
- مقدارهای گمشده را بدون Hardcode کردن عرض‌های عمومی Resolve کند؛
- Snapshot مرورگر را با Source Evidence هماهنگ کند؛
- در نبود Viewport لازم، از تولید نتیجه قطعی خودداری کند.

وجود Breakpoint ثابت نمی‌کند صفحه در عمل Responsive یا قابل استفاده است. برای این نتیجه به شواهد Runtime مرورگر نیاز است.

## ۹. Saved Value و Effective Value

افزونه وردپرس مقدار ذخیره‌شده و Reference را صادر می‌کند. اگر Mobile مقدار ندارد، افزونه نباید بی‌صدا مقدار Desktop را جای آن بنویسد.

Python بعداً نتیجه‌ای با Provenance روشن می‌سازد:

```text
مقدار ذخیره‌شده Mobile: وجود ندارد
مقدار مؤثر Mobile: 40px
منبع ارث‌بری: Desktop
```

این جداسازی مانع پنهان‌شدن استنتاج داخل Collector می‌شود.

## ۱۰. Bridge Context

فایل `bridge/source-context.json` حداقل اطلاعات Source لازم برای Browser Runtime Collector 1.4.0 را دارد:

- Analysis Set و Bundle ID؛
- Source Export Root Hash؛
- Site و Document Fingerprint؛
- Locator Candidateهای Privacy-safe؛
- Document ID به‌صورت String؛
- Source Element Key؛
- Elementor Element ID واقعی؛
- شواهد Duplicate ID؛
- Source Path و Ancestor Chain؛
- Source Truth و Availability.

افزونه مرورگر این فایل را فقط با انتخاب صریح کاربر و به‌صورت محلی Import می‌کند. هیچ ارتباط شبکه‌ای میان دو افزونه ایجاد نمی‌شود. Binding مرورگر اولیه است و Correlation نهایی متعلق به Python است.

## ۱۱. ساختار ZIP

یک بسته کامل می‌تواند این ساختار را داشته باشد:

```text
package-manifest.json
checksums.sha256
bridge/source-context.json
environment/
sources/
indexes/
coverage/source-coverage.json
provenance/provenance.json
diagnostics/diagnostics.json
validation/package-validation.json
schemas/
```

فایل اختیاری خالی فقط برای کامل نشان‌دادن بسته ساخته نمی‌شود.

## ۱۲. Diagnostics چه چیزهایی را بررسی می‌کند؟

- سازگاری WordPress و PHP؛
- بارگذاری Manifest و Registry؛
- قابل‌نوشتن‌بودن Job، Artifact، Immutable Input Snapshot و Bundle Storage؛
- ZIP و JSON؛
- REST API؛
- حالت Executor؛
- وضعیت Recovery با WP-Cron؛
- Jobهای Stale؛
- Cleanup؛
- آخرین Heartbeat و خطای Scheduling.

گزارش قابل کپی Privacy-safe است و محتوای سند را در خود ندارد.

## ۱۳. عیب‌یابی Export

### Job روی صفر می‌ماند

REST، Nonce و Session مدیریت را بررسی کنید. صفحه را Refresh و Resume را اجرا کنید. در نسخه 3.4 صرفاً غیرفعال‌بودن WP-Cron نباید Worker را متوقف کند.

### ساخت ZIP در دسترس نیست

نسخه 3.7.1 برای ساخت Bundle از نویسنده ZIP قطعی داخلی با روش STORE استفاده می‌کند و به PHP ZIP Extension نیاز ندارد. در صورت مسدودشدن ساخت ZIP، Diagnostics مربوط به Filesystem، CRC32B، محدودیت Path و Private Storage را بررسی کنید. EDIS بدون بسته کامل و Validateشده موفقیت گزارش نمی‌کند.

### جست‌وجوی سند نتیجه ندارد

بررسی کنید سند داده Elementor داشته باشد، مدیر فعلی اجازه ویرایش آن را داشته باشد و Status یا Type آن در Query محدودشده قرار بگیرد.

### Component داده ندارد

در Data Sources هر دو ستون Source Truth و Source Availability را بخوانید. نبود یک قابلیت در نسخه فعال Elementor ممکن است `NOT_APPLICABLE` یا `UNAVAILABLE` باشد و الزاماً خطای افزونه نیست.

### Job قدیمی یا Stale شده است

Resume فقط برای Job فرمت جاری مجاز است که Snapshot و Artifactهای کامل‌شده آن سالم باشند. Worker از Cursor آخرین مرحله تأییدشده ادامه می‌دهد و Heartbeat جدید ثبت می‌کند.

### فرمت Job ناسازگار است

Job ساخته‌شده با 3.6.2 یا قبل‌تر در 3.7.1 بی‌صدا Resume نمی‌شود. Export تازه بسازید تا Snapshot تغییرناپذیر و Step Recordهای نسخه جاری تولید شوند. Bundle کامل و Validateشده قبلی یک Artifact تاریخی است و بازنویسی نمی‌شود.

### Source هنگام Snapshot تغییر کرده است

کد `EDIS_SOURCE_CHANGED_DURING_SNAPSHOT` یعنی Saved Source بین دو خواندن محدود مرحله Capture تغییر کرده است. Editor را Save کنید، ویرایش هم‌زمان را متوقف کنید و Job جدید بسازید. Job دارای Source ترکیبی ادامه داده نمی‌شود.

### Artifact مرحله Resume تطابق ندارد

کد `EDIS_RESUME_ARTIFACT_MISMATCH` یعنی Artifact کامل‌شده حذف یا دستکاری شده، یا SHA-256/قرارداد اجرای ثبت‌شده آن دیگر برابر نیست. Job Fail-closed می‌شود. فایل‌ها را دستی جایگزین نکنید؛ Storage را بررسی و Export تازه ایجاد کنید.

## ۱۴. دانشنامه اجزا

Help داخلی و دو فایل زیر برای همه Source Collectorها، Index Builderها و Bundle Processorها توضیح هم‌سطح انگلیسی و فارسی دارند:

```text
docs/collector-encyclopedia.md
docs/collector-encyclopedia-fa.md
```

هر مدخل توضیح می‌دهد داده چیست، چگونه خوانده می‌شود، چه فیلدهایی دارد، Python چه استفاده‌ای می‌کند، چه چیزی به LLM می‌رسد، چه نتیجه‌ای مجاز نیست، محدودیت نسخه و حریم خصوصی چیست و چگونه عیب‌یابی شود.

## ۱۵. محدودیت اعتبارسنجی

Package Validation سازگاری داخلی مسیرها، Hashها، JSONها، Artifactهای اعلام‌شده و ساختار بسته را بررسی می‌کند. این Validation ثابت نمی‌کند هر Addon المنتور برای همیشه یک Storage Contract مستند و ثابت دارد. Componentهایی که به API داخلی یا Fixture محدود وابسته‌اند، تا زمان اثبات بیشتر به‌صورت `PARTIAL` باقی می‌مانند.

## ۱۶. کنترل‌های ایمنی شواهد در نسخه ۳.۶.۰

### Selection Snapshot

هر Export سندمحور فایل `selection/selection-snapshot.json` دارد. این فایل Export Scope، Dependency Scope، شناسه سندهای انتخاب‌شده، نسخه انتخاب، Hash Canonical منبع انتخابی و دلیل ورود هر سند یا Artifact مشترک را ثبت می‌کند. Python با آن می‌تواند ثابت کند Bundle مربوط به کدام نسخه ذخیره‌شده Source بوده است.

### Evidence Conservation

فایل `validation/evidence-conservation.json` تعداد داده‌های Raw Source را با Indexهای دترمینیستیک مقایسه می‌کند. Elementهای انتخاب‌شده، Responsive declarationهای Legacy، Variantها و Propertyهای Atomic و Class bindingها بررسی می‌شوند. اختلاف با کد `EDIS_EVIDENCE_LOSS_DETECTED` گزارش می‌شود و به‌صورت خاموش نتیجه خالیِ موفق تلقی نمی‌شود.

### سه بُعد مستقل Validation

رابط و Artifact اعتبارسنجی این سه نتیجه را جدا نگه می‌دارند:

```text
package_integrity
contract_validation
analysis_readiness
```

ممکن است ZIP از نظر ساختاری سالم باشد ولی برای بعضی تحلیل‌ها شواهد کافی نداشته باشد. یک PASS عمومی این تفاوت را پنهان نمی‌کند.

### Unknown Structure Ledger

فایل `diagnostics/unknown-structures.json` مسیرهای محدودی را ثبت می‌کند که در Raw Document حفظ شده‌اند ولی هنوز در Indexها مدل نشده‌اند. فیلدهای ناشناخته Elementor یا Addon تا حد امن حفظ می‌شوند و حذف یا تفسیر حدسی نمی‌شوند.

### جداسازی سخت‌گیرانه یک سند

در حالت `SINGLE_DOCUMENT + REQUIRED_DEPENDENCIES` فقط رکوردهای سند انتخاب‌شده و Context ضروری Site/Kit صادر می‌شود. عنوان و Source Tree سندهای نامرتبط وارد Bundle نمی‌شود. Full Site Context فقط زمانی استفاده شود که تحلیل واقعاً Inventory کامل را لازم دارد.

### آمادگی Bridge و دانلود مستقیم

Job کامل‌شده وضعیت آماده‌بودن Browser Bridge Context را نشان می‌دهد. گزینه **دانلود Browser Bridge Context** دقیقاً همان فایل Validateشده `bridge/source-context.json` داخل Bundle را تحویل می‌دهد و نسخه دوم یا متفاوتی از Context نمی‌سازد.

### مقایسه با Source قبلی

در صورت فعال‌بودن، `comparison/previous-export-diff.json` تغییرات محدود و دترمینیستیک Source را نسبت به آخرین Export ذخیره‌شده EDIS برای همان سند گزارش می‌کند. این فایل فقط Diff منبع است و درباره کیفیت UX تصمیم نمی‌گیرد.

### حالت ساخت Fixture واقعی

Fixture Mode فایل `fixture/fixture-metadata.json` را با واقعیت‌های محیط، شناسه‌های Source انتخاب‌شده، وضعیت Verification و قالب Expected Behavior تولید می‌کند. Fixture مصنوعی یا Real Fixture تأییدنشده هرگز به‌عنوان Fixture واقعیِ تأییدشده Elementor معرفی نمی‌شود.

### پیش‌نمایش حریم خصوصی

Preflight مشخص می‌کند متن اصلی، URLهای Media، عنوان سندهای دیگر و Metadata تشخیصی محیط احتمالاً وارد بسته می‌شوند یا نه. برای کمترین افشا از Strict و برای عیب‌یابی فقط در محیط کنترل‌شده از Diagnostic استفاده کنید.


## ۱۷. کنترل‌های پایداری نسخه ۳.۷.۰

### Snapshot تغییرناپذیر ورودی سند

هنگام ساخت Job، Source سندهای انتخاب‌شده پیش از شروع Collectorها در Storage خصوصی محافظت‌شده Capture می‌شود. Snapshot شامل بایت‌های دقیق Saved Source، فراداده محدود سند، Manifest، Hash هر فایل و Hash معنایی Snapshot است. پس از Commit، Collector سند به‌جای خواندن دوباره `_elementor_data` زنده فقط از همین Snapshot می‌خواند.

این Snapshot فقط Source سندهای انتخاب‌شده و فراداده محدود همان Collector را Freeze می‌کند. Registryهای دیگر سایت Collector مستقل دارند و تا وقتی Artifact خودشان چنین ادعایی نکرده، بخشی از Snapshot ثابت سند محسوب نمی‌شوند.

### Gate تغییر Source

هر Source هنگام Capture محدود دو بار خوانده می‌شود. اگر Capture Record یکسان نباشد، Snapshot موقت حذف و Job با `EDIS_SOURCE_CHANGED_DURING_SNAPSHOT` مسدود می‌شود. در نتیجه Selection مربوط به یک Revision ذخیره‌شده روی Revision دیگری Project نمی‌شود.

### قرارداد یکپارچگی Resume

فرمت Job `2.1.0` برای هر Component کامل‌شده این اطلاعات را ثبت می‌کند:

```text
component_id
component_schema_version
implementation_version
input_snapshot_sha256
step_input_sha256
artifact_file_sha256
```

در صورت قدیمی‌بودن فرمت Job، نبود یا تغییر Snapshot، Prefix نبودن مراحل کامل‌شده، تغییر نسخه Schema/Implementation، تغییر ورودی مرحله یا اختلاف Hash فایل Artifact، Resume رد می‌شود. این موارد خطای Integrity هستند و Retry موقت محسوب نمی‌شوند.

### انضباط شواهد Breakpoint

تشخیص پسوند Responsive قدیمی فقط از IDهایی استفاده می‌کند که واقعاً از Breakpoint Manager المنتور Export شده‌اند. شناخته‌بودن نام یک پسوند به معنای وجود آن در سایت نیست. Active State و Direction نیز از نام ID حدس زده نمی‌شوند. Resolution نهایی پس از Validation Registry و شواهد Runtime لازم در Python انجام می‌شود.

### Retention و Cleanup

Snapshotها زیر همان سیاست Private Storage مربوط به Job و Artifact نگهداری می‌شوند و Cleanup زمان‌بندی‌شده Snapshotهای منقضی را حذف می‌کند. بررسی Symlink و Managed Path همچنان Fail-closed است. Job لغوشده یا ناقص، Snapshot یا Artifact نیمه‌کاره را به Bundle قابل دانلود تبدیل نمی‌کند.

### رفتار Upgrade

Job ناقص ساخته‌شده با 3.6.2 یا قبل‌تر باید پس از Upgrade دوباره ساخته شود. Bundle کامل‌شده قبلی تغییر داده نمی‌شود. نسخه 3.7.1، WordPress Bundle Schema را از `3.2.0` به `3.3.0` ارتقا می‌دهد؛ Selection Snapshot Schema همچنان `1.2.0` است. Browser و Python باید Bundle Schema `3.3.0` را صریحاً Route کنند و تا پیش از آن، سازگاری Cross-product در وضعیت `insufficient_evidence` باقی می‌ماند.

## ۱۸. قرارداد دترمینیسم نسخه ۳.۷.۰

### JSON ذخیره‌شده بدون اتلاف

JSON سندهای انتخاب‌شده پیش از تبدیل به Arrayهای PHP با Parser نوع‌دار خوانده می‌شود. تفاوت Object و Array، کلیدهای عددنما و معنای دقیق عدد اعشاری حفظ می‌شود. UTF-8 نامعتبر، کلید تکراری و عدد خارج از Budget قطعی با Diagnostic صریح Job را متوقف می‌کند و هیچ ترمیم خاموشی انجام نمی‌شود.

### ترتیب EDIS-CJ-2

کلیدهای Object بر اساس بایت‌های دقیق UTF-8 مرتب می‌شوند. Unicode Normalization برابر `NONE` است؛ بنابراین متن composed و decomposed دو Evidence متفاوت باقی می‌مانند. PHP، JavaScript مرورگر و Python باید Vectorهای یکسان را پاس کنند تا سازگاری هماهنگ اعلام شود.

### Hash معنایی و Hash نمونه Artifact

هر Artifact نهایی دو Hash دارد. `semantic_payload_sha256` فیلدهای Operational تعریف‌شده در Policy نسخه `1.0.0` را کنار می‌گذارد. `artifact_instance_sha256` تمام Envelope به‌جز فیلد خودارجاع Instance Hash را پوشش می‌دهد. Manifest بسته نیز Hash دقیق فایل را ثبت می‌کند. برابر بودن Hash معنایی، اختلاف Instance یا فایل را مجاز نمی‌کند.

### ZIP قطعی

پروفایل EDIS-ZIP-1 از روش STORE و بدون Compression استفاده می‌کند تا نتیجه به نسخه zlib/libzip وابسته نباشد. ترتیب Path، Timestamp، Attribute، Flag و Comment ثابت است. ZIP64 عمداً پشتیبانی نمی‌شود و عبور از Limit به‌جای ساخت فرمت متفاوت با Diagnostic متوقف می‌شود.

### Storage پایدار و Lock فایل

نوشتن‌های حیاتی شامل حلقه کامل Write، `fflush`، `fsync`، Permission، Rename اتمیک و بررسی نهایی SHA-256 است. Self-test داخل بسته نصب فقط انحصاری‌بودن Lock میان دو Handle محلی را بدون اجرای Process جدید بررسی می‌کند. انحصاری‌بودن میان دو Process مستقل در Gate تست سورس/CI اجرا می‌شود. Storage اشتراکی، NFS یا Cluster همچنان به آزمون هم‌زمانی واقعی همان محیط نیاز دارد؛ نتیجه Local به‌تنهایی برای Storage توزیع‌شده کافی نیست.

### Upgrade و سازگاری کراس‌پروداکت

Jobهای دارای Job Format قدیمی‌تر از `2.1.0` یا Input Snapshot Format قدیمی‌تر از `2.0.0` باید دوباره ساخته شوند. ZIPهای کامل تاریخی تغییر نمی‌کنند. Bundle Schema `3.3.0`، Shared Envelope `2.0.0`، EDIS-CJ-2 و دو Hash باید در Browser و Python صریحاً Route شوند؛ تا قبل از عبور هر دو محصول از Vectorهای مشترک، وضعیت هماهنگ `insufficient_evidence` است.

### Verification توسعه

بسته سورس Workflowهای PHP 8.2 تا 8.5، PHPUnit، WordPress Coding Standards امنیت‌محور، PHPCompatibilityWP، Smoke Test با E_ALL و WordPress Plugin Check را دارد. وجود Workflow به معنای PASS نیست؛ نتیجه واقعی CI و گزارش Verification انتشار را بررسی کنید.


Package Manifest یک `semantic_identity` صریح بر پایه Source Export Root و سیاست نسخه‌بندی‌شده بسته‌بندی دارد؛ فهرست فایل‌ها و شناسه‌های هر اجرا فقط Evidence سطح Instance هستند.



## اجرای وردپرس و عملیات نسخه 3.7.1

### Site Health

از مسیر **ابزارها ← سلامت سایت** وضعیت Runtime قطعی EDIS، زمان‌بندی بازیابی و سلامت Private Storage را بررسی کنید. تست Storage عمداً Async است، چون Durable Write، Atomic Replace و انحصاری‌بودن Lock میان Handleهای محلی را آزمایش می‌کند. آزمون بین‌پردازه‌ای در Source/CI انجام می‌شود و کد نصب‌شونده Process جدید اجرا نمی‌کند. نتیجه Critical ساخت Export را متوقف می‌کند.

### فرمان‌های WP-CLI

```bash
wp edis status
wp edis worker status
wp edis worker run
wp edis jobs repair
wp edis jobs repair --apply
wp edis storage self-test
```

فرمان Repair بدون `--apply` فقط Dry Run است. این فرمان Lease فعال، نسخه Schema، Hash Snapshot تغییرناپذیر یا کنترل Stepهای کامل‌شده را دور نمی‌زند.

### Multisite

Network Activation همه Siteهای موجود و Siteهای تازه‌ساخته‌شده را مقداردهی می‌کند. Capability، تنظیمات، Cron و فضای Private هر Site مستقل است. Job، Token و Bundle بین Siteها مشترک نیست. اگر Preflight یکی از Siteها هنگام Network Activation شکست بخورد، تغییر Siteهای قبلی همان تلاش Rollback می‌شود.

### Deactivation و Uninstall

Deactivation ساخت Job جدید و Eventهای زمان‌بندی‌شده را متوقف می‌کند، اما تنظیمات و Evidence را نگه می‌دارد. Uninstall مستقل است. گزینه **نگه‌داری Evidence هنگام حذف افزونه** تعیین می‌کند داده خصوصی باقی بماند یا حذف شود. در حالت حذف، Optionها، Capability، Jobها، Snapshotها، Artifactها و Bundleهای همان Site پاک می‌شوند.

### ابزارهای Privacy

Personal Data Export فقط رکورد عملیاتی محدود Job را برمی‌گرداند. Personal Data Erase و حذف User، Jobها و فایل‌های خصوصی همان User را در Site جاری حذف می‌کند. Saved Source به‌عنوان آیتم Privacy Export افشا نمی‌شود.

### بارگذاری شرطی

در درخواست عادی Frontend، Registry، Storeها، REST Controllerها و Worker ساخته نمی‌شوند. Runtime برنامه فقط در Admin، REST، Cron، AJAX یا WP-CLI بارگذاری می‌شود.

### نسخه خصوصی Job

نسخه 3.7.1 از Job Format `2.1.0` و Input Snapshot Format `2.0.0` استفاده می‌کند. Job ناقص 3.7.0 با Job Format `2.0.0` باید دوباره ساخته شود. Bundle Schema عمومی `3.3.0` و Selection Snapshot `1.2.0` تغییر نکرده‌اند. Package Manifest Schema نسخه `2.1.0` است و نسخه تاریخی `2.0.0` برای Validation بسته‌های قبلی نگه‌داری می‌شود.

### سازگاری با WordPress Filesystem

EDIS روش Filesystem تشخیص‌داده‌شده توسط WordPress را گزارش می‌کند، اما Worker پس‌زمینه Credential تعاملی FTP/SSH درخواست نمی‌کند. Export فقط وقتی فعال می‌ماند که Backend خصوصی مستقیم آزمون‌های نوشتن پایدار، جایگزینی اتمیک و قفل فایل EDIS را پاس کند. روش غیردایرکت WordPress فقط شاهد مدیریتی است و مجوز کاهش تضمین‌های Storage نیست.

## ۱۹. کنترل‌های خطایابی و بازیابی نسخه 3.7.2

نسخه 3.7.2 عمداً فقط برای تشخیص نقص و اصلاح خطاها ساخته شده بود. Bundle Schema عمومی `3.3.0`، Shared Envelope `2.0.0`، Package Manifest `2.1.0`، Selection Snapshot `1.2.0`، EDIS-CJ-2 و EDIS-ZIP-1 تغییر نکرده‌اند.

### رگرسیون نوع Payloadهای Elementor

برای Artifactهای زیر آزمون مستقیم بسته نهایی اضافه شده است:

```text
sources/elementor/kit-settings.json      $.evidence.settings → JSON object
indexes/site-settings-index.json         $.evidence.groups   → JSON object
indexes/site-settings-index.json         $.evidence.source   → string|null
```

خطای Type اکنون نوع تعریف‌شده و نوع واقعی JSON را گزارش می‌کند؛ برای نمونه: `Expected string|null; actual array`. مقدار Evidence داخل پیام خطا نمایش داده نمی‌شود.

اگر همین خطا پس از نصب کامل 3.7.2 تکرار شد، Job شکست‌خورده و بایت‌های Artifact نهایی را حفظ کنید و ZIP را دستی اصلاح نکنید. پیام جدید expected/actual مدرک لازم برای تشخیص شاخه باقی‌مانده است.

### تشخیص نصب مخلوط

پیش از بارگذاری Collectorها، SHA-256 فایل‌های حیاتی Runtime، Schema و Configuration بررسی می‌شود. `EDIS_INSTALLATION_MIXED_VERSION` یعنی فایل‌های چند Build با هم ترکیب شده‌اند یا یک فایل حیاتی پس از بسته‌بندی تغییر کرده است. پوشه افزونه را کامل حذف و ZIP کامل 3.7.2 را نصب کنید؛ نسخه جدید را روی پوشه قدیمی Overlay نکنید.

### حالت تنزل‌یافته Private Storage

نبود مسیر خصوصی مناسب دیگر باعث Exception مهارنشده هنگام Boot نمی‌شود. EDIS وارد حالت Fail-closed می‌شود: وردپرس در دسترس می‌ماند، Export غیرفعال است و Site Health و اعلان مدیریت فقط Diagnostic امن را نمایش می‌دهند.

EDIS چند Candidate محدود خارج از Web Root را بررسی می‌کند. برای Windows و Local بهتر است در `wp-config.php` یک مسیر صریح با Slash رو به جلو و خارج از `app/public` تعریف شود:

```php
define( 'EDIS_EVIDENCE_PRIVATE_STORAGE_DIR', 'C:/Users/Nestech/Local Sites/nurro/app/edis-private' );
```

PHP باید در این مسیر امکان ساخت، Lock، Sync، جایگزینی اتمیک و حذف فایل را داشته باشد. مسیر را داخل `wp-content`، Uploads یا هر مسیر عمومی قرار ندهید.

### رفتار Upgrade

Job ناقص 3.7.1 با Implementation Record نسخه 3.7.2 Resume نمی‌شود و باید Job تازه ساخته شود. Package کامل قبلی که Validation خودش را پاس کرده، Historical Artifact تغییرناپذیر باقی می‌ماند.


## ۲۰. کنترل‌های اصلاحی فورنزیک نسخه 3.7.6

نسخهٔ 3.7.6 هیچ قرارداد یا Schema عمومی frozen را تغییر نمی‌دهد و پنج نقص بازتولیدشدهٔ پیاده‌سازی را اصلاح می‌کند.

### اثبات قفل فرایند مستقل روی مسیر فعال

Activation، Bootstrap برنامه، Site Health، عیب‌یابی WP-CLI و Preflight ساخت Export کنترل Storage را روی همان مسیر فعال اجرا می‌کنند. EDIS در فرایند PHP وردپرس قفل را نگه می‌دارد، با `proc_open` یک `PHP_BINARY` مستقل اجرا می‌کند و فقط وقتی ادامه می‌دهد که فرایند دوم همان قفل را `BLOCKED` گزارش کند. `UNAVAILABLE` و `FAIL` هر دو Blocker هستند. در صورت شکست، خود وردپرس و Diagnostics در حالت degraded در دسترس می‌مانند.

### ایمنی مسیر منطقی و فیزیکی

اگر نوشتار منطقی مسیر داخل `ABSPATH` باشد، حتی وقتی symlink والد آن را به بیرون هدایت کند، مسیر رد می‌شود. ancestorهای موجود که به مسیر فیزیکی دیگری redirect می‌شوند نیز پذیرفته نمی‌شوند. مسیر مستقیم، مطلق و بیرون از web root تنظیم کنید و از symlink یا junction استفاده نکنید.

### اصلاح Semantic Identity

حذف فیلدهای عملیاتی فقط در rootهای تعریف‌شدهٔ Policy نسخهٔ `1.0.0` انجام می‌شود. فیلدهای nested در Saved Source یا Addon فقط به‌دلیل نامی مانند `created_at`، `user_id` یا `token` حذف نمی‌شوند. Hash بسته‌های تاریخی بازنویسی نمی‌شود؛ در صورت اهمیت این داده‌ها Export تازهٔ 3.7.6 بسازید.

### هویت پایدار قفل Job

Resume/Retry پیش از هر تغییر Job قفل همان Job را می‌گیرد و تا نخستین Advance نگه می‌دارد. Cleanup و Remove عادی، JSON مربوط به Job را حذف می‌کنند اما sentinel خالی `.lock` را نگه می‌دارند تا inode قفل در میانهٔ کار عوض نشود. تا زمانی که Worker ممکن است فعال باشد lock sentinelها را دستی حذف نکنید.

راهنمای کامل ارتقا و Rollback در `docs/MIGRATION-V3.7.2-TO-V3.7.6.md` قرار دارد.

## ۲۱. رفتار Storage در LocalWP در نسخه 3.7.9

طبق ساختار رسمی Local، روت وردپرس سایت به‌شکل `<site>/app/public` است. EDIS در محیط Local مسیر خصوصی ترجیحی را از همین ساختار استخراج می‌کند:

```text
<site>/edis-private-storage
```

برای `C:/Users/Nestech/Local Sites/nurro/app/public` مسیر ترجیحی برابر است با `C:/Users/Nestech/Local Sites/nurro/edis-private-storage`. مسیر قبلی `<site>/app/edis-private-storage` نیز به‌عنوان fallback امن باقی مانده است.

شکست Private Storage دیگر Activation افزونه را متوقف نمی‌کند. افزونه در حالت Fail-closed فعال می‌ماند، ساخت Export جدید را غیرفعال می‌کند و wp-admin، Site Health، دکمهٔ اجرای دوبارهٔ آزمون و دستورهای `wp edis storage paths` و `wp edis storage self-test` را در دسترس نگه می‌دارد.

در ویندوز ممکن است Runtime وب مقدار `PHP_BINARY` را `php-cgi.exe` گزارش کند. نسخه 3.7.9 در صورت وجود از `php.exe` هم‌سطح آن برای اثبات قفل فرایند مستقل استفاده می‌کند و binary، exit code، stdout و stderr آزمون را در Diagnostics خصوصی ثبت می‌کند.

راهنمای مهاجرت 3.7.8 به 3.7.9 در بستهٔ کامل انتشار قرار دارد.

## ۲۲. کیت اعتبارسنجی نسخه 3.7.10

نسخه 3.7.10 ابزارهای اعتبارسنجی را فقط به بستهٔ سورس اضافه می‌کند. این نسخه قابلیت تازه‌ای به مدیریت WordPress اضافه نمی‌کند و قراردادهای frozen را تغییر نمی‌دهد. در مخزن سورس فرمان `php tools/validation/run-local-validation.php --report=validation/evidence/local-validation.json` را اجرا کنید. Gateهای WordPress، Elementor، Windows، Composer و Python تا زمان ارائهٔ شاهد اجرایی واقعی تأییدشده محسوب نمی‌شوند.


## ۲۳. صحت شواهد اعتبارسنجی در نسخه 3.7.11

نسخه 3.7.11 فقط ابزارهای اعتبارسنجی موجود در بستهٔ سورس را اصلاح می‌کند. اگر یک Gate محلی الزامی Skip شود یا در دسترس نباشد، مقدار `summary.local_state` اکنون `INCOMPLETE` است و دیگر به‌اشتباه `PASS` گزارش نمی‌شود. Gateهای خارجی جداگانه خلاصه می‌شوند و گزینهٔ `--strict-external` فقط همان الزامات خارجی را ارزیابی می‌کند. خروجی فرمان‌ها به‌صورت فایل‌محور و محدود ثبت می‌شود تا Pipeهای پرخروجی قفل نشوند و فایل گزارش نیز با جایگزینی اتمیک و بررسی SHA-256 ثبت می‌شود. بستهٔ نصب WordPress و قراردادهای frozen تغییری نکرده‌اند.
