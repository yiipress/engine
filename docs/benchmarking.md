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
| Full build (parse + render + write) | ~1.5s |
| Parse only (metadata, no body) | ~150ms |
| Parse with body read | ~200ms |
| Render only (markdown→HTML) | ~260ms |
| Single short markdown render | ~2μs |
| Single long markdown render | ~73μs |

Measured on PHP 8.5 with `ext-md4c` and `ext-yaml`, xdebug enabled, OPCache disabled.
