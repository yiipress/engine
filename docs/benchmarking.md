# Benchmarking

YiiPress uses [PHPBench](https://phpbench.readthedocs.io/) to track performance regressions.

## Generating benchmark data

Generate 10,000 small test entries (default):

```bash
make bench-generate
```

Generate 1,000 realistic entries (~27KB each, with links, images, tables, styled text):

```bash
make bench-generate-realistic
```

Custom entry count:

```bash
make bench-generate 5000
```

Generated data is stored in `benchmarks/data/` and is gitignored.

## Running benchmarks

Run all benchmarks:

```bash
make bench
```

Run a specific benchmark class:

```bash
BENCH_FILTER=LargeContentBuildBench make bench
```

Profile the public build command with Xdebug:

```bash
make profile-build CLI_ARGS='build --content-dir=benchmarks/data/content --output-dir=runtime/profile-output --workers=1 --no-cache'
```

Print build phase timings without Xdebug:

```bash
make yii CLI_ARGS='build --content-dir=benchmarks/data/content --output-dir=runtime/profile-output --workers=4 --no-cache --profile'
```

Render the full build path without writing site output files:

```bash
make yii CLI_ARGS='build --content-dir=benchmarks/data/content --output-dir=runtime/profile-output --workers=4 --no-cache --no-write --profile'
```

Use `--profile` for quick before/after checks while optimizing. Use `make profile-build` when call-level attribution is needed.
Use `--no-write` to separate render/template/processor cost from output directory and file writing cost.

## Benchmark classes

- **`ContentParserBench`** — measures parsing speed for site config, navigation, collections, authors, and entries (with and without body loading)
- **`MarkdownRendererBench`** — measures `iliaal/mdparser` markdown-to-HTML rendering for short and long documents
- **`SyntaxHighlighterBench`** — measures the reusable highlighter package path for plain HTML, raw code, a single highlighted block, and a page with many highlighted blocks
- **`QuestionProcessorBench`** — measures pages with 100 Markdown question blocks in inline and heading-grouped modes
- **`AssetFingerprintingBench`** — measures fingerprint lookup and HTML asset URL rewriting
- **`BuildProfileBench`** — measures overhead of disabled and enabled build phase timers
- **`OEmbedProcessorBench`** — measures standalone URL-to-embed expansion across pluggable oEmbed providers
- **`SmallSiteBuildBench`** — measures the public `yii build` command end to end on 10k small entries, including full rebuilds, no-write renders, and incremental rebuilds
- **`LargeContentBuildBench`** — measures the public `yii build` command end to end on 1k realistic entries (~27KB each), including full rebuilds, no-write renders, and incremental rebuilds

## Baseline results

### 10k small entries (~1KB each)

| Benchmark                               | Time   |
|-----------------------------------------|--------|
| Full rebuild, sequential                | ~9.281s |
| Full rebuild, 4 workers                 | ~4.176s |
| Incremental rebuild, no changes         | ~248.290ms |
| Incremental rebuild, 1 changed entry    | ~248.754ms |

### 1k realistic entries (~27KB each)

| Benchmark                               | Time    |
|-----------------------------------------|---------|
| Full rebuild, sequential                | ~2.213s |
| Full rebuild, 4 workers                 | ~868.285ms |
| Incremental rebuild, no changes         | ~94.132ms |
| Incremental rebuild, 1 changed entry    | ~88.954ms |

These end-to-end benchmarks intentionally go through the public CLI entry point instead of internal renderer/parser classes,
so they track real rebuild timing rather than component-only throughput.

Measured on PHP 8.5.8 with `ext-mdparser`, `ext-yaml`, and `ext-pcntl`, xdebug off, and OPCache enabled.

`PortableWorkerPoolBench` tracks the startup and job-transport overhead of two portable worker processes used by Windows builds.

Benchmarks are run with xdebug disabled automatically (`make bench` sets `XDEBUG_MODE=off`).

## Full regeneration investigation (September 2026)

Measure full regeneration with `--no-cache`, using the public build command. Run timing comparisons with
Xdebug disabled; profiler timings include instrumentation overhead and are only used to locate expensive calls.
The benchmark setup and teardown are outside the measured build invocation.

```bash
make bench CLI_ARGS='--filter=benchFullRebuild --iterations=5 --report=aggregate'
make bench CLI_ARGS='--filter=OutputMinifierBench --report=aggregate'
make profile-build CLI_ARGS='build --content-dir=benchmarks/data/content --output-dir=runtime/profile-output --workers=1 --no-cache --profile'
```

The initial Xdebug profile attributed 5.19 seconds to `preg_match_all()` called by `OutputMinifier::html()`
in an 18.02-second profiled build of 10,000 entries. The Mermaid div lookahead repeated a group containing
another variable-length repetition of unquoted attribute characters. Ordinary divs without a Mermaid class
caused excessive backtracking. Long attributes could also exhaust PCRE's backtracking limit, causing the
minifier to return the original HTML.

Consume one unquoted character per lookahead iteration to remove the ambiguous nested repetition while
preserving quoted attributes and Mermaid class matching. `OutputMinifierBench::benchOrdinaryDivs` exercises
this failure path; PHPUnit covers long ordinary attributes and Mermaid whitespace after a long unquoted attribute.

On this development machine (Docker, PHP 8.5.10, PHPBench 1.7.0, OPCache enabled), five iterations with
Xdebug disabled produced these PHPBench modal estimates. These are before/after measurements on the same machine;
the older baseline tables above were measured separately.

| Full rebuild | Before | After | Time reduction | Relative standard deviation, before / after |
|---|---:|---:|---:|---:|
| 10,000 small entries, sequential | 9.746 s | 4.653 s | 52.3% | 0.92% / 0.09% |
| 10,000 small entries, 4 workers | 4.984 s | 3.647 s | 26.8% | 1.74% / 1.63% |
| 1,000 realistic entries, sequential | 2.347 s | 1.971 s | 16.0% | 0.83% / 1.25% |
| 1,000 realistic entries, 4 workers | 1.156 s | 1.059 s | 8.4% | 0.18% / 0.50% |

The ordinary-div microbenchmark fell from 2.172 ms to 33.127 µs per 100 divs. Gains depend on page markup;
these measurements do not establish the same improvement for every theme or content set.

The follow-up Xdebug profile reduced the same `preg_match_all()` call from 5.19 seconds to 0.050 seconds.
Across 10,901 generated files, 7,901 were byte-identical. The remaining 3,000 HTML pages previously skipped
minification: applying the fixed minifier to each original page reproduced its new output exactly.
The full PHPUnit suite passed (1,030 tests, 3,743 assertions), as did PHPStan.

The first change alone had a tradeoff in the existing synthetic benchmark with 100 repeated article/pre/script blocks and no divs:
a longer confirmation run (1,000 revolutions, five iterations) increased from 1.298 ms to 1.577 ms (21.5%).
The full rebuild improvements above include the complete pipeline and outweigh that regression on both tested datasets.
The optimization is therefore workload-dependent, not a universal minifier speedup.

Local investigation artifacts are retained in `runtime/xdebug/regeneration-before.cachegrind` and
`runtime/xdebug/regeneration-after.cachegrind` (gitignored).

### Follow-up experiments

The post-fix Xdebug profile still attributed 0.456 seconds to `preg_replace()` directly inside
`OutputMinifier::html()`, and 1.177 seconds to `TemplateContext::themeAssetUrl()` across 65,196 calls.
Three approaches were measured separately:

1. **Protected-block whitespace:** the old regex searched the entire accumulated page before every protected block.
   Replacing it with a tail check helped, but trimming the accumulated string still copied growing prefixes.
   The final implementation trims each unprotected fragment before appending it. Its whitespace mask excludes NUL,
   and spaces adjoining ordinary text remain intact. `benchManyProtectedBlocks` tests scaling with 1,000 repeated blocks.
2. **Theme-asset URL cache (rejected):** caching by owner, root path, and path, with manifest invalidation,
   halved the focused helper benchmark (4.886 → 2.432 µs). Full-build changes were small and inconsistent,
   so the production cache was removed. The focused benchmark remains for future investigations.
3. **Native array whitespace replacement (rejected):** replacing the PHP token loop with `preg_replace()` on an array
   changed the 100-block benchmark from 215.814 to 210.018 µs and the 1,000-block benchmark from 3.875 to 3.762 ms,
   but ordinary divs worsened from 32.986 to 34.575 µs. This small, mixed component result did not justify the change.

A fresh baseline at `fd38cc3` and the initial tail-trimming experiment were measured separately with five PHPBench
iterations, Xdebug off, using the same two datasets and full public CLI builds:

| Full rebuild | `fd38cc3` | Initial tail trimming | Tail trimming + experimental asset cache |
|---|---:|---:|---:|
| 10,000 small entries, sequential | 4.757 s (±1.14%) | 4.269 s (±0.98%) | 4.199 s (±1.11%) |
| 10,000 small entries, 4 workers | 3.829 s (±3.62%) | 3.464 s (±1.67%) | 3.559 s (±2.47%) |
| 1,000 realistic entries, sequential | 2.007 s (±1.36%) | 1.901 s (±0.82%) | 1.892 s (±0.51%) |
| 1,000 realistic entries, 4 workers | 1.056 s (±0.49%) | 1.058 s (±0.36%) | 1.054 s (±0.39%) |

The realistic four-worker results are unchanged within noise. Do not infer a speedup for that scenario.

The final fragment-based implementation also removes the earlier synthetic regression. With the same benchmark
fixtures, the committed `fd38cc3` implementation versus the final candidate measured:

| Minifier input | `fd38cc3` | Final fragment trimming |
|---|---:|---:|
| 100 repeated article/pre/script blocks | 1.581 ms | 196.828 µs |
| 1,000 repeated article/pre/script blocks | 138.739 ms | 2.061 ms |
| 100 ordinary divs | 32.853 µs | 32.758 µs |

The larger case exposes the repeated whole-page scans: increasing the input tenfold previously increased time
about 88-fold, versus about tenfold with fragment trimming. Ordinary-div performance remains unchanged.

Final full-build measurements, with only fragment trimming retained:

| Full rebuild | `fd38cc3` | Final | Time reduction |
|---|---:|---:|---:|
| 10,000 small entries, sequential | 4.757 s | 4.231 s (±0.47%) | 11.1% |
| 10,000 small entries, 4 workers | 3.829 s | 3.428 s (±2.28%) | 10.5% |
| 1,000 realistic entries, sequential | 2.007 s | 1.893 s (±0.51%) | 5.7% |
| 1,000 realistic entries, 4 workers | 1.056 s | 1.052 s (±0.49%) | Within noise |

The final Xdebug profile reduced time in `preg_replace()` called directly by `OutputMinifier::html()` from
0.456 seconds to 0.009 seconds. The new `rtrim()` and final-character checks together took 0.011 seconds.
Total instrumented build time was essentially unchanged (13.67 → 13.64 seconds); use the Xdebug-off PHPBench
results above for overall speed, not profiler wall time.

Byte-for-byte comparisons against `fd38cc3` output found no differences in all 10,901 small-site files and
all 1,130 realistic-site files. The final profile is retained locally as
`runtime/xdebug/regeneration-fragments.cachegrind` (gitignored).

Final validation: `make test` passed 1,031 tests and 3,747 assertions; `make phpstan` reported no errors.

### Parallel worker completion polling

Profiling the parent and all children of a four-worker 10,000-entry build showed 28 worker processes across
8 consecutive pools: entries, feeds, three listing batches, and three archive batches. The parent spent
3.903 seconds in `usleep()` in a 6.44-second instrumented build. This includes legitimate waiting for workers;
it is not all recoverable overhead. Serialization inside the pools took another 0.174 seconds.

The pool checked completion every 100 ms. The focused two-worker benchmark took 101.017 ms, and three
consecutive small batches took 302.856 ms. With a 10 ms interval these fell to 41.114 ms and 123.194 ms.
An added PHPUnit case checks that a slower first worker and faster second worker both finish before their
results are aggregated; it makes no wall-clock assertions.

Five-iteration, Xdebug-off full-build comparison against `b686ed6`:

| Full rebuild, 4 workers | 100 ms polling | 10 ms polling | 1 ms polling (retained) |
|---|---:|---:|---:|
| 10,000 small entries | 3.373 s (±2.52%) | 3.207 s (±0.58%) | 3.101 s (±0.35%) |
| 1,000 realistic entries | 1.051 s (±0.12%) | 954.156 ms (±0.61%) | 934.494 ms (±1.01%) |

Reproduce the timing checks with:

```bash
make bench CLI_ARGS='--filter=PortableWorkerPoolBench --report=aggregate'
make bench CLI_ARGS='--filter=benchFullRebuild4Workers --iterations=5 --report=aggregate'
```

The multi-process profile used an additional inherited PHP INI scan directory, setting
`xdebug.start_with_request=yes`, `xdebug.output_dir=/app/runtime/xdebug`, and
`xdebug.profiler_output_name=parallel.%p.cachegrind`, with `XDEBUG_MODE=profile` inherited by every worker.
The original profiles are retained locally under `runtime/xdebug/parallel-before/` (gitignored).

The retained 1 ms interval reduced full-build time by 8.1% and 11.1%, respectively. Sequential builds do not
use the pool. The focused two-worker benchmark measured 36.446 ms at 1 ms polling, and three batches took
109.057 ms.

An alternating, Xdebug-off CPU check of 20 small batches measured 10.6–12.0 ms of parent CPU time at 10 ms
polling versus 12.5–12.8 ms at 1 ms. Elapsed time fell from 820–841 ms to 708–723 ms. These CPU measurements
cover short batches on this Linux development machine; they do not establish a cross-platform CPU bound.

The follow-up profile contains the same 28 workers plus the parent. Parent sleep time fell from 3.903 to
3.354 seconds, while status checks took 0.005 seconds. The complete profiled build fell from 6.44 to 5.84 seconds.
Profiles are retained locally under `runtime/xdebug/parallel-after/` (gitignored).

The final small parallel build's 10,901 files matched its original parallel output byte for byte. The realistic
parallel build's 1,130 files also matched the previously verified sequential output byte for byte.

Validation: `make test` passed 1,032 tests and 3,750 assertions; `make phpstan` reported no errors.

### Secondary worker startup and feed payloads

A further comparison against `4c78743` tested two changes: limiting feed-job entries before serialization,
and increasing the default listing/archive threshold from 32 to 128 tasks per worker. With the new threshold,
batches below 256 tasks run in the parent; larger batches can still use multiple independent workers.
Feed jobs retain their explicit one-collection-per-worker threshold, and entry-page scheduling is unchanged.
The threshold is a measured heuristic for the bundled templates, not a universal crossover for custom templates.

Five-iteration PHPBench modal estimates, with Xdebug disabled:

| Full rebuild, 4 workers | Baseline | Feed payload only | Both changes | Both, repeat |
|---|---:|---:|---:|---:|
| 10,000 small entries | 3.084 s (±0.24%) | 3.044 s (±1.09%) | 2.671 s (±0.68%) | 2.646 s (±0.88%) |
| 1,000 realistic entries | 917.672 ms (±0.43%) | 903.419 ms (±0.95%) | 928.222 ms (±1.98%) | 908.337 ms (±1.32%) |

The larger fixture improves by 13.4–14.2%. The realistic fixture already keeps its small listing/archive
batches sequential; its results do not establish a consistent full-build improvement. Feed-payload limiting
alone produces only a small full-build change, near measurement noise.

The new `FeedWorkerJobBench` isolates construction and serialization of a default limited feed job with
10,000 distinct entries. The original job took 14.266 ms (±1.93%); limiting it to the first 20 entries before
serialization took 12.393 µs (±2.21%). Zero and negative limits still retain all entries, and the caller's
complete entry list is unchanged. PHPUnit covers these cases, ordering, empty tasks, serialization round trips,
and scheduling boundaries at 255, 256, and 512 tasks. An existing parallel aggregation test now explicitly
sets its small test workload's threshold so it actually exercises workers.

```bash
make bench CLI_ARGS='--filter=FeedWorkerJobBench --report=aggregate'
make bench CLI_ARGS='--filter=benchFullRebuild4Workers --iterations=5 --report=aggregate'
```

The follow-up multi-process Xdebug profile has 7 workers plus the parent, down from 28 workers: entry and
feed pools remain, while the three listing and three archive pools disappear. Time in serialization called
by the pool drops from 0.182 to 0.055 seconds, and parent sleeping drops from 3.354 to 2.434 seconds.
Instrumented total time increases from 5.84 to 6.11 seconds because listing/archive rendering is now serial
under Xdebug's instrumentation overhead. The Xdebug-off measurements above establish the production-speed
gain; profiler wall time is not a substitute. New profiles are retained locally as
`runtime/xdebug/secondary.*.cachegrind` (gitignored).

All 10,901 small-site files and 1,130 realistic-site files match the previous outputs byte for byte.
Validation: `make test` passed 1,039 tests and 3,797 assertions; `make phpstan` reported no errors.

### Collapsing text whitespace without a PHP token loop

The `dcc49cd` multi-process Xdebug profile still identifies HTML minification as a rendering hotspot.
In one entry worker, `OutputMinifier::html()` takes 0.664 seconds, including 0.440 seconds in
`minifyHtmlFragment()`. The latter splits each fragment into tags and text and invokes `preg_replace()`
separately for each text token.

The replacement skips complete tags with PCRE's `(*SKIP)(*F)` and collapses text whitespace in one native
replacement pass. A preliminary scan detects unmatched markup and sends it through the existing token
implementation, preserving its behavior for incomplete tags. Regex failures also use that fallback.
Protected element bodies and whitespace inside attributes remain intact.

Xdebug-off focused PHPBench modal estimates:

| Subject | Before | After |
|---|---:|---:|
| 100 article/pre/script blocks | 191.264 µs (±0.86%) | 126.738 µs (±1.07%) |
| 100 ordinary divs | 31.760 µs (±2.16%) | 19.577 µs (±2.05%) |
| 1,000 article/pre/script blocks | 1.968 ms (±1.87%) | 1.372 ms (±2.29%) |

These represent 30–38% reductions in minifier time. A deterministic comparison of 10,000 mixed-markup
inputs, including incomplete tags, quotes, protected blocks, and control characters, matches the original
implementation. PHPUnit adds explicit attribute-whitespace and incomplete-tag regression cases.

Reproduce with:

```bash
make bench CLI_ARGS='--filter=OutputMinifierBench --report=aggregate'
make bench CLI_ARGS='--filter=benchFullRebuild --iterations=5 --report=aggregate'
```

The added `benchIncompleteMarkup` subject caught excessive backtracking in the first candidate's tag scan
(1.637 ms versus the original 24.098 µs). Making the disjoint tag alternatives possessive fixes that
regression: the final fallback benchmark takes 26.513 µs (±1.16%). This retains about 2.4 µs of scanning
overhead for that malformed fragment, in exchange for the measured normal-output reductions.

Final five-iteration full-build modal estimates against the fresh `dcc49cd` baseline:

| Full rebuild | Before | Final | Time reduction |
|---|---:|---:|---:|
| 10,000 small entries, sequential | 4.024 s (±0.42%) | 3.798 s (±0.70%) | 5.6% |
| 10,000 small entries, 4 workers | 2.598 s (±0.71%) | 2.519 s (±0.80%) | 3.0% |
| 1,000 realistic entries, sequential | 1.794 s (±0.69%) | 1.681 s (±0.28%) | 6.3% |
| 1,000 realistic entries, 4 workers | 884.632 ms (±0.35%) | 858.675 ms (±0.66%) | 2.9% |

The final Xdebug profile reduces `minifyHtmlFragment()` time in the corresponding entry worker from
0.440 to 0.062 seconds and total `OutputMinifier::html()` time from 0.664 to 0.168 seconds. The complete
instrumented build drops from 6.11 to 5.16 seconds. Instrumentation magnifies the cost of the eliminated
PHP calls; the Xdebug-off full-build results above are the relevant speedup measurements. Final profiles
are retained locally as `runtime/xdebug/minifier-final.*.cachegrind` (gitignored).

All 10,901 small-site files and 1,130 realistic-site files match the `dcc49cd` outputs byte for byte.
Validation: `make test` passed 1,041 tests and 3,801 assertions; `make phpstan` reported no errors.

### Native collection sorting experiment (not retained)

A follow-up to `4fa6036` examined the PHP comparator calls in collection sorting and site-wide feed sorting.
The previous Xdebug profile attributes about 0.160 seconds to collection sorting, but instrumentation
amplifies the cost of calling the comparator repeatedly. The tested candidate computed collection keys once,
used native stable `asort()`/`arsort()`, and rebuilt the entry list from the sorted indices. Site-wide feed
sorting was left unchanged in this experiment.

The added `EntrySorterBench::benchSortDates` uses 10,000 entries with mixed, repeated dates. Five-iteration,
Xdebug-off modal estimates fall from 10.992 ms (±1.75%) to 1.961 ms (±0.93%): an 82.2% local reduction.
However, the full four-worker rebuilds do not establish a benefit:

| Full rebuild, 4 workers | `4fa6036` baseline | Native collection sorting candidate |
|---|---:|---:|
| 10,000 small entries | 2.529 s (±0.55%) | 2.551 s (±0.63%) |
| 1,000 realistic entries | 857.366 ms (±0.31%) | 859.260 ms (±0.37%) |

The candidate was reverted because it did not demonstrate a full-regeneration improvement. The benchmark
and ordering regression tests are retained for future work. Tests cover stable ties in both directions,
null dates, dates before the Unix epoch, microseconds, equivalent time zones, numeric-looking titles,
unknown sort fields, and duplicate explicit-order entries. All passed against both implementations.

```bash
make bench CLI_ARGS='--filter=EntrySorterBench --report=aggregate'
make bench CLI_ARGS='--filter=benchFullRebuild4Workers --iterations=5 --report=aggregate'
make test CLI_ARGS='--filter=EntrySorterTest'
```

The reverted candidate is retained locally as `runtime/EntrySorterCandidate.php` (gitignored).

Validation after reverting the candidate: `make test` passed 1,045 tests and 3,814 assertions;
`make phpstan` reported no errors. Production code is unchanged from `4fa6036`.

### Avoiding worker startup for small feed batches

The next retained optimization schedules collection feeds by the number of entries actually included,
respecting positive feed limits and counting all entries for zero/negative limits. Batches below 1,000
selected entries run sequentially. Larger batches retain parallel execution, capped by collection count
and requested workers. This threshold is a conservative heuristic from the measurements below, not a
universal crossover for every content processor or machine. Entry-page scheduling is unchanged.

`FeedBatchBench` parses the 1,000-entry realistic fixture outside the timed section and generates the
three collection feeds (Atom, RSS, JSON) without writing output. It uses the default feed processors;
parent and children both omit authors in this focused benchmark. Five-iteration, Xdebug-off modal estimates:

| Feed workload | Sequential | Forced parallel | Final selector |
|---|---:|---:|---:|
| 20 entries per collection | 5.702 ms (±1.57%) | 76.649 ms (±1.12%) | 5.980 ms (±1.12%) |
| 100 entries per collection | 31.195 ms (±1.87%) | 91.227 ms (±1.67%) | 30.904 ms (±1.90%) |
| Unlimited, 1,000 entries total | 148.094 ms (±2.94%) | 138.696 ms (±1.94%) | 139.152 ms (±1.35%) |

This establishes a substantial startup penalty for small default-pipeline feeds, while retaining parallel
execution for the larger measured batch. It does not establish that 1,000 is the exact optimal threshold.

Five-iteration full-build measurements with four requested workers:

| Full rebuild | Initial baseline | Forced sequential feeds experiment | Final selector | Baseline repeated afterward |
|---|---:|---:|---:|---:|
| 10,000 small entries | 2.529 s (±0.55%) | 2.478 s (±0.74%) | 2.505 s (±1.80%) | 2.556 s (±0.11%) |
| 1,000 realistic entries | 857.366 ms (±0.31%) | 787.580 ms (±0.46%) | 813.760 ms (±1.34%) | 861.080 ms (±0.64%) |

The final realistic rebuild is 5.1–5.5% faster than the bracketing baselines. The small-entry fixture shows
only a modest change (about 1–2%), with higher candidate variance, so that gain is less conclusive.
The repeated baseline restored the original feed worker count temporarily; the final tree uses the selector.

The follow-up Xdebug build starts four entry workers, eliminating the three feed workers. The profiled feed
phase falls from 314 to 233 ms; total instrumented build time changes from 5.16 to 5.11 seconds. These
instrumented timings are diagnostic; use the Xdebug-off results above for overall performance. Profiles
are retained locally as `runtime/xdebug/feed-volume.*.cachegrind` (gitignored).

```bash
make bench CLI_ARGS='--filter=FeedBatchBench --report=aggregate'
make bench CLI_ARGS='--filter=benchFullRebuild4Workers --iterations=5 --report=aggregate'
```

All 10,901 small-site files and 1,130 realistic-site files match the preceding verified outputs byte for byte.
PHPUnit covers empty batches, selected-entry limits, unlimited feeds, the 999/1,000 boundary, single-collection
batches, and worker caps. `make test` passed 1,054 tests and 3,823 assertions; `make phpstan` reported no errors.
