# EDIS Evidence Component Encyclopedia

Version 3.7.11

> This is the complete registry-driven guide for every Source Collector, Deterministic Index Builder, and Bundle Processor declared by this release. Components export source evidence and deterministic indexes; they do not score UX, resolve final values, or perform final source/runtime correlation.

## How to read an entry

- **Source truth** describes confidence in the source contract and implementation.
- **Source availability** describes whether evidence exists in this export.
- **Observed/exported** values remain distinct from deterministic indexes and Python-resolved values.
- Python owns final validation, merge, resolution, correlation, formulas, rules, and TruthReport.
- Browser owns rendered DOM, geometry, computed style, relationship, and interaction evidence.
- The LLM explains deterministic Python output; it does not create missing facts.

## Browser Bridge Source Context

- **Technical ID:** `bridge_source_context`
- **Component type:** `BUNDLE_PROCESSOR`
- **Group:** `bundle`
- **Source kind:** `deterministic_bundle_processor`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `bridge/source-context.json`
- **Schema:** `urn:edis:schema:bridge:source-context` version `1.0.0`
- **Dependencies:** `elementor_document_index` (REQUIRED), `elementor_element_structure_index` (OPTIONAL), `environment` (REQUIRED)

### Plain-language summary

The minimum source identity and element-index facts needed by the Browser collector.

### What is this evidence?

It enables preliminary source binding without embedding full documents or contacting WordPress.

### Where does it come from and how is it retrieved?

Built from verified source artifacts and projected to the minimum schema required for explicit local Browser import. Full documents are not embedded.

### Which fields are exported?

Analysis-set ID, WordPress bundle ID, source-export root hash, site fingerprint, URL profile, site locator candidates, multisite scope, bounded document records and bounded Element Structure Index projection.

### Why is it important to the pipeline?

Python later validates both packages and finalizes correlation.

### How can Python use it?

Python later validates both packages and finalizes correlation.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Bridge Context enables preliminary binding only. Browser absence of context is not failure, and Python owns final correlation.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`edis.cross-product-contract`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Bundle Diagnostics

- **Technical ID:** `bundle_diagnostics`
- **Component type:** `BUNDLE_PROCESSOR`
- **Group:** `bundle`
- **Source kind:** `deterministic_bundle_processor`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `diagnostics/diagnostics.json`
- **Schema:** `urn:edis:schema:bundle:diagnostics` version `1.0.0`
- **Dependencies:** `bridge_source_context` (OPTIONAL), `elementor_architecture_index` (OPTIONAL), `elementor_breakpoints` (OPTIONAL), `elementor_capability_evidence` (OPTIONAL), `elementor_document_index` (OPTIONAL), `elementor_document_inventory` (OPTIONAL), `elementor_document_source` (OPTIONAL), `elementor_dynamic_references` (OPTIONAL), `elementor_element_structure_index` (OPTIONAL), `elementor_feature_flags` (OPTIONAL), `elementor_global_classes_order` (OPTIONAL), `elementor_global_classes_registry` (OPTIONAL), `elementor_installation` (OPTIONAL), `elementor_kit_metadata` (OPTIONAL), `elementor_kit_settings` (OPTIONAL), `elementor_legacy_global_styles` (OPTIONAL), `elementor_performance_configuration` (OPTIONAL), `elementor_reference_index` (OPTIONAL), `elementor_registered_document_types` (OPTIONAL), `elementor_registered_widgets` (OPTIONAL), `elementor_responsive_declaration_index` (OPTIONAL), `elementor_site_settings_index` (OPTIONAL), `elementor_unknown_structure_ledger` (OPTIONAL), `elementor_usage_summary` (OPTIONAL), `elementor_variables_registry` (OPTIONAL), `environment` (OPTIONAL), `evidence_conservation` (OPTIONAL), `export_comparison` (OPTIONAL), `fixture_capture` (OPTIONAL), `plugin` (OPTIONAL), `selection_snapshot` (OPTIONAL), `theme` (OPTIONAL)

### Plain-language summary

A normalized diagnostic stream for the source export.

### What is this evidence?

Semantic and operational diagnostics remain distinguishable.

### Where does it come from and how is it retrieved?

Aggregates diagnostics emitted by committed components. Localized prose is not used as semantic identity.

### Which fields are exported?

Diagnostic code, severity, semantic/operational scope, message key, structured context, component ID and deterministic ordering.

### Why is it important to the pipeline?

Python uses codes and structured context, not translated prose, for deterministic behavior.

### How can Python use it?

Python uses codes and structured context, not translated prose, for deterministic behavior.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Diagnostics explain evidence and processing state; they do not replace package validation or UX findings.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`edis.cross-product-contract`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Estimated Export Size

- **Technical ID:** `estimated_export_size`
- **Component type:** `BUNDLE_PROCESSOR`
- **Group:** `bundle`
- **Source kind:** `deterministic_bundle_processor`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `diagnostics/estimated-export-size.json`
- **Schema:** `urn:edis:schema:bundle:estimated-size` version `1.0.0`
- **Dependencies:** `bridge_source_context` (OPTIONAL), `bundle_diagnostics` (OPTIONAL), `elementor_architecture_index` (OPTIONAL), `elementor_breakpoints` (OPTIONAL), `elementor_capability_evidence` (OPTIONAL), `elementor_document_index` (OPTIONAL), `elementor_document_inventory` (OPTIONAL), `elementor_document_source` (OPTIONAL), `elementor_dynamic_references` (OPTIONAL), `elementor_element_structure_index` (OPTIONAL), `elementor_feature_flags` (OPTIONAL), `elementor_global_classes_order` (OPTIONAL), `elementor_global_classes_registry` (OPTIONAL), `elementor_installation` (OPTIONAL), `elementor_kit_metadata` (OPTIONAL), `elementor_kit_settings` (OPTIONAL), `elementor_legacy_global_styles` (OPTIONAL), `elementor_performance_configuration` (OPTIONAL), `elementor_reference_index` (OPTIONAL), `elementor_registered_document_types` (OPTIONAL), `elementor_registered_widgets` (OPTIONAL), `elementor_responsive_declaration_index` (OPTIONAL), `elementor_site_settings_index` (OPTIONAL), `elementor_usage_summary` (OPTIONAL), `elementor_variables_registry` (OPTIONAL), `environment` (OPTIONAL), `export_comparison` (OPTIONAL), `fixture_capture` (OPTIONAL), `plugin` (OPTIONAL), `theme` (OPTIONAL)

