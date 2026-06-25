# Export Workflow — EDIS 3.7.11

1. Authenticate and authorize the export capability and each selected document.
2. Normalize the request and canonicalize document/element selections.
3. Allocate the job ID.
4. Read exact saved `_elementor_data` bytes into a temporary private snapshot.
5. Parse source with EDIS-CJ-2; reject invalid UTF-8, duplicate keys and normalization-limit violations.
6. Re-read each source and compare the complete capture record; fail with `EDIS_SOURCE_CHANGED_DURING_SNAPSHOT` on drift.
7. Write source, metadata and `input-manifest.json` with durable atomic writes.
8. Atomically commit the Input Snapshot 2.0.0 directory.
9. Create Job Format 2.1.0 bound to the committed snapshot hash and initialized with an empty worker lease.
10. Advance selected source collectors, index builders and bundle processors in deterministic phase order.
11. Before each resume, verify the exact execution-plan prefix, step versions, step-input hashes and artifact-file hashes.
12. Build each artifact envelope using EDIS-CJ-2 and compute semantic and instance hashes.
13. Validate every final JSON artifact against the routed schemas and verify both hashes.
14. Build `package-manifest.json`, checksums and package-validation evidence.
15. Write the final EDIS-ZIP-1 archive to a temporary path, verify its bytes and atomically commit bundle metadata.
16. Permit download only after capability, nonce, owner, object access, token, managed-path, size and SHA-256 checks pass.

## Resume contract

```text
job_format_version == 2.1.0
input_snapshot_format_version == 2.0.0
input snapshot hash matches
completed components equal the exact current-plan prefix
component schema/implementation versions match
step input hashes match
artifact files exist and file SHA-256 values match
```

A mismatch is an integrity failure, not a transient retry.

## Snapshot scope

The immutable snapshot freezes selected document source and bounded document metadata used by the document-source collector. Kit settings, Variables, Global Classes, theme/plugin inventory and other registries remain independent evidence artifacts with separate provenance unless a future contract explicitly freezes them together.

## Package determinism

Archive entry order is UTF-8 byte order. Entries use STORE, fixed DOS time `1980-01-01 00:00:00`, UTF-8 flags, fixed `0644` file attributes, no comments, no extra fields and no ZIP64. Rebuilding from identical files must produce identical bytes and SHA-256.

## Upgrade behavior

Incomplete 3.7.0 and earlier jobs are incompatible with Job Format 2.1.0 and must be recreated. Input Snapshot Format remains 2.0.0. Completed historical packages are not rewritten. Bundle Schema 3.3.0 and Shared Envelope 2.0.0 require explicit Browser/Python routing.


## WordPress scheduling and lease behavior

The authenticated REST worker is the primary interactive executor. WP-Cron and `wp edis worker run` are bounded wake-up paths; neither owns semantic ordering.

Before advancing a non-terminal job, a worker acquires the job lock and claims a lease. An unexpired foreign lease prevents a second worker from advancing the job. Each committed step refreshes the heartbeat and lease expiry. Terminal states clear the lease. `wp edis jobs repair` is dry-run by default and only requeues stale jobs when `--apply` is provided.

## Conditional boot

Normal frontend page views do not load collectors, schema registries, private stores or admin/REST controllers. Admin, REST, Cron, AJAX and WP-CLI requests load the bounded application runtime required by that context.


## Version 3.7.11 lock-owned transitions

Before job creation, preflight reruns the complete active-path private-storage test and requires separate-process exclusion. Before Resume or Retry changes any job field, it acquires the stable per-job lock. The resumed transition is persisted and the first advancement executes before that lock is released. On lock conflict, status, revision and diagnostics remain unchanged.

Expiry cleanup and ordinary removal may delete a job JSON record but do not unlink its `.lock` sentinel. This preserves lock identity across processes and prevents a new pathname/inode from being created while an earlier handle remains locked.

Operational-key exclusions in semantic projection are matched by full path at the approved envelope roots. Source fields nested below evidence objects are preserved regardless of their bare property names.
