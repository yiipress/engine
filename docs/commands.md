# Console commands

All commands are run via the `yii` CLI entry point (or `composer serve` for the dev server).

## `yii build`

Generates static HTML content from source files.

```
yii build [--content-dir=content] [--output-dir=output] [--workers=1] [--no-cache]
```

**Options:**

- `--content-dir`, `-c` — path to the content directory (default: `content`). Absolute or relative to project root.
- `--output-dir`, `-o` — path to the output directory (default: `output`). Absolute or relative to project root.
- `--workers`, `-w` — number of parallel workers (default: `1`). Uses `pcntl_fork()` to distribute entry rendering across processes.
- `--no-cache` — disable build cache. By default, rendered HTML is cached in `runtime/cache/build/` keyed by source file content hash. Unchanged entries skip markdown rendering and template application on subsequent builds.

The command:

1. Parses site config, navigation, collections, authors, and entries from the content directory.
2. Cleans the output directory.
3. Converts markdown to HTML via MD4C and applies the entry template.
4. Writes each entry as `index.html` at its resolved permalink path.
5. Generates Atom (`feed.xml`) and RSS 2.0 (`rss.xml`) feeds for each collection with `feed: true`.
6. Generates `sitemap.xml` containing all entry URLs, collection listing URLs, and the home page.

With `--workers=N` (N > 1), entry rendering and writing is parallelized across N forked processes. Feeds and sitemap are generated after entry writing in the parent process.

## `yii serve`

Starts PHP built-in web server for local development.

```
yii serve [--port=8080]
```

Alternatively, use `composer serve` which disables the process timeout.

## `yii` / `yii list`

Shows available commands and help.
