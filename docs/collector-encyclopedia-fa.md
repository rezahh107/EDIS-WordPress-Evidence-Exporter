# دانشنامه کامل اجزای شواهد EDIS

نسخه 3.7.11

> این سند راهنمای Registry-driven تمام Source Collectorها، Deterministic Index Builderها و Bundle Processorهای همین نسخه است. اجزا فقط شواهد منبع و Indexهای دترمینیستیک تولید می‌کنند؛ UX را امتیازدهی نمی‌کنند، مقدار نهایی را Resolve نمی‌کنند و Correlation نهایی Source/Runtime را انجام نمی‌دهند.

## روش خواندن هر مدخل

- **Source Truth** میزان اعتماد به قرارداد و پیاده‌سازی منبع را نشان می‌دهد.
- **Source Availability** نشان می‌دهد شواهد در همین Export موجود است یا نه.
- مقدار مشاهده‌شده یا صادرشده از Index مشتق‌شده و مقدار Resolve‌شده توسط Python جدا می‌ماند.
- اعتبارسنجی نهایی، Merge، Resolver، Correlation، Formula، Rule و TruthReport متعلق به Python است.
- DOM، Geometry، Computed Style، Relationship و Interaction Runtime متعلق به افزونه مرورگر است.
- مدل زبانی فقط خروجی دترمینیستیک Python را توضیح می‌دهد و واقعیت گمشده نمی‌سازد.

## Source Context برای اتصال مرورگر

- **شناسه فنی:** `bridge_source_context`
- **نوع جزء:** `BUNDLE_PROCESSOR`
- **گروه:** `bundle`
- **نوع منبع:** `deterministic_bundle_processor`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `bridge/source-context.json`
- **Schema:** `urn:edis:schema:bridge:source-context` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_document_index` (REQUIRED), `elementor_element_structure_index` (OPTIONAL), `environment` (REQUIRED)

### خلاصه ساده

حداقل اطلاعات هویت Source و Element Index مورد نیاز افزونه مرورگر.

### این داده چیست؟

Binding اولیه را بدون قراردادن سند کامل یا ارتباط شبکه با WordPress ممکن می‌کند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

از Source Artifactهای تأییدشده ساخته و به حداقل Schema لازم برای Import محلی Browser Project می‌شود. سند کامل داخل آن قرار نمی‌گیرد.

### چه فیلدهایی صادر می‌شوند؟

Analysis Set ID، WordPress Bundle ID، Source Export Root Hash، Site Fingerprint، URL Profile، Site Locator Candidate، Multisite Scope و Projection محدود Document و Element Structure Index.

### چرا برای Pipeline مهم است؟

Python بعداً هر دو Package را Validate می‌کند و Correlation نهایی را می‌سازد.

### Python چگونه از آن استفاده می‌کند؟

Python بعداً هر دو Package را Validate می‌کند و Correlation نهایی را می‌سازد.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Bridge Context فقط Binding اولیه را ممکن می‌کند. نبود Context در Browser خطا نیست و Correlation نهایی متعلق به Python است.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:bridge:source-context",
  "schema_version": "1.0.0",
  "artifact_type": "bridge_source_context",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "bridge_source_context",
    "component_type": "BUNDLE_PROCESSOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "bridge_source_context",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`edis.cross-product-contract`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## Diagnostics بسته

- **شناسه فنی:** `bundle_diagnostics`
- **نوع جزء:** `BUNDLE_PROCESSOR`
- **گروه:** `bundle`
- **نوع منبع:** `deterministic_bundle_processor`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `diagnostics/diagnostics.json`
- **Schema:** `urn:edis:schema:bundle:diagnostics` نسخه `1.0.0`
- **وابستگی‌ها:** `bridge_source_context` (OPTIONAL), `elementor_architecture_index` (OPTIONAL), `elementor_breakpoints` (OPTIONAL), `elementor_capability_evidence` (OPTIONAL), `elementor_document_index` (OPTIONAL), `elementor_document_inventory` (OPTIONAL), `elementor_document_source` (OPTIONAL), `elementor_dynamic_references` (OPTIONAL), `elementor_element_structure_index` (OPTIONAL), `elementor_feature_flags` (OPTIONAL), `elementor_global_classes_order` (OPTIONAL), `elementor_global_classes_registry` (OPTIONAL), `elementor_installation` (OPTIONAL), `elementor_kit_metadata` (OPTIONAL), `elementor_kit_settings` (OPTIONAL), `elementor_legacy_global_styles` (OPTIONAL), `elementor_performance_configuration` (OPTIONAL), `elementor_reference_index` (OPTIONAL), `elementor_registered_document_types` (OPTIONAL), `elementor_registered_widgets` (OPTIONAL), `elementor_responsive_declaration_index` (OPTIONAL), `elementor_site_settings_index` (OPTIONAL), `elementor_unknown_structure_ledger` (OPTIONAL), `elementor_usage_summary` (OPTIONAL), `elementor_variables_registry` (OPTIONAL), `environment` (OPTIONAL), `evidence_conservation` (OPTIONAL), `export_comparison` (OPTIONAL), `fixture_capture` (OPTIONAL), `plugin` (OPTIONAL), `selection_snapshot` (OPTIONAL), `theme` (OPTIONAL)

### خلاصه ساده

جریان نرمال‌شده Diagnostic برای Export منبع.

### این داده چیست؟

Diagnostic معنایی و عملیاتی از هم جدا می‌مانند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

Diagnosticهای Componentهای Commitشده را Aggregate می‌کند و متن ترجمه‌شده در Semantic Identity استفاده نمی‌شود.

### چه فیلدهایی صادر می‌شوند؟

Diagnostic Code، Severity، Scope معنایی/عملیاتی، Message Key، Context ساخت‌یافته، Component ID و ترتیب دترمنیستیک.

### چرا برای Pipeline مهم است؟

Python برای رفتار دترمینیستیک از Code و Context ساختاریافته استفاده می‌کند، نه متن ترجمه‌شده.

### Python چگونه از آن استفاده می‌کند؟

Python برای رفتار دترمینیستیک از Code و Context ساختاریافته استفاده می‌کند، نه متن ترجمه‌شده.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Diagnostics وضعیت شواهد و پردازش را توضیح می‌دهد و جای Package Validation یا UX Finding را نمی‌گیرد.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:bundle:diagnostics",
  "schema_version": "1.0.0",
  "artifact_type": "bundle_diagnostics",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "bundle_diagnostics",
    "component_type": "BUNDLE_PROCESSOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "bundle_diagnostics",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`edis.cross-product-contract`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## اندازه تخمینی خروجی

- **شناسه فنی:** `estimated_export_size`
- **نوع جزء:** `BUNDLE_PROCESSOR`
- **گروه:** `bundle`
- **نوع منبع:** `deterministic_bundle_processor`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `diagnostics/estimated-export-size.json`
- **Schema:** `urn:edis:schema:bundle:estimated-size` نسخه `1.0.0`
- **وابستگی‌ها:** `bridge_source_context` (OPTIONAL), `bundle_diagnostics` (OPTIONAL), `elementor_architecture_index` (OPTIONAL), `elementor_breakpoints` (OPTIONAL), `elementor_capability_evidence` (OPTIONAL), `elementor_document_index` (OPTIONAL), `elementor_document_inventory` (OPTIONAL), `elementor_document_source` (OPTIONAL), `elementor_dynamic_references` (OPTIONAL), `elementor_element_structure_index` (OPTIONAL), `elementor_feature_flags` (OPTIONAL), `elementor_global_classes_order` (OPTIONAL), `elementor_global_classes_registry` (OPTIONAL), `elementor_installation` (OPTIONAL), `elementor_kit_metadata` (OPTIONAL), `elementor_kit_settings` (OPTIONAL), `elementor_legacy_global_styles` (OPTIONAL), `elementor_performance_configuration` (OPTIONAL), `elementor_reference_index` (OPTIONAL), `elementor_registered_document_types` (OPTIONAL), `elementor_registered_widgets` (OPTIONAL), `elementor_responsive_declaration_index` (OPTIONAL), `elementor_site_settings_index` (OPTIONAL), `elementor_usage_summary` (OPTIONAL), `elementor_variables_registry` (OPTIONAL), `environment` (OPTIONAL), `export_comparison` (OPTIONAL), `fixture_capture` (OPTIONAL), `plugin` (OPTIONAL), `theme` (OPTIONAL)

### خلاصه ساده

تخمین بایت بر اساس Artifactهای مرحله‌بندی‌شده.

### این داده چیست؟

به UI کمک می‌کند اندازه تقریبی را توضیح دهد بدون ادعای حجم دقیق ZIP.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

Byteهای EDIS-CJ-2 Artifactهای Commitشده را پیش از افزودن Schema، Manifest، Checksum و Framing قطعی ZIP با روش STORE می‌شمارد.

### چه فیلدهایی صادر می‌شوند؟

Canonical Byte Count هر Component مرحله‌ای، مجموع Estimated Uncompressed JSON Bytes و Flag صریح Estimate-only.

### چرا برای Pipeline مهم است؟

Python فقط برای برنامه‌ریزی Ingestion از آن استفاده می‌کند.

### Python چگونه از آن استفاده می‌کند؟

Python فقط برای برنامه‌ریزی Ingestion از آن استفاده می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

این Estimate اندازه نهایی ZIP یا Performance Metric نیست.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:bundle:estimated-size",
  "schema_version": "1.0.0",
  "artifact_type": "estimated_export_size",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "estimated_export_size",
    "component_type": "BUNDLE_PROCESSOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "estimated_export_size",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`edis.contract`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## کنترل حفظ شواهد

- **شناسه فنی:** `evidence_conservation`
- **نوع جزء:** `BUNDLE_PROCESSOR`
- **گروه:** `bundle`
- **نوع منبع:** `deterministic_bundle_processor`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `validation/evidence-conservation.json`
- **Schema:** `urn:edis:schema:validation:evidence-conservation` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_document_source` (OPTIONAL), `elementor_element_structure_index` (OPTIONAL), `elementor_responsive_declaration_index` (OPTIONAL), `elementor_dynamic_references` (OPTIONAL)

