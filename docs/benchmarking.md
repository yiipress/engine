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

A tradeoff remains in the existing synthetic benchmark with 100 repeated article/pre/script blocks and no divs:
a longer confirmation run (1,000 revolutions, five iterations) increased from 1.298 ms to 1.577 ms (21.5%).
The full rebuild improvements above include the complete pipeline and outweigh that regression on both tested datasets.
The optimization is therefore workload-dependent, not a universal minifier speedup.

Local investigation artifacts are retained in `runtime/xdebug/regeneration-before.cachegrind` and
`runtime/xdebug/regeneration-after.cachegrind` (gitignored).
