# Benchmarking

YiiPress uses [PHPBench](https://phpbench.readthedocs.io/) to track performance regressions.

## Generating benchmark data

Generate 10,000 test entries (default):

```bash
make bench-generate
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
make bench "--filter=ContentParserBench"
```

## Benchmark classes

- **`ContentParserBench`** — measures parsing speed for site config, navigation, collections, authors, and entries (with and without body loading)
- **`MarkdownRendererBench`** — measures MD4C markdown-to-HTML rendering for short and long documents
- **`BuildBench`** — measures full build pipeline (parse + render + write), parse-only, and render-only phases

## Baseline results (10k entries)

| Benchmark | Time |
|---|---|
| Full build, sequential | ~1.52s |
| Full build, 2 workers | ~1.32s |
| Full build, 4 workers | ~1.19s |
| Full build, 8 workers | ~1.12s |
| Parse only (metadata, no body) | ~93ms |
| Parse with body read | ~137ms |
| Render only (markdown→HTML) | ~197ms |
| Single short markdown render | ~1.4μs |
| Single long markdown render | ~69μs |

Measured on PHP 8.5 with `ext-md4c`, `ext-yaml`, and `ext-pcntl`, xdebug off, OPCache disabled.

Benchmarks are run with xdebug disabled automatically (`make bench` sets `XDEBUG_MODE=off`).
