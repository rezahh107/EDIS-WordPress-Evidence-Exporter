# EDIS 3.7.14 validation kit

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

Release 3.7.14 closes the selected correctness mechanisms for privacy projection, observation truth, temporal provenance, manifest-authoritative release construction, generated-evidence placement, documentation synchronization, failure-state normalization, and exact-build qualification. Frozen public schema versions remain unchanged unless separately authorized.