### Plain-language summary

A byte estimate calculated from staged source artifacts.

### What is this evidence?

It helps the UI explain staged evidence size without pretending that schemas, manifests, checksums and ZIP framing are already included.

### Where does it come from and how is it retrieved?

Counts the EDIS-CJ-2 bytes of committed artifacts before final schemas, manifest, checksums and deterministic STORE-only ZIP framing are added.

### Which fields are exported?

Canonical byte count per staged component, total estimated uncompressed JSON bytes and explicit estimate-only flag.

### Why is it important to the pipeline?

Python may use it for ingestion planning only.

### How can Python use it?

Python may use it for ingestion planning only.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

The estimate is not the final ZIP size and is not a performance metric.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`edis.contract`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Evidence Conservation

- **Technical ID:** `evidence_conservation`
- **Component type:** `BUNDLE_PROCESSOR`
- **Group:** `bundle`
- **Source kind:** `deterministic_bundle_processor`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `validation/evidence-conservation.json`
- **Schema:** `urn:edis:schema:validation:evidence-conservation` version `1.0.0`
- **Dependencies:** `elementor_document_source` (OPTIONAL), `elementor_element_structure_index` (OPTIONAL), `elementor_responsive_declaration_index` (OPTIONAL), `elementor_dynamic_references` (OPTIONAL)

### Plain-language summary

Checks that source elements, responsive variants, and class bindings are conserved by their indexes.

### What is this evidence?

Checks that source elements, responsive variants, and class bindings are conserved by their indexes.

### Where does it come from and how is it retrieved?

Built deterministically from already committed source artifacts; it does not perform UX analysis.

### Which fields are exported?

Versioned machine-readable records, counts, source paths, status, and diagnostics.

### Why is it important to the pipeline?

Python uses this artifact to verify completeness and decide whether downstream processing is safe.

### How can Python use it?

Python validates the artifact and preserves missing or partial evidence without guessing.

### What reaches the LLM?

Only Python findings derived from this evidence.

### What cannot be concluded?

It does not establish rendered behavior or UX quality.

### Version limitations

Coverage depends on the exported source structures and declared schema version.

### Privacy impact

No new page content is collected beyond the source artifacts already selected for export.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`edis.cross-product-contract`

### Troubleshooting

Review selected documents, source availability, conservation diagnostics, and package validation.

## Selection Snapshot

- **Technical ID:** `selection_snapshot`
- **Component type:** `BUNDLE_PROCESSOR`
- **Group:** `bundle`
- **Source kind:** `deterministic_bundle_processor`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `selection/selection-snapshot.json`
- **Schema:** `urn:edis:schema:bundle:selection-snapshot` version `1.0.0`
- **Dependencies:** `elementor_document_source` (OPTIONAL), `elementor_document_inventory` (OPTIONAL)

### Plain-language summary

The exact selection and scope used to create this immutable bundle.

### What is this evidence?

The exact selection and scope used to create this immutable bundle.

### Where does it come from and how is it retrieved?

Built deterministically from already committed source artifacts; it does not perform UX analysis.

### Which fields are exported?

Versioned machine-readable records, counts, source paths, status, and diagnostics.

### Why is it important to the pipeline?

Python uses this artifact to verify completeness and decide whether downstream processing is safe.

### How can Python use it?

Python validates the artifact and preserves missing or partial evidence without guessing.

### What reaches the LLM?

Only Python findings derived from this evidence.

### What cannot be concluded?

It does not establish rendered behavior or UX quality.

### Version limitations

Coverage depends on the exported source structures and declared schema version.

### Privacy impact

No new page content is collected beyond the source artifacts already selected for export.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`edis.cross-product-contract`

### Troubleshooting

Review selected documents, source availability, conservation diagnostics, and package validation.

## Source Coverage

- **Technical ID:** `source_coverage`
- **Component type:** `BUNDLE_PROCESSOR`
- **Group:** `bundle`
- **Source kind:** `deterministic_bundle_processor`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `coverage/source-coverage.json`
- **Schema:** `urn:edis:schema:coverage:source` version `1.0.0`
- **Dependencies:** `bridge_source_context` (OPTIONAL), `bundle_diagnostics` (OPTIONAL), `elementor_architecture_index` (OPTIONAL), `elementor_breakpoints` (OPTIONAL), `elementor_capability_evidence` (OPTIONAL), `elementor_document_index` (OPTIONAL), `elementor_document_inventory` (OPTIONAL), `elementor_document_source` (OPTIONAL), `elementor_dynamic_references` (OPTIONAL), `elementor_element_structure_index` (OPTIONAL), `elementor_feature_flags` (OPTIONAL), `elementor_global_classes_order` (OPTIONAL), `elementor_global_classes_registry` (OPTIONAL), `elementor_installation` (OPTIONAL), `elementor_kit_metadata` (OPTIONAL), `elementor_kit_settings` (OPTIONAL), `elementor_legacy_global_styles` (OPTIONAL), `elementor_performance_configuration` (OPTIONAL), `elementor_reference_index` (OPTIONAL), `elementor_registered_document_types` (OPTIONAL), `elementor_registered_widgets` (OPTIONAL), `elementor_responsive_declaration_index` (OPTIONAL), `elementor_site_settings_index` (OPTIONAL), `elementor_unknown_structure_ledger` (OPTIONAL), `elementor_usage_summary` (OPTIONAL), `elementor_variables_registry` (OPTIONAL), `environment` (OPTIONAL), `estimated_export_size` (OPTIONAL), `evidence_conservation` (OPTIONAL), `export_comparison` (OPTIONAL), `fixture_capture` (OPTIONAL), `plugin` (OPTIONAL), `selection_snapshot` (OPTIONAL), `theme` (OPTIONAL)

### Plain-language summary

Source-only coverage facts.

### What is this evidence?

Coverage states whether evidence was available and contract-verified.

### Where does it come from and how is it retrieved?

Aggregates committed component artifacts after collection. It does not merge Browser coverage or decide rule readiness.

### Which fields are exported?

Per-component type, source truth, source availability, diagnostic count, truth summary, availability summary and source component count.

### Why is it important to the pipeline?

Python combines this with runtime and binding coverage to decide rule readiness.

