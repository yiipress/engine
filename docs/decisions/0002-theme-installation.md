# 0002. `get:theme` installs from GitHub release tarballs through the system `curl`, with a journal and a JSON lock

- Status: proposed
- Date: 2026-10-29
- Decided by: team lead (new modules, a new command, a new project file format); proposed by the architect
- Issue: [#177](https://github.com/yiipress/engine/issues/177), «Reusable installation infrastructure», «Versions and
  release selection», «Restoring a project» (lock data), «Deterministic theme fallback» (only what `get:theme` needs)
- Builds on: [0001](0001-extension-manifest.md)
- Full reference: [0002-theme-installation-spec.md](0002-theme-installation-spec.md)

## Problem

`yiipress get:theme --url=https://github.com/<owner>/<repo> [--version=X.Y.Z] [--no-activate]` must pick a
compatible stable release, download and check it without running package code, put `package/` into
`themes/<name>/`, record it in a project lock file, optionally set `theme` in `content/config.yaml`, and leave
everything as it was on any failure. Later commands (`restore:extensions`, `update:*`, `remove:*`,
`get:processor`) must reuse the same parts.

## Context and constraints

- The static binary (checked on nightly `702e9a5`, 2026-10-29, via `get_loaded_extensions()` in a project processor)
  loads `zlib`, `Phar`, `hash`, `json`, but no `openssl`: there is no `https://` stream wrapper. `self-update`
  therefore already downloads through the system `curl` (`src/Update/AvailableUrlDownloader.php`). Build config
  for macOS and Windows lists the same extensions; `zlib` there is not yet verified.
- The distroless image (`docker/Dockerfile.distroless-binary`) has no `curl` and no shell.
- GitHub source tarballs (checked 2026-10-29 on `yiipress/highlighter` 1.0.3) have one enclosing directory
  `<repo>-<tag>/` and a pax global header `comment=<commit sha>`. GitHub does not promise byte-stable source
  archives beyond its one-year commitment of 2023-02-21
  ([GitHub blog](https://github.blog/open-source/git/update-on-the-future-stability-of-source-code-archives-and-hashes/),
  read 2026-10-29).
- Unauthenticated GitHub API: 60 requests per hour per IP (`x-ratelimit-limit: 60`, read 2026-10-29).
- `TemplateResolver` falls back through every registered theme in order (`minimal`, `themes/*` A–Z, `local`).
  An installed but inactive theme can therefore change output when the active theme lacks a template.
- In a PHAR or binary, `RuntimePaths` puts runtime files in the system temp dir, often another filesystem than
  the project, so staging there cannot end in an atomic `rename()`.

## Options

**Download and archive reading**

1. **System `curl` + own streaming tar reader** (chosen): HTTPS through `curl` (`--proto =https`, size and time
   limits, headers to a file), PHP streams when an `https` wrapper exists (PHAR on system PHP, Docker dev image).
   The tarball is read entry by entry from `compress.zlib://` by a small ustar/pax reader that sees every type
   flag and size before writing a byte. No binary change.
2. **Add `openssl` to the static binary, PHP streams + `PharData`**: works in distroless, full control of redirects
   and limits in PHP. Rejected for this step: a CA bundle has to be shipped and kept current for macOS and
   Windows, binary size grows by an unmeasured amount, three build pipelines change, and `PharData::extractTo()`
   gives no per-entry limits or type checks. Can be added later behind the same `HttpClientInterface`.
3. **System `curl` + system `tar`**: least PHP code. Rejected: validating before extraction means parsing
   `tar -tv` output, which differs between GNU tar, bsdtar and Windows `tar.exe`; links and sizes are easy to miss.

**Interruption safety**

1. **Mutation lock + journal with backups** (chosen): `flock` on `.yiipress/mutation.lock`, a journal written before
   the first change, backups of `yiipress.lock` and `config.yaml`, recovery on the next mutating command, builds
   refuse while a journal exists. Every later command needs it anyway (#177 «Concurrent operations»).
2. **Ordered steps and `try/finally` cleanup only**: half the code, but a killed process leaves a theme directory
   with no lock entry, which #177 forbids treating as installed. Rejected.

**What is downloaded and locked**

1. **The tag's source tarball, locked by archive SHA-256, commit and per-file SHA-256** (chosen): no work for
   authors; `restore:extensions` can still prove the files are the recorded ones if GitHub's bytes ever change.
2. **A required release asset `extension.tar.gz`**: byte-stable, may hold built CSS/JS without committing them.
   Rejected for now: every author needs a release workflow before the first install. The lock's `archive.kind`
   leaves room to add it.

## Decision

Option 1 in each group. Shared code goes to `src/Extension/` (`Http`, `Release`, `Archive`, `Lock`,
`Transaction`, `Install`); theme-only code to `src/Extension/Theme/`; the command is
`src/Console/GetThemeCommand.php`, plus `recover:extensions` so a refused build has a command to name. The lock is
`yiipress.lock` (JSON) in the project root, next to `themes/`; work files live in `.yiipress/` there. Every check
(release, archive, manifest, destination, config edit) runs before the first change to project files.

## Consequences

- Better: one installer core for all types; no binary rebuild; locks survive tarball byte changes; `Ctrl-C` at any
  point is recoverable; a build never sees half-installed state.
- Worse: no `get:theme` in the distroless image or on systems without `curl` (clear error, documented); size limits
  are enforced during transfer only where `curl` supports it, otherwise right after; one more hidden directory
  (`.yiipress/`) to ignore in VCS; builds read `yiipress.lock` (a local file, no network).
- Follow-up: the fallback step replaced the stop-gap with the rules in spec «Theme fallback» (`fallback` recorded in
  `yiipress.lock`, chain theme → `minimal` unless `none` → `local`). `restore:extensions`
  treats an archive checksum mismatch with matching commit and files as a warning (open question 3, answered).

## Questions for the lead (all answered)

1. Stop-gap for inactive themes: include in `get-theme` or leave to the `fallback` step. Answered by the lead
   (2026-10-29): included in `get-theme`; the `fallback` step later replaced it with spec «Theme fallback».
2. `--engine-version=X.Y.Z`, accepted only when the engine version is unknown (source checkouts, the dev image):
   add or not. Answered by the lead (2026-10-29): added, only for an unknown engine version. See spec «Command».
3. For `restore`: an archive SHA-256 mismatch with the same commit and identical files: error (as #177 says) or
   warning. Answered by the owner (2026-10-29): a warning; restore continues. See spec «Restore».
4. Limits in spec «Limits» are proposals, not measured. Answered by the lead (2026-10-29): they stay as proposed
   defaults and are documented as such.

## Confirmation

- PHPUnit through public behaviour with a fake `HttpClientInterface` and fixture tarballs: one test per error code
  in the spec; a crash test kills the transaction after each step and checks that recovery restores the previous
  files byte for byte.
- Review point: no class under `src/Extension/` calls `include`/`require`, `PharData::extractTo()` or `exec` other
  than the `curl` call in `CurlHttpClient`.
- End to end in the dev container and with the nightly binary: install, `--no-activate`, version pinning, a
  collision, an interrupted install, against a test repository (to be created by the owner, or a local fixture
  server).
