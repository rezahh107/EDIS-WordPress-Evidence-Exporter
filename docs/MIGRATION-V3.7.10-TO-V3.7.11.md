# Migration: EDIS 3.7.10 → 3.7.11

## Scope

This is a source-side validation-evidence corrective release. It adds no WordPress Admin feature and changes no frozen evidence schema.

## Upgrade

1. Replace the complete plugin directory; do not overlay files from different builds.
2. Install the 3.7.11 ZIP and activate it normally.
3. For repository validation, run:

```bash
php tools/validation/run-local-validation.php --report=validation/evidence/local-validation.json
```

## Evidence semantics

- `local_state=PASS` now requires every repository-owned local gate to pass.
- Skipped or unavailable required local gates produce `local_state=INCOMPLETE` and a non-zero process exit.
- `external_state` is independent from local completion.
- `--strict-external` also requires every external gate to pass.

## Jobs and artifacts

Incomplete jobs created by 3.7.10 are not silently resumed by 3.7.11. Valid completed historical packages remain immutable and are not rewritten.

## Rollback

Delete the complete 3.7.11 plugin directory and install the complete 3.7.10 ZIP. Do not mix files from both versions.