### How can Python use it?

Python combines this with runtime and binding coverage to decide rule readiness.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Source coverage cannot state whether runtime or correlation evidence is sufficient. Python combines separate coverage namespaces later.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`edis.contract`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Fixture Capture Metadata

- **Technical ID:** `fixture_capture`
- **Component type:** `BUNDLE_PROCESSOR`
- **Group:** `bundle_processing`
- **Source kind:** `DERIVED_FIXTURE_METADATA`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `fixture/fixture-metadata.json`
- **Schema:** `urn:edis:schema:fixture:capture-metadata` version `1.0.0`
- **Dependencies:** `environment` (OPTIONAL), `elementor_installation` (OPTIONAL), `elementor_document_source` (OPTIONAL), `selection_snapshot` (OPTIONAL)

### Plain-language summary

Optional metadata that turns an export into a controlled real-fixture authoring package.

### What is this evidence?

Optional metadata that turns an export into a controlled real-fixture authoring package.

### Where does it come from and how is it retrieved?

Built deterministically from already committed source artifacts and local previous-export metadata. It does not inspect rendered browser state.

### Which fields are exported?

Versioned machine-readable records, document identifiers, source hashes, bounded counts, states and provenance.

### Why is it important to the pipeline?

Python can use this evidence to verify source change, fixture context and ingestion expectations without guessing.

### How can Python use it?

Python validates the artifact, preserves absence of prior evidence, and decides whether comparison or fixture assertions are applicable.

### What reaches the LLM?

Only Python findings derived from this evidence; the artifact itself does not authorize scoring.

### What cannot be concluded?

It cannot establish rendered behavior, visual quality, UX correctness or final source/runtime correlation.

### Version limitations

Detailed deltas depend on bounded summaries saved by a prior EDIS 3.4+ completed export.

### Privacy impact

May expose selected document identifiers, environment versions and source hashes, but does not add page text beyond already selected source artifacts.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`edis.cross-product-contract`

### Troubleshooting

Review selected documents, previous export metadata, source availability, fixture mode and package diagnostics.

## Previous Export Comparison

- **Technical ID:** `export_comparison`
- **Component type:** `BUNDLE_PROCESSOR`
- **Group:** `bundle_processing`
- **Source kind:** `DERIVED_SOURCE_DIFF`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `comparison/previous-export-diff.json`
- **Schema:** `urn:edis:schema:comparison:previous-source-export` version `1.0.0`
- **Dependencies:** `elementor_document_source` (OPTIONAL), `elementor_element_structure_index` (OPTIONAL), `elementor_responsive_declaration_index` (OPTIONAL), `elementor_dynamic_references` (OPTIONAL)

### Plain-language summary

A bounded source-evidence diff against the previous completed EDIS export.

### What is this evidence?

A bounded source-evidence diff against the previous completed EDIS export.

### Where does it come from and how is it retrieved?

Built deterministically from already committed source artifacts and local previous-export metadata. It does not inspect rendered browser state.

### Which fields are exported?

Versioned machine-readable records, document identifiers, source hashes, bounded counts, states and provenance.

### Why is it important to the pipeline?

Python can use this evidence to verify source change, fixture context and ingestion expectations without guessing.

### How can Python use it?

Python validates the artifact, preserves absence of prior evidence, and decides whether comparison or fixture assertions are applicable.

### What reaches the LLM?

Only Python findings derived from this evidence; the artifact itself does not authorize scoring.

### What cannot be concluded?

It cannot establish rendered behavior, visual quality, UX correctness or final source/runtime correlation.

### Version limitations

Detailed deltas depend on bounded summaries saved by a prior EDIS 3.4+ completed export.

### Privacy impact

May expose selected document identifiers, environment versions and source hashes, but does not add page text beyond already selected source artifacts.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`edis.cross-product-contract`

### Troubleshooting

Review selected documents, previous export metadata, source availability, fixture mode and package diagnostics.

## Architecture Index

- **Technical ID:** `elementor_architecture_index`
- **Component type:** `INDEX_BUILDER`
- **Group:** `indexes`
- **Source kind:** `deterministic_source_index`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `indexes/architecture-index.json`
- **Schema:** `urn:edis:schema:index:architecture` version `1.0.0`
- **Dependencies:** `elementor_element_structure_index` (REQUIRED)

### Plain-language summary

Architecture classification from explicit saved element shapes.

### What is this evidence?

It prevents one Atomic element from causing rejection of an otherwise hybrid document.

### Where does it come from and how is it retrieved?

Built from saved element shapes and explicit fields using a versioned deterministic classifier; unknown fields are retained rather than rejected.

### Which fields are exported?

Per-document architecture kinds, per-element architecture kind, counts for legacy/container/atomic/unknown, hybrid flag and classification provenance.

### Why is it important to the pipeline?

Python chooses compatible parser paths per element and preserves unknown structures.

### How can Python use it?

Python chooses compatible parser paths per element and preserves unknown structures.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Architecture classification does not imply design quality or guarantee all addon element semantics are known.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.atomic-elements`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Document Index

- **Technical ID:** `elementor_document_index`
- **Component type:** `INDEX_BUILDER`
- **Group:** `indexes`
- **Source kind:** `deterministic_index`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `indexes/document-index.json`
- **Schema:** `urn:edis:schema:index:documents` version `1.0.0`
- **Dependencies:** `elementor_document_inventory` (REQUIRED), `elementor_document_source` (OPTIONAL)

### Plain-language summary

A stable document lookup table built from source artifacts.

### What is this evidence?

It links document identifiers, fingerprints, hashes, types, and artifact locations.

### Where does it come from and how is it retrieved?

Built deterministically from exported document inventory/source artifacts; it does not query a new source.

### Which fields are exported?

Document key, string document ID, type, state, source hash, architecture summary, locator candidates and deterministic order.

### Why is it important to the pipeline?

Python uses it for fast routing and validation before loading large document artifacts.

### How can Python use it?

Python uses it for fast routing and validation before loading large document artifacts.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

The index cannot be more trustworthy or available than its required source dependencies.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`edis.contract`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Element Structure Index

- **Technical ID:** `elementor_element_structure_index`
- **Component type:** `INDEX_BUILDER`
- **Group:** `indexes`
- **Source kind:** `deterministic_source_index`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `indexes/element-structure-index.json`
- **Schema:** `urn:edis:schema:index:element-structure` version `1.0.0`
- **Dependencies:** `elementor_document_source` (REQUIRED), `elementor_registered_widgets` (OPTIONAL)

### Plain-language summary

A lightweight identity and ancestry record for every saved element.

### What is this evidence?

It preserves real IDs, duplicate counts, source paths, order, types, and architecture kind.

### Where does it come from and how is it retrieved?

A bounded deterministic walker traverses saved V3, Container, Atomic and hybrid element trees. It never invents missing Elementor IDs.

### Which fields are exported?

Document ID/fingerprint, source element key, source-record hash, real Elementor element ID, duplicate count/uniqueness, parent ID, root-to-leaf ancestor IDs excluding self, source path, document order, element kind, elType, widget type and architecture kind.

### Why is it important to the pipeline?

Python and Browser use it as the source side of Runtime–Elementor binding.

### How can Python use it?

Python and Browser use it as the source side of Runtime–Elementor binding.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

This index is the source side of Browser binding. It does not prove that a runtime node matches until Python verifies both packages.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.page-content`, `elementor.atomic-elements`, `edis.contract`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Elementor Capability Evidence

