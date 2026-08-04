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

Linux (x86-64) and macOS (Apple silicon):

```shell
curl -fsSL https://raw.githubusercontent.com/yiipress/engine/9ec820b7c9d62160c183d3a6c1497b01afb4b133/install.sh | sh
```

Windows (x86-64, PowerShell):

```powershell
irm https://raw.githubusercontent.com/yiipress/engine/9ec820b7c9d62160c183d3a6c1497b01afb4b133/install.ps1 | iex
```

Both installers verify the latest release checksum and put `yiipress` on `PATH`. Run the same command again to update it.
Set `YIIPRESS_INSTALL_DIR` to install into another directory.

## Why YiiPress

- **Static binary first** — download `yiipress`, put it in your project, and run it.
- **Plain files** — content, navigation, authors, and configuration live in Markdown and YAML.
- **Complete site output** — entries, pages, feeds, sitemap, search index, taxonomy pages, author pages, redirects, and `404.html`.
- **Fast builds** — incremental cache, parallel rendering, native Markdown, and native syntax highlighting.
- **Friendly theming** — start with the bundled theme, then override PHP templates only where needed.
