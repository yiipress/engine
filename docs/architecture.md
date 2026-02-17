# Architecture

YiiPress operates in two modes: **build** (static site generation) and **serve** (near realtime development preview).
Both modes share the same content pipeline but differ in output target.

## High-level flow

```
content/          ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
  *.md        ──▶ │  Parse   │──▶ │  Index   │──▶ │  Render  │──▶ │  Write   │
  *.yaml          └──────────┘    └──────────┘    └──────────┘    └──────────┘
templates/                                              │           output/
plugins/                                           (templates)      (build)
                                                                  or HTTP response
                                                                    (serve)
```

1. **Parse** — read content files, extract front matter and markdown body
2. **Index** — organize entries into collections, taxonomies, archives; resolve permalinks
3. **Render** — apply plugins, convert markdown to HTML, render templates
4. **Write** — emit static files to `output/` (build) or return HTTP response (serve)

## Core concepts

### Content source

All user content lives under `content/`. The engine treats this directory as read-only input.

- `content/config.yaml` — site-wide settings
- `content/navigation.yaml` — menu definitions
- `content/<name>/` — collection directories, each with `_collection.yaml` and entry `.md` files
- `content/authors/` — author definitions
- `content/assets/` and `content/<collection>/assets/` — static assets copied as-is

### Value objects

The domain model consists of immutable value objects. No entity has mutable state after construction.

- **SiteConfig** — parsed `content/config.yaml`
- **Navigation** — parsed `content/navigation.yaml`
- **Collection** — parsed `_collection.yaml` + resolved settings (permalink pattern, sort, pagination)
- **Entry** — parsed front matter + raw markdown body + resolved metadata (slug, date, permalink)
- **Author** — parsed author file
- **Taxonomy** — a taxonomy type (tags, categories) with its terms and associated entries
- **TaxonomyTerm** — a single term (e.g., one tag) with its entry list
- **Page** — a rendered output page: resolved URL + HTML content (entry page, listing page, archive page, feed, sitemap, etc.)

### Collections and entries

A `Collection` groups entries and defines their shared behavior (sorting, pagination, permalink pattern, feed generation). Each first-level directory under `content/` is a collection. Since there could be collections with a large number of entries, it is recommended to use lazy loading for entries and yield for iteration so it fits into servers with limited memory.

An `Entry` belongs to exactly one collection. Its metadata is resolved by merging (highest priority first):

```
entry front matter → collection _collection.yaml → content/config.yaml → engine defaults
```

## Performance architecture

Performance is a first-class architectural concern, not an afterthought. It is achieved by eliminating unnecessary work rather than adding complexity.

### Principle 1: Eliminate unnecessary dependencies

Every dependency might add autoload overhead, potential bugs, and attack surface.
Prefer PHP built-ins and small, focused C extensions over large PHP libraries:

- **YAML parsing** — use PHP's `yaml_parse()` (PECL yaml extension) instead of pure-PHP YAML parsers.
  The `yaml_parse()` function wraps libyaml (C) and is significantly faster.
  Front matter parsing is called once per entry, so this is on the hot path for large sites.