### خلاصه ساده

بررسی می‌کند Elementها، Responsive Variantها و Class Bindingهای Source در Indexها حفظ شده‌اند.

### این داده چیست؟

بررسی می‌کند Elementها، Responsive Variantها و Class Bindingهای Source در Indexها حفظ شده‌اند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

به‌صورت دترمینیستیک از Artifactهای Source که قبلاً Commit شده‌اند ساخته می‌شود و تحلیل UX انجام نمی‌دهد.

### چه فیلدهایی صادر می‌شوند؟

رکوردهای ماشین‌خوان نسخه‌بندی‌شده، شمارش‌ها، Source Path، وضعیت و Diagnostic.

### چرا برای Pipeline مهم است؟

Python با این Artifact کامل‌بودن داده را بررسی می‌کند و تصمیم می‌گیرد ادامه پردازش امن است یا خیر.

### Python چگونه از آن استفاده می‌کند؟

Python Artifact را Validate می‌کند و نبود یا Partial بودن داده را بدون حدس حفظ می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط Findingهای تولیدشده توسط Python را دریافت می‌کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده رفتار رندرشده یا کیفیت UX را ثابت نمی‌کند.

### محدودیت نسخه

پوشش به ساختارهای Source صادرشده و نسخه Schema وابسته است.

### اثر حریم خصوصی

محتوای جدیدی فراتر از Source Artifactهای انتخاب‌شده جمع‌آوری نمی‌شود.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:validation:evidence-conservation",
  "schema_version": "1.0.0",
  "artifact_type": "evidence_conservation",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "evidence_conservation",
    "component_type": "BUNDLE_PROCESSOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "evidence_conservation",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`edis.cross-product-contract`

### عیب‌یابی

اسناد انتخاب‌شده، Source Availability، Diagnosticهای Conservation و Package Validation را بررسی کنید.

## تصویر ثابت انتخاب Export

- **شناسه فنی:** `selection_snapshot`
- **نوع جزء:** `BUNDLE_PROCESSOR`
- **گروه:** `bundle`
- **نوع منبع:** `deterministic_bundle_processor`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `selection/selection-snapshot.json`
- **Schema:** `urn:edis:schema:bundle:selection-snapshot` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_document_source` (OPTIONAL), `elementor_document_inventory` (OPTIONAL)

### خلاصه ساده

انتخاب و Scope دقیقی که این Bundle تغییرناپذیر با آن ساخته شده است.

### این داده چیست؟

انتخاب و Scope دقیقی که این Bundle تغییرناپذیر با آن ساخته شده است.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

به‌صورت دترمینیستیک از Artifactهای Source که قبلاً Commit شده‌اند ساخته می‌شود و تحلیل UX انجام نمی‌دهد.

### چه فیلدهایی صادر می‌شوند؟

رکوردهای ماشین‌خوان نسخه‌بندی‌شده، شمارش‌ها، Source Path، وضعیت و Diagnostic.

### چرا برای Pipeline مهم است؟

Python با این Artifact کامل‌بودن داده را بررسی می‌کند و تصمیم می‌گیرد ادامه پردازش امن است یا خیر.

### Python چگونه از آن استفاده می‌کند؟

Python Artifact را Validate می‌کند و نبود یا Partial بودن داده را بدون حدس حفظ می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط Findingهای تولیدشده توسط Python را دریافت می‌کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده رفتار رندرشده یا کیفیت UX را ثابت نمی‌کند.

### محدودیت نسخه

پوشش به ساختارهای Source صادرشده و نسخه Schema وابسته است.

### اثر حریم خصوصی

محتوای جدیدی فراتر از Source Artifactهای انتخاب‌شده جمع‌آوری نمی‌شود.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:bundle:selection-snapshot",
  "schema_version": "1.0.0",
  "artifact_type": "selection_snapshot",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "selection_snapshot",
    "component_type": "BUNDLE_PROCESSOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "selection_snapshot",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`edis.cross-product-contract`

### عیب‌یابی

اسناد انتخاب‌شده، Source Availability، Diagnosticهای Conservation و Package Validation را بررسی کنید.

## پوشش داده منبع

- **شناسه فنی:** `source_coverage`
- **نوع جزء:** `BUNDLE_PROCESSOR`
- **گروه:** `bundle`
- **نوع منبع:** `deterministic_bundle_processor`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `coverage/source-coverage.json`
- **Schema:** `urn:edis:schema:coverage:source` نسخه `1.0.0`
- **وابستگی‌ها:** `bridge_source_context` (OPTIONAL), `bundle_diagnostics` (OPTIONAL), `elementor_architecture_index` (OPTIONAL), `elementor_breakpoints` (OPTIONAL), `elementor_capability_evidence` (OPTIONAL), `elementor_document_index` (OPTIONAL), `elementor_document_inventory` (OPTIONAL), `elementor_document_source` (OPTIONAL), `elementor_dynamic_references` (OPTIONAL), `elementor_element_structure_index` (OPTIONAL), `elementor_feature_flags` (OPTIONAL), `elementor_global_classes_order` (OPTIONAL), `elementor_global_classes_registry` (OPTIONAL), `elementor_installation` (OPTIONAL), `elementor_kit_metadata` (OPTIONAL), `elementor_kit_settings` (OPTIONAL), `elementor_legacy_global_styles` (OPTIONAL), `elementor_performance_configuration` (OPTIONAL), `elementor_reference_index` (OPTIONAL), `elementor_registered_document_types` (OPTIONAL), `elementor_registered_widgets` (OPTIONAL), `elementor_responsive_declaration_index` (OPTIONAL), `elementor_site_settings_index` (OPTIONAL), `elementor_unknown_structure_ledger` (OPTIONAL), `elementor_usage_summary` (OPTIONAL), `elementor_variables_registry` (OPTIONAL), `environment` (OPTIONAL), `estimated_export_size` (OPTIONAL), `evidence_conservation` (OPTIONAL), `export_comparison` (OPTIONAL), `fixture_capture` (OPTIONAL), `plugin` (OPTIONAL), `selection_snapshot` (OPTIONAL), `theme` (OPTIONAL)

### خلاصه ساده

واقعیت‌های Coverage فقط برای داده منبع.

### این داده چیست؟

Coverage مشخص می‌کند داده موجود و قرارداد آن تا چه حد تأیید شده است.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

پس از Collection، Artifactهای Commitشده را Aggregate می‌کند و Browser Coverage را Merge یا Rule Readiness را تعیین نمی‌کند.

### چه فیلدهایی صادر می‌شوند؟

Component Type، Source Truth، Source Availability، Diagnostic Count برای هر Component و خلاصه Truth، Availability و تعداد Source Component.

### چرا برای Pipeline مهم است؟

Python این Coverage را با Runtime و Binding ترکیب می‌کند تا آمادگی Rule را تعیین کند.

### Python چگونه از آن استفاده می‌کند؟

Python این Coverage را با Runtime و Binding ترکیب می‌کند تا آمادگی Rule را تعیین کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Source Coverage نمی‌تواند کافی‌بودن Runtime یا Correlation Evidence را اعلام کند؛ Python بعداً Coverageهای جدا را ترکیب می‌کند.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:coverage:source",
  "schema_version": "1.0.0",
  "artifact_type": "source_coverage",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "source_coverage",
    "component_type": "BUNDLE_PROCESSOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "source_coverage",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`edis.contract`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## فراداده ساخت Fixture

- **شناسه فنی:** `fixture_capture`
- **نوع جزء:** `BUNDLE_PROCESSOR`
- **گروه:** `bundle_processing`
- **نوع منبع:** `DERIVED_FIXTURE_METADATA`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `fixture/fixture-metadata.json`
- **Schema:** `urn:edis:schema:fixture:capture-metadata` نسخه `1.0.0`
- **وابستگی‌ها:** `environment` (OPTIONAL), `elementor_installation` (OPTIONAL), `elementor_document_source` (OPTIONAL), `selection_snapshot` (OPTIONAL)

### خلاصه ساده

فراداده اختیاری برای تبدیل خروجی به بسته نگارش Fixture واقعی و کنترل‌شده.

### این داده چیست؟

فراداده اختیاری برای تبدیل خروجی به بسته نگارش Fixture واقعی و کنترل‌شده.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

به‌صورت دترمینیستیک از Artifactهای Source ثبت‌شده و Metadata محلی خروجی قبلی ساخته می‌شود و وضعیت رندر مرورگر را بررسی نمی‌کند.

### چه فیلدهایی صادر می‌شوند؟

رکوردهای ماشین‌خوان نسخه‌بندی‌شده، شناسه سند، Hash منبع، شمارش محدود، وضعیت و Provenance.

### چرا برای Pipeline مهم است؟

Python با این داده می‌تواند تغییر Source، زمینه Fixture و انتظارهای Ingestion را بدون حدس بررسی کند.

### Python چگونه از آن استفاده می‌کند؟

Python Artifact را Validate می‌کند، نبود سابقه قبلی را حفظ می‌کند و درباره قابل‌اجرا بودن Comparison یا Fixture assertion تصمیم می‌گیرد.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط Findingهای Python را دریافت می‌کند و این Artifact اجازه Scoring نمی‌دهد.

### چه نتیجه‌ای نمی‌توان گرفت؟

رفتار رندرشده، کیفیت بصری، درستی UX یا Correlation نهایی Source/Runtime از این داده ثابت نمی‌شود.

### محدودیت نسخه

Deltaهای تفصیلی به Summary محدود ذخیره‌شده توسط یک خروجی کامل EDIS 3.4 یا جدیدتر وابسته‌اند.

### اثر حریم خصوصی

ممکن است شناسه سند، نسخه‌های محیط و Hashهای Source را نشان دهد، اما متن صفحه جدیدی فراتر از Source انتخاب‌شده اضافه نمی‌کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:fixture:capture-metadata",
  "schema_version": "1.0.0",
  "artifact_type": "fixture_capture",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "fixture_capture",
    "component_type": "BUNDLE_PROCESSOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "fixture_capture",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`edis.cross-product-contract`

### عیب‌یابی

انتخاب سندها، Metadata خروجی قبلی، Availability منبع، Fixture mode و Diagnostics بسته را بررسی کنید.

## مقایسه با خروجی قبلی

- **شناسه فنی:** `export_comparison`
- **نوع جزء:** `BUNDLE_PROCESSOR`
- **گروه:** `bundle_processing`
- **نوع منبع:** `DERIVED_SOURCE_DIFF`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `comparison/previous-export-diff.json`
- **Schema:** `urn:edis:schema:comparison:previous-source-export` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_document_source` (OPTIONAL), `elementor_element_structure_index` (OPTIONAL), `elementor_responsive_declaration_index` (OPTIONAL), `elementor_dynamic_references` (OPTIONAL)

