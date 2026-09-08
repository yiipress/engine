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
