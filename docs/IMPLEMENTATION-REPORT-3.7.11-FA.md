# گزارش پیاده‌سازی EDIS 3.7.11

## وضعیت

```text
release_type: validation_evidence_corrective
runtime_feature_change: false
frozen_contract_change: false
repository_ready: true
production_ready_verified: false
```

## یافته‌های اصلاح‌شده

### EDIS-VAL-001 — ترفیع نادرست اجرای ناقص به PASS

در 3.7.10، خلاصه فقط Gateهای دارای `FAIL` را بررسی می‌کرد. بنابراین `--skip-npm`، `--skip-build` یا نبود ابزار محلی می‌توانست در کنار Gateهای `NOT_RUN` همچنان `local_state=PASS` تولید کند.

در 3.7.11 تمام Gateهای محلی الزامی باید صریحاً `PASS` باشند؛ در غیر این صورت وضعیت `INCOMPLETE` یا `FAIL` است.

### EDIS-VAL-002 — خطر قفل Pipe در فرمان پرخروجی

خواندن متوالی stdout و stderr می‌توانست هنگامی که یکی از Pipeها پر می‌شود، فرایند والد و فرزند را متوقف کند. خروجی اکنون در فایل‌های خصوصی موقت نوشته می‌شود و پس از پایان فرایند فقط SHA-256، شمار بایت و Tail محدود خوانده می‌شود.

### EDIS-VAL-003 — ثبت تأییدنشدهٔ فایل Evidence

نوشتن مستقیم گزارش بدون بررسی نتیجه می‌توانست شکست write را پنهان کند. گزارش اکنون در فایل موقت هم‌دایرکتوری نوشته، flush/fsync، جایگزین و با SHA-256 بررسی می‌شود.

### EDIS-VAL-004 — اختلاط وضعیت محلی و خارجی

خلاصهٔ 3.7.10 تمام `NOT_RUN`ها را در یک مجموعه قرار می‌داد. 3.7.11 وضعیت‌های `local_state` و `external_state`، فهرست Gateهای محلی ناقص و Gateهای خارجی حل‌نشده را جدا می‌کند.

### EDIS-VAL-005 — ناپایداری PHP lint در زنجیرهٔ چندفرایندی

اجرای صدها فرمان `php -l` در همان فرایند Runner می‌توانست در بعضی محیط‌ها منابع child process را انباشته و Gate بعدی را متوقف کند. 3.7.11 تمام PHP lint را در یک فرایند مستقل اجرا می‌کند و فقط گزارش ساختاریافته و اتمیک آن را به Runner بازمی‌گرداند.

## عدم تغییر

- Bundle Schema: `3.3.0`
- Shared Artifact Envelope: `2.0.0`
- Package Manifest: `2.1.0`
- Selection Snapshot: `1.2.0`
- Canonical JSON: `EDIS-CJ-2`
- ZIP profile: `EDIS-ZIP-1`
- ZIP64: ممنوع
- رفتار Runtime نصب‌شده: بدون قابلیت جدید

## محدودیت‌های باقی‌مانده

Composer، WordPress واقعی، Plugin Check، Elementor واقعی، Windows/LocalWP و Python ingestion تا زمان اجرای معتبر، `NOT_RUN`، `BLOCKED_EXTERNAL` یا `insufficient_evidence` باقی می‌مانند.
