# Deployment

YiiPress generates static HTML, CSS, JavaScript, feeds, and metadata files during the build process, eliminating the need for YiiPress or a PHP runtime in production. Any static file hosting service can serve the generated `output/` directory.

The recommended build tool is the static `yiipress` binary:

```bash
./yiipress build --content-dir=content
```

The generated files will be placed in the output directory (default: `output`). You can then host this directory with any static file server or hosting service.

## Build Tool Options

Prefer these options in this order:

1. **Static binary** — best default for local builds and most CI systems. It has the runtime embedded and does not require PHP or Composer.
2. **Published Docker image** — useful when your CI platform already standardizes on containers.
3. **PHAR or source checkout** — intended for contributors or environments that already provide PHP 8.5 and required extensions.

## Any Web Host (FTP / cPanel)

The simplest deployment option is to upload the build output directly to your hosting provider's webroot:

1. Build your site: `./yiipress build --content-dir=content`
2. Open your hosting control panel or FTP client.
3. Upload the entire contents of the `output` directory to your server's webroot folder. This folder is commonly named `www`, `public_html`, `htdocs`, or `html` depending on your provider.
4. Your site is live. The server only serves static files.

## GitHub Pages

[GitHub Pages](https://pages.github.com/) hosts static sites directly from a GitHub repository for free. The recommended approach is to use a GitHub Actions workflow that builds the site and publishes it to the `github-pages` environment on every push.

### How GitHub Pages works

GitHub Pages serves the files uploaded to its `github-pages` deployment environment. A workflow uploads the build artifact using `actions/upload-pages-artifact` and then deploys it using `actions/deploy-pages`.

### Example GitHub Actions workflow

Create `.github/workflows/deploy.yml` in your repository:

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

      - name: Download YiiPress
        run: |
          curl -fsSL https://github.com/yiipress/engine/releases/download/vX.Y.Z/yiipress-linux-amd64.tar.gz \
            | tar -xz
          chmod +x yiipress

      - name: Build site
        run: |
          ./yiipress build --output-dir=_site --no-cache

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

Enable GitHub Pages in your repository settings: go to **Settings → Pages** and set the source to **GitHub Actions**.

> **Tip:** Replace `vX.Y.Z` with a real YiiPress version tag for reproducible, stable builds.

> **Real-world example:** This is exactly how the YiiPress documentation itself is built and deployed — see [`.github/workflows/build-docs.yml`](https://github.com/yiipress/engine/blob/master/.github/workflows/build-docs.yml) in this repository.

## Cloudflare Pages

[Cloudflare Pages](https://pages.cloudflare.com/) offers a free, globally distributed CDN for static sites with automatic deployments from Git.

1. Push your project to a GitHub or GitLab repository.
2. In the [Cloudflare dashboard](https://dash.cloudflare.com/), go to **Workers & Pages → Create application → Pages → Connect to Git**.
3. Select your repository and configure the build settings:
   - **Install command:** `curl -fsSL https://github.com/yiipress/engine/releases/download/vX.Y.Z/yiipress-linux-amd64.tar.gz | tar -xz && chmod +x yiipress`
   - **Build command:** `./yiipress build --output-dir=_site --no-cache`
   - **Build output directory:** `_site`
4. Click **Save and Deploy**.

Cloudflare Pages will rebuild and redeploy your site automatically on every push to the configured branch. Replace `vX.Y.Z` with a fixed YiiPress release.

> **Alternative:** If you prefer, build the site locally or in your own CI pipeline, then deploy the `_site/` directory using the [Wrangler CLI](https://developers.cloudflare.com/workers/wrangler/):
> ```bash
> npx wrangler pages deploy _site --project-name=my-site
> ```

## Minimal Docker Image

You can package your generated static site into a tiny Docker image using [`lipanski/docker-static-website`](https://github.com/lipanski/docker-static-website). This image is based on BusyBox httpd and is only a few megabytes in size, making it useful for self-hosted or containerized environments.

Build the site first:

```bash
./yiipress build --output-dir=_site --no-cache
```

Then create a `Dockerfile` in your project root:

```dockerfile
FROM lipanski/docker-static-website:latest

COPY _site .
```

Build and run the image:

```bash
docker build -t my-site .
docker run -p 3000:3000 my-site
```

Your site will be available at `http://localhost:3000`.

To deploy, push the image to any container registry and run it on any platform that supports Docker — a VPS, Fly.io, Railway, Render, or any Kubernetes cluster.

## YiiPress Static Binary, PHAR, and Distroless Image

YiiPress can be packaged as reproducible artifacts:

```bash
make package
```

The command builds the Linux static executable and writes it to `dist/linux-amd64/`:

- `yiipress` — static Linux executable built with static-php-cli micro SAPI and the YiiPress PHAR embedded.

Additional package targets are available:

```bash
make package-phar
make package-linux
make package-macos
make package-windows
make package-distroless
make package-distroless-push
```

- `make package-linux` is the explicit Linux static executable target. Set `PACKAGE_PLATFORM=linux/amd64` and `PACKAGE_LINUX_DIST=...` to override the defaults.
- `make package-macos` builds the native macOS static executable into `dist/macos-<arch>/yiipress` with the shell packaging script. It is intended for macOS hosts with PHP, Composer, Rust, and the Xcode command-line build toolchain available. `PACKAGE_MACOS_ARCH` must match the host architecture (`arm64` or `amd64`); set `PACKAGE_MACOS_DIST=...` to override the output directory. The intermediate PHAR used by `static-php-cli` is kept under `runtime/package-macos/` and is not part of the macOS artifact.
- `make package-windows` builds only `dist/windows-amd64/yiipress.exe` with the PowerShell packaging script. It is intended for Windows hosts with PowerShell 7 (`pwsh`), PHP, Composer, Rust, and the Visual Studio C++ toolchain available. The intermediate PHAR used by `static-php-cli` is kept under `runtime/package-windows/` and is not part of the Windows artifact.
- `make package-phar` builds `dist/phar/yiipress.phar` separately for environments that already have PHP 8.5 and required extensions. Set `PACKAGE_PHAR_DIST=...` to override the output directory.
- `make package-distroless` builds a local `${IMAGE}-static:${IMAGE_TAG}` image from the `distroless` Docker target. The image copies only the static `yiipress` binary into a distroless base and runs it as the entrypoint.
- `make package-distroless-push` builds and pushes the same image.

The PHAR builder copies only runtime inputs into the build stage: `config/`, `public/`, `src/`, `themes/`, `yii`, Composer metadata, and the PHAR build script. Dependencies are installed with `--no-dev` inside that stage before the PHAR is assembled.
The static executable includes `ext-highlighter`, so syntax highlighting does not need FFI or an external shared library. `serve` uses ReactPHP stream sockets with preforked worker processes, serves built files and live reload SSE in the server loop, keeps one shared live reload watcher per worker, and does not require PHP's native `sockets` extension.
Relative `content-dir`, `output-dir`, `new`, `clean`, `serve`, and `import` paths are resolved from the directory where you run `yiipress`, not from the packaged executable location.
PHAR and static binary runs keep build cache and incremental manifests under the OS temp directory, keyed by the current project directory, instead of writing to `runtime/` in the site checkout. `yiipress clean` removes that packaged cache as well as the configured output directory.

GitHub Actions builds the same outputs in the `Package Static Builds` workflow. Commits to `master` publish separate nightly PHAR, Linux, macOS, and Windows workflow artifacts and push the distroless image as `ghcr.io/<owner>/<repo>-static:nightly` plus a commit-specific `nightly-<sha>` tag. Version tags publish the standalone `yiipress.phar`, Linux archive, macOS archive, and Windows archive to the GitHub release and push semver tags for the distroless image.
