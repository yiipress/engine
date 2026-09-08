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
Profiler timings include instrumentation overhead; use Xdebug-disabled benchmarks for timing comparisons.
To profile a changed-entry build, first build an isolated fixture/output directory normally, edit one entry,
then run `make profile-build` with the same content and output paths, without `--no-cache`.

## Benchmark classes

- **`ContentParserBench`** — measures parsing speed for site config, navigation, collections, authors, and entries (with and without body loading)
- **`MarkdownRendererBench`** — measures `iliaal/mdparser` markdown-to-HTML rendering for short and long documents
- **`SyntaxHighlighterBench`** — measures the reusable highlighter package path for plain HTML, raw code, a single highlighted block, and a page with many highlighted blocks
- **`QuestionProcessorBench`** — measures pages with 100 Markdown question blocks in inline and heading-grouped modes
- **`AssetFingerprintingBench`** — measures fingerprint lookup and HTML asset URL rewriting
- **`DirectoryRemoverBench`** — compares build-output cleanup with `yiisoft/files` on 10,000 page directories; setup is outside timing, with one revision and no warmup because removal is destructive
- **`BuildProfileBench`** — measures overhead of disabled and enabled build phase timers
- **`OEmbedProcessorBench`** — measures standalone URL-to-embed expansion across pluggable oEmbed providers
- **`SmallSiteBuildBench`** — measures the public `yii build` command end to end on 10k small entries, including full rebuilds, no-write renders, and incremental rebuilds
- **`LargeContentBuildBench`** — measures the public `yii build` command end to end on 1k realistic entries (~27KB each), including full rebuilds, no-write renders, and incremental rebuilds

## Current results (8 September 2026)

### 10k small entries (~1 KB each)

| Benchmark | Time | Relative standard deviation |
|---|---:|---:|
| Full rebuild, sequential | 3.577 s | ±0.40% |
| Full rebuild, 4 workers | 2.157 s | ±1.11% |
| Full rebuild, 8 workers | 1.895 s | ±1.53% |
| Incremental rebuild, no changes, sequential | 261.031 ms | ±1.99% |
| Incremental rebuild, 1 changed entry, sequential | 829.506 ms | ±1.41% |

### 1k realistic entries (~27 KB each)

| Benchmark | Time | Relative standard deviation |
|---|---:|---:|
| Full rebuild, sequential | 1.655 s | ±1.30% |
| Full rebuild, 4 workers | 767.456 ms | ±0.82% |
| Incremental rebuild, no changes, sequential | 95.578 ms | ±1.00% |
| Incremental rebuild, 1 changed entry, sequential | 195.331 ms | ±1.38% |

These end-to-end benchmarks intentionally go through the public CLI entry point instead of internal renderer/parser classes,
so they track real rebuild timing rather than component-only throughput.

Results were measured on the `performance` branch in Docker on an AMD Ryzen 9 7950X
(16 cores, 32 threads), using PHP 8.5.10, PHPBench 1.7.0, `ext-mdparser`, `ext-yaml`, and `ext-pcntl`,
with Xdebug off and CLI OPCache enabled. Tables report modal estimates and relative standard deviation
from five iterations, with one measured build per iteration. Timings depend on hardware and filesystem.

Full rebuilds use `--no-cache` and one warmup, so measured builds include replacing existing output.
Unchanged incremental builds also use one warmup. Fixture setup and teardown are outside the measured
invocation.

Single-entry edit benchmarks build the site and append a newline to one entry during setup, outside
the timer. They intentionally have no `Warmup` attribute (PHPBench defaults to zero warmup calls) and
use exactly one timed revision, which consumes the pending edit. Additional warmups or revisions would
measure unchanged rebuilds. Do not override `--warmup` or `--revs` for these subjects. PHPBench 1.7.0
rejects the CLI override `--warmup=0`, so use the benchmark's default configuration instead.

PHPUnit regression coverage checks PHPBench's effective metadata, including setup ordering, to prevent
warmups or multiple revisions from consuming the edit before the intended measurement.

```bash
make bench CLI_ARGS="'--filter=benchFullRebuild|benchIncrementalNoChanges' --iterations=5 --report=aggregate"
make bench CLI_ARGS='--filter=benchIncrementalSingleChangedEntry --iterations=5 --report=aggregate'
```

`PortableWorkerPoolBench` tracks the startup and job-transport overhead of two portable worker processes used by parallel builds.

Benchmarks are run with xdebug disabled automatically (`make bench` sets `XDEBUG_MODE=off`).