- **Technical ID:** `elementor_capability_evidence`
- **Component type:** `INDEX_BUILDER`
- **Group:** `indexes`
- **Source kind:** `deterministic_capability_index`
- **Implementation:** `real`
- **Declared source truth:** `PARTIAL`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `indexes/capability-evidence.json`
- **Schema:** `urn:edis:schema:index:capabilities` version `1.0.0`
- **Dependencies:** `elementor_architecture_index` (OPTIONAL), `elementor_breakpoints` (OPTIONAL), `elementor_feature_flags` (OPTIONAL), `elementor_global_classes_registry` (OPTIONAL), `elementor_installation` (REQUIRED), `elementor_registered_document_types` (OPTIONAL), `elementor_registered_widgets` (OPTIONAL), `elementor_variables_registry` (OPTIONAL), `environment` (REQUIRED)

### Plain-language summary

A capability map that distinguishes observed registration, document usage, version expectation, and unknown state.

### What is this evidence?

It prevents later guidance from proposing actions unavailable in the installed Elementor environment.

### Where does it come from and how is it retrieved?

Built from multiple source artifacts while keeping `observed registration`, `document usage`, `version expectation` and `unknown` separate.

### Which fields are exported?

Observed registrations, document usage, feature states, version expectations, Free/Pro evidence, Atomic/Variables/Classes/Interactions evidence and confidence origin for each capability.

### Why is it important to the pipeline?

Python uses observed facts as stronger evidence than version expectations.

### How can Python use it?

Python uses observed facts as stronger evidence than version expectations.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

A version expectation never becomes observed support. Python uses this map as a constraint, not as proof of a rendered action.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `PARTIAL` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.widgets`, `elementor.documents`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Reference Index

- **Technical ID:** `elementor_reference_index`
- **Component type:** `INDEX_BUILDER`
- **Group:** `indexes`
- **Source kind:** `deterministic_source_index`
- **Implementation:** `real`
- **Declared source truth:** `PARTIAL`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `indexes/reference-index.json`
- **Schema:** `urn:edis:schema:index:references` version `1.0.0`
- **Dependencies:** `elementor_dynamic_references` (REQUIRED), `elementor_global_classes_registry` (OPTIONAL), `elementor_legacy_global_styles` (OPTIONAL), `elementor_variables_registry` (OPTIONAL)

### Plain-language summary

A lookup index for global, variable, class, and dynamic references.

### What is this evidence?

It reports candidate registry targets and unresolved references without calculating runtime values.

### Where does it come from and how is it retrieved?

Built deterministically by joining saved reference evidence to exported registry candidates without calculating final effective values.

### Which fields are exported?

Reference occurrence, document/source path, reference kind/hash, registry candidate IDs, resolution availability, ambiguity and missing-target diagnostics.

### Why is it important to the pipeline?

Python performs final resolution and records exact provenance.

### How can Python use it?

Python performs final resolution and records exact provenance.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

A candidate match is not the same as final resolution when registries are partial or duplicate identifiers exist.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `PARTIAL` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.global-styles`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Responsive Declaration Index

- **Technical ID:** `elementor_responsive_declaration_index`
- **Component type:** `INDEX_BUILDER`
- **Group:** `indexes`
- **Source kind:** `deterministic_source_index`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `indexes/responsive-declaration-index.json`
- **Schema:** `urn:edis:schema:index:responsive-declarations` version `1.0.0`
- **Dependencies:** `elementor_breakpoints` (REQUIRED), `elementor_document_source` (REQUIRED)

### Plain-language summary

A list of saved responsive property declarations and their exact source paths.

### What is this evidence?

It distinguishes a missing declaration from an explicitly saved value.

### Where does it come from and how is it retrieved?

Scans legacy suffix keys only for breakpoint IDs observed from the Elementor breakpoint manager, plus Atomic variants under styles.*.variants[].meta.breakpoint. Inheritance and direction are not resolved.

### Which fields are exported?

Declaration kind, document and element IDs, style ID, variant index, property, original property key, breakpoint/device, state, saved value, and exact source path.

### Why is it important to the pipeline?

Python resolves effective values using real breakpoint configuration and preserves inherited-from provenance.

### How can Python use it?

Python resolves effective values using real breakpoint configuration and preserves inherited-from provenance.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Unregistered suffix names are not promoted to breakpoint facts. A saved declaration has effective meaning only after Python resolution with real breakpoint and Browser evidence.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.responsive-data`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Site Settings Index

- **Technical ID:** `elementor_site_settings_index`
- **Component type:** `INDEX_BUILDER`
- **Group:** `indexes`
- **Source kind:** `deterministic_source_index`
- **Implementation:** `real`
- **Declared source truth:** `PARTIAL`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `indexes/site-settings-index.json`
- **Schema:** `urn:edis:schema:index:site-settings` version `1.0.0`
- **Dependencies:** `elementor_kit_settings` (REQUIRED), `elementor_legacy_global_styles` (OPTIONAL)

### Plain-language summary

A categorized index over saved Kit settings.

### What is this evidence?

It gives Python stable paths without replacing the raw Kit artifact.

### Where does it come from and how is it retrieved?

Built deterministically from active-Kit settings using transparent key classification. Original settings remain the source artifact.

### Which fields are exported?

Categorized active-Kit setting keys and saved values grouped as colors, typography, layout, identity, lightbox or other, with source provenance.

### Why is it important to the pipeline?

Python can locate typography, color, layout, identity, and custom-style source sections.

### How can Python use it?

Python can locate typography, color, layout, identity, and custom-style source sections.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Categories are navigation aids, not claims about runtime effect. Addon-specific settings may remain in `other`.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `PARTIAL` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.site-settings`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Source Usage Summary

