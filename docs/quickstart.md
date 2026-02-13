# Quickstart

## 1. Create a new project

```bash
composer create-project samdark/yiipress myblog
cd myblog
```

## 2. Configure your site

Edit `content/config.yaml`:

```yaml
title: My Blog
description: A personal blog
base_url: https://example.com
language: en

date_format: "F j, Y"
entries_per_page: 10

permalink: /:collection/:slug/

taxonomies:
  - tags
  - categories
```

## 3. Create a collection

Create a blog collection directory and its config:

```bash
mkdir -p content/blog
```

Create `content/blog/_collection.yaml`:

```yaml
title: Blog
sort_by: date
sort_direction: desc
feed: true
listing: true
```

## 4. Write your first post

Create `content/blog/2024-01-15-hello-world.md`:

```markdown
---
title: "Hello World"
tags:
  - general
---

Welcome to my blog! This is my first post.

## What is YiiPress?

YiiPress is a static blog engine built on Yii3. It is:

- Exceptionally fast
- File-based (no database)
- Extensible with plugins
```

## 5. Create a standalone page

Create `content/about.md`:

```markdown
---
title: "About"
---

This is my personal blog where I write about programming.
```

## 6. Add navigation

Create `content/navigation.yaml`:

```yaml
main:
  - title: Home
    url: /
  - title: Blog
    url: /blog/
  - title: About
    url: /about/
```

## 7. Build the site

```bash
make yii build
```

This generates static HTML in the `output/` directory:

```
output/
├── blog/
│   ├── hello-world/
│   │   └── index.html
│   └── index.html
├── about/
│   └── index.html
├── tags/
│   ├── general/
│   │   └── index.html
│   └── index.html
├── sitemap.xml
└── blog/
    ├── feed.xml
    └── rss.xml
```

## 8. Preview locally

Start the dev server:

```bash
make up
```

Open `http://localhost:8087` in your browser (port is configured in `docker/.env`).

## Build options

Include drafts and future-dated posts during development:

```bash
make yii build -- --drafts --future
```

Use multiple workers for faster builds:

```bash
make yii build -- --workers=4
```

Disable cache for a clean build:

```bash
make yii build -- --no-cache
```

## Next steps

- Add authors in `content/authors/` — see [Content](content.md#authors)
- Customize permalinks — see [Content](content.md#permalinks)
- Configure markdown extensions — see [Configuration](config.md#markdown-extensions)
- Link between posts using relative `.md` paths — see [Content](content.md#internal-links)
- Learn about all build options — see [Commands](commands.md)