### خلاصه ساده

Diff محدود شواهد Source نسبت به خروجی کامل قبلی EDIS.

### این داده چیست؟

Diff محدود شواهد Source نسبت به خروجی کامل قبلی EDIS.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

به‌صورت دترمینیستیک از Artifactهای Source ثبت‌شده و Metadata محلی خروجی قبلی ساخته می‌شود و وضعیت رندر مرورگر را بررسی نمی‌کند.

### چه فیلدهایی صادر می‌شوند؟

رکوردهای ماشین‌خوان نسخه‌بندی‌شده، شناسه سند، Hash منبع، شمارش محدود، وضعیت و Provenance.

### چرا برای Pipeline مهم است؟

Python با این داده می‌تواند تغییر Source، زمینه Fixture و انتظارهای Ingestion را بدون حدس بررسی کند.

### Python چگونه از آن استفاده می‌کند؟

Python Artifact را Validate می‌کند، نبود سابقه قبلی را حفظ می‌کند و درباره قابل‌اجرا بودن Comparison یا Fixture assertion تصمیم می‌گیرد.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط Findingهای Python را دریافت می‌کند و این Artifact اجازه Scoring نمی‌دهد.

### چه نتیجه‌ای نمی‌توان گرفت؟

رفتار رندرشده، کیفیت بصری، درستی UX یا Correlation نهایی Source/Runtime از این داده ثابت نمی‌شود.

### محدودیت نسخه

Deltaهای تفصیلی به Summary محدود ذخیره‌شده توسط یک خروجی کامل EDIS 3.4 یا جدیدتر وابسته‌اند.

### اثر حریم خصوصی

ممکن است شناسه سند، نسخه‌های محیط و Hashهای Source را نشان دهد، اما متن صفحه جدیدی فراتر از Source انتخاب‌شده اضافه نمی‌کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:comparison:previous-source-export",
  "schema_version": "1.0.0",
  "artifact_type": "export_comparison",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "export_comparison",
    "component_type": "BUNDLE_PROCESSOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "export_comparison",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`edis.cross-product-contract`

### عیب‌یابی

انتخاب سندها، Metadata خروجی قبلی، Availability منبع، Fixture mode و Diagnostics بسته را بررسی کنید.

## Index معماری

- **شناسه فنی:** `elementor_architecture_index`
- **نوع جزء:** `INDEX_BUILDER`
- **گروه:** `indexes`
- **نوع منبع:** `deterministic_source_index`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `indexes/architecture-index.json`
- **Schema:** `urn:edis:schema:index:architecture` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_element_structure_index` (REQUIRED)

### خلاصه ساده

طبقه‌بندی معماری از شکل صریح داده ذخیره‌شده.

### این داده چیست؟

وجود یک عنصر Atomic باعث رد کل سند Hybrid نمی‌شود.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

از Shape ذخیره‌شده Element و فیلدهای صریح با Classifier دترمنیستیک نسخه‌بندی‌شده ساخته می‌شود و فیلد ناشناخته به‌جای رد شدن حفظ می‌شود.

### چه فیلدهایی صادر می‌شوند؟

Architecture Kind هر Document و Element، Countهای Legacy/Container/Atomic/Unknown، Hybrid Flag و Classification Provenance.

### چرا برای Pipeline مهم است؟

Python مسیر Parser را برای هر عنصر انتخاب می‌کند و ساختار ناشناخته را حفظ می‌کند.

### Python چگونه از آن استفاده می‌کند؟

Python مسیر Parser را برای هر عنصر انتخاب می‌کند و ساختار ناشناخته را حفظ می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Architecture Classification کیفیت طراحی را نشان نمی‌دهد و شناخت همه Semantics عناصر Addon را تضمین نمی‌کند.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:index:architecture",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_architecture_index",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_architecture_index",
    "component_type": "INDEX_BUILDER",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_architecture_index",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.atomic-elements`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## Index اسناد

- **شناسه فنی:** `elementor_document_index`
- **نوع جزء:** `INDEX_BUILDER`
- **گروه:** `indexes`
- **نوع منبع:** `deterministic_index`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `indexes/document-index.json`
- **Schema:** `urn:edis:schema:index:documents` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_document_inventory` (REQUIRED), `elementor_document_source` (OPTIONAL)

### خلاصه ساده

جدول Lookup پایدار اسناد ساخته‌شده از شواهد منبع.

### این داده چیست؟

شناسه، Fingerprint، Hash، نوع و محل Artifact سند را به هم وصل می‌کند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

به‌صورت دترمنیستیک از Artifactهای Document Inventory و Source ساخته می‌شود و Source جدیدی Query نمی‌کند.

### چه فیلدهایی صادر می‌شوند؟

Document Key، Document ID رشته‌ای، Type، State، Source Hash، خلاصه Architecture، Locator Candidate و ترتیب دترمنیستیک.

### چرا برای Pipeline مهم است؟

Python پیش از بارگذاری اسناد بزرگ از آن برای Routing و Validation سریع استفاده می‌کند.

### Python چگونه از آن استفاده می‌کند؟

Python پیش از بارگذاری اسناد بزرگ از آن برای Routing و Validation سریع استفاده می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Truth و Availability این Index نمی‌تواند از Dependencyهای الزامی آن بالاتر باشد.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:index:documents",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_document_index",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_document_index",
    "component_type": "INDEX_BUILDER",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_document_index",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`edis.contract`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## شاخص ساختار عناصر

- **شناسه فنی:** `elementor_element_structure_index`
- **نوع جزء:** `INDEX_BUILDER`
- **گروه:** `indexes`
- **نوع منبع:** `deterministic_source_index`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `indexes/element-structure-index.json`
- **Schema:** `urn:edis:schema:index:element-structure` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_document_source` (REQUIRED), `elementor_registered_widgets` (OPTIONAL)

### خلاصه ساده

رکورد سبک هویت و Ancestor برای هر عنصر ذخیره‌شده.

### این داده چیست؟

ID واقعی، Duplicate Count، Source Path، ترتیب، نوع و Architecture Kind را حفظ می‌کند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

یک Walker محدود و دترمنیستیک درخت V3، Container، Atomic و Hybrid ذخیره‌شده را طی می‌کند و ID گمشده Elementor را جعل نمی‌کند.

### چه فیلدهایی صادر می‌شوند؟