- **Technical ID:** `elementor_usage_summary`
- **Component type:** `INDEX_BUILDER`
- **Group:** `indexes`
- **Source kind:** `deterministic_source_index`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `indexes/usage-summary.json`
- **Schema:** `urn:edis:schema:index:usage-summary` version `1.0.0`
- **Dependencies:** `elementor_element_structure_index` (REQUIRED), `elementor_reference_index` (OPTIONAL), `elementor_responsive_declaration_index` (OPTIONAL)

### Plain-language summary

Source-level counts for planning and bounded ingestion.

### What is this evidence?

It summarizes what exists without assigning quality or UX meaning.

### Where does it come from and how is it retrieved?

Aggregates already-exported indexes only. It does not discover additional documents or elements.

### Which fields are exported?

Deterministic counts by document type, element kind, widget type, architecture kind, responsive declaration kind and reference kind.

### Why is it important to the pipeline?

Python uses counts for performance planning, coverage checks, and report navigation.

### How can Python use it?

Python uses counts for performance planning, coverage checks, and report navigation.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Counts are source usage facts, not complexity, performance or cognitive-load scores.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`edis.contract`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Unknown Structure Ledger

- **Technical ID:** `elementor_unknown_structure_ledger`
- **Component type:** `INDEX_BUILDER`
- **Group:** `indexes`
- **Source kind:** `deterministic_source_index`
- **Implementation:** `real`
- **Declared source truth:** `PARTIAL`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `diagnostics/unknown-structures.json`
- **Schema:** `urn:edis:schema:index:unknown-structures` version `1.0.0`
- **Dependencies:** `elementor_document_source` (OPTIONAL)

### Plain-language summary

A ledger of unknown or forward-version element fields that remain preserved in raw source.

### What is this evidence?

A ledger of unknown or forward-version element fields that remain preserved in raw source.

### Where does it come from and how is it retrieved?

Built deterministically from already committed source artifacts; it does not perform UX analysis.

### Which fields are exported?

Versioned machine-readable records, counts, source paths, status, and diagnostics.

### Why is it important to the pipeline?

Python uses this artifact to verify completeness and decide whether downstream processing is safe.

### How can Python use it?

Python validates the artifact and preserves missing or partial evidence without guessing.

### What reaches the LLM?

Only Python findings derived from this evidence.

### What cannot be concluded?

It does not establish rendered behavior or UX quality.

### Version limitations

Coverage depends on the exported source structures and declared schema version.

### Privacy impact

No new page content is collected beyond the source artifacts already selected for export.

### Availability and truth-state interpretation

The declared truth state is `PARTIAL` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`edis.cross-product-contract`

### Troubleshooting

Review selected documents, source availability, conservation diagnostics, and package validation.

## Performance Configuration

- **Technical ID:** `elementor_performance_configuration`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `elementor_capabilities`
- **Source kind:** `elementor_options_and_features`
- **Implementation:** `real`
- **Declared source truth:** `PARTIAL`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `sources/elementor/performance-configuration.json`
- **Schema:** `urn:edis:schema:elementor:performance-configuration` version `1.0.0`
- **Dependencies:** `elementor_feature_flags` (OPTIONAL), `elementor_installation` (REQUIRED)

### Plain-language summary

Saved configuration that may influence generated assets and runtime behavior.

### What is this evidence?

These facts help Python explain why source and browser observations may differ.

### Where does it come from and how is it retrieved?

Read from bounded saved Elementor options and feature configuration, without running a performance benchmark.

### Which fields are exported?

Observed Elementor performance-related option keys, normalized state/value, source location and unknown entries.

### Why is it important to the pipeline?

Python treats them as environment context, not as a performance score.

### How can Python use it?

Python treats them as environment context, not as a performance score.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Configuration does not prove actual performance. Browser/PageSpeed evidence is required, and option names can change between releases.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `PARTIAL` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.performance`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Registered Document Types

- **Technical ID:** `elementor_registered_document_types`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `elementor_capabilities`
- **Source kind:** `elementor_documents_manager`
- **Implementation:** `real`
- **Declared source truth:** `PARTIAL`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `sources/elementor/registered-document-types.json`
- **Schema:** `urn:edis:schema:elementor:registered-document-types` version `1.0.0`
- **Dependencies:** `elementor_installation` (REQUIRED), `plugin` (REQUIRED)

### Plain-language summary

Document types available in the active Elementor environment.

### What is this evidence?

Pages, posts, headers, footers, popups, archives, and addon types may use different source contracts.

### Where does it come from and how is it retrieved?

Read from Elementor document manager registrations when safely accessible.

### Which fields are exported?

Observed document-type names, registration/provider facts, manager availability, count and provenance.

### Why is it important to the pipeline?

Python routes document parsers and action mapping by observed type.

### How can Python use it?

Python routes document parsers and action mapping by observed type.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Registration does not prove a document of that type exists or is publicly routable.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `PARTIAL` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.general-structure`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Registered Widgets

- **Technical ID:** `elementor_registered_widgets`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `elementor_capabilities`
- **Source kind:** `elementor_widgets_manager`
- **Implementation:** `real`
- **Declared source truth:** `PARTIAL`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `sources/elementor/registered-widgets.json`
- **Schema:** `urn:edis:schema:elementor:registered-widgets` version `1.0.0`
- **Dependencies:** `elementor_installation` (REQUIRED), `plugin` (REQUIRED)

### Plain-language summary

Widget types registered by Elementor Core, Pro, or addons.

### What is this evidence?

Registration evidence prevents Python and the LLM from suggesting unavailable widgets.

### Where does it come from and how is it retrieved?

Read from the active Elementor widget manager when available. It records registration facts, not rendered instances.

### Which fields are exported?

Observed widget names, provider/core-or-addon evidence, registration count, manager availability and provenance.

### Why is it important to the pipeline?

