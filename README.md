<p align="center">
    <img src="logo.svg" alt="YiiPress" width="280">
</p>

**YiiPress** is a fast, file-based static website generator built on [Yii3](https://www.yiiframework.com/) and PHP 8.5. 
Write Markdown, run one command, get a fully static site — blogs, docs, portfolios, feeds, sitemaps, taxonomy pages, authors, search, and all.

[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![Yii3](https://img.shields.io/badge/Yii-3-007bff)](https://www.yiiframework.com/)
[![Docs](https://img.shields.io/badge/docs-yiipress.github.io-blueviolet)](https://yiipress.github.io/engine/)

---

## Features

### Content

- **Markdown** with 15+ configurable extensions (tables, footnotes, strikethrough, task lists, Mermaid diagrams, LaTeX math, wiki-links…)
- **Collections** — group entries into blogs, docs sections, portfolios, or any other set
- **Standalone pages** — about, contact, and other one-off pages outside collections
- **Taxonomies** — tags and categories with index and term pages
- **Authors** — per-author profile pages with bio and entry archives
- **Date archives** — yearly and monthly archive pages
- **Drafts & scheduling** — `draft: true` and future-dated entries excluded from production builds
- **Entry summaries** — auto-generated or manual via `[cut]` marker in body
- **Cross-references** — link between entries by file path; permalinks can change without breaking links

### Build

- **Parallel builds** — auto-selects a sensible worker count by default, with manual override available; 10 000 entries built in ~2.8 s with 4 workers in the current end-to-end benchmark
- **Incremental builds** — only re-renders files that changed since last build
- **Build cache** — parsed Markdown and front matter cached between runs
- **Dry-run mode** — preview what would be generated without writing anything
- **Build diagnostics** — warns about broken internal links, missing images, invalid front matter

### SEO & Standards

- RSS and Atom feeds per collection
- XML sitemap
- Open Graph and Twitter Card meta tags
- Canonical URLs
- Configurable `robots.txt`
- Redirect pages (for permalink migrations)
- Static `404.html` for Netlify, GitHub Pages, Vercel, Cloudflare Pages

### Extensions

- **Syntax highlighting** — server-side, via a Rust/[syntect](https://github.com/trishume/syntect) FFI library; zero client-side JavaScript
- **Configurable highlight themes** — choose a built-in syntect theme in `content/config.yaml` via `highlight_theme`
- **Table of contents** — auto-generated from headings, with `id` injection
- **Mermaid diagrams** — flowcharts, sequence, Gantt, pie, and more
- **YouTube & Vimeo shortcodes** — responsive embeds with a single tag
- **Auto-embeds for provider URLs** — standalone YouTube, Vimeo, and Twitter/X links expand automatically
- **Client-side search** — fuzzy search with modal UI; no external dependencies; opt-in
- **Asset fingerprinting** — enabled by default; content-hash filenames for CSS, JS, images, and other copied assets
- **Telegram import** — import channel exports as Markdown entries

### Developer Experience

- Live-reload dev server (`make up`)
- `yiipress new` — scaffold entries from archetypes
- `yiipress clean` — wipe output and caches
- PHP template engine with partials — no new templating language to learn
- Theme system — installable and distributable themes
- Docker-based setup — one command to start

---

## Performance

10 000 entries across 3 collections:

| Mode                | Time    |
|---------------------|---------|
| Sequential          | ~3.4 s  |
| 4 workers           | ~2.8 s  |
| Incremental         | ~358 ms |

1 000 realistic entries (large posts, images, tables, code blocks):

| Mode       | Time    |
|------------|---------|
| Sequential | ~2.0 s  |
| 4 workers  | ~1.1 s  |
| Incremental| ~108 ms |

---

## Quick Start

```bash
composer create-project yiipress/engine mysite
cd mysite
```

Configure `content/config.yaml`:

```yaml
title: My Site
base_url: https://example.com
permalink: /:collection/:slug/
taxonomies:
  - tags
  - categories
highlight_theme: "Solarized (dark)"
```

Create a collection in `content/posts/_collection.yaml`:

```yaml
title: Posts
sort_by: date
sort_direction: desc
feed: true
listing: true
```

Write a page in `content/posts/2024-01-15-hello-world.md`:

```markdown
---
title: Hello World
tags:
  - general
---

Welcome to my site!
```

Build and preview:

```bash
make yii build
make up          # dev server at http://localhost:8087
```

---

## Documentation

Full documentation is available in the [`docs/`](docs/) directory and rendered at **[yiipress.yiiframework.com](https://yiipress.yiiframework.com/)**.

| Guide | Description |
|---|---|
| [Quickstart](docs/quickstart.md) | Create your first site in minutes |
| [Content](docs/content.md) | Collections, front matter, taxonomies, authors |
| [Configuration](docs/config.md) | All `config.yaml` options |
| [Commands](docs/commands.md) | CLI reference |
| [Templates](docs/template.md) | Template variables, partials, themes |
| [Plugins](docs/plugins.md) | Content processors and importers |
| [Deployment](docs/deployment.md) | GitHub Pages, Netlify, Vercel, Cloudflare Pages |
