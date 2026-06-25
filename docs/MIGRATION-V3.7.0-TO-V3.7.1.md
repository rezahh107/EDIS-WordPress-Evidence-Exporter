# Migration: EDIS Evidence Exporter 3.7.0 to 3.7.1

## Scope

Version 3.7.1 is a WordPress runtime and standards hardening release. It does **not** change Bundle Schema `3.3.0`, Shared Envelope `2.0.0`, Selection Snapshot `1.2.0`, EDIS-CJ-2 or EDIS-ZIP-1. Package Manifest Schema advances from `2.0.0` to `2.1.0` to remove release-specific plugin-version constants and accept a versioned SemVer producer field.

Private Job Format changes from `2.0.0` to `2.1.0` to add explicit worker leases. Private Input Snapshot remains `2.0.0`.

## Before upgrading

1. Download any completed packages that must be retained.
2. Record or cancel queued/running 3.7.0 jobs; they cannot be resumed under Job Format `2.1.0`.
3. Back up the database and configured private-storage root.
4. In multisite, record whether the plugin is site-active or network-active.
5. Review **Retain evidence on uninstall**. Upgrade does not run uninstall.

## Upgrade

1. Replace plugin files through the normal WordPress deployment process.
2. Activate the plugin or network-activate it as appropriate.
3. Open **Tools → Site Health** and run the asynchronous EDIS storage check.
4. Run `wp edis storage self-test` in deployments that use WP-CLI.
5. Confirm `wp edis worker status` reports no unexpected stale jobs.
6. Create a new bounded export and verify download SHA-256.

## Multisite storage change

Version 3.7.1 namespaces configured private storage by network and blog ID. The plugin does not automatically assign ambiguous files from the pre-3.7.1 shared multisite root to a site. Treat completed old bundles as historical artifacts and create new jobs per site. Do not manually copy Job, token or lease records between sites.

## Rollback

Rolling code back to 3.7.0 does not convert Job Format `2.1.0` to `2.0.0`. Cancel or allow cleanup of incomplete 3.7.1 jobs, restore the prior code through the deployment system and create new jobs under the active version. Public Bundle 3.3.0 artifacts remain historically valid according to their own package validation; they are not rewritten.

## Verification state

A successful file replacement is not proof of runtime compatibility. Require executed PHP matrix, WordPress activation, Plugin Check, Site Health, multisite lifecycle, Elementor smoke and worker recovery results before declaring a production release.

---

# راهنمای مهاجرت EDIS از 3.7.0 به 3.7.1

## دامنه تغییر

نسخه 3.7.1 برای سخت‌گیری Runtime وردپرس و استانداردهاست. Bundle Schema `3.3.0`، Shared Envelope `2.0.0`، Selection Snapshot `1.2.0`، EDIS-CJ-2 و EDIS-ZIP-1 تغییر نکرده‌اند. Package Manifest Schema از `2.0.0` به `2.1.0` ارتقا یافته تا Const وابسته به نسخه افزونه حذف و Producer SemVer به‌صورت نسخه‌بندی‌شده پذیرفته شود.

Job Format خصوصی از `2.0.0` به `2.1.0` ارتقا یافته تا Lease صریح Worker ثبت شود. Input Snapshot خصوصی همچنان `2.0.0` است.

## پیش از Upgrade

1. Bundleهای کامل موردنیاز را دانلود کنید.
2. Jobهای Queue/Running نسخه 3.7.0 را ثبت یا لغو کنید؛ Resume آن‌ها در 3.7.1 مجاز نیست.
3. از Database و Private Storage پشتیبان بگیرید.
4. در Multisite، Site Activation یا Network Activation بودن افزونه را ثبت کنید.
5. گزینه نگه‌داری Evidence هنگام Uninstall را بررسی کنید؛ Upgrade باعث Uninstall نمی‌شود.

## پس از Upgrade

1. افزونه را طبق روش عادی Deploy و فعال کنید.
2. در Multisite نوع Activation قبلی را حفظ کنید.
3. از **ابزارها ← سلامت سایت** تست Async مربوط به EDIS Storage را اجرا کنید.
4. در محیط دارای WP-CLI، فرمان `wp edis storage self-test` را اجرا کنید.
5. با `wp edis worker status` وضعیت Jobها را بررسی کنید.
6. یک Export جدید بسازید و SHA-256 دانلود را تأیید کنید.

## تغییر Multisite

نسخه 3.7.1 Storage را با Network ID و Blog ID جدا می‌کند. فایل‌های مبهم ریشه مشترک نسخه قبل به‌صورت خودکار به Site خاص نسبت داده نمی‌شوند. Bundleهای کامل قبلی را Historical Artifact در نظر بگیرید و برای هر Site Job جدید بسازید. فایل Job، Token یا Lease را دستی بین Siteها کپی نکنید.

## Rollback

Rollback کد، Job Format `2.1.0` را به `2.0.0` تبدیل نمی‌کند. Job ناقص را لغو یا Cleanup کنید، نسخه قبلی را از مسیر Deployment برگردانید و Job تازه بسازید. Bundle عمومی 3.3.0 مطابق Validation خودش Historical Artifact باقی می‌ماند و بازنویسی نمی‌شود.
