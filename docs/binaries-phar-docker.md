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

The PHAR builder copies only runtime inputs into the build stage: `config/`, `public/`, `src/`, `themes/`, `yii`, Composer metadata, and the PHAR build script. Dependencies are installed with `--no-dev` inside that stage before the PHAR is assembled.
The static executable includes `ext-highlighter`, so syntax highlighting does not need FFI or an external shared library. `serve` uses ReactPHP stream sockets with preforked worker processes, serves built files and live reload SSE in the server loop, keeps one shared live reload watcher per worker, and does not require PHP's native `sockets` extension.
Relative `content-dir`, `output-dir`, `new`, `clean`, `serve`, and `import` paths are resolved from the directory where you run `yiipress`, not from the packaged executable location.
PHAR and static binary runs keep build cache and incremental manifests under the OS temp directory, keyed by the current project directory, instead of writing to `runtime/` in the site checkout. `yiipress clean` removes that packaged cache as well as the configured output directory.

GitHub Actions builds the same outputs in the `Package Static Builds` workflow. Commits to `master` publish separate nightly PHAR, Linux, macOS, and Windows workflow artifacts and push the distroless image as `ghcr.io/<owner>/<repo>-static:nightly` plus a commit-specific `nightly-<sha>` tag. Version tags publish the standalone `yiipress.phar`, Linux archive, macOS archive, and Windows archive to the GitHub release and push semver tags for the distroless image.