- **Markdown-to-HTML** — use [MD4C](https://github.com/mity/md4c) via PECL `ext-md4c` (`md4c_toHtml()`)
  instead of pure-PHP parsers like Parsedown. MD4C is a C library that is an order of magnitude faster.  
- **Templating** — use plain PHP templates. PHP itself is a template language.  
  YiiPress uses Yii3 view renderer, which renders plain PHP files with no compilation step.

### Principle 2: Do less work

- **Two-pass front matter extraction** — first pass: read only the front matter (bytes before the second `---`).
  Do not read the markdown body into memory until render time. For index-only operations
  (listing pages, taxonomy grouping, sorting), the body is never needed.
- **Single-file rebuild** — support rebuilding a single entry without re-processing the entire site.
  The index can be cached and selectively invalidated. This is critical for incremental builds
  and for the `serve` mode live reload.
- **Skip unnecessary pages** — do not generate taxonomy pages if no entries use taxonomies.
  Do not generate feeds for collections with `feed: false`. Do not generate archive pages
  for collections not sorted by date.

### Principle 3: Parallelize output

Entry rendering (template execution + file write) consumes 40–60% of total build time.
Each entry page is independent — no entry's rendered output depends on another entry's output.

Use `pcntl_fork()` to render and write entries in parallel (benchmarked; fibers don't help since work is CPU-bound):

```
entries = indexedEntries
N workers
each worker processes entries[i] where i % N == workerIndex
parent waits for all workers
continue with collection index pages (serial, fast)
```

This approach requires no shared memory or synchronization — each worker writes to a different file path.
The parent process handles collection listing pages, taxonomy pages, feeds, and sitemap serially
(these are few and fast).

### Principle 4: Leverage PHP runtime optimizations

- **OPCache** — templates are PHP files included repeatedly. OPCache compiles them once and reuses
  the compiled bytecode.
- **JIT** — PHP 8's JIT compiler (opcache.jit=1255) provides additional gains for CPU-bound template rendering.
- **Preloading** — consider `opcache.preload` for the engine's own classes to eliminate autoload overhead during build.

### Principle 5: Build and serve must produce identical output

The same content pipeline (parse → index → render) must be used in both modes.
The only difference is the output target: filesystem vs. HTTP response.
This prevents the class of bugs where static build and dynamic serve produce different HTML.

## Build pipeline

The build pipeline is a sequence of discrete stages. Each stage produces output consumed by the next.

### Stage 1: Parse

Responsible for reading raw files from disk and producing structured data.

- **ContentParser** — orchestrates parsing of all content files
  - **FrontMatterParser** — extracts YAML front matter from `.md` files.
    Reads only the bytes between the two `---` delimiters, parses with `yaml_parse()`.
    Does not read the markdown body — that is deferred to render time
  - **CollectionConfigParser** — parses `_collection.yaml` files
  - **SiteConfigParser** — parses `content/config.yaml`
  - **NavigationParser** — parses `content/navigation.yaml`
  - **FilenameParser** — extracts date and slug from entry filenames

Parsing is file-level and stateless. Each file is parsed independently.

**Performance considerations:**

- Parse only what is needed. Front matter is small; the markdown body is stored as a raw string and not parsed until render.
- If body is not needed, postpone parsing it until render by even not loading it from disk.
- Use `SplFileInfo` / directory iterators to avoid loading file contents until needed.
- Consider fibers to parse files concurrently when I/O bound.
- Consider using FFI or PECL extensions to parse YAML front matter faster.

**Memory strategy:**

- `FrontMatterParser` reads only front matter bytes via `fgets()` line-by-line. The markdown body is never loaded into memory during parsing.
- `Entry` and `Author` store `bodyOffset` and `bodyLength` instead of the body string. The `body()` method reads from disk on demand via `fseek()`/`fread()`.
- `parseEntries()`, `parseAllEntries()`, and `parseAuthors()` return `Generator` instances. Entries and authors are yielded one at a time, never collected into arrays by the parser. Consumers decide whether to collect or stream.
- YAML config files (`config.yaml`, `_collection.yaml`, `navigation.yaml`) are small and loaded fully — this is intentional since their size is negligible.

### Stage 2: Index

Builds the in-memory site model from parsed data.

- **SiteIndex** — the complete indexed site, holding all collections, entries, taxonomies, archives, and navigation
  - Resolves entry permalinks using collection and site-level patterns
  - Sorts entries within each collection
  - Groups entries by taxonomy terms into pre-computed lookup structures (term → entry list)
  - Groups entries by date for archive pages
  - Resolves author references
  - Filters out drafts and future-dated entries (unless in dev mode)
  - Computes pagination slices

The index is built once and is read-only afterward. All queries during rendering go through it.
Optimize the structure for future access for the rest of the build pipeline so it is both O(1) or O(N) and memory efficient.

**Memory considerations:**
- Entries store raw markdown, not parsed HTML. HTML is produced on demand during render.
- The index holds references, not copies. For example, a taxonomy term holds entry references, not duplicated entry data.
- For very large sites (10k+ entries), consider lazy collections that load entry bodies from disk on demand, keeping only metadata in memory.

### Stage 3: Render

Converts the indexed site model into output pages.

- **PageGenerator** — iterates the site index and produces `Page` objects for every output URL:
  - Entry pages (one per entry)
  - Collection listing pages (paginated)
  - Taxonomy listing pages (all tags, all categories)
  - Taxonomy term pages (entries for a specific tag/category, paginated)
  - Author pages
  - Date-based archive pages (yearly, monthly)
  - Feed pages (RSS/Atom per collection with `feed: true`)
  - Sitemap
  - Redirect pages (for entries with `redirect_to`)
  - Error pages (404)

- **MarkdownRenderer** — converts raw markdown to HTML via MD4C (PECL `ext-md4c`), applying plugins before and after conversion
- **TemplateRenderer** — renders plain PHP templates via Yii view component, resolving template by type and collection name. No intermediate template language — PHP is the template language

Render order:

```
raw markdown → plugins (pre-parse) → markdown-to-HTML → plugins (post-parse) → template → layout → Page
```

**Performance considerations:**
- Render pages independently — no page depends on another page's rendered output.
- Parallelize entry rendering via `pcntl_fork()` — distribute entries across N workers (see Performance architecture above).
- Cache parsed markdown between builds (keyed by file content hash).
- OPCache eliminates repeated PHP template compilation. Ensure templates are stable files, not generated on the fly.

### Stage 4: Write

Outputs rendered pages to their destination.

- **StaticWriter** — writes `Page` objects as files to `output/` directory, creating directory structure from permalinks.
  In parallel mode, each forked worker writes its own subset of entry pages via `file_put_contents()`.
  Collection index pages, feeds, sitemap, and taxonomy pages are written by the parent process after workers finish
- **AssetCopier** — copies `content/assets/`, `content/<collection>/assets/`, and processed build assets to `output/`
- **HttpResponder** — in serve mode, returns the rendered page as an HTTP response instead of writing to disk

## Content processor pipeline

Content processors transform entry content through a sequential pipeline. Each processor implements `ContentProcessorInterface` with a single `process(string $content, Entry $entry): string` method.

### ContentProcessorPipeline

`ContentProcessorPipeline` chains processors in order. Each processor receives the output of the previous one:

```php
$content = $entry->body();
foreach ($processors as $processor) {
    $content = $processor->process($content, $entry);
}
```

Two separate pipelines are configured via Yii3 DI container in `config/common/di/content-pipeline.php`:

- **contentPipeline** — used by `EntryRenderer` for entry pages: `MarkdownProcessor` → `SyntaxHighlightProcessor`
- **feedPipeline** — used by `FeedGenerator` for feeds: `MarkdownProcessor` only (no syntax highlighting in feeds)

### Built-in processors

- **MarkdownProcessor** — converts markdown to HTML using md4c. Accepts `MarkdownConfig` for feature toggles
- **SyntaxHighlightProcessor** — server-rendered code block highlighting via Rust FFI. Uses syntect + rayon compiled to a shared library (`src/Highlighter/`)

### Planned processors

- **ShortcodeProcessor** — `[youtube id="..."  /]`, `[figure ... /]` expansion (before markdown)
- **TableOfContentsProcessor** — heading extraction and ToC generation (after markdown)
- **MermaidProcessor** — diagram rendering (after markdown)
- **OEmbedProcessor** — URL-to-embed expansion (before markdown)

## Serve mode

In serve mode, YiiPress runs as a Yii3 web application. Instead of writing static files, it renders pages on the fly.

- Routes are dynamically registered from the site index (one route per known permalink).
- A catch-all route handles 404s.
- File watching and live reload trigger re-parse and re-index on content changes.
- The existing Yii3 web infrastructure (router, middleware, view renderer) is reused.

## Caching

Caching targets the most expensive operations:

- **Parsed front matter cache** — keyed by file path + modification time. Avoids re-parsing unchanged files.
- **Markdown HTML cache** — keyed by content hash (accounts for plugin changes). Avoids re-rendering unchanged content.
- **Site index cache** — for incremental builds, the full index is cached and selectively invalidated when files change.

Cache storage: filesystem (`runtime/cache/`). No external dependencies.

## Directory structure (source code)

```
src/
├── Console/
│   ├── BuildCommand.php          # `yiipress build`
│   └── ServeCommand.php          # `yiipress serve`
├── Content/
│   ├── Model/
│   │   ├── SiteConfig.php
│   │   ├── Collection.php
│   │   ├── Entry.php
│   │   ├── Author.php
│   │   ├── Taxonomy.php
│   │   ├── TaxonomyTerm.php
│   │   ├── Navigation.php
│   │   └── Page.php
│   ├── Parser/
│   │   ├── ContentParser.php     # Orchestrates all parsing
│   │   ├── FrontMatterParser.php
│   │   ├── CollectionConfigParser.php
│   │   ├── SiteConfigParser.php
│   │   ├── NavigationParser.php
│   │   └── FilenameParser.php
│   ├── SiteIndex.php             # Indexed site model
│   └── SiteIndexBuilder.php      # Builds the index from parsed data
├── Highlighter/
│   ├── Cargo.toml                # Rust crate config (syntect + rayon)
│   ├── src/lib.rs                # Rust FFI library for code highlighting
│   └── SyntaxHighlighter.php     # PHP FFI binding
├── Processor/
│   ├── ContentProcessorInterface.php  # Processor interface
│   ├── ContentProcessorPipeline.php   # Chains processors in order
│   ├── MarkdownProcessor.php          # Markdown-to-HTML via md4c
│   └── SyntaxHighlightProcessor.php   # Code block highlighting
├── Render/
│   ├── PageGenerator.php         # Produces Page objects from site index
│   ├── MarkdownRenderer.php      # Low-level md4c wrapper
│   └── TemplateRenderer.php      # PHP template rendering via Yii view
├── Build/
│   ├── AuthorPageWriter.php      # Writes author index and individual pages
│   ├── BuildCache.php            # Content-hash based render cache
│   ├── BuildManifest.php         # Tracks source→output mappings for incremental builds
│   ├── CollectionListingWriter.php # Paginated collection listing pages
├── Shared/
│   └── ApplicationParams.php
├── Web/                          # Yii3 web actions for serve mode
│   ├── LiveReload/
│   │   ├── FileWatcher.php       # Polls directories for file changes
│   │   ├── LiveReloadAction.php  # SSE endpoint streaming reload events
│   │   ├── LiveReloadMiddleware.php # Injects live-reload JS into HTML
│   │   └── SiteBuildRunner.php   # Triggers yii build on file changes
│   ├── StaticFile/
│   │   └── StaticFileAction.php  # Serves files from output directory
│   ├── NotFound/
│   └── Shared/
└── Environment.php
```

## Dependency flow

```
Console Commands
      │
      ▼
  ContentParser ──▶ Model (value objects)
      │
      ▼
  SiteIndexBuilder ──▶ SiteIndex
      │
      ▼
  PageGenerator ──▶ MarkdownRenderer + TemplateRenderer ──▶ Page
      │                     │
      │               PluginRegistry
      ▼
  StaticWriter / HttpResponder
```

Dependencies point inward: output depends on render, render depends on index, index depends on parsing. The model layer has no dependencies on any other layer.

## Key design decisions

- **Value objects over entities** — all domain objects are immutable after construction. No hidden state mutations.
- **Composition over inheritance** — plugins, parsers, and renderers are composed, not subclassed.
- **Late markdown parsing** — markdown body is stored raw and converted to HTML only during render. This keeps memory usage low during parse and index stages.
- **File-based caching** — no external cache dependencies. Cache invalidation uses file modification time and content hashing.
- **Yii3 for web, minimal for build** — serve mode leverages Yii3 routing, DI, and view rendering. Build mode uses Yii3 DI for wiring but bypasses HTTP infrastructure.
- **Plugin simplicity** — plugins are string-in, string-out transformers. No complex lifecycle or dependency graph between plugins.
- **C for hot paths** — markdown-to-HTML (MD4C) and YAML parsing (`yaml_parse()`) are delegated to C libraries. PHP orchestrates; C does the byte-level work.
- **Fork-based parallelism** — `pcntl_fork()` for parallel entry rendering. No shared memory, no threads, no synchronization. Each worker is a full copy of the indexed site that writes to its own file paths.
