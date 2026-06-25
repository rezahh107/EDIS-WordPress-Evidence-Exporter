# Truth and Availability Model

`source_truth_state` describes confidence in the source contract: VERIFIED, PARTIAL, UNKNOWN, UNSUPPORTED.

`source_availability` describes whether evidence exists for this export: AVAILABLE, PARTIAL, INSUFFICIENT, DISABLED, UNAVAILABLE, NOT_APPLICABLE, ERROR.

These dimensions are independent. `PARTIAL + AVAILABLE` means evidence exists but its semantic contract remains fixture- or version-bounded.