Python distinguishes observed registration from document usage and version expectations.

### How can Python use it?

Python distinguishes observed registration from document usage and version expectations.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

A registered widget may never be used. Addons can alter registrations at runtime, so this contract remains PARTIAL across versions.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `PARTIAL` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.widgets-manager`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Elementor Breakpoints

- **Technical ID:** `elementor_breakpoints`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `elementor_core`
- **Source kind:** `elementor_breakpoints_manager`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `sources/elementor/breakpoints.json`
- **Schema:** `urn:edis:schema:elementor:breakpoints` version `1.0.0`
- **Dependencies:** `elementor_installation` (REQUIRED)

### Plain-language summary

Configured responsive boundaries from the active Elementor environment.

### What is this evidence?

A breakpoint is a viewport boundary where Elementor may store a device-specific declaration. It is configuration evidence, not a usability score.

### Where does it come from and how is it retrieved?

Read only from the active Elementor breakpoint manager API. Active state is marked unknown when the API returns the all-breakpoints collection, and direction is not inferred from breakpoint names.

### Which fields are exported?

Breakpoint ID, label, observed active-state evidence, configured numeric value, unit, manager order, unresolved direction state, source adapter and retrieval method.

### Why is it important to the pipeline?

Python maps responsive suffixes, resolves inheritance, aligns browser observations, and refuses comparisons when required viewport evidence is missing.

### How can Python use it?

Python maps responsive suffixes, resolves inheritance, aligns browser observations, and refuses comparisons when required viewport evidence is missing.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Breakpoints describe configuration only. Direction remains UNVERIFIED when the public manager API does not expose it; Browser viewport observations are required for comparison.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.breakpoints-manager`, `elementor.responsive-data`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Elementor Features and Experiments

- **Technical ID:** `elementor_feature_flags`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `elementor_core`
- **Source kind:** `elementor_experiments_manager_and_options`
- **Implementation:** `real`
- **Declared source truth:** `PARTIAL`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `sources/elementor/feature-flags.json`
- **Schema:** `urn:edis:schema:elementor:feature-flags` version `1.0.0`
- **Dependencies:** `elementor_installation` (REQUIRED)

### Plain-language summary

Feature flags indicate which editor systems are enabled.

### What is this evidence?

Feature and experiment state is source evidence for Atomic Editor, breakpoints, variables, classes, and related capabilities.

### Where does it come from and how is it retrieved?

Read from bounded Elementor option/config sources. The collector preserves observed values and does not convert version expectations into observed facts.

### Which fields are exported?

Observed experiment and feature keys, stored state, normalized state, source location, and unknown/unrecognized entries.

### Why is it important to the pipeline?

Python uses observed states as evidence and keeps version-based expectations separate.

### How can Python use it?

Python uses observed states as evidence and keeps version-based expectations separate.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Feature storage and naming can change between Elementor releases; unknown keys remain visible and this component stays PARTIAL until fixtures cover them.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `PARTIAL` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.experiments`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Elementor Installation

- **Technical ID:** `elementor_installation`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `elementor_core`
- **Source kind:** `elementor_constants_and_public_api`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `sources/elementor/installation.json`
- **Schema:** `urn:edis:schema:elementor:installation` version `1.0.0`
- **Dependencies:** `environment` (REQUIRED), `plugin` (REQUIRED)

### Plain-language summary

Elementor product and version evidence.

### What is this evidence?

This establishes which source contracts may exist.

### Where does it come from and how is it retrieved?

Read from defined plugin constants and observed WordPress plugin registration, without loading remote version services.

### Which fields are exported?

Elementor Core and Pro presence, installed versions, activation facts, and adapter compatibility metadata.

### Why is it important to the pipeline?

Python selects compatible parsers and marks unsupported version ranges honestly.

### How can Python use it?

Python selects compatible parsers and marks unsupported version ranges honestly.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

A high version number is only version evidence; it is not proof that Variables, Atomic Editor, Interactions, or an addon capability is active.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.data-structure`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Active Kit Metadata

- **Technical ID:** `elementor_kit_metadata`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `elementor_design_system`
- **Source kind:** `wordpress_option_and_post_meta`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `sources/elementor/kit-metadata.json`
- **Schema:** `urn:edis:schema:elementor:kit-metadata` version `1.0.0`
- **Dependencies:** `elementor_installation` (REQUIRED)

### Plain-language summary

The active Kit is the source container for site-wide Elementor settings.

### What is this evidence?

Kit identity lets Python connect page references to the correct global registry source.

### Where does it come from and how is it retrieved?

Read from Elementor active-Kit APIs or the saved active-Kit option, then verified against WordPress post metadata when available.

### Which fields are exported?

Active Kit document ID as a string, post status/type, saved-source hash candidates, storage evidence, and Kit selection provenance.

### Why is it important to the pipeline?

Python uses it to scope Global Styles, Site Settings, and design-system references.

### How can Python use it?

Python uses it to scope Global Styles, Site Settings, and design-system references.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

A Kit ID does not mean every global setting or registry exists in that Kit.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.global-styles`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Elementor Kit Settings

- **Technical ID:** `elementor_kit_settings`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `elementor_design_system`
- **Source kind:** `wordpress_post_meta`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `sources/elementor/kit-settings.json`
- **Schema:** `urn:edis:schema:elementor:kit-settings` version `1.0.0`
- **Dependencies:** `elementor_kit_metadata` (REQUIRED)

### Plain-language summary

Raw saved settings of the active Elementor Kit.

### What is this evidence?

Kit settings contain global source declarations that individual documents may reference.

### Where does it come from and how is it retrieved?

Read from active-Kit post meta. Strict mode removes keys that look like code, tracking, URL, email, API, token, nonce or secret configuration.

### Which fields are exported?

Saved `_elementor_page_settings` keys and values for the active Kit, Kit ID, source paths, privacy filtering and provenance.

### Why is it important to the pipeline?

Python resolves source references while preserving the original saved values and property paths.

### How can Python use it?

Python resolves source references while preserving the original saved values and property paths.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Addon-specific or future Kit keys may be unknown. Unknown values are preserved as source evidence when privacy policy allows, not interpreted as UX facts.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.global-styles`, `elementor.site-settings`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Global Classes Order

