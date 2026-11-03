# Task440 attribution experiment

Diagnostic branch only, based on published6250c. This workflow replaces `benchmark.yml` **only on
`diagnostics-440-6250c`** and accepts only manual dispatch. It is not the original benchmark gate,
an optimization candidate or a production PR. Preparing/reviewing this code grants no push or dispatch permission.
The Lead must separately authorize the reviewed exact head before any provider action.

The source checkouts are immutable actuald835 and6250c. A single dev image is built on the hosted runner,
used for both normal dependency/fixture setup commands, inspected and then pinned by image ID for collection.
PHP/PCRE/extensions/binary/allowlisted INI settings, original benchmark/lock/config hashes and every generated
fixture file must match before collection. Production source hashes are captured separately because the source
revisions deliberately differ. No source, benchmark, assertion, retry or required-check setting changes there.

Four top-level profiled invocations run sequentially, once each: baseline/current Asset12, then baseline/current
Feed parallel/unlimited. The Asset driver calls the original setup, original target once cold and100 times steady
in named wrappers. The Feed driver calls original setup and original `benchParallel(['limit' => 0])` once.
Its existing generated three-collection fixture yields three independent workers per reference, bounded by the
original maximum of four. Missing any expected worker profile makes the experiment inconclusive.
The Feed benchmark passes no asset manifest; this experiment does not posit an asset-matcher cause for Feed.

Phase-specific additional INI directories and `XDEBUG_MODE=profile` are inherited by real `proc_open` children.
Profiler activation is checked inside the sole real driver; no extra activation workload runs.
The collector checks target wrapper/benchmark call counts and requires actual worker Feed/pipeline paths.
Cachegrind raw event units, function self/inclusive costs and call edges are retained in parsed JSON beside every
raw profile. Inclusive/nested/recursive/overlapping worker costs must not be added into parent wall time.
Native `preg_match` costs do not explain instructions inside PCRE or hardware behavior.
See [Xdebug profiling documentation](https://xdebug.org/docs/profiler).

Limits are40 minutes for the job and6 minutes per collection step. The inner watchdog stops after345 seconds,
leaving time to stop only that invocation's named container. It also samples aggregate raw-profile size every100ms
against1GiB and checks final size. Sampling can overshoot between polls; an overshoot is failure/inconclusive,
not an allowed complete result. Partial or oversized raw profiles are preserved, without truncation or deletion.
Runtime mismatch, driver failure, deadline, budget, unsupported/incomplete Cachegrind or missing worker paths stop
all later collection. No retry, alternate profiler, extra target, PHPBench loop, new baseline or gate run exists.
The `always()` finalizer/upload retain failure artifacts; a runner/job cancellation can still prevent artifact
completion and must be reported as incomplete. No historical-runner hardware equivalence or stable-gain claim.

Evidence includes workflow/driver/INI/override bytes, dispatch head/run URL/job key, source/fixture hashes,
allowlisted runtime identity, sanitized Compose structure, stdout/stderr/exit code, raw profiles and parsed
summaries, and a SHA-256/size manifest. The numeric provider job URL must be recorded from the normal Actions
API after a future authorized run; it is unavailable before the job is created. Environment dumps, phpinfo,
provider credentials, deployment keys and expanded service environment values are never artifact inputs.
The task440 attachment cap remains binding; use the provider artifact and local evidence paths on that task.

Offline preparation checks use synthetic Cachegrind fixtures and mocked process/container control only.
`diagnostic-lint` uses an already existing image, disabled profiling, a read-only source mount and no network.
They do not execute the proposed experiment. Independent exact-head review precedes the Lead's provider decision.
Both actual remote benchmark failures remain open and unattributed regardless of a diagnostic job's status.

The first provider attempt failed before profiling because `compose run` rejected `--no-build`.
The preparation fix removes that unsupported option. Both image overrides explicitly reset the service's
`build` definition to null; `--pull never` remains, and the common inspected image ID is unchanged.
Before identity collection, `cli_support.py` compares the actual Make dry-run commands with the installed
`compose run --help` options and rejects an effective configuration that still contains a build definition.
This checks the exact runtime/profile commands without executing them; unsupported flags fail before collection.
The upload explicitly includes hidden files, so the manifest's `tools/.gitignore` is included instead of silently
omitted. No environment files are copied into the evidence tree. The original failed artifact remains preserved;
this fix does not authorize a second dispatch or change the collection budget or profiler targets.
