# Security Policy

## Supported release

EDIS Evidence Exporter 3.7.11 is the currently supported validation-hardening release in this package. Older development handoff builds should be treated as historical evidence only unless their release checksums and installation integrity pass.

## Capability model

The plugin uses the custom WordPress capability `edis_export_evidence` for its admin screens, REST endpoints, diagnostics and download controllers. Document-scoped export actions additionally require `edit_post` for every selected WordPress/Elementor document. Existing jobs are owner-bound and are rechecked against document permissions before status, resume, retry, cancel or download operations.

`manage_options` may be used by WordPress administrators to grant or manage the custom capability, but it is not the direct authorization rule for normal EDIS REST and admin operations.

## Nonce policy

WordPress nonces are used only for CSRF protection on admin/download flows. They are never treated as authentication or authorization. Every sensitive request also requires the capability and object-level authorization checks above.

## Private storage assumptions

EDIS requires direct local filesystem semantics for deterministic evidence storage: durable writes, atomic replace/rename, advisory locks and a separate PHP-process lock-exclusion probe must pass on the active private-storage path. FTP/SSH-style filesystem abstractions are not accepted for background evidence creation because they cannot prove the byte-level and lock-level guarantees required by EDIS-ZIP and EDIS-CJ.

The private-storage directory must be outside the public WordPress web root and must not be a symlink or be redirected through a symlink ancestor. Export creation fails closed when this cannot be proven; diagnostics remain accessible when degraded mode is available.

## Report a vulnerability

Report security issues privately. Include the plugin version, PHP version, WordPress version, multisite state, storage mode, reproduction steps, relevant diagnostics, and SHA-256 of any package involved. Do not include private exported evidence unless explicitly requested and redacted.

## In scope

- Authorization bypass, owner-binding bypass or cross-site/cross-user export access.
- Download token replay, expiry or disclosure defects.
- Direct access to private storage or generated bundles.
- Evidence corruption, mixed-version installation acceptance or invalid package acceptance.
- XSS, CSRF, unsafe REST schema handling, unsafe admin output, and privacy exporter/eraser boundary failures.

## Out of scope unless paired with a concrete exploit

- Generic scanner reports without a reproduction path.
- Findings requiring modified frozen contracts or synthetic Elementor behavior presented as real evidence.
- Issues in unrelated WordPress, Elementor, hosting or browser components.