Document ID/Fingerprint، Source Element Key، Source Record Hash، Elementor Element ID واقعی، Duplicate Count و Uniqueness، Parent ID، Ancestor ID از Root به Leaf بدون خود عنصر، Source Path، Document Order، Element Kind، elType، Widget Type و Architecture Kind.

### چرا برای Pipeline مهم است؟

Python و افزونه مرورگر از آن به‌عنوان سمت Source در Binding استفاده می‌کنند.

### Python چگونه از آن استفاده می‌کند؟

Python و افزونه مرورگر از آن به‌عنوان سمت Source در Binding استفاده می‌کنند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

این Index سمت Source در Browser Binding است و تا زمانی که Python هر دو بسته را Verify نکند، Match نهایی Runtime را ثابت نمی‌کند.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:index:element-structure",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_element_structure_index",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_element_structure_index",
    "component_type": "INDEX_BUILDER",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_element_structure_index",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.page-content`, `elementor.atomic-elements`, `edis.contract`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## شواهد قابلیت‌های Elementor

- **شناسه فنی:** `elementor_capability_evidence`
- **نوع جزء:** `INDEX_BUILDER`
- **گروه:** `indexes`
- **نوع منبع:** `deterministic_capability_index`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `PARTIAL`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `indexes/capability-evidence.json`
- **Schema:** `urn:edis:schema:index:capabilities` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_architecture_index` (OPTIONAL), `elementor_breakpoints` (OPTIONAL), `elementor_feature_flags` (OPTIONAL), `elementor_global_classes_registry` (OPTIONAL), `elementor_installation` (REQUIRED), `elementor_registered_document_types` (OPTIONAL), `elementor_registered_widgets` (OPTIONAL), `elementor_variables_registry` (OPTIONAL), `environment` (REQUIRED)

### خلاصه ساده

نقشه قابلیت که Registration مشاهده‌شده، Usage سند، انتظار نسخه‌ای و Unknown را جدا می‌کند.

### این داده چیست؟

مانع می‌شود راهنمای نهایی قابلیت غیرقابل استفاده در Elementor نصب‌شده پیشنهاد کند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

از چند Source Artifact ساخته می‌شود و `observed registration`، `document usage`، `version expectation` و `unknown` را جدا نگه می‌دارد.

### چه فیلدهایی صادر می‌شوند؟

Registration مشاهده‌شده، Usage در سند، Feature State، Version Expectation، شواهد Free/Pro، Atomic/Variables/Classes/Interactions و منشأ Confidence هر Capability.

### چرا برای Pipeline مهم است؟

Python شواهد مشاهده‌شده را قوی‌تر از انتظار مبتنی بر نسخه در نظر می‌گیرد.

### Python چگونه از آن استفاده می‌کند؟

Python شواهد مشاهده‌شده را قوی‌تر از انتظار مبتنی بر نسخه در نظر می‌گیرد.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Version Expectation هرگز به Observed Support تبدیل نمی‌شود. Python این Map را Constraint می‌داند نه اثبات Action رندرشده.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `PARTIAL` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:index:capabilities",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_capability_evidence",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_capability_evidence",
    "component_type": "INDEX_BUILDER",
    "source_truth_state": "PARTIAL",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_capability_evidence",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.widgets`, `elementor.documents`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## Index ارجاع‌ها

- **شناسه فنی:** `elementor_reference_index`
- **نوع جزء:** `INDEX_BUILDER`
- **گروه:** `indexes`
- **نوع منبع:** `deterministic_source_index`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `PARTIAL`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `indexes/reference-index.json`
- **Schema:** `urn:edis:schema:index:references` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_dynamic_references` (REQUIRED), `elementor_global_classes_registry` (OPTIONAL), `elementor_legacy_global_styles` (OPTIONAL), `elementor_variables_registry` (OPTIONAL)

### خلاصه ساده

Index جست‌وجوی Referenceهای Global، Variable، Class و Dynamic.

### این داده چیست؟

Registry Candidate و Reference Resolveنشده را بدون محاسبه مقدار Runtime گزارش می‌کند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

به‌صورت دترمنیستیک Reference ذخیره‌شده را به Registry Candidate صادرشده وصل می‌کند بدون محاسبه Effective Value نهایی.

### چه فیلدهایی صادر می‌شوند؟

Reference Occurrence، Document/Source Path، Reference Kind/Hash، Registry Candidate ID، Resolution Availability، Ambiguity و Missing Target Diagnostics.

### چرا برای Pipeline مهم است؟

Python Resolution نهایی را انجام می‌دهد و Provenance دقیق ثبت می‌کند.

### Python چگونه از آن استفاده می‌کند؟

Python Resolution نهایی را انجام می‌دهد و Provenance دقیق ثبت می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Candidate Match در صورت Partial بودن Registry یا وجود Duplicate ID همان Resolution نهایی نیست.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `PARTIAL` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:index:references",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_reference_index",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_reference_index",
    "component_type": "INDEX_BUILDER",
    "source_truth_state": "PARTIAL",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_reference_index",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.global-styles`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## شاخص اعلان‌های واکنش‌گرا

- **شناسه فنی:** `elementor_responsive_declaration_index`
- **نوع جزء:** `INDEX_BUILDER`
- **گروه:** `indexes`
- **نوع منبع:** `deterministic_source_index`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `indexes/responsive-declaration-index.json`
- **Schema:** `urn:edis:schema:index:responsive-declarations` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_breakpoints` (REQUIRED), `elementor_document_source` (REQUIRED)

### خلاصه ساده

فهرست Propertyهای Responsive ذخیره‌شده با Source Path دقیق.

### این داده چیست؟

نبود Declaration را از مقدار صریح ذخیره‌شده جدا می‌کند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

کلیدهای Legacy را فقط برای Breakpoint IDهای مشاهده‌شده از Manager اسکن می‌کند و Atomic Variantهای styles.*.variants[].meta.breakpoint را نیز حفظ می‌کند. Inheritance و Direction Resolve نمی‌شوند.

### چه فیلدهایی صادر می‌شوند؟

نوع Declaration، شناسه سند و Element، Style ID، Variant Index، Property، کلید اصلی، Breakpoint/Device، State، Saved Value و Source Path دقیق.

### چرا برای Pipeline مهم است؟

Python با Breakpoint واقعی Effective Value را Resolve می‌کند و Provenance ارث‌بری را نگه می‌دارد.

### Python چگونه از آن استفاده می‌کند؟

Python با Breakpoint واقعی Effective Value را Resolve می‌کند و Provenance ارث‌بری را نگه می‌دارد.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Suffix ثبت‌نشده به واقعیت Breakpoint ارتقا داده نمی‌شود. معنای مؤثر Declaration فقط با Resolver پایتون و شواهد واقعی Breakpoint و Browser مشخص می‌شود.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:index:responsive-declarations",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_responsive_declaration_index",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_responsive_declaration_index",
    "component_type": "INDEX_BUILDER",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_responsive_declaration_index",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.responsive-data`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## Index تنظیمات سایت

- **شناسه فنی:** `elementor_site_settings_index`
- **نوع جزء:** `INDEX_BUILDER`
- **گروه:** `indexes`
- **نوع منبع:** `deterministic_source_index`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `PARTIAL`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `indexes/site-settings-index.json`
- **Schema:** `urn:edis:schema:index:site-settings` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_kit_settings` (REQUIRED), `elementor_legacy_global_styles` (OPTIONAL)

### خلاصه ساده

Index دسته‌بندی‌شده روی تنظیمات ذخیره‌شده Kit.

### این داده چیست؟

مسیر پایدار به Python می‌دهد بدون اینکه Artifact خام Kit را جایگزین کند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

به‌صورت دترمنیستیک از Active Kit Settings و با Key Classification شفاف ساخته می‌شود. Setting اصلی همچنان Source Artifact است.

### چه فیلدهایی صادر می‌شوند؟

کلید و مقدار ذخیره‌شده Active Kit در گروه‌های Color، Typography، Layout، Identity، Lightbox یا Other همراه Provenance.

### چرا برای Pipeline مهم است؟

Python بخش‌های Typography، Color، Layout، Identity و Custom Style را پیدا می‌کند.

### Python چگونه از آن استفاده می‌کند؟

Python بخش‌های Typography، Color، Layout، Identity و Custom Style را پیدا می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Category فقط برای پیمایش داده است و اثر Runtime را ثابت نمی‌کند. Setting مخصوص Addon ممکن است در `other` باقی بماند.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `PARTIAL` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:index:site-settings",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_site_settings_index",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_site_settings_index",
    "component_type": "INDEX_BUILDER",
    "source_truth_state": "PARTIAL",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_site_settings_index",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.site-settings`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## خلاصه استفاده در منبع

