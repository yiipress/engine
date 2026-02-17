# Performance Profiling Findings

## Initial Profile (before optimizations, opcache off)

Profiled with XDebug + phpbench on `benchFullBuildSequential` (10k entries, sequential, no cache).

| Self Time | Calls  | Function                          | Category        |
|-----------|--------|-----------------------------------|-----------------|
| 2320ms    | 20,000 | `EntryRenderer->render`           | Rendering       |
| 1823ms    | 20,000 | `EntryRenderer->renderTemplate`   | Rendering       |
| 1441ms    | 3      | `BuildBench->cleanOutputDir`      | I/O cleanup     |
| 1225ms    | 20,000 | `require::entry.php` (template)   | Rendering       |
| 1110ms    | 60,000 | `TemplateContext->partial`         | Rendering       |
| 678ms     | 60,000 | `TemplateContext::{closure}`       | Rendering       |
| 588ms     | 20,000 | `file_put_contents`               | I/O write       |
| 583ms     | 20,006 | `ContentParser->parseEntries`     | Parsing         |
| 434ms     | 20,000 | `EntryParser->parse`              | Parsing         |
| 431ms     | 20,000 | `mkdir`                           | I/O             |
| 308ms     | 80,000 | `TemplateResolver->resolve`       | Rendering       |
| 283ms     | 60,000 | `TemplateResolver->resolvePartial`| Rendering       |
| 191ms     | 20,000 | `Entry->body`                     | I/O read        |
| 184ms     | 20,000 | `MarkdownRenderer->render`        | Rendering       |

## Post-optimization Profile (opcache on)

| Self Time | Calls  | Function                          | Category        |
|-----------|--------|-----------------------------------|-----------------|
| 597ms     | 20,006 | `ContentParser->parseEntries`     | Parsing         |
| 557ms     | 20,000 | `file_put_contents`               | I/O write       |
| 516ms     | 20,000 | `EntryRenderer->renderTemplate`   | Rendering       |
| 445ms     | 20,000 | `EntryParser->parse`              | Parsing         |
| 427ms     | 20,000 | `EntryRenderer::{closure}`        | Rendering       |
| 425ms     | 20,000 | `mkdir`                           | I/O             |
| 355ms     | 20,000 | `require::entry.php`              | Rendering       |
| 328ms     | 20,000 | `FrontMatterParser->parse`        | Parsing         |
| 258ms     | 60,000 | `TemplateContext->partial`         | Rendering       |
| 194ms     | 20,000 | `ContentProcessorPipeline`        | Rendering       |
| 188ms     | 20,000 | `Entry->body`                     | I/O read        |
| 177ms     | 40,000 | `fopen`                           | I/O             |
| 168ms     | 60,000 | `TemplateContext::{closure}`       | Rendering       |
| 162ms     | 20,000 | `MarkdownRenderer->render`        | Rendering       |
| 152ms     | 20,000 | `md4c_toHtml`                     | Rendering       |

Remaining bottlenecks are mostly irreducible I/O: writing output files, reading entry bodies,
creating directories, and parsing content (which happens once per entry).

## Completed Optimizations

### 1. Eliminate redundant entry parsing

`BuildCommand::execute` called `parseEntries()` 8× per collection. Each call re-reads every `.md`
file from disk and re-parses YAML front matter. Refactored to parse once and reuse arrays.

`ParallelEntryWriter` called `parseEntries()` 2× internally (`collectAllEntries` + `collectTasks`).
Refactored to accept pre-built tasks.

Result: ~50% speedup in ParallelEntryWriter benchmarks.

### 2. Cache PermalinkResolver results

`PermalinkResolver::resolve()` was called twice per entry. Now resolved once and reused via
`$fileToPermalink` map.

### 3. Cache TemplateResolver->resolve and resolvePartial

Added in-memory cache keyed by `theme + template name`. Eliminates ~140k redundant `is_file()`
filesystem calls per 10k-entry build.

### 4. Cache template closures and TemplateContext in EntryRenderer

Template closures are created once per unique template path and reused for all entries.
`TemplateContext` instances and partial closures are cached per theme name, avoiding
object allocation per entry.

### 5. Cache partial closures in TemplateContext

Partial template closures are created once per partial name and reused, avoiding redundant
`resolvePartial` calls and closure allocation.

### 6. Deduplicate mkdir in ParallelEntryWriter

Collect unique output directories before creating them, avoiding redundant `dirname` + `is_dir`
calls when many entries share the same output directory.

### 7. Optimize php.ini for CLI build workload

Settings in `docker/php.ini` (shared by dev and prod):

| Setting                          | Default | Optimized | Why                                              |
|----------------------------------|---------|-----------|--------------------------------------------------|
| `opcache.enable_cli`             | `0`     | `1`       | Cache compiled PHP files across CLI invocations   |
| `opcache.validate_timestamps`    | `1`     | `0`       | Files don't change mid-build, skip stat() calls   |
| `opcache.save_comments`          | `1`     | `0`       | No doc-comment reflection used, saves memory      |
| `opcache.file_update_protection` | `2`     | `0`       | No need to wait 2s for file stability in CLI      |
| `opcache.interned_strings_buffer`| `8`     | `16`      | More headroom for string interning                |

Production-only (`docker/php-prod.ini`):

| Setting       | Value    | Why                                                    |
|---------------|----------|--------------------------------------------------------|
| `opcache.jit` | `tracing`| JIT compilation for CPU-bound work. Incompatible with xdebug, so dev-only disabled. |

Test memory usage dropped from 30MB to 12MB after disabling `save_comments`.

## Benchmark Results

10k entries, 3 collections, opcache on, xdebug off:

| Benchmark                   | Time    |
|-----------------------------|---------|
| Sequential                  | 1.489s  |
| 2 Workers                   | 1.314s  |
| 4 Workers                   | 1.181s  |
| 8 Workers                   | 1.180s  |
| Cached Sequential           | 1.494s  |
| Cached 4 Workers            | 1.176s  |
| Parse Only                  | 108ms   |
| Render Only                 | 217ms   |

Realistic (1k large entries):

| Benchmark                   | Time    |
|-----------------------------|---------|
| Sequential                  | 252ms   |
| 4 Workers                   | 152ms   |
| 8 Workers                   | 135ms   |

## How to Profile

```bash
make bench-profile BENCH_FILTER=benchFullBuildSequential
```

Cachegrind files are written to `runtime/xdebug/`. Open in KCachegrind / QCachegrind for
visual analysis, or parse with a script:

```python
# Extract top functions by self time from cachegrind output
import re
from collections import defaultdict

fn_names = {}
fn_self_time = defaultdict(int)
fn_calls = defaultdict(int)
current_fn = None

with open('runtime/xdebug/cachegrind.out.<id>') as f:
    for line in f:
        line = line.strip()
        m = re.match(r'^fn=\((\d+)\)\s+(.*)', line)
        if m:
            fn_names[m.group(1)] = m.group(2).strip()
            current_fn = m.group(1)
            fn_calls[current_fn] += 1
            continue
        m = re.match(r'^fn=\((\d+)\)$', line)
        if m:
            current_fn = m.group(1)
            fn_calls[current_fn] += 1
            continue
        if current_fn and re.match(r'^\d+\s+\d+', line):
            parts = line.split()
            if len(parts) >= 2:
                fn_self_time[current_fn] += int(parts[1])

for fid, t in sorted(fn_self_time.items(), key=lambda x: -x[1])[:30]:
    ms = t / 100000
    print(f'{ms:>10.1f}ms  {fn_calls.get(fid, 0):>8}  {fn_names.get(fid, fid)}')
```
