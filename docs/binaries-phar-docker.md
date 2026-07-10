# Binaries, PHAR, Docker

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

## Dockerfiles

`docker/Dockerfile` is the source build Dockerfile. It contains development and production image targets, PHAR packaging targets, and the expensive static-php-cli Linux binary build targets used by `make package*` and CI packaging jobs.

`docker/Dockerfile.distroless-binary` is the final image assembly Dockerfile for GitHub Actions. It does not build PHP, Composer dependencies, the PHAR, or static-php-cli. It copies an already-built `dist/linux-amd64/yiipress` binary into the distroless base image, so nightly and release container images reuse the Linux binary artifact instead of rebuilding it.

The PHAR builder copies only runtime inputs into the build stage: `config/`, `public/`, `src/`, `themes/`, `yii`, Composer metadata, and the PHAR build scripts. Dependencies are installed with `--no-dev` inside that stage before the PHAR is assembled.

PHPDoc comments are stripped from packaged PHP files to keep the standalone PHAR and embedded static-binary PHAR smaller while preserving runtime comments, code, and dependency PHPDoc that is read through reflection at runtime. Benchmark fixture helpers, Composer's `installed.json`, VCS placeholders, and non-runtime type stubs are omitted because packaged commands do not need them.
Packaged PHAR entries are gzip-compressed before the archive is finalized. The static binary appends that same PHAR to the micro SAPI executable, so PHAR compression reduces both the standalone PHAR and the Linux, macOS, and Windows static executables.
The static executable includes `ext-highlighter`, so syntax highlighting does not need FFI or an external shared library. `serve` uses ReactPHP stream sockets, serves built files and live reload SSE in the server loop, keeps one shared live reload watcher per server process, and does not require PHP's native `sockets` extension. On POSIX platforms with PCNTL and signal support, `serve` can prefork worker processes; Windows static binaries run a single server process.
Relative `content-dir`, `output-dir`, `new`, `clean`, `serve`, and `import` paths are resolved from the directory where you run `yiipress`, not from the packaged executable location.
PHAR and static binary runs keep build cache and incremental manifests under the OS temp directory, keyed by the current project directory, instead of writing to `runtime/` in the site checkout. `yiipress clean` removes that packaged cache as well as the configured output directory.

GitHub Actions builds the same outputs in the `Package Static Builds` workflow. Commits to `master` publish separate nightly PHAR, Linux, macOS, and Windows workflow artifacts, create immutable GitHub prereleases tagged as `nightly-<run>-<attempt>-<sha>` with the Linux binary and checksums, and push the distroless image as `ghcr.io/<owner>/<repo>-static:nightly` plus a commit-specific `nightly-<sha>` tag. The reusable build action resolves `version: nightly` to the newest matching nightly prerelease.

The `Run Tests` workflow runs PHPUnit in the Linux Docker test image, then builds the native Windows and macOS binaries. Both platform jobs exercise a complete site lifecycle with the packaged executable: initialize a project, create content, build it, check generated links, verify output, and clean the build.

Version tags run the `Release` workflow. It builds the PHAR, Linux, macOS, and Windows binaries, pushes only the binary-based distroless image as `ghcr.io/<owner>/<repo>-static:<tag>` plus semver aliases, then creates a draft GitHub release, attaches all binaries and `SHA256SUMS`, writes release notes from commits with their authors, and publishes the release. The Linux binary and PHAR come from the same static-package build, and the release image is assembled from the Linux binary artifact, so the expensive packaging build is not repeated for those outputs.
