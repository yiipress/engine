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
[![Static Analysis](https://github.com/yiipress/engine/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/yiipress/engine/actions/workflows/static-analysis.yml)
[![Coverage](https://codecov.io/gh/yiipress/engine/branch/master/graph/badge.svg)](https://codecov.io/gh/yiipress/engine)
[![Package](https://github.com/yiipress/engine/actions/workflows/package-static.yml/badge.svg)](https://github.com/yiipress/engine/actions/workflows/package-static.yml)

YiiPress is a fast, file-based static website engine powered by [Yii3](https://www.yiiframework.com/).
Write Markdown, build static HTML, and ship blogs, documentation, feeds, sitemaps, taxonomy pages, authors, search, redirects,
and assets without a database or any runtime.

Use the static binary for normal projects: it includes the runtime and native extensions, so you do not need Docker, PHP, Composer,
or a database on the machine that builds or previews the site.

## Features

- Static Linux, macOS, and Windows binaries for simple installs with no PHP runtime.
- Binary-only distroless Docker images for container-based workflows.
- Reusable GitHub Action for fast binary-based CI builds.
- Incremental and parallel builds with native Markdown, YAML, and syntax highlighting.
- Plain Markdown/YAML content that works well with Git.
- PHP templates for full control without learning a custom template language.
- Static production output that can be hosted anywhere.
- Markdown content with front matter, collections, standalone pages, taxonomies, authors, date archives, and summaries.
- Feeds, sitemap, canonical URLs, social meta tags, redirects, and static `404.html`.
- Native server-side syntax highlighting powered by Rust and syntect.
- Table of contents, Mermaid diagrams, video embeds, fuzzy search, asset fingerprinting, and Telegram import.
- Yii3-based web application, routing, dependency injection, middleware, and PHP template support.

## Requirements

- For users: the `yiipress` static binary for your platform.
- For GitHub Actions: the YiiPress build action, which downloads the Linux binary automatically.
- For container workflows: Docker, using the published binary-only YiiPress image.
- For engine development from source: Docker and Docker Compose.

Source installs require PHP 8.5 and native extensions. Prefer the binary unless you are developing the engine itself.

## Installation

Download the static binary from the latest GitHub release or workflow artifact, then put it in your site repository as
`yiipress` (`yiipress.exe` on Windows).

Create a content project:

```shell
mkdir mysite
cd mysite
cp /path/to/yiipress ./yiipress
chmod +x ./yiipress
```

Initialize content:

```shell
./yiipress init
```

Start the development server:

```shell
./yiipress serve
```

The preview server prints the URL it is listening on.

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
./yiipress build
```

Generated files are written to `output/`.

Common commands:

```shell
./yiipress new "My First Post" --collection=blog
./yiipress build --workers=4
./yiipress build --drafts --future
./yiipress clean
```

Engine packaging commands, used by maintainers:

```shell
make package-phar
make package
make package-macos
make package-distroless
```

When using `serve`, HTML pages include live reload and a small edit button that opens the current Markdown source file in the
editor configured in `content/config.yaml`.

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
- [Configuration](docs/configuration.md)
- [Importing content](docs/importing-content.md)
- [Commands](docs/commands.md)
- [GitHub Actions](docs/github-actions.md)
- [Templates](docs/templates.md)
- [Plugins](docs/plugins.md)
- [Deployment](docs/deployment.md)
- [Architecture](docs/architecture.md)
- [Engine](docs/engine.md)
- [Binaries, PHAR, Docker](docs/binaries-phar-docker.md)

## License

YiiPress Static Website Engine is free software. It is released under the terms of the BSD License.
Please see [`LICENSE.md`](./LICENSE.md) for more information.
