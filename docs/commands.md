# Console commands

All commands are run via the `yii` CLI entry point (or `composer serve` for the dev server).

## `yii build`

Generates static HTML content from source files.

```
yii build [--content-dir=content] [--output-dir=output] [--workers=1] [--no-cache] [--drafts] [--future]
```

**Options:**

- `--content-dir`, `-c` — path to the content directory (default: `content`). Absolute or relative to project root.
- `--output-dir`, `-o` — path to the output directory (default: `output`). Absolute or relative to project root.
- `--workers`, `-w` — number of parallel workers (default: `1`). Uses `pcntl_fork()` to distribute entry rendering across processes.
- `--no-cache` — disable build cache. By default, rendered HTML is cached in `runtime/cache/build/` keyed by source file content hash. Unchanged entries skip markdown rendering and template application on subsequent builds.
- `--drafts` — include draft entries in the build. By default, entries with `draft: true` in front matter are excluded from HTML output, feeds, and sitemap.
- `--future` — include future-dated entries in the build. By default, entries with a date in the future are excluded from HTML output, feeds, and sitemap.

The command:

1. Parses site config, navigation, collections, authors, and entries from the content directory.
2. Cleans the output directory.
3. Renders collection entries — converts markdown to HTML via MD4C, applies the entry template, writes each entry as `index.html` at its resolved permalink path. Drafts and future-dated entries are excluded by default.
4. Renders standalone pages — markdown files in the content root directory (e.g., `contact.md` → `/contact/`).
5. Generates Atom (`feed.xml`) and RSS 2.0 (`rss.xml`) feeds for each collection with `feed: true`.
6. Generates paginated collection listing pages (e.g., `/blog/`, `/blog/page/2/`) for collections with `listing: true`.
7. Generates `sitemap.xml` containing all entry URLs, standalone page URLs, collection listing URLs, and the home page.
8. Generates taxonomy pages for each taxonomy defined in `config.yaml` (e.g., `/tags/`, `/tags/php/`, `/categories/`).

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
