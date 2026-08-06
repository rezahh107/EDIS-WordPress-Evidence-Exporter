# EDIS 3.7.16 validation kit

This source-only kit records validation scope and evidence states. It is excluded from the WordPress installation ZIP.

Run repository-owned gates:

```bash
php tools/validation/run-local-validation.php --report=release-build/validation-evidence/local-validation.json
```

On Windows PowerShell:

```powershell
./tools/validation/run-local-validation.ps1 --report=release-build/validation-evidence/windows-local-validation.json
```

`summary.local_state=PASS` requires every required local gate to pass. A skipped or unavailable local gate produces `INCOMPLETE` and a non-zero exit code. External WordPress, Elementor, Windows/LocalWP, Composer, and Python gates are summarized independently in `summary.external_state`.

`--strict-external` additionally requires all external gates to pass. It does not change the evidence state of a skipped local gate.

Command output is captured through private temporary files; evidence retains SHA-256, byte counts, and bounded tails rather than loading complete output into PHP memory. Generated validation evidence is written only below `release-build/validation-evidence/`; it is never repository/source authority and is not added to `plugin.manifest.json`. Report files are committed through verified atomic replacement.

Release 3.7.16 validates the canonical `EDIS-DIAGNOSTIC-1` record, private bounded persistence, exact authorized resolution, positive-allowlist privacy, explicit uncertainty, browser/admin projection, truthful persistence fallback, and Safe Worker state mapping. Product/platform and package producer identity are 3.7.16; worker compatibility remains 3.7.15. Frozen public evidence and package schema versions remain unchanged.
