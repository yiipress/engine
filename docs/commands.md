# Commands

The examples below assume the static binary is in your project directory and use `./yiipress` so they work without changing `PATH`. Source checkouts expose the same commands through `./yii`; engine contributors should run them through the repository `make` targets.

## `build`

Generates static HTML content from source files.

```
./yiipress build [--content-dir=content] [--output-dir=output] [--workers=auto] [--no-cache] [--drafts] [--future] [--dry-run] [--no-write] [--profile]
```

**Options:**

- `--content-dir`, `-c` — path to the content directory (default: `content`). Absolute or relative to project root.
- `--output-dir`, `-o` — path to the output directory (default: `output`). Absolute or relative to project root.
- `--workers`, `-w` — number of parallel workers or `auto` (default: `auto`). Auto mode detects available CPU capacity inside the container, caps it at `4`, and still falls back to sequential work for small task sets.
- `--no-cache` — disable build cache and incremental builds. Forces a full rebuild, clearing the output directory. By default, rendered HTML is cached and a build manifest tracks source file hashes for incremental builds.
- `--drafts` — include draft entries in the build. By default, entries with `draft: true` in front matter are excluded from HTML output, feeds, and sitemap.
- `--future` — include future-dated entries in the build. By default, entries with a date in the future are excluded from HTML output, feeds, and sitemap.
- `--dry-run` — list all files that would be generated without writing anything. The output directory is not created or modified.
- `--no-write` — run the full render/generation path without writing site output files. This is intended for performance diagnostics: unlike `--dry-run`, it renders entries, templates, feeds, listings, archives, sitemap data, search data, taxonomy pages, and author pages. Build cache and incremental manifest writes are skipped.
- `--profile` — print phase timings for the real build path. Use this before and after optimization work to see which build phases moved.

### Incremental builds

By default, subsequent builds are incremental — only changed source files are re-rendered and re-written. A build manifest tracks source file hashes between builds. Source installs store it under `runtime/cache/`; PHAR and static binary runs store it under the OS temp directory, keyed by the current project directory. If no files changed, the build exits immediately with "No changes detected".

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
7. Generates Atom (`feed.xml`) and RSS 2.0 (`rss.xml`) feeds for each collection with `feed: true`, capped by collection `feed_limit` (`20` by default, `0` for unlimited).
8. Generates paginated collection listing pages (e.g., `/blog/`, `/blog/page/2/`) for collections with `listing: true`.
9. Generates `sitemap.xml` containing all entry URLs, standalone page URLs, collection listing URLs, and the home page.
10. Generates taxonomy pages for each taxonomy defined in `config.yaml` (e.g., `/tags/`, `/tags/php/`, `/categories/`).

With `--workers=N` (N > 1), entry rendering and writing is parallelized across N forked processes. With `--workers=auto`, YiiPress uses up to the detected worker count and lets page writers clamp back to sequential mode for smaller workloads. Feeds are generated after entry writing and can be split per collection across workers. Sitemap generation remains serial.

## `serve`

Starts the preview server for local development.

```
./yiipress serve [address] [--content-dir=content] [--output-dir=output] [--port=19777] [--workers=2]
```

**Options:**

- `--content-dir`, `-c` — path to the content directory (default: `content`). Absolute or relative to project root.
- `--output-dir`, `-o` — path to the output directory served by the preview server (default: `output`). Absolute or relative to project root.
- `--port`, `-p` — port to serve at when the address argument does not include a port (default: `19777`).
- `--workers`, `-w` — number of preforked server workers (default: `2`).

On startup, `serve` prints the URL it is listening on. Build progress is printed by rebuilds triggered after file changes. Content and output paths resolve from the current working directory, so run the binary from the site directory or pass explicit `--content-dir` and `--output-dir` paths.

HTML pages served by `serve` include a fixed bottom-right **Edit** button. It opens the markdown source file for the current page using the `editor` command from `content/config.yaml`; when `editor` is omitted, YiiPress uses the platform default opener (`open`, `xdg-open`, or Windows `start`). The button resolves source files through the build manifest, so it is available for entry and standalone markdown pages and hidden failures are reported in the browser console.

Before starting the server, `serve` verifies that the content directory exists and that the output directory exists or can be created and written to. If the check fails, pass explicit paths, for example `./yiipress serve --content-dir=content --output-dir=output`.

