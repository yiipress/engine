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

## Benchmark classes

- **`ContentParserBench`** — measures parsing speed for site config, navigation, collections, authors, and entries (with and without body loading)
- **`MarkdownRendererBench`** — measures MD4C markdown-to-HTML rendering for short and long documents
- **`AssetFingerprintingBench`** — measures fingerprint lookup and HTML asset URL rewriting
- **`OEmbedProcessorBench`** — measures standalone URL-to-embed expansion across pluggable oEmbed providers
- **`SmallSiteBuildBench`** — measures the public `yii build` command end to end on 10k small entries, including full rebuilds and incremental rebuilds
- **`LargeContentBuildBench`** — measures the public `yii build` command end to end on 1k realistic entries (~27KB each), including full rebuilds and incremental rebuilds

## Baseline results

### 10k small entries (~1KB each)

| Benchmark                               | Time   |
|-----------------------------------------|--------|
| Full rebuild, sequential                | ~10.042s |
| Full rebuild, 4 workers                 | ~4.586s  |
| Incremental rebuild, no changes         | ~367.443ms |
| Incremental rebuild, 1 changed entry    | ~358.293ms |

### 1k realistic entries (~27KB each)

| Benchmark                               | Time    |
|-----------------------------------------|---------|
| Full rebuild, sequential                | ~2.738s |
| Full rebuild, 4 workers                 | ~1.332s |
| Incremental rebuild, no changes         | ~102.017ms |
| Incremental rebuild, 1 changed entry    | ~105.087ms |

These end-to-end benchmarks intentionally go through the public CLI entry point instead of internal renderer/parser classes,
so they track real rebuild timing rather than component-only throughput.

Measured on PHP 8.5 with `ext-md4c`, `ext-yaml`, and `ext-pcntl`, xdebug off, OPCache disabled.

Benchmarks are run with xdebug disabled automatically (`make bench` sets `XDEBUG_MODE=off`).
