# Migration Guide — EDIS 3.7.5 to 3.7.6

source_version: 3.7.5  
target_version: 3.7.6  
contract_change: none  
public_schema_change: none

Version 3.7.6 is a local-convenience hardening release. It keeps frozen public evidence contracts unchanged and improves LocalWP/private-storage activation behavior.

## What changed

- In `WP_ENVIRONMENT_TYPE=local`, EDIS can auto-provision a protected sibling directory outside the public WordPress root, usually `dirname(ABSPATH) . /edis-private-storage`.
- Activation in local mode no longer aborts solely because storage preflight is unavailable or not yet writable. The plugin activates into fail-closed diagnostic mode and exports remain disabled until storage passes.
- If the independent-process lock probe is unavailable in local mode, EDIS may accept `PASS_LOCAL_SINGLE_PROCESS` only when same-process locking, atomic writes, durable writes, atomic rename/replace and cleanup pass. A real multiprocess lock failure is still blocking.
- Paths inside `ABSPATH`, including `app/public` and `wp-content`, are still rejected.

## Recommended LocalWP configuration

If your WordPress root is:

```text
E:/Nuuro Local/app/public
```

Use either no explicit storage constant, or this explicit constant in `wp-config.php` before `/* That's all, stop editing! */`:

```php
define( 'EDIS_EVIDENCE_PRIVATE_STORAGE_DIR', dirname( __DIR__ ) . '/edis-private-storage' );
```

Do not use:

```php
define( 'EDIS_EVIDENCE_PRIVATE_STORAGE_DIR', 'E:/Nuuro Local/app/public' );
```

## Rollback

Rollback does not rewrite 3.7.6 jobs into older job formats. Stop workers, deactivate the plugin, remove the complete plugin directory, restore the prior complete plugin version and create a fresh job if needed.

# راهنمای مهاجرت — EDIS 3.7.5 به 3.7.6

نسخهٔ 3.7.6 قرارداد frozen و Schemaهای عمومی را تغییر نمی‌دهد. تمرکز این نسخه راحت‌تر کردن استفاده در LocalWP است، بدون اینکه اجازهٔ نوشتن داخل `app/public` داده شود.

اگر ریشهٔ وردپرس شما این باشد:

```text
E:/Nuuro Local/app/public
```

مسیر امن پیشنهادی این است:

```text
E:/Nuuro Local/app/edis-private-storage
```

در `wp-config.php` می‌توانید این را بگذارید:

```php
define( 'EDIS_EVIDENCE_PRIVATE_STORAGE_DIR', dirname( __DIR__ ) . '/edis-private-storage' );
```

در حالت local، اگر storage آماده نباشد، افزونه دیگر فقط به همین دلیل فعال‌سازی را متوقف نمی‌کند؛ فعال می‌شود، ولی Exportها تا زمان پاس‌شدن تست storage غیرفعال می‌مانند.
