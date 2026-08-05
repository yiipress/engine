---
permalink: "/"
title: "YiiPress Documentation"
show_title: false
---

<p align="center">
    <img src="/assets/logo.svg" height="140" alt="YiiPress">
</p>

[YiiPress](https://github.com/yiipress/engine) turns Markdown files into a fast static website. Use the static binary for day-to-day work: no PHP, Composer, database, or application server is needed for building or serving a preview.

## Install or update

[code-group]
[code-tab label="Linux (x86-64)"]
```shell
curl -fsSL https://raw.githubusercontent.com/yiipress/engine/master/install.sh | sh
```
[/code-tab]
[code-tab label="macOS (Apple silicon)"]
```shell
curl -fsSL https://raw.githubusercontent.com/yiipress/engine/master/install.sh | sh
```
[/code-tab]
[code-tab label="Windows (x86-64)"]
```powershell
irm https://raw.githubusercontent.com/yiipress/engine/master/install.ps1 | iex
```
[/code-tab]
[/code-group]

Both installers verify the latest release checksum and put `yiipress` on `PATH`. Run the same command again to update it.
Set `YIIPRESS_INSTALL_DIR` to install into another directory.

## Why YiiPress

- **Static binary first** — download `yiipress`, put it in your project, and run it.
- **Plain files** — content, navigation, authors, and configuration live in Markdown and YAML.
- **Complete site output** — entries, pages, feeds, sitemap, search index, taxonomy pages, author pages, redirects, and `404.html`.
- **Fast builds** — incremental cache, parallel rendering, native Markdown, and native syntax highlighting.
- **Friendly theming** — start with the bundled theme, then override PHP templates only where needed.
