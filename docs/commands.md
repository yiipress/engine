# Console commands

All commands are run via the `yii` CLI entry point (or `composer serve` for the dev server).

## `yii build`

Generates static HTML content from source files.

```
yii build [--content-dir=content] [--output-dir=output] [--workers=1] [--no-cache] [--drafts] [--future] [--dry-run]
```

**Options:**

- `--content-dir`, `-c` — path to the content directory (default: `content`). Absolute or relative to project root.
- `--output-dir`, `-o` — path to the output directory (default: `output`). Absolute or relative to project root.
- `--workers`, `-w` — number of parallel workers (default: `1`). Uses `pcntl_fork()` to distribute entry rendering across processes.
- `--no-cache` — disable build cache and incremental builds. Forces a full rebuild, clearing the output directory. By default, rendered HTML is cached in `runtime/cache/build/` and a build manifest tracks source file hashes for incremental builds.
- `--drafts` — include draft entries in the build. By default, entries with `draft: true` in front matter are excluded from HTML output, feeds, and sitemap.
- `--future` — include future-dated entries in the build. By default, entries with a date in the future are excluded from HTML output, feeds, and sitemap.
- `--dry-run` — list all files that would be generated without writing anything. The output directory is not created or modified.

### Incremental builds

By default, subsequent builds are incremental — only changed source files are re-rendered and re-written. A build manifest (`runtime/cache/build-manifest-*.json`) tracks source file hashes between builds. If no files changed, the build exits immediately with "No changes detected".

Aggregate pages (feeds, listings, archives, sitemap, taxonomy, author pages) are always regenerated since they depend on the full entry set.

If config files (`config.yaml`, `navigation.yaml`, `_collection.yaml`) change, a full rebuild is triggered automatically.

Use `--no-cache` to force a full rebuild.

### Build diagnostics

During the build, diagnostics are run on all entries and standalone pages. Warnings are printed before output generation:

- **Broken internal links** — markdown links to `.md` files that don't resolve to any known entry or page.
- **Missing images** — `![...](path)` references to local files that don't exist in the content directory.
- **Unknown authors** — `authors` front matter values that don't match any author file in `content/authors/`.
- **Empty taxonomy values** — empty strings in `tags` or `categories` arrays.

Diagnostics are informational and do not prevent the build from completing.

The command:

1. Parses site config, navigation, collections, authors, and entries from the content directory.
2. Runs build diagnostics and prints warnings.
3. Cleans the output directory (or skips unchanged entries on incremental builds).
4. Renders collection entries — converts markdown to HTML via MD4C, applies the entry template, writes each entry as `index.html` at its resolved permalink path. Drafts and future-dated entries are excluded by default.
5. Renders standalone pages — markdown files in the content root directory (e.g., `contact.md` → `/contact/`).
6. Copies content assets (images, SVGs, etc.) to the output directory.
7. Generates Atom (`feed.xml`) and RSS 2.0 (`rss.xml`) feeds for each collection with `feed: true`.
8. Generates paginated collection listing pages (e.g., `/blog/`, `/blog/page/2/`) for collections with `listing: true`.
9. Generates `sitemap.xml` containing all entry URLs, standalone page URLs, collection listing URLs, and the home page.
10. Generates taxonomy pages for each taxonomy defined in `config.yaml` (e.g., `/tags/`, `/tags/php/`, `/categories/`).

With `--workers=N` (N > 1), entry rendering and writing is parallelized across N forked processes. Feeds and sitemap are generated after entry writing in the parent process.

## `yii serve`

Starts PHP built-in web server for local development.

```
yii serve [--port=8080]
```

Alternatively, use `composer serve` which disables the process timeout.

See [Web application](web-app.md) for details on static file serving and live reload.

## `yii clean`

Clears build output and caches.

```
yii clean [--output-dir=output]
```

**Options:**

- `--output-dir`, `-o` — path to the output directory (default: `output`). Absolute or relative to project root.

The command removes:

1. The output directory (default: `output/`).
2. The build cache directory (`runtime/cache/build/`).

If a directory does not exist, it is skipped with a notice.

## `yii` / `yii list`

Shows available commands and help.
