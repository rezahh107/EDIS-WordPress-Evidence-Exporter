# Controlled real Elementor fixtures

This directory intentionally contains no synthetic file presented as a real Elementor export.

A fixture may be added only after a controlled export from a documented WordPress/Elementor environment. Each fixture directory must contain a manifest validated by `fixture-manifest.schema.json`, original exported bytes, SHA-256 values, and environment metadata.

Initial target coverage is deliberately limited to:

1. Legacy V3 document.
2. Flexbox Container document.
3. Atomic or Hybrid V4 document.

Until those controlled exports are supplied, `fixtures-manifest.json` remains:

```text
verification_state: insufficient_evidence
```
