---
title: "GitHub Actions"
showTitle: true
---

YiiPress provides a composite GitHub Action that downloads the Linux static binary from a YiiPress release and runs `yiipress build`. It is intended for site repositories that want a fast build without installing PHP, Composer, Docker, or YiiPress source dependencies.

Use a fixed YiiPress version for reproducible builds:

```yaml
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout repository
        uses: actions/checkout@v4

      - name: Build YiiPress site
        uses: yiipress/engine/.github/actions/build@X.Y.Z
        with:
          version: X.Y.Z
```

For testing unreleased changes from the current `master` branch, use `version: nightly`.
The nightly binary is mutable and intended for preview builds only; use a fixed release tag
for production sites.

The action accepts these inputs:

| Input | Default | Description |
|---|---|---|
| `version` | `latest` | YiiPress release tag to download. Use a fixed tag such as `1.2.3` for stable builds, or `nightly` to test the current master build. |
| `content-dir` | `content` | Content directory passed to `yiipress build`. |
| `output-dir` | `_site` | Output directory passed to `yiipress build`. Change it when the host expects a custom output directory. |
| `working-directory` | `.` | Repository subdirectory where the build runs. |
| `args` | `--no-cache` | Extra arguments appended to `yiipress build`, one argument per line. |
| `binary-path` | runner temp directory | Path where the downloaded binary is installed. Leave it unset unless later steps need the binary at a fixed path. |
| `github-token` | workflow token | Token used when resolving `version: latest` through the GitHub API. |

The action exposes `version` and `binary-path` outputs if later workflow steps need to report or reuse the downloaded binary.

The downloaded archive is verified against the release `SHA256SUMS` asset. Use a YiiPress release produced by the official release workflow, and pin `version` to that release for reproducible builds.

## GitHub Pages

For GitHub Pages, build into `_site`, upload it as a Pages artifact, and deploy it:

```yaml
name: Build and Deploy to GitHub Pages

on:
  push:
    branches:
      - master
  workflow_dispatch:

permissions:
  contents: read
  pages: write
  id-token: write

concurrency:
  group: "pages"
  cancel-in-progress: false

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout repository
        uses: actions/checkout@v4

      - name: Build YiiPress site
        uses: yiipress/engine/.github/actions/build@X.Y.Z
        with:
          version: X.Y.Z

      - name: Upload artifact
        uses: actions/upload-pages-artifact@v4

  deploy:
    environment:
      name: github-pages
      url: ${{ steps.deployment.outputs.page_url }}
    runs-on: ubuntu-latest
    needs: build
    steps:
      - name: Deploy to GitHub Pages
        id: deployment
        uses: actions/deploy-pages@v4
```

Enable GitHub Pages in the repository settings and set the source to GitHub Actions.
