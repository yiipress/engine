# Configuration

YiiPress has two separate configuration layers:

- **Content config** (`content/config.yaml`) — site-level settings for templates and content generation
- **Engine config** (`config/`) — Yii3 framework internals (DI, routing, middleware, environments)

Users edit `content/config.yaml` to customize their site. Engine config should rarely need changes.

## Content config

`content/config.yaml` defines site-wide settings available to all templates via the `$config` variable.

```yaml
title: My Site
description: A site built with YiiPress
base_url: https://example.com
language: en
charset: UTF-8

default_author: john-doe

date_format: F j, Y
entries_per_page: 10

permalink: /:collection/:slug/

taxonomies:
  - tags
  - categories

params:
  github_url: https://github.com/example/mysite
  twitter: "@example"
```

### Fields

- **title** — site title, used in layouts, feeds, and meta tags
- **description** — site description for meta tags and feeds
- **base_url** — full base URL including scheme (used in feeds, sitemaps, canonical URLs)
- **language** — default language code (e.g., `en`, `ru`)
- **charset** — character encoding (default: `UTF-8`)
- **default_author** — author slug (referencing a file in `content/authors/`), used when entries have no explicit `authors` field
- **date_format** — PHP date format string for displaying dates in templates
- **entries_per_page** — default pagination size (overridden by collection `_collection.yaml`)
- **permalink** — default permalink pattern (overridden by collection or entry)
- **taxonomies** — list of enabled taxonomy types
- **theme** — default theme name for the site (see [Templates](template.md))
- **params** — arbitrary key-value pairs for use in templates
- **markdown** — markdown extensions configuration (see below)

### Usage in templates

Currently, the entry template receives individual variables (`$siteTitle`, `$entryTitle`, `$content`, `$date`, `$author`, `$collection`). Full `$config` access in templates is planned for the theming system.

### Markdown extensions

The `markdown` section controls which markdown extensions are enabled. All options are boolean.

```yaml
markdown:
  tables: true
  strikethrough: true
  tasklists: true
  autolinks: true
  collapse_whitespace: false
  latex_math: false
  wikilinks: false
  underline: false
  html_blocks: true
  html_spans: true
```

- **tables** — GitHub-style tables (default: `true`)
- **strikethrough** — strikethrough with `~text~` (default: `true`)
- **tasklists** — GitHub-style task lists (default: `true`)
- **autolinks** — permissive URL autolinks without `<>` (default: `true`)
- **collapse_whitespace** — collapse non-trivial whitespace into a single space (default: `false`)
- **latex_math** — LaTeX math spans `$...$` and `$$...$$` (default: `false`)
- **wikilinks** — wiki-style links `[[link]]` (default: `false`)
- **underline** — underscore `_` denotes underline instead of emphasis (default: `false`)
- **html_blocks** — allow raw HTML blocks (default: `true`)
- **html_spans** — allow inline raw HTML (default: `true`)

If the `markdown` section is omitted, all defaults apply.

### Defaults and overrides

Collection `_collection.yaml` fields override content config defaults:

- Collection `entries_per_page` overrides `config.yaml` `entries_per_page`
- Collection `permalink` overrides `config.yaml` `permalink`
- Entry `permalink` overrides collection permalink

Resolution order: entry → collection → content config → engine defaults.

## Engine config

The `config/` directory contains Yii3 framework configuration:

- `config/common/` — DI containers, routes, aliases, bootstrap
- `config/web/` — web-specific DI and params
- `config/console/` — console-specific params and commands
- `config/environments/` — environment overrides (dev, test, prod)

Engine config controls framework internals: routing, middleware, dependency injection,
asset management, and view rendering. It is not exposed to templates.
