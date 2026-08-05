# Quickstart

This guide uses the static `yiipress` binary. It is the recommended way to use YiiPress because it includes the PHP runtime and native extensions.

## 1. Create a site directory

```bash
mkdir myblog
cd myblog
```

Install YiiPress for your platform using the command on the [documentation home page](README.md#install-or-update).
The installer puts the executable on `PATH`, so the same commands work on Linux, macOS, and Windows.

```bash
yiipress --version
```

## 2. Create the initial files

Run:

```bash
yiipress init:content
```

This creates `content/config.yaml`, `content/navigation.yaml`, and two starter collections. Edit `content/config.yaml`:

```yaml
title: My Blog
description: A personal blog
base_url: https://example.com
languages: [en]

date_format: "F j, Y"
entries_per_page: 10

permalink: /:collection/:slug/

taxonomies:
  - tags
  - categories
```

## 3. Review collections

`yiipress init:content` creates a `page` collection for standalone pages and a `blog` collection for dated posts:

```text
content/
├── page/
│   └── _collection.yaml
└── blog/
    └── _collection.yaml
```

## 4. Write your first post

Create the post with the scaffold command:

```bash
yiipress new "Hello World" --collection=blog
```

Then edit the generated file in `content/blog/`. A typical post looks like this:

```markdown
---
title: "Hello World"
tags:
  - general
---

Welcome to my blog! This is my first post.

## What is YiiPress?

YiiPress is a static blog engine built on [Yii3](https://yiisoft.github.io/docs/guide/intro/what-is-yii.html). It is:

- Exceptionally fast
- File-based (no database)
- Extensible with plugins
```

## 5. Create a page

Use the same command without `--collection` for a root-level page:

```bash
yiipress new "About"
```

A simple `content/about.md` page looks like this:

```markdown
---
title: "About"
---

This is my personal blog where I write about programming.
```

## 6. Add navigation

Edit `content/navigation.yaml`:

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
yiipress build
```

This generates static HTML in the `output/` directory:

```
output/
├── blog/
│   ├── feed.xml
│   ├── feed.json
│   ├── hello-world/
│   │   └── index.html
│   ├── rss.xml
│   └── index.html
├── about/
│   └── index.html
├── tags/
│   ├── general/
│   │   └── index.html
│   └── index.html
└── sitemap.xml
```

## 8. Preview locally

Start the dev server:

```bash
yiipress serve
```

Open the URL printed by the command. The preview server rebuilds after content changes and refreshes the browser.

## Build options

Include drafts and future-dated posts during development:

```bash
yiipress build --drafts --future
```

Use multiple workers for faster builds:

```bash
yiipress build --workers=4
```

By default, YiiPress uses `--workers=auto`, which detects available CPU capacity and uses up to 4 workers automatically.

Disable cache for a clean build:

```bash
yiipress build --no-cache
```

## Next steps

- Add authors in `content/authors/` — see [Content](content.md#authors)
- Customize permalinks — see [Content](content.md#permalinks)
- Configure markdown extensions — see [Configuration](configuration.md#markdown-extensions)
- Link between posts using relative `.md` paths — see [Content](content.md#internal-links)
- Learn about all build options — see [Commands](commands.md)
