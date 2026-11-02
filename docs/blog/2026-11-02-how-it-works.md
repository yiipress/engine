---
title: How it works
summary: Follow a Markdown file through YiiPress, from plain files to a complete static website.
---

Welcome to the YiiPress blog. This is where we will share product updates and explain the ideas behind the engine.
For the first post, let's follow a page from a Markdown file to the website a visitor reads.

## Start with files

YiiPress is a static website engine built with Yii3. You write content in Markdown, keep settings in YAML,
and build a directory of files that a static host can serve. Visitors do not need a running PHP application
or a database to read the result.

A small site might look like this:

```text
content/
├── config.yaml
├── navigation.yaml
└── blog/
    ├── _collection.yaml
    └── hello-world.md
```

`config.yaml` holds site settings such as the title and public URL. `navigation.yaml` defines menus.
The `blog` directory is a collection: a group of entries with shared settings for URLs, sorting, pagination,
and feeds. Its `_collection.yaml` might contain:

```yaml
title: Blog
permalink: /blog/:slug/
sort_by: date
sort_order: desc
feed: true
```

Each entry can begin with YAML front matter. Here is `hello-world.md`:

```markdown
---
title: Hello world
date: 2026-11-02
summary: Our first update.
---

This is our first post, written in **Markdown**.
```

## Build the site

With the `yiipress` binary installed, run:

```shell
yiipress build
```

The binary includes PHP and the native extensions it needs, so you do not need a separate PHP or Composer
installation to use it. The build follows four stages:

1. **Parse.** Read the configuration and entry metadata. YiiPress records where each Markdown body is stored
   so it can load the body later, when rendering needs it.
2. **Index.** Work out URLs, filter drafts and future posts, sort collections, and connect entries to authors,
   tags, categories, and date archives. This gives the rest of the build a consistent view of the site.
3. **Render.** Convert Markdown to HTML, run content processors, and place the result in PHP templates.
   The bundled theme supplies the page layout; you can override templates to change it.
4. **Write.** Save the pages and supporting files into `output/`.

For the example above, the entry becomes `output/blog/hello-world/index.html`, served at `/blog/hello-world/`.
The collection also gets a listing, date archives, and feeds. Depending on the site settings and content,
the build can produce taxonomy pages, author pages, a search index, redirects, and a sitemap.
Content assets, such as images, are copied into the output too.

The Markdown and templates are build inputs. The visitor receives the resulting HTML, CSS, JavaScript,
and other static files.

## Keep the build work small

The pipeline is designed to avoid unnecessary work. Native extensions handle Markdown conversion and syntax
highlighting. Incremental builds use cached rendering and dependency information to reuse output when its inputs
have not changed. Larger builds can distribute rendering work across independent worker processes.

These mechanisms support the same goal: let PHP coordinate the build, then serve ordinary files.
They do not require an application server on the production host.

## Preview, then publish

While writing, run:

```shell
yiipress serve
```

The preview server rebuilds and serves the generated site, with live reload as you edit content or templates.
It uses the same build pipeline as `yiipress build`, so you preview the files that the build produces.

When the site is ready, run `yiipress build` and deploy the contents of `output/` to your static host.
Set `base_url` in `config.yaml` to the site's public URL so generated canonical links and feeds use the right address.

This blog is built the same way: a collection, a Markdown post, and the existing YiiPress theme.
To try it yourself, follow the [Quickstart](../quickstart.md). For more detail, read about
[content and collections](../content.md), [templates](../templates.md), or the [architecture](../architecture.md).
