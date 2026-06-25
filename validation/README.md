# EDIS 3.7.11 validation kit

This source-only kit records validation scope and evidence states. It is excluded from the WordPress installation ZIP.

Run repository-owned gates:

```bash
php tools/validation/run-local-validation.php --report=validation/evidence/local-validation.json
```

On Windows PowerShell:

```powershell
./tools/validation/run-local-validation.ps1 --report=validation/evidence/windows-local-validation.json
```

`summary.local_state=PASS` now requires every required local gate to pass. A skipped or unavailable local gate produces `INCOMPLETE` and a non-zero exit code. External WordPress, Elementor, Windows/LocalWP, Composer, and Python gates are summarized independently in `summary.external_state`.

`--strict-external` additionally requires all external gates to pass. It does not change the evidence state of a skipped local gate.

Command output is captured through private temporary files; evidence retains SHA-256, byte counts, and bounded tails rather than loading complete output into PHP memory. Report files are committed through verified atomic replacement.

No file in this kit changes EDIS evidence schemas, frozen contracts, runtime resolution boundaries, or installed WordPress export behavior.