- **شناسه فنی:** `elementor_usage_summary`
- **نوع جزء:** `INDEX_BUILDER`
- **گروه:** `indexes`
- **نوع منبع:** `deterministic_source_index`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `indexes/usage-summary.json`
- **Schema:** `urn:edis:schema:index:usage-summary` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_element_structure_index` (REQUIRED), `elementor_reference_index` (OPTIONAL), `elementor_responsive_declaration_index` (OPTIONAL)

### خلاصه ساده

شمارش‌های سطح Source برای برنامه‌ریزی و Ingestion محدود.

### این داده چیست؟

وجود داده را خلاصه می‌کند بدون اینکه کیفیت یا UX را قضاوت کند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

فقط Indexهای صادرشده را Aggregate می‌کند و Document یا Element جدیدی کشف نمی‌کند.

### چه فیلدهایی صادر می‌شوند؟

Count دترمنیستیک بر اساس Document Type، Element Kind، Widget Type، Architecture Kind، Responsive Declaration و Reference Kind.

### چرا برای Pipeline مهم است؟

Python از شمارش برای Performance Planning، Coverage و ناوبری گزارش استفاده می‌کند.

### Python چگونه از آن استفاده می‌کند؟

Python از شمارش برای Performance Planning، Coverage و ناوبری گزارش استفاده می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Countها Usage Fact منبع هستند و Complexity، Performance یا Cognitive Load Score محسوب نمی‌شوند.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:index:usage-summary",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_usage_summary",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_usage_summary",
    "component_type": "INDEX_BUILDER",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_usage_summary",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`edis.contract`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## دفتر ساختارهای ناشناخته

- **شناسه فنی:** `elementor_unknown_structure_ledger`
- **نوع جزء:** `INDEX_BUILDER`
- **گروه:** `indexes`
- **نوع منبع:** `deterministic_source_index`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `PARTIAL`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `diagnostics/unknown-structures.json`
- **Schema:** `urn:edis:schema:index:unknown-structures` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_document_source` (OPTIONAL)

### خلاصه ساده

دفتر فیلدهای ناشناخته یا نسخه آینده Element که در Raw Source حفظ شده‌اند.

### این داده چیست؟

دفتر فیلدهای ناشناخته یا نسخه آینده Element که در Raw Source حفظ شده‌اند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

به‌صورت دترمینیستیک از Artifactهای Source که قبلاً Commit شده‌اند ساخته می‌شود و تحلیل UX انجام نمی‌دهد.

### چه فیلدهایی صادر می‌شوند؟

رکوردهای ماشین‌خوان نسخه‌بندی‌شده، شمارش‌ها، Source Path، وضعیت و Diagnostic.

### چرا برای Pipeline مهم است؟

Python با این Artifact کامل‌بودن داده را بررسی می‌کند و تصمیم می‌گیرد ادامه پردازش امن است یا خیر.

### Python چگونه از آن استفاده می‌کند؟

Python Artifact را Validate می‌کند و نبود یا Partial بودن داده را بدون حدس حفظ می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط Findingهای تولیدشده توسط Python را دریافت می‌کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده رفتار رندرشده یا کیفیت UX را ثابت نمی‌کند.

### محدودیت نسخه

پوشش به ساختارهای Source صادرشده و نسخه Schema وابسته است.

### اثر حریم خصوصی

محتوای جدیدی فراتر از Source Artifactهای انتخاب‌شده جمع‌آوری نمی‌شود.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `PARTIAL` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:index:unknown-structures",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_unknown_structure_ledger",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_unknown_structure_ledger",
    "component_type": "INDEX_BUILDER",
    "source_truth_state": "PARTIAL",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_unknown_structure_ledger",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`edis.cross-product-contract`

### عیب‌یابی

اسناد انتخاب‌شده، Source Availability، Diagnosticهای Conservation و Package Validation را بررسی کنید.

## تنظیمات عملکرد

- **شناسه فنی:** `elementor_performance_configuration`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `elementor_capabilities`
- **نوع منبع:** `elementor_options_and_features`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `PARTIAL`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `sources/elementor/performance-configuration.json`
- **Schema:** `urn:edis:schema:elementor:performance-configuration` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_feature_flags` (OPTIONAL), `elementor_installation` (REQUIRED)

### خلاصه ساده

تنظیمات ذخیره‌شده‌ای که ممکن است روی Asset و Runtime اثر بگذارد.

### این داده چیست؟

این شواهد به Python کمک می‌کند تفاوت داده منبع و مرورگر را توضیح دهد.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

از Option و Feature Configuration محدود Elementor خوانده می‌شود و Performance Benchmark اجرا نمی‌شود.

### چه فیلدهایی صادر می‌شوند؟

کلیدهای Performance مرتبط با Elementor، State یا Value نرمال‌شده، محل منبع و Entryهای ناشناخته.

### چرا برای Pipeline مهم است؟

Python این داده را Context محیط می‌داند، نه امتیاز Performance.

### Python چگونه از آن استفاده می‌کند؟

Python این داده را Context محیط می‌داند، نه امتیاز Performance.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Configuration عملکرد واقعی را ثابت نمی‌کند؛ Browser/PageSpeed Evidence لازم است و نام Optionها ممکن است میان نسخه‌ها تغییر کند.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `PARTIAL` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:elementor:performance-configuration",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_performance_configuration",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_performance_configuration",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "PARTIAL",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_performance_configuration",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.performance`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## انواع سند ثبت‌شده

- **شناسه فنی:** `elementor_registered_document_types`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `elementor_capabilities`
- **نوع منبع:** `elementor_documents_manager`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `PARTIAL`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `sources/elementor/registered-document-types.json`
- **Schema:** `urn:edis:schema:elementor:registered-document-types` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_installation` (REQUIRED), `plugin` (REQUIRED)

### خلاصه ساده

انواع سند قابل استفاده در محیط فعال Elementor.

### این داده چیست؟

Page، Post، Header، Footer، Popup، Archive و نوع‌های Addon ممکن است قرارداد متفاوت داشته باشند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

در صورت دسترسی امن از Registrationهای Document Manager در Elementor خوانده می‌شود.

### چه فیلدهایی صادر می‌شوند؟

نام Document Typeهای مشاهده‌شده، شواهد Registration و Provider، دسترسی Manager، تعداد و Provenance.

### چرا برای Pipeline مهم است؟

Python Parser و Action Mapping را بر اساس نوع مشاهده‌شده انتخاب می‌کند.

### Python چگونه از آن استفاده می‌کند؟

Python Parser و Action Mapping را بر اساس نوع مشاهده‌شده انتخاب می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Registration ثابت نمی‌کند سندی از آن Type وجود دارد یا Public Routable است.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `PARTIAL` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:elementor:registered-document-types",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_registered_document_types",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_registered_document_types",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "PARTIAL",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_registered_document_types",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.general-structure`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## Widgetهای ثبت‌شده

- **شناسه فنی:** `elementor_registered_widgets`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `elementor_capabilities`
- **نوع منبع:** `elementor_widgets_manager`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `PARTIAL`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `sources/elementor/registered-widgets.json`
- **Schema:** `urn:edis:schema:elementor:registered-widgets` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_installation` (REQUIRED), `plugin` (REQUIRED)

### خلاصه ساده

نوع Widgetهایی که Core، Pro یا Addonها ثبت کرده‌اند.

### این داده چیست؟

شواهد Registration مانع پیشنهاد Widget غیرقابل استفاده توسط Python و LLM می‌شود.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

در صورت دسترسی از Widget Manager فعال Elementor خوانده می‌شود. این داده Registration را ثبت می‌کند نه Instance رندرشده را.

### چه فیلدهایی صادر می‌شوند؟

نام Widgetهای مشاهده‌شده، شواهد Provider یا Core/Add-on، تعداد Registration، دسترسی Manager و Provenance.

### چرا برای Pipeline مهم است؟

Python Registration مشاهده‌شده را از Usage سند و انتظار نسخه‌ای جدا می‌کند.

### Python چگونه از آن استفاده می‌کند؟

Python Registration مشاهده‌شده را از Usage سند و انتظار نسخه‌ای جدا می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Widget ثبت‌شده ممکن است هیچ‌جا استفاده نشده باشد. Addonها می‌توانند Registration را در Runtime تغییر دهند، بنابراین این قرارداد میان نسخه‌ها می‌تواند PARTIAL بماند.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `PARTIAL` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:elementor:registered-widgets",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_registered_widgets",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_registered_widgets",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "PARTIAL",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_registered_widgets",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.widgets-manager`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## نقاط توقف واکنش‌گرا (Breakpoints)

