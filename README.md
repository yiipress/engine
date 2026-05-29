<p align="center">
    <a href="https://github.com/yiipress" target="_blank">
        <img src="./logo.svg" height="100px" alt="YiiPress">
    </a>
    <h1 align="center">YiiPress Static Website Engine</h1>
    <br>
</p>

[![Latest Stable Version](https://poser.pugx.org/yiipress/engine/v)](https://packagist.org/packages/yiipress/engine)
[![Total Downloads](https://poser.pugx.org/yiipress/engine/downloads)](https://packagist.org/packages/yiipress/engine)
[![Tests](https://github.com/yiipress/engine/actions/workflows/run-tests.yml/badge.svg)](https://github.com/yiipress/engine/actions/workflows/run-tests.yml)
[![Docker](https://github.com/yiipress/engine/actions/workflows/docker-build.yml/badge.svg)](https://github.com/yiipress/engine/actions/workflows/docker-build.yml)

The package provides a fast, file-based static website engine powered by [Yii3](https://www.yiiframework.com/) and PHP 8.5.
Write Markdown, build static HTML, and serve blogs, documentation, feeds, sitemaps, taxonomy pages, authors, search, and assets
without a database.

## Requirements

- Docker and Docker Compose for the standard development, test, and build workflow.
- PHP 8.5 with required extensions when running the application outside Docker.
- [`ext-highlighter`](https://github.com/yiipress/highlighter) for native server-side syntax highlighting.

The Docker images include the required PHP extensions, including the YiiPress highlighter extension.

## Installation

Create a project:

```shell
composer create-project yiipress/engine mysite
cd mysite
```

Build the Docker image and initialize content:

```shell
make build
make yii init
```

Start the development server:

```shell
make up
```

The preview server is available at the host port configured by `DEV_PORT` in `docker/.env`.

## General Usage

Configure the site in `content/config.yaml`:

```yaml
title: My Site
base_url: https://example.com
languages: [en]
permalink: /:collection/:slug/
taxonomies:
  - tags
  - categories
highlight_theme: "Solarized (dark)"
```

Create a collection in `content/blog/_collection.yaml`:

```yaml
title: Blog
sort_by: date
sort_direction: desc
feed: true
listing: true
```

Write an entry in `content/blog/2026-01-15-hello-world.md`:

```markdown
---
title: Hello World
tags:
  - general
---

Welcome to my site.
```

Build the static site:

```shell
make yii build
```

Generated files are written to `output/`.

Common commands:

```shell
make yii new "My First Post"
make yii build -- --workers=4
make yii build -- --drafts --future
make yii clean
make package
make package-distroless
```

When using `serve`, HTML pages include live reload and a small edit button that opens the current Markdown source file in the
editor configured in `content/config.yaml`.

## Features

- Markdown content with front matter, collections, standalone pages, taxonomies, authors, date archives, and summaries.
- Incremental and parallel builds with cache-aware output generation.
- Feeds, sitemap, canonical URLs, social meta tags, redirects, and static `404.html`.
- Native server-side syntax highlighting powered by Rust and syntect.
- Table of contents, Mermaid diagrams, video embeds, fuzzy search, asset fingerprinting, and Telegram import.
- Yii3-based web application, routing, dependency injection, middleware, and PHP template support.

## Performance

Current benchmark highlights:

| Scenario | Mode | Time |
|---|---:|---:|
| 10 000 entries across 3 collections | 4 workers | ~2.8 s |
| 10 000 entries across 3 collections | incremental | ~358 ms |
| 1 000 realistic entries | 4 workers | ~1.1 s |
| 1 000 realistic entries | incremental | ~108 ms |

See [`docs/benchmarking.md`](docs/benchmarking.md) for benchmark workflow details.

## Documentation

Full documentation is available in [`docs/`](docs/) and at [yiipress.yiiframework.com](https://yiipress.yiiframework.com/).

- [Quickstart](docs/quickstart.md)
- [Content](docs/content.md)
- [Configuration](docs/config.md)
- [Commands](docs/commands.md)
- [Templates](docs/template.md)
- [Plugins](docs/plugins.md)
- [Deployment](docs/deployment.md)
- [Architecture](docs/architecture.md)

## License

YiiPress Static Website Engine is free software. It is released under the terms of the BSD License.
Please see [`LICENSE.md`](./LICENSE.md) for more information.
