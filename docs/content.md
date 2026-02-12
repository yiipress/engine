# Content files

Content that YiiPress is generating from is stored in the `content` directory.

## Directory structure

```
content/
├── assets/                        # Global assets (logo, favicon, fonts)
│   └── logo.svg
├── blog/                          # Collection: blog
│   ├── _collection.yaml           # Collection config (title, description, pagination settings)
│   ├── assets/                    # Blog assets (images for blog entries)
│   │   └── hello-world-banner.svg
│   ├── 2024-01-15-hello-world.md  # Entry (date in filename)
│   └── my-second-post.md          # Entry (date in front matter)
├── docs/                          # Collection: docs (non-blog, sorted by weight)
│   ├── _collection.yaml
│   ├── getting-started.md
│   └── configuration.md
├── page/                          # Collection: standalone pages (no listing, no feed)
│   ├── _collection.yaml
│   ├── about.md
│   └── contact.md
├── authors/                       # Author definitions
│   ├── assets/                    # Author avatars
│   │   └── john-doe.svg
│   ├── john-doe.md
│   └── jane-smith.md
├── config.yaml                    # Site-wide settings (see docs/config.md)
└── navigation.yaml                # Menu definitions
```

## Collections

First-level directories under `content/` are **collections** (e.g., `blog/`, `docs/`).
Each collection groups related entries and can have its own pagination, sorting, and template settings.

A collection directory must contain a `_collection.yaml` file that defines collection-level metadata:

```yaml
title: Blog
description: Latest posts and articles
permalink: /blog/:slug/
sort_by: date
sort_order: desc
entries_per_page: 10
feed: true
```

### Collection `_collection.yaml` fields

- **title** — collection display name
- **description** — collection description for meta tags and feeds
- **permalink** — URL pattern for entries in this collection (see [Permalinks](#permalinks))
- **sort_by** — field to sort entries by: `date` (default), `weight`, `title`
- **sort_order** — `desc` (default) or `asc`
- **entries_per_page** — number of entries per page, `0` for no pagination
- **feed** — `true` to generate RSS/Atom feed for this collection
- **listing** — `true` to generate a collection index page (default: `true`)

## Entries

Each entry is a markdown file with YAML front matter.

### File naming

Entries can optionally embed the date in the filename:

- `2024-01-15-hello-world.md` — date `2024-01-15`, slug `hello-world`
- `my-post.md` — date must be specified in front matter, slug `my-post`

The filename-derived date is used only when `date` is not set in front matter.
The filename-derived slug is used only when `slug` is not set in front matter.

### Front matter fields

```yaml
---
title: My First Post
date: 2024-01-15
slug: my-first-post
draft: false
tags:
  - php
  - yii
categories:
  - tutorials
authors:
  - john-doe
summary: A brief introduction to YiiPress.
permalink: /custom/path/
layout: post
weight: 10
language: en
redirect_to: /new-url/
extra:
  custom_field: value
---
```

- **title** (required) — entry title
- **date** — publication date (`YYYY-MM-DD` or `YYYY-MM-DDTHH:MM:SS+00:00`); entries with a future date are excluded from build by default (scheduling)
- **slug** — URL slug; overrides filename-derived slug
- **draft** — `true` to exclude from build (default: `false`)
- **tags** — list of tag slugs
- **categories** — list of category slugs
- **authors** — list of author slugs (referencing files in `content/authors/`)
- **summary** — manual excerpt; if omitted, auto-generated from content
- **permalink** — per-entry URL override; takes precedence over collection pattern
- **layout** — template layout name (default: collection-specific or `entry`)
- **weight** — integer for custom sorting in non-blog collections (lower = first)
- **language** — language code for multilingual content (e.g., `en`, `ru`)
- **redirect_to** — URL to redirect to (generates redirect HTML instead of content)
- **extra** — arbitrary key-value pairs accessible in templates

### Permalinks

Permalink patterns support the following placeholders:

- `:year`, `:month`, `:day` — from entry date
- `:slug` — entry slug
- `:collection` — collection name
- `:title` — slugified title

Default pattern: `/:collection/:slug/`

Examples:
- `/:collection/:year/:month/:slug/` → `/blog/2024/01/hello-world/`
- `/:year/:slug/` → `/2024/hello-world/`

## Pages

Standalone pages ("about", "contact") are simply entries in the `page` collection.
The `page` collection uses different defaults: no listing page, no feed, no pagination, permalink `/:slug/`, sorted by weight.

`content/page/_collection.yaml`:

```yaml
title: Pages
permalink: /:slug/
sort_by: weight
entries_per_page: 0
feed: false
listing: false
```

A page entry:

```yaml
---
title: About
layout: page
weight: 1
---
```

## Authors

Author definitions live in `content/authors/`. Each file defines one author:

```yaml
---
title: John Doe
email: john@example.com
url: https://johndoe.com
avatar: /authors/assets/john-doe.svg
---
Author bio in markdown.
```

Author slugs (filenames without `.md`) are referenced from entry front matter.
Author archive pages are generated automatically at `/authors/:slug/`.

## Taxonomies

Tags and categories are defined inline in entry front matter. Archive pages are generated automatically:

- `/tags/` — all tags
- `/tags/:slug/` — entries with a specific tag
- `/categories/` — all categories
- `/categories/:slug/` — entries with a specific category

## Date-based archives

Date-based archive pages are generated automatically for collections sorted by date:

- `/:collection/2024/` — yearly archive
- `/:collection/2024/01/` — monthly archive

## Navigation

`content/navigation.yaml` defines menus:

```yaml
main:
  - title: Home
    url: /
  - title: Blog
    url: /blog/
  - title: About
    url: /about/
  - title: Docs
    url: /docs/
    children:
      - title: Getting Started
        url: /docs/getting-started/
      - title: Configuration
        url: /docs/configuration/
```

## Assets

Assets are stored at two levels:

- **`content/assets/`** — global assets (site logo, favicon, fonts) not tied to any collection
- **`content/<collection>/assets/`** — collection assets (images, files used by entries in that collection)

This keeps assets co-located with the content that uses them.
When a collection is deleted or moved, its assets go with it.

Within a collection `assets/` directory, a common convention is to name files after the entry they belong to:

```
content/blog/assets/
├── hello-world-banner.svg
└── getting-started-screenshot.png
```

Reference assets from markdown using absolute paths:

```markdown
![Banner](/blog/assets/hello-world-banner.svg)
```

All `assets/` directories are copied to the build output preserving their path structure.

The project-level `assets/` directory (outside `content/`) is for build-time assets
(CSS, JS) processed by the asset pipeline. Content assets are separate and copied as-is.

## Multilingual content

For multilingual sites, entries can specify a `language` front matter field.
Translations of the same entry share the same slug but differ in language:

```
content/blog/
├── hello-world.md           # language: en (default)
└── hello-world.ru.md        # language: ru
```

The language suffix in the filename (`.ru.md`) is a shorthand for setting `language: ru` in front matter.