- **Technical ID:** `elementor_global_classes_order`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `elementor_design_system`
- **Source kind:** `wordpress_post_meta_source_backed`
- **Implementation:** `real`
- **Declared source truth:** `PARTIAL`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `sources/elementor/global-classes-order.json`
- **Schema:** `urn:edis:schema:elementor:global-classes-order` version `1.0.0`
- **Dependencies:** `elementor_global_classes_registry` (REQUIRED)

### Plain-language summary

The stored ordering list associated with Global Classes.

### What is this evidence?

Order evidence is necessary to reconstruct possible precedence.

### Where does it come from and how is it retrieved?

Read from Elementor Global Classes ordering storage and exported separately from class definitions.

### Which fields are exported?

Saved ordered class IDs, storage key, duplicate/missing-order diagnostics, record count and provenance.

### Why is it important to the pipeline?

Python keeps registry order distinct from element attachment order and runtime cascade.

### How can Python use it?

Python keeps registry order distinct from element attachment order and runtime cascade.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Registry order is not automatically equal to the order attached to a particular element or the final runtime cascade.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `PARTIAL` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.global-classes-order`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Global Classes Registry

- **Technical ID:** `elementor_global_classes_registry`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `elementor_design_system`
- **Source kind:** `elementor_global_classes_storage`
- **Implementation:** `real`
- **Declared source truth:** `PARTIAL`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `sources/elementor/global-classes.json`
- **Schema:** `urn:edis:schema:elementor:global-classes` version `1.0.0`
- **Dependencies:** `elementor_feature_flags` (OPTIONAL), `elementor_installation` (REQUIRED)

### Plain-language summary

Reusable class definitions from Elementor design-system storage.

### What is this evidence?

Classes preserve reusable declarations separately from element-local settings.

### Where does it come from and how is it retrieved?

Read from bounded observed Global Classes storage. Class declarations remain separate from element-local declarations and runtime computed values.

### Which fields are exported?

Global Class IDs, saved declaration records, registry metadata, source storage candidates, unknown fields and diagnostics.

### Why is it important to the pipeline?

Python resolves class references and compares registry order, attached order, and local declarations without inventing precedence.

### How can Python use it?

Python resolves class references and compares registry order, attached order, and local declarations without inventing precedence.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Class precedence cannot be finalized from the registry alone; attachment order, local declarations and browser evidence are also required.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `PARTIAL` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.global-classes`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Legacy Global Styles

- **Technical ID:** `elementor_legacy_global_styles`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `elementor_design_system`
- **Source kind:** `wordpress_post_meta`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `sources/elementor/legacy-global-styles.json`
- **Schema:** `urn:edis:schema:elementor:legacy-global-styles` version `1.0.0`
- **Dependencies:** `elementor_kit_settings` (REQUIRED)

### Plain-language summary

Classic Elementor global color and typography registries.

### What is this evidence?

Classic documents often store only __globals__ references to these records.

### Where does it come from and how is it retrieved?

Read from active-Kit saved settings, while document `__globals__` references remain separate evidence.

### Which fields are exported?

Legacy global color and typography registries, IDs, labels, saved values, active Kit source path and unresolved-reference diagnostics.

### Why is it important to the pipeline?

Python joins document references to Kit records and reports unresolved IDs.

### How can Python use it?

Python joins document references to Kit records and reports unresolved IDs.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Legacy globals and Atomic Variables/Classes are different source systems and must not be silently merged by the plugin.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.global-styles`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Variables Registry

- **Technical ID:** `elementor_variables_registry`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `elementor_design_system`
- **Source kind:** `elementor_variables_storage`
- **Implementation:** `real`
- **Declared source truth:** `PARTIAL`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `sources/elementor/variables.json`
- **Schema:** `urn:edis:schema:elementor:variables` version `1.0.0`
- **Dependencies:** `elementor_feature_flags` (OPTIONAL), `elementor_installation` (REQUIRED)

### Plain-language summary

Reusable source values managed by Elementor Variables.

### What is this evidence?

Variables may be referenced by Atomic styles and design-system records.

### Where does it come from and how is it retrieved?

Read from observed Elementor Variables storage contracts and preserved without resolving references into element properties.

### Which fields are exported?

Registry data records, registry version, watermark, variable IDs, stored values/types when available, storage key candidates and diagnostics.

### Why is it important to the pipeline?

Python resolves variable references, reports missing references, and preserves registry version/watermark evidence.

### How can Python use it?

Python resolves variable references, reports missing references, and preserves registry version/watermark evidence.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Variables are version-sensitive and may be unavailable in older or non-Atomic environments. A registry record does not prove runtime application.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `PARTIAL` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.variables-storage`, `elementor.design-system`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Dynamic and Global References

- **Technical ID:** `elementor_dynamic_references`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `elementor_documents`
- **Source kind:** `elementor_saved_document_references`
- **Implementation:** `real`
- **Declared source truth:** `PARTIAL`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `sources/elementor/dynamic-references.json`
- **Schema:** `urn:edis:schema:elementor:dynamic-references` version `1.0.0`
- **Dependencies:** `elementor_document_source` (REQUIRED)

### Plain-language summary

Saved references such as __globals__, variables, classes, and privacy-safe dynamic-tag signatures.

### What is this evidence?

References connect local declarations to registries without copying sensitive dynamic values.

### Where does it come from and how is it retrieved?

A bounded recursive scan inspects saved document settings for Global, Variable, CSS custom-property, Dynamic Tag and class-reference candidates without exporting sensitive configurations.

### Which fields are exported?

Reference kind, document/element IDs, property path, binding order, style or registry ID, privacy-safe hash, and bounded raw value only where contract-safe.

### Why is it important to the pipeline?

Python resolves known registries, preserves unresolved references, and never treats a dynamic expression as its runtime value.

### How can Python use it?