- **شناسه فنی:** `elementor_breakpoints`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `elementor_core`
- **نوع منبع:** `elementor_breakpoints_manager`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `sources/elementor/breakpoints.json`
- **Schema:** `urn:edis:schema:elementor:breakpoints` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_installation` (REQUIRED)

### خلاصه ساده

مرزهای Responsive واقعی تنظیم‌شده در محیط فعال المنتور.

### این داده چیست؟

Breakpoint مرزی از عرض صفحه است که Elementor می‌تواند برای آن مقدار جدا ذخیره کند؛ این فقط شواهد تنظیمات است، نه امتیاز کیفیت.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

فقط از API فعال Breakpoint Manager خوانده می‌شود. اگر API مجموعه همه Breakpointها را برگرداند Active State نامشخص می‌ماند و Direction از روی نام Breakpoint حدس زده نمی‌شود.

### چه فیلدهایی صادر می‌شوند؟

Breakpoint ID، عنوان، شواهد Active State، مقدار عددی، واحد، Manager Order، وضعیت تأییدنشده Direction، Source Adapter و روش بازیابی.

### چرا برای Pipeline مهم است؟

Python پسوندهای Responsive را به Device واقعی وصل می‌کند، ارث‌بری را Resolve می‌کند، Snapshot مرورگر را تطبیق می‌دهد و در نبود شواهد کافی نتیجه قطعی نمی‌سازد.

### Python چگونه از آن استفاده می‌کند؟

Python پسوندهای Responsive را به Device واقعی وصل می‌کند، ارث‌بری را Resolve می‌کند، Snapshot مرورگر را تطبیق می‌دهد و در نبود شواهد کافی نتیجه قطعی نمی‌سازد.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Breakpoint فقط تنظیمات را توصیف می‌کند. اگر API عمومی Direction را ندهد این فیلد UNVERIFIED باقی می‌ماند و برای مقایسه به Viewport Observation مرورگر نیاز است.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:elementor:breakpoints",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_breakpoints",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_breakpoints",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_breakpoints",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.breakpoints-manager`, `elementor.responsive-data`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## قابلیت‌ها و آزمایش‌های Elementor

- **شناسه فنی:** `elementor_feature_flags`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `elementor_core`
- **نوع منبع:** `elementor_experiments_manager_and_options`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `PARTIAL`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `sources/elementor/feature-flags.json`
- **Schema:** `urn:edis:schema:elementor:feature-flags` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_installation` (REQUIRED)

### خلاصه ساده

Feature Flagها نشان می‌دهند کدام بخش‌های ویرایشگر فعال‌اند.

### این داده چیست؟

وضعیت Feature و Experiment برای Atomic Editor، Breakpoint، Variable و Class شواهد منبع فراهم می‌کند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

از Option و Config محدود Elementor خوانده می‌شود. مقدار مشاهده‌شده حفظ می‌شود و انتظار مبتنی بر نسخه به واقعیت مشاهده‌شده تبدیل نمی‌شود.

### چه فیلدهایی صادر می‌شوند؟

کلیدهای Feature و Experiment مشاهده‌شده، مقدار ذخیره‌شده، حالت نرمال‌شده، محل منبع و Entryهای ناشناخته.

### چرا برای Pipeline مهم است؟

Python وضعیت مشاهده‌شده را از انتظار مبتنی بر نسخه جدا نگه می‌دارد.

### Python چگونه از آن استفاده می‌کند؟

Python وضعیت مشاهده‌شده را از انتظار مبتنی بر نسخه جدا نگه می‌دارد.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

محل ذخیره و نام Featureها ممکن است میان نسخه‌های Elementor تغییر کند؛ کلید ناشناخته حفظ می‌شود و تا تکمیل Fixtureها این Component می‌تواند PARTIAL بماند.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `PARTIAL` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:elementor:feature-flags",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_feature_flags",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_feature_flags",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "PARTIAL",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_feature_flags",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.experiments`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## نصب المنتور

- **شناسه فنی:** `elementor_installation`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `elementor_core`
- **نوع منبع:** `elementor_constants_and_public_api`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `sources/elementor/installation.json`
- **Schema:** `urn:edis:schema:elementor:installation` نسخه `1.0.0`
- **وابستگی‌ها:** `environment` (REQUIRED), `plugin` (REQUIRED)

### خلاصه ساده

شواهد نسخه و محصول المنتور.

### این داده چیست؟

این داده مشخص می‌کند کدام قراردادهای منبع ممکن است در سایت وجود داشته باشند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

از Constantهای افزونه و Registration مشاهده‌شده در وردپرس خوانده می‌شود و سرویس نسخه Remote فراخوانی نمی‌شود.

### چه فیلدهایی صادر می‌شوند؟

وجود Elementor Core و Pro، نسخه‌های نصب‌شده، وضعیت فعال‌بودن و فراداده سازگاری Adapter.

### چرا برای Pipeline مهم است؟

Python Parser سازگار را انتخاب می‌کند و نسخه‌های ناشناخته را صادقانه علامت می‌زند.

### Python چگونه از آن استفاده می‌کند؟

Python Parser سازگار را انتخاب می‌کند و نسخه‌های ناشناخته را صادقانه علامت می‌زند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

بالابودن شماره نسخه فقط شواهد نسخه است و فعال‌بودن Variables، Atomic Editor، Interactions یا قابلیت Addon را ثابت نمی‌کند.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:elementor:installation",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_installation",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_installation",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_installation",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.data-structure`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## اطلاعات Kit فعال

- **شناسه فنی:** `elementor_kit_metadata`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `elementor_design_system`
- **نوع منبع:** `wordpress_option_and_post_meta`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `sources/elementor/kit-metadata.json`
- **Schema:** `urn:edis:schema:elementor:kit-metadata` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_installation` (REQUIRED)

### خلاصه ساده

Kit فعال محل اصلی بسیاری از تنظیمات سراسری Elementor است.

### این داده چیست؟

شناسه Kit به Python اجازه می‌دهد Referenceهای صفحه را به Registry درست وصل کند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

از API کیت فعال Elementor یا Option ذخیره‌شده Active Kit خوانده و در صورت دسترسی با Post Metadata وردپرس تطبیق داده می‌شود.

### چه فیلدهایی صادر می‌شوند؟

Document ID کیت فعال به‌صورت String، Post Status و Type، Candidateهای Saved Source Hash، شواهد Storage و Provenance انتخاب Kit.

### چرا برای Pipeline مهم است؟

Python از آن برای تعیین محدوده Global Styles، Site Settings و Design System استفاده می‌کند.

### Python چگونه از آن استفاده می‌کند؟

Python از آن برای تعیین محدوده Global Styles، Site Settings و Design System استفاده می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

وجود Kit ID به‌معنی وجود همه Global Settingها یا Registryها در آن Kit نیست.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:elementor:kit-metadata",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_kit_metadata",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_kit_metadata",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_kit_metadata",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.global-styles`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## تنظیمات Kit المنتور

- **شناسه فنی:** `elementor_kit_settings`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `elementor_design_system`
- **نوع منبع:** `wordpress_post_meta`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `sources/elementor/kit-settings.json`
- **Schema:** `urn:edis:schema:elementor:kit-settings` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_kit_metadata` (REQUIRED)

### خلاصه ساده

تنظیمات خام ذخیره‌شده Kit فعال.

### این داده چیست؟

Kit Settings شامل Declarationهای سراسری است که سندها به آن‌ها Reference می‌دهند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

از Post Meta کیت فعال خوانده می‌شود. Strict Mode کلیدهای شبیه Code، Tracking، URL، Email، API، Token، Nonce یا Secret را حذف می‌کند.

### چه فیلدهایی صادر می‌شوند؟

کلیدها و مقادیر ذخیره‌شده `_elementor_page_settings` برای Active Kit، Kit ID، Source Path، Privacy Filtering و Provenance.

### چرا برای Pipeline مهم است؟

Python Referenceها را Resolve می‌کند و مقدار ذخیره‌شده و مسیر Property را حفظ می‌کند.

### Python چگونه از آن استفاده می‌کند؟

Python Referenceها را Resolve می‌کند و مقدار ذخیره‌شده و مسیر Property را حفظ می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

کلیدهای مخصوص Addon یا نسخه آینده ممکن است ناشناخته باشند. در صورت اجازه Privacy، مقدار به‌عنوان Source Evidence حفظ می‌شود و به UX Fact تبدیل نمی‌شود.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:elementor:kit-settings",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_kit_settings",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_kit_settings",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_kit_settings",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.global-styles`, `elementor.site-settings`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## ترتیب کلاس‌های سراسری

- **شناسه فنی:** `elementor_global_classes_order`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `elementor_design_system`
- **نوع منبع:** `wordpress_post_meta_source_backed`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `PARTIAL`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `sources/elementor/global-classes-order.json`
- **Schema:** `urn:edis:schema:elementor:global-classes-order` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_global_classes_registry` (REQUIRED)

### خلاصه ساده

فهرست ترتیب ذخیره‌شده Global Classes.

### این داده چیست؟

برای بازسازی اولویت احتمالی به شواهد ترتیب نیاز است.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

از Storage ترتیب Global Classes در Elementor خوانده و جدا از تعریف Classها صادر می‌شود.

### چه فیلدهایی صادر می‌شوند؟

Class IDهای مرتب‌شده ذخیره‌شده، Storage Key، Diagnosticهای Duplicate یا Missing Order، تعداد رکورد و Provenance.

### چرا برای Pipeline مهم است؟

Python ترتیب Registry را از ترتیب اتصال به عنصر و Cascade مرورگر جدا نگه می‌دارد.

### Python چگونه از آن استفاده می‌کند؟