See [Preview](preview.md) for static file serving and live reload behavior. Implementation details are in [Engine](engine.md#serve-mode).

## `init`

Initializes a content directory with the minimal YiiPress structure:

- `config.yaml`
- `navigation.yaml`
- `page/_collection.yaml`
- `blog/_collection.yaml`

```
./yiipress init [--content-dir=content]
```

**Options:**

- `--content-dir`, `-c` — path to the content directory to create (default: `content`). Absolute or relative to project root.

The command creates parent directories as needed and fails if any scaffolded file already exists.

## `theme:init`

Initializes editable theme files in the project from a bundled theme.

```
./yiipress theme:init [target-dir] [--theme=minimal] [--content-dir=content]
```

**Arguments:**

- `target-dir` — directory to initialize theme files in (default: `themes/custom`). Absolute or relative to project root.

**Options:**

- `--theme`, `-t` — bundled theme name to use as the source (default: `minimal`).
- `--content-dir`, `-c` — path to the content directory containing `config.yaml` (default: `content`). Absolute or relative to project root.

The command creates parent directories as needed, fails if any target file already exists, and updates `config.yaml` to use the initialized theme. The theme name is derived from the target directory name, so the default target `themes/custom` sets `theme: "custom"`.

## `new`

Scaffolds a new content entry or standalone page.

```
./yiipress new <title> [--collection=<name>] [--content-dir=content] [--draft]
```

**Arguments:**

- `title` — title of the new entry (required).

**Options:**

- `--collection`, `-c` — collection to create the entry in. If omitted, creates a standalone page in the content root.
- `--content-dir`, `-d` — path to the content directory (default: `content`).
- `--draft` — mark the entry as a draft (`draft: true` in front matter).

**Behavior:**

- For collections with `sort_by: date`, the filename is prefixed with today's date (e.g., `2024-03-15-my-post.md`).
- For other collections, the filename is the slugified title (e.g., `my-post.md`).
- Standalone pages get a `permalink` field set to `/<slug>/`.
- If `default_author` is set in `config.yaml`, it is added to the `authors` front matter.
- The command fails if the target file already exists or the collection is not found.

**Examples:**

```bash
./yiipress new "My First Post" --collection=blog
./yiipress new "Draft Ideas" --collection=blog --draft
./yiipress new "About Us"
```

## `import`

Imports content from external sources into a YiiPress collection.

```
./yiipress import <source> [--collection=blog] [--content-dir=content] [--<importer-options>...]
```

**Arguments:**

- `source` — source type to import from (required). Currently supported: `telegram`.

**Common options:**

- `--collection`, `-c` — target collection name (default: `blog`).
- `--content-dir`, `-d` — path to the content directory (default: `content`).

Each importer declares its own options (see below). The command dynamically registers them based on the selected source.

**Behavior:**

- Each importer reads source-specific data and converts it to markdown files with front matter.
- If the target collection directory doesn't exist, it is created along with a default `_collection.yaml`.
- Existing `_collection.yaml` files are not overwritten.
- Media files (photos, attachments) are copied to the collection's `assets/` directory.

### Telegram import

Imports messages from a Telegram Desktop channel export. Export a channel via Telegram Desktop: Settings > Advanced > Export Telegram data (select JSON format).

**Importer options:**

- `--directory` — path to the Telegram export directory containing `result.json` (required). Absolute or relative to project root.

The importer reads `result.json` from the export directory and converts each message to a markdown file:

- **Hashtags** → `tags` in front matter (e.g., `#php` becomes tag `php`). Hashtags are removed from the body text.
- **Bold** → `**text**`
- **Italic** → `*text*`
- **Strikethrough** → `~~text~~`
- **Inline code** → `` `code` ``
- **Pre-formatted blocks** → fenced code blocks
- **Text links** → converted to standard Markdown link syntax
- **Photos** → copied to `assets/` and referenced with root-relative asset paths

The title is extracted from the first line of the message. The filename is prefixed with the message date (e.g., `2024-03-15-my-post.md`).

Supports both single-chat exports (`result.json` with `messages` array) and full exports (`result.json` with `chats.list` structure).

**Examples:**

```bash
./yiipress import telegram --directory=/path/to/telegram-export
./yiipress import telegram --directory=/path/to/telegram-export --collection=channel
./yiipress import telegram --directory=./telegram-data --content-dir=content
```

### Adding custom importers

Importers implement `YiiPress\Import\ContentImporterInterface` and are registered via [Yii3 DI](https://yiisoft.github.io/docs/guide/concept/di-container.html) in `config/common/di/importer.php`. Each importer declares its own options via the `options()` method. See [Importing content](importing-content.md) for details.

## `clean` / `clear`

Clears build output and caches.

```
./yiipress clean [--output-dir=output]
./yiipress clear [--output-dir=output]
```

**Options:**

- `--output-dir`, `-o` — path to the output directory (default: `output`). Absolute or relative to project root.

The command removes:

1. The output directory (default: `output/`).
2. The build cache and incremental manifests. Source installs use `runtime/cache/`; PHAR and static binary runs use a project-scoped cache under the OS temp directory.

If a directory does not exist, it is skipped with a notice.

## `yiipress` / `list`

Shows available commands and help.