Python resolves known registries, preserves unresolved references, and never treats a dynamic expression as its runtime value.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Reference candidates are not resolved values. Dynamic Tag contents can be private, so raw configurations remain excluded.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `PARTIAL` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.global-styles`, `elementor.dynamic-tags`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Elementor Document Inventory

- **Technical ID:** `elementor_document_inventory`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `elementor_documents`
- **Source kind:** `wordpress_query_and_post_meta`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `sources/elementor/document-inventory.json`
- **Schema:** `urn:edis:schema:elementor:document-inventory` version `1.0.0`
- **Dependencies:** `elementor_installation` (REQUIRED), `elementor_registered_document_types` (OPTIONAL)

### Plain-language summary

A bounded list of source documents and privacy-safe identity facts.

### What is this evidence?

The inventory is the starting point for explicit document selection and source/runtime matching.

### Where does it come from and how is it retrieved?

Queried through bounded paginated WordPress post APIs and Elementor metadata checks. It does not copy document source.

### Which fields are exported?

Document ID as string, title hash or bounded label policy, document type, post status, edit permission, Elementor-data presence, routability candidates and bounded result count.

### Why is it important to the pipeline?

Python uses document fingerprints, source hashes, types, and locator candidates to identify compatible runtime observations.

### How can Python use it?

Python uses document fingerprints, source hashes, types, and locator candidates to identify compatible runtime observations.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Inventory is bounded and permission-scoped. Missing records can mean query limits, status filters or access restrictions rather than absence.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.general-structure`, `wordpress.posts`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Selected Document Source

- **Technical ID:** `elementor_document_source`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `elementor_documents`
- **Source kind:** `wordpress_post_meta`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `sources/elementor/documents/selected-documents.json`
- **Schema:** `urn:edis:schema:elementor:document-source` version `1.0.0`
- **Dependencies:** `elementor_document_inventory` (REQUIRED)

### Plain-language summary

The recursive saved Elementor element trees selected for analysis.

### What is this evidence?

Source documents preserve V3, Container, Atomic, and hybrid structures without rejecting unknown fields.

### Where does it come from and how is it retrieved?

Captured once into protected private storage when the job is created, verified for drift, then read only from that immutable job snapshot. The worker does not reread live document source between steps.

### Which fields are exported?

Selected document ID/type/status, immutable job snapshot identity, captured saved Elementor tree, page settings, saved-source SHA-256, original-source inclusion state and source paths.

### Why is it important to the pipeline?

Python parses elements, settings, styles, interactions, editor metadata, responsive declarations, and references.

### How can Python use it?

Python parses elements, settings, styles, interactions, editor metadata, responsive declarations, and references.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Saved source is not rendered DOM. The snapshot freezes selected document source only; other environment registries remain separately timestamped source evidence.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`elementor.page-content`, `elementor.atomic-elements`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Plugin Inventory

- **Technical ID:** `plugin`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `wordpress`
- **Source kind:** `wordpress_public_api`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `environment/plugins.json`
- **Schema:** `urn:edis:schema:wordpress:plugins` version `1.0.0`
- **Dependencies:** `environment` (REQUIRED)

### Plain-language summary

Active plugin facts.

### What is this evidence?

The list identifies addons that may register Elementor widgets, controls, or document types.

### Where does it come from and how is it retrieved?

Read from WordPress plugin APIs. No plugin source code, settings, license key, or secret configuration is exported.

### Which fields are exported?

Plugin basename, name, version, activation state, network activation evidence, and bounded provenance needed to identify addons.

### Why is it important to the pipeline?

Python can explain addon provenance and avoid assuming every element comes from Elementor Core.

### How can Python use it?

Python can explain addon provenance and avoid assuming every element comes from Elementor Core.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

An active addon does not prove that any of its widgets are used in the selected documents.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`wordpress.plugins`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## Theme Inventory

- **Technical ID:** `theme`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `wordpress`
- **Source kind:** `wordpress_public_api`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `environment/theme.json`
- **Schema:** `urn:edis:schema:wordpress:theme` version `1.0.0`
- **Dependencies:** `environment` (REQUIRED)

### Plain-language summary

Theme facts affecting source and render context.

### What is this evidence?

The active theme can add templates, styles, breakpoints, and layout behavior.

### Where does it come from and how is it retrieved?

Read through WordPress theme APIs. Theme files and customizer secrets are not copied.

### Which fields are exported?

Active theme name, stylesheet, template, version, parent-theme identity and bounded theme provenance.

### Why is it important to the pipeline?

Python uses theme identity as provenance and a compatibility constraint.

### How can Python use it?

Python uses theme identity as provenance and a compatibility constraint.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

Theme identity does not reveal which CSS rule produced a rendered value; Browser evidence is required.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`wordpress.themes`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

## WordPress Environment

- **Technical ID:** `environment`
- **Component type:** `SOURCE_COLLECTOR`
- **Group:** `wordpress`
- **Source kind:** `wordpress_public_api`
- **Implementation:** `real`
- **Declared source truth:** `VERIFIED`
- **Default source availability:** `AVAILABLE`
- **Artifact path:** `environment/wordpress.json`
- **Schema:** `urn:edis:schema:wordpress:environment` version `1.0.0`
- **Dependencies:** None

### Plain-language summary

Environment facts used to interpret every other artifact.

### What is this evidence?

Versions and environment capabilities that influence source contracts.

### Where does it come from and how is it retrieved?

Read through public WordPress runtime APIs. URLs are represented by bounded facts or hashes rather than credentials, query strings, or fragments.

### Which fields are exported?

WordPress and PHP versions, locale, timezone, multisite flag, debug flag, memory limit, privacy-safe URL hashes, and site path scope.

### Why is it important to the pipeline?

Python routes schemas, compatibility rules, and capability checks with these facts.

### How can Python use it?

Python routes schemas, compatibility rules, and capability checks with these facts.

### What reaches the LLM?

The LLM receives only Python findings derived from this evidence. It does not receive authority to reinterpret missing evidence as fact.

### What cannot be concluded?

This evidence describes saved source configuration, not rendered usability or design quality. Browser evidence and Python resolution are required for runtime conclusions.

### Version limitations

A version or environment fact does not prove that a specific Elementor feature works correctly; capability and source evidence must confirm it.

### Privacy impact

The component avoids secrets, credentials, form values, raw cookies, nonces, and unrestricted content. Privacy mode may further reduce exported fields.

### Availability and truth-state interpretation

The declared truth state is `VERIFIED` and the default availability is `AVAILABLE`. The actual export may downgrade availability or truth according to declared `REQUIRED`, `OPTIONAL`, and `CONDITIONAL` dependencies. Empty evidence is never silently promoted to success.

### Example artifact envelope

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
    "evidence": "<typed component payload>",
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

### Official and contract references

`wordpress.version`, `php.version`

### Troubleshooting

Review Source Availability, diagnostics, Elementor activation/version, selected documents, and the component dependency list.

