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
BENCH_FILTER=RealisticBuildBench make bench
```

## Benchmark classes

- **`ContentParserBench`** — measures parsing speed for site config, navigation, collections, authors, and entries (with and without body loading)
- **`MarkdownRendererBench`** — measures MD4C markdown-to-HTML rendering for short and long documents
- **`BuildBench`** — measures full build pipeline with 10k small entries, sequential and parallel, cached and uncached
- **`RealisticBuildBench`** — measures full build pipeline with 1k realistic entries (~27KB each, cross-links, images, tables, styled text)

## Baseline results

### 10k small entries (~1KB each)

| Benchmark                      | Time   |
|--------------------------------|--------|
| Full build, sequential         | ~1.54s |
| Full build, 2 workers          | ~1.30s |
| Full build, 4 workers          | ~1.18s |
| Full build, 8 workers          | ~1.12s |
| Full build, cached, sequential | ~1.47s |
| Full build, cached, 4 workers  | ~1.14s |
| Parse only (metadata, no body) | ~96ms  |
| Parse with body read           | ~137ms |
| Render only (markdown→HTML)    | ~196ms |
| Single short markdown render   | ~1.4μs |
| Single long markdown render    | ~73μs  |

### 1k realistic entries (~27KB each)

| Benchmark                      | Time   |
|--------------------------------|--------|
| Full build, sequential         | ~250ms |
| Full build, 4 workers          | ~150ms |
| Full build, 8 workers          | ~131ms |
| Full build, cached, sequential | ~151ms |
| Full build, cached, 4 workers  | ~122ms |
| Render only (markdown→HTML)    | ~103ms |

Caching provides ~40% speedup for sequential builds with realistic content. Combined with parallel workers, the total speedup is ~2x.

Measured on PHP 8.5 with `ext-md4c`, `ext-yaml`, and `ext-pcntl`, xdebug off, OPCache disabled.

Benchmarks are run with xdebug disabled automatically (`make bench` sets `XDEBUG_MODE=off`).