Python ترتیب Registry را از ترتیب اتصال به عنصر و Cascade مرورگر جدا نگه می‌دارد.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Registry Order الزاماً با ترتیب Classهای متصل به یک Element یا Cascade نهایی Runtime یکسان نیست.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `PARTIAL` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:elementor:global-classes-order",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_global_classes_order",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_global_classes_order",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "PARTIAL",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_global_classes_order",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.global-classes-order`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## Registry کلاس‌های سراسری

- **شناسه فنی:** `elementor_global_classes_registry`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `elementor_design_system`
- **نوع منبع:** `elementor_global_classes_storage`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `PARTIAL`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `sources/elementor/global-classes.json`
- **Schema:** `urn:edis:schema:elementor:global-classes` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_feature_flags` (OPTIONAL), `elementor_installation` (REQUIRED)

### خلاصه ساده

تعریف کلاس‌های قابل استفاده مجدد در Design System المنتور.

### این داده چیست؟

Classها Declarationهای قابل استفاده مجدد را جدا از تنظیمات Local عنصر نگه می‌دارند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

از Storage محدود و مشاهده‌شده Global Classes خوانده می‌شود. Class Declaration از Local Declaration عنصر و مقدار Computed Runtime جدا می‌ماند.

### چه فیلدهایی صادر می‌شوند؟

Global Class IDها، Declarationهای ذخیره‌شده، Registry Metadata، Candidateهای Source Storage، فیلدهای ناشناخته و Diagnostics.

### چرا برای Pipeline مهم است؟

Python Reference کلاس‌ها و ترتیب Registry و اتصال را بررسی می‌کند بدون اینکه اولویت اثبات‌نشده بسازد.

### Python چگونه از آن استفاده می‌کند؟

Python Reference کلاس‌ها و ترتیب Registry و اتصال را بررسی می‌کند بدون اینکه اولویت اثبات‌نشده بسازد.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

اولویت Class فقط از Registry قطعی نمی‌شود؛ Attachment Order، Local Declaration و Browser Evidence نیز لازم است.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `PARTIAL` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:elementor:global-classes",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_global_classes_registry",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_global_classes_registry",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "PARTIAL",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_global_classes_registry",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.global-classes`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## استایل‌های سراسری کلاسیک

- **شناسه فنی:** `elementor_legacy_global_styles`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `elementor_design_system`
- **نوع منبع:** `wordpress_post_meta`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `sources/elementor/legacy-global-styles.json`
- **Schema:** `urn:edis:schema:elementor:legacy-global-styles` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_kit_settings` (REQUIRED)

### خلاصه ساده

Registry کلاسیک رنگ و Typography سراسری Elementor.

### این داده چیست؟

سندهای کلاسیک معمولاً فقط Referenceهای __globals__ را نگه می‌دارند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

از تنظیمات ذخیره‌شده Active Kit خوانده می‌شود و Referenceهای `__globals__` سند به‌صورت شواهد جدا باقی می‌مانند.

### چه فیلدهایی صادر می‌شوند؟

Registryهای قدیمی Global Color و Typography، ID، Label، مقدار ذخیره‌شده، Source Path کیت فعال و Diagnosticهای Reference حل‌نشده.

### چرا برای Pipeline مهم است؟

Python Referenceهای سند را به رکوردهای Kit وصل می‌کند و IDهای Resolveنشده را گزارش می‌دهد.

### Python چگونه از آن استفاده می‌کند؟

Python Referenceهای سند را به رکوردهای Kit وصل می‌کند و IDهای Resolveنشده را گزارش می‌دهد.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Legacy Global و Atomic Variables/Classes دو سیستم Source متفاوت‌اند و افزونه نباید آن‌ها را بی‌صدا یکی کند.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:elementor:legacy-global-styles",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_legacy_global_styles",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_legacy_global_styles",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_legacy_global_styles",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.global-styles`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## Registry متغیرها

- **شناسه فنی:** `elementor_variables_registry`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `elementor_design_system`
- **نوع منبع:** `elementor_variables_storage`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `PARTIAL`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `sources/elementor/variables.json`
- **Schema:** `urn:edis:schema:elementor:variables` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_feature_flags` (OPTIONAL), `elementor_installation` (REQUIRED)

### خلاصه ساده

مقادیر قابل استفاده مجدد در سیستم Variables المنتور.

### این داده چیست؟

Variableها ممکن است توسط Styleهای Atomic و Design System استفاده شوند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

از قراردادهای Storage مشاهده‌شده Variables در Elementor خوانده و بدون Resolve کردن Reference در Property عنصر حفظ می‌شود.

### چه فیلدهایی صادر می‌شوند؟

رکوردهای Data Registry، Version، Watermark، Variable ID، مقدار و Type ذخیره‌شده در صورت دسترسی، Candidateهای Storage Key و Diagnostics.

### چرا برای Pipeline مهم است؟

Python Reference متغیرها را Resolve می‌کند، Reference گمشده را گزارش می‌دهد و Version و Watermark را حفظ می‌کند.

### Python چگونه از آن استفاده می‌کند؟

Python Reference متغیرها را Resolve می‌کند، Reference گمشده را گزارش می‌دهد و Version و Watermark را حفظ می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Variables به نسخه حساس است و ممکن است در محیط قدیمی یا غیر Atomic وجود نداشته باشد. رکورد Registry اعمال‌شدن در Runtime را ثابت نمی‌کند.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `PARTIAL` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:elementor:variables",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_variables_registry",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_variables_registry",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "PARTIAL",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_variables_registry",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.variables-storage`, `elementor.design-system`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## Referenceهای Dynamic و Global

- **شناسه فنی:** `elementor_dynamic_references`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `elementor_documents`
- **نوع منبع:** `elementor_saved_document_references`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `PARTIAL`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `sources/elementor/dynamic-references.json`
- **Schema:** `urn:edis:schema:elementor:dynamic-references` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_document_source` (REQUIRED)

### خلاصه ساده

Referenceهای ذخیره‌شده مانند __globals__، Variable، Class و امضای امن Dynamic Tag.

### این داده چیست؟

Referenceها Declaration محلی را بدون کپی مقدار حساس به Registry وصل می‌کنند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

یک Scan بازگشتی محدود، Settingهای ذخیره‌شده را برای Global، Variable، CSS Custom Property، Dynamic Tag و Class Reference Candidate بررسی می‌کند بدون صدور Configuration حساس.

### چه فیلدهایی صادر می‌شوند؟

نوع Reference، شناسه سند/Element، Property Path، Binding Order، Style یا Registry ID، Hash امن و Raw Value محدود فقط در موارد مجاز.

### چرا برای Pipeline مهم است؟

Python Registryهای شناخته‌شده را Resolve می‌کند، Reference گمشده را حفظ می‌کند و Expression را با مقدار Runtime اشتباه نمی‌گیرد.

### Python چگونه از آن استفاده می‌کند؟

Python Registryهای شناخته‌شده را Resolve می‌کند، Reference گمشده را حفظ می‌کند و Expression را با مقدار Runtime اشتباه نمی‌گیرد.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Reference Candidate مقدار Resolveشده نیست. محتوای Dynamic Tag می‌تواند خصوصی باشد، بنابراین Raw Configuration حذف می‌شود.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `PARTIAL` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:elementor:dynamic-references",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_dynamic_references",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_dynamic_references",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "PARTIAL",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_dynamic_references",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.global-styles`, `elementor.dynamic-tags`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## فهرست اسناد المنتور

- **شناسه فنی:** `elementor_document_inventory`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `elementor_documents`
- **نوع منبع:** `wordpress_query_and_post_meta`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `sources/elementor/document-inventory.json`
- **Schema:** `urn:edis:schema:elementor:document-inventory` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_installation` (REQUIRED), `elementor_registered_document_types` (OPTIONAL)

### خلاصه ساده

فهرست محدود اسناد منبع و اطلاعات هویتی امن.

### این داده چیست؟

Inventory نقطه شروع انتخاب سند و Match منبع با Runtime است.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

با Query محدود و صفحه‌بندی‌شده WordPress و بررسی Metadata Elementor خوانده می‌شود و Source سند را کپی نمی‌کند.

### چه فیلدهایی صادر می‌شوند؟

Document ID به‌صورت String، Title Hash یا Label محدود مطابق Privacy، Document Type، Post Status، Permission ویرایش، وجود Elementor Data، Routability Candidate و Result Count محدود.

### چرا برای Pipeline مهم است؟

Python از Fingerprint، Hash منبع، نوع سند و Locator Candidate برای تطبیق Runtime استفاده می‌کند.

### Python چگونه از آن استفاده می‌کند؟

Python از Fingerprint، Hash منبع، نوع سند و Locator Candidate برای تطبیق Runtime استفاده می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Inventory محدود و وابسته به Permission است. نبود رکورد می‌تواند ناشی از Query Limit، Status Filter یا Access Restriction باشد نه نبود سند.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:elementor:document-inventory",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_document_inventory",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_document_inventory",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_document_inventory",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.general-structure`, `wordpress.posts`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## منبع اسناد انتخاب‌شده

- **شناسه فنی:** `elementor_document_source`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `elementor_documents`
- **نوع منبع:** `wordpress_post_meta`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `sources/elementor/documents/selected-documents.json`
- **Schema:** `urn:edis:schema:elementor:document-source` نسخه `1.0.0`
- **وابستگی‌ها:** `elementor_document_inventory` (REQUIRED)

### خلاصه ساده

درخت بازگشتی عناصر ذخیره‌شده Elementor که برای تحلیل انتخاب شده‌اند.

### این داده چیست؟

اسناد منبع ساختار V3، Container، Atomic و Hybrid را نگه می‌دارند و Field ناشناخته را حذف نمی‌کنند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

در زمان ساخت Job یک‌بار در Private Storage محافظت‌شده Capture و از نظر Drift بررسی می‌شود؛ سپس Worker فقط همان Snapshot تغییرناپذیر را می‌خواند و بین مراحل به Source زنده برنمی‌گردد.

### چه فیلدهایی صادر می‌شوند؟

Document ID/Type/Status، هویت Immutable Job Snapshot، درخت ذخیره‌شده Captureشده، Page Settings، Saved Source SHA-256، وضعیت Include شدن Source اصلی و Source Pathها.

### چرا برای Pipeline مهم است؟

Python عناصر، Settings، Styles، Interactions، Editor Metadata، Responsive Declaration و Referenceها را Parse می‌کند.

### Python چگونه از آن استفاده می‌کند؟

Python عناصر، Settings، Styles، Interactions، Editor Metadata، Responsive Declaration و Referenceها را Parse می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

Saved Source همان DOM رندرشده نیست. Snapshot فقط Source اسناد انتخاب‌شده را Freeze می‌کند و Registryهای محیط Timestamp مستقل خود را دارند.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:elementor:document-source",
  "schema_version": "1.0.0",
  "artifact_type": "elementor_document_source",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "elementor_document_source",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "elementor_document_source",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`elementor.page-content`, `elementor.atomic-elements`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## فهرست افزونه‌ها

- **شناسه فنی:** `plugin`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `wordpress`
- **نوع منبع:** `wordpress_public_api`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `environment/plugins.json`
- **Schema:** `urn:edis:schema:wordpress:plugins` نسخه `1.0.0`
- **وابستگی‌ها:** `environment` (REQUIRED)

### خلاصه ساده

واقعیت‌های افزونه‌های فعال.

### این داده چیست؟

این فهرست افزونه‌هایی را مشخص می‌کند که ممکن است Widget، Control یا Document Type جدید ثبت کنند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

از APIهای افزونه‌های وردپرس خوانده می‌شود. Source Code، تنظیمات، License Key یا اطلاعات محرمانه افزونه صادر نمی‌شود.

### چه فیلدهایی صادر می‌شوند؟

Plugin Basename، نام، نسخه، وضعیت فعال‌بودن، شواهد Network Activation و Provenance محدود برای تشخیص Addonها.

### چرا برای Pipeline مهم است؟

Python می‌تواند منشأ Addonها را تشخیص دهد و همه عناصر را به Elementor Core نسبت ندهد.

### Python چگونه از آن استفاده می‌کند؟

Python می‌تواند منشأ Addonها را تشخیص دهد و همه عناصر را به Elementor Core نسبت ندهد.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

فعال‌بودن Addon ثابت نمی‌کند Widgetهای آن در اسناد انتخاب‌شده استفاده شده‌اند.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:wordpress:plugins",
  "schema_version": "1.0.0",
  "artifact_type": "plugin",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "plugin",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "plugin",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`wordpress.plugins`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## اطلاعات پوسته

- **شناسه فنی:** `theme`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `wordpress`
- **نوع منبع:** `wordpress_public_api`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `environment/theme.json`
- **Schema:** `urn:edis:schema:wordpress:theme` نسخه `1.0.0`
- **وابستگی‌ها:** `environment` (REQUIRED)

### خلاصه ساده

اطلاعات پوسته که روی منبع و رندر اثر می‌گذارد.

### این داده چیست؟

پوسته فعال می‌تواند Template، Style و رفتار Layout اضافه کند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

از APIهای Theme وردپرس خوانده می‌شود. فایل‌های Theme یا تنظیمات محرمانه Customizer کپی نمی‌شوند.

### چه فیلدهایی صادر می‌شوند؟

نام Theme فعال، Stylesheet، Template، نسخه، هویت Parent Theme و Provenance محدود Theme.

### چرا برای Pipeline مهم است؟

Python از هویت پوسته برای Provenance و محدودیت سازگاری استفاده می‌کند.

### Python چگونه از آن استفاده می‌کند؟

Python از هویت پوسته برای Provenance و محدودیت سازگاری استفاده می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

هویت Theme مشخص نمی‌کند کدام CSS Rule مقدار رندرشده را ساخته است؛ شواهد مرورگر لازم است.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:wordpress:theme",
  "schema_version": "1.0.0",
  "artifact_type": "theme",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "theme",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "theme",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`wordpress.themes`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

## محیط وردپرس

- **شناسه فنی:** `environment`
- **نوع جزء:** `SOURCE_COLLECTOR`
- **گروه:** `wordpress`
- **نوع منبع:** `wordpress_public_api`
- **وضعیت پیاده‌سازی:** `real`
- **Source Truth اعلام‌شده:** `VERIFIED`
- **Source Availability پیش‌فرض:** `AVAILABLE`
- **مسیر Artifact:** `environment/wordpress.json`
- **Schema:** `urn:edis:schema:wordpress:environment` نسخه `1.0.0`
- **وابستگی‌ها:** ندارد

### خلاصه ساده

اطلاعات محیطی که برای تفسیر همه خروجی‌ها لازم است.

### این داده چیست؟

نسخه‌ها و قابلیت‌های محیط که روی قراردادهای داده اثر می‌گذارند.

### داده کجا قرار دارد و EDIS چگونه آن را می‌خواند؟

از APIهای عمومی Runtime وردپرس خوانده می‌شود. آدرس‌ها به‌صورت واقعیت محدود یا Hash صادر می‌شوند و Credential، Query و Fragment وارد خروجی نمی‌شود.

### چه فیلدهایی صادر می‌شوند؟

نسخه WordPress و PHP، Locale، Timezone، وضعیت Multisite و Debug، Memory Limit، Hashهای Privacy-safe آدرس و Site Path Scope.

### چرا برای Pipeline مهم است؟

Python با این داده نسخه Schema، سازگاری و قابلیت‌های قابل استفاده را تعیین می‌کند.

### Python چگونه از آن استفاده می‌کند؟

Python با این داده نسخه Schema، سازگاری و قابلیت‌های قابل استفاده را تعیین می‌کند.

### چه چیزی به مدل زبانی می‌رسد؟

مدل زبانی فقط نتیجه‌ای را می‌بیند که Python از این شواهد استخراج کرده است و اجازه ندارد نبود داده را به واقعیت تبدیل کند.

### چه نتیجه‌ای نمی‌توان گرفت؟

این داده تنظیمات ذخیره‌شده را توصیف می‌کند، نه کیفیت نهایی رندر یا تجربه کاربری. برای نتیجه Runtime به شواهد مرورگر و Resolver پایتون نیاز است.

### محدودیت نسخه

نسخه یا مشخصات محیط به‌تنهایی ثابت نمی‌کند یک قابلیت Elementor درست کار می‌کند؛ Capability و شواهد منبع باید آن را تأیید کنند.

### اثر حریم خصوصی

این جزء از صادرکردن رمز، توکن، Cookie، Nonce، مقدار فرم و محتوای نامحدود خودداری می‌کند و Privacy Mode می‌تواند داده را محدودتر کند.

### تفسیر Availability و Truth State

Truth State اعلام‌شده `VERIFIED` و Availability پیش‌فرض `AVAILABLE` است. خروجی واقعی می‌تواند بر اساس وابستگی‌های `REQUIRED`، `OPTIONAL` و `CONDITIONAL` تنزل پیدا کند. داده خالی هرگز به‌صورت خاموش موفق تلقی نمی‌شود.

### نمونه Envelope خروجی

```json
{
  "schema_id": "urn:edis:schema:wordpress:environment",
  "schema_version": "1.0.0",
  "artifact_type": "environment",
  "producer": {
    "product": "edis-evidence-exporter",
    "version": "3.7.11"
  },
  "captured_at": "2026-06-14T00:00:00Z",
  "canonicalization": {
    "profile": "EDIS-CJ-2",
    "hash_algorithm": "sha256"
  },
  "data": {
    "component_id": "environment",
    "component_type": "SOURCE_COLLECTOR",
    "source_truth_state": "VERIFIED",
    "source_availability": "AVAILABLE",
    "evidence": "«محموله تایپ‌شده جزء»",
    "source_references": [],
    "provenance": {
      "collector_id": "environment",
      "adapter_id": "<adapter>"
    }
  },
  "diagnostics": [],
  "semantic_payload_sha256": "sha256:<semantic digest>",
  "artifact_instance_sha256": "sha256:<instance digest>"
}
```

### منابع رسمی و قرارداد

`wordpress.version`, `php.version`

### عیب‌یابی

وضعیت Source Availability، Diagnostics، فعال‌بودن و نسخه المنتور، اسناد انتخاب‌شده و وابستگی‌های جزء را بررسی کنید.

