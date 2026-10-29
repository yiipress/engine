# Extension packages

An extension package is a theme or a content processor that can be shared between YiiPress sites. Every package
describes itself in an `extension.yaml` manifest: what it is, which version it is, which YiiPress versions it
supports and where its files are.

YiiPress reads and validates the manifest without running any code from the package. Themes are installed from
GitHub releases with [`get:theme`](#installing-a-theme). Installing processors, updating and removing packages and
the extension catalog are planned.

## Package layout

```text
wonder/
├── extension.yaml        # the manifest
├── package/              # installed files: templates, assets, processor code
│   └── ...
└── screenshots/          # catalog files, not installed
    └── home.png
```

The package root holds `extension.yaml` and the `package/` directory. Only `package/` is installed. Paths in the
manifest are relative:

- processor entry points are relative to `package/`;
- catalog files (theme screenshots, processor examples) are relative to the package root.

## Theme example

```yaml
manifest: 1
id: yiipress/wonder
type: theme
name: Wonder
description: A clean blog theme with dark mode.
version: 1.0.0
yiipress: ^0.1.13
license: BSD-3-Clause
authors:
  - name: Jane Doe
    url: https://example.com
theme:
  fallback: minimal
  screenshots: [screenshots/home.png]
```

## Processor example

```yaml
manifest: 1
id: example/emoji
type: processor
name: Emoji
description: "Replaces :shortcodes: with emoji."
version: 0.2.0
yiipress: ">=0.1.13 <0.3"
license: MIT
authors: [{name: Example Team}]
processor:
  entry_points:
    - path: emoji.php
      stages: [before_markdown]
```

Quote a value when it contains `: `, starts with `>`, `*`, `&`, `!`, `[` or `{`, or looks like a number you want
as text. Without quotes, `description: Replaces :shortcodes: with emoji.` is not valid YAML.

## Common fields

All keys are lowercase. An unknown key at any level is an error, so a typo such as `entry_point` is reported
instead of being ignored. An empty value (`~` or `null`) counts as missing.

| Field | Type | Required | Rules |
| --- | --- | --- | --- |
| `manifest` | integer | yes | Manifest format version. Must be `1`. |
| `id` | string | yes | `vendor/name`. Each part has 1 to 64 lowercase letters, digits and single hyphens, for example `acme/dark-blog`. Windows device names such as `con`, `nul` or `com1` are not allowed. |
| `type` | string | yes | `theme` or `processor`. `importer` and `plugin` are reserved for later versions. |
| `name` | string | yes | Display name, 1 to 100 characters, one line. |
| `description` | string | yes | 1 to 500 characters; may span several lines. |
| `version` | string | yes | [SemVer](https://semver.org/) version such as `1.0.0` or `1.0.0-beta.1`. No `v` prefix, no build metadata. |
| `yiipress` | string | yes | Supported YiiPress versions as a Composer constraint (see below). |
| `license` | string | yes | SPDX identifier or expression such as `MIT` or `MIT OR Apache-2.0`, or `proprietary`. |
| `authors` | list | yes | 1 to 20 authors. Each has `name` (required), `email` and `url` (an `https://` URL). |
| `homepage` | string | no | `https://` URL of the project. |
| `platforms` | mapping | no | `os`: any of `linux`, `macos`, `windows`; `arch`: any of `amd64`, `arm64`. Omit a list to support all values. |
| `theme` | mapping | no | Theme fields. Allowed only when `type: theme`. |
| `processor` | mapping | yes for processors | Processor fields. Allowed only when `type: processor`. |

## Theme fields

| Field | Type | Required | Rules |
| --- | --- | --- | --- |
| `fallback` | string | no | `minimal` (default): templates the theme does not provide come from the built-in theme. `none`: no fallback. |
| `intended_use` | string | no | What the theme is for, 1 to 200 characters, one line. |
| `screenshots` | list | no | Up to 10 catalog paths to `.png`, `.jpg`, `.jpeg` or `.webp` files. |
| `demo` | string | no | `https://` URL of a demo site. |

The theme is installed into a directory named after the `name` part of `id`, so a theme cannot be called `minimal`
or `local`.

## Processor fields

| Field | Type | Required | Rules |
| --- | --- | --- | --- |
| `entry_points` | list | yes | 1 to 50 entry points; paths are unique, ignoring case. |
| `entry_points[].path` | string | yes | PHP file in `package/`. It follows the [project processor](plugins.md) contract: it returns a `ContentProcessorInterface` or a callable. |
| `entry_points[].stages` | list | yes | Where the entry point may run: `before_markdown`, `after_markdown`. This describes the processor; it does not enable it. |
| `examples` | list | no | Up to 20 `{input, output}` pairs of catalog paths to Markdown and HTML samples. |

## Path rules

Every path in the manifest must work on Linux, macOS and Windows:

- `/` as the separator; no leading `/`, no `\`, no drive letter or `:`;
- no empty, `.` or `..` segment;
- at most 255 bytes in total and 100 bytes per segment, valid UTF-8;
- no control characters and none of `<>:"|?*`;
- no segment that is a Windows device name (`con`, `aux.php`, `nul.tar.gz`) or ends in a dot or a space.

When a package is read from disk, each path must also be a regular file, and its real path must stay inside its
base directory. `extension.yaml` and `package/` must resolve inside the package root too. A symlink that points
outside the package is an error.

## YiiPress versions

`yiipress` uses [Composer constraint syntax](https://getcomposer.org/doc/articles/versions.md):

| Constraint | Meaning |
| --- | --- |
| `^0.1.13` | `>=0.1.13 <0.2.0` (recommended) |
| `~0.1.13` | `>=0.1.13 <0.2.0` |
| `>=0.1.13 <0.3` | a range; a space or `,` means AND |

Two ranges joined with `||` accept either of them, for example `^0.1.13 || ^1.0`.

Constraints that accept every version (`*`, `x`, `>=0`, `!=0.5.0`), branch names such as `dev-master` and
stability flags such as `@dev` are not allowed: a package states the versions it was tested with.

The running YiiPress is checked as follows:

- a release checks its own version, for example `0.1.13`;
- a nightly build checks the latest release tag it is built on, stamped at packaging time, for example
  «nightly built on 0.1.13 (commit 80964110a7e8)»;
- a build from source has no known version, so the check fails.

## Errors

YiiPress reports every problem in the manifest at once. Each error names the field and the value:

```text
extension.yaml has 2 errors:
- version: "v1.0" is not a SemVer version such as 1.0.0 (no "v" prefix, no build metadata, no leading zeros).
- processor.entry_points[0].path: file "emoji.php" does not exist in package/.
```

| Code | Meaning |
| --- | --- |
| `file_missing` | No `extension.yaml` in the package root. |
| `file_unreadable` | `extension.yaml` is not a readable regular file. |
| `file_too_large` | Over 64 KiB, or more than 10 000 YAML nodes. |
| `yaml_invalid` | Not valid UTF-8 YAML, or more than one YAML document. |
| `not_mapping` | The top level, or a field that must be a mapping, is not one. |
| `field_missing` | A required field is absent or empty. |
| `field_type` | A field has the wrong type (string, integer, list, mapping). |
| `field_unknown` | A key that is not defined at this level, including another type's section. |
| `field_invalid` | Length, characters or number of items out of range. |
| `field_duplicate` | A value repeats in a list that must be unique. |
| `manifest_unsupported` | `manifest` is not `1`. |
| `id_invalid` | `id` does not follow the `vendor/name` rules. |
| `id_reserved` | A theme is named `minimal` or `local`. |
| `type_invalid` | `type` is not a known extension type. |
| `type_unsupported` | `type` is `importer` or `plugin`. |
| `type_mismatch` | The package is of another type than expected, for example a processor installed as a theme. |
| `version_invalid` | `version` is not a SemVer version. |
| `constraint_invalid` | `yiipress` is not a valid constraint, accepts every version, names a branch or has a stability flag. |
| `url_invalid` | Not an `https://` URL. |
| `email_invalid` | Not a valid email address. |
| `value_invalid` | A value outside the allowed set (`fallback`, `stages`, `os`, `arch`). |
| `path_invalid` | A path breaks a path rule. |
| `path_not_found` | A path is not a file in the package. |
| `path_outside` | A path, `extension.yaml` or `package/` resolves to a location outside its base directory. |
| `entry_not_php` | A processor entry point does not end in `.php`. |
| `package_dir_missing` | No `package/` directory. |
| `engine_version_unknown` | The version of the running YiiPress is unknown. |
| `engine_incompatible` | The running YiiPress does not satisfy `yiipress`. |
| `platform_unsupported` | The local operating system or architecture is not in `platforms`. |

## Installing a theme

```shell
yiipress get:theme --url=https://github.com/yiipress/theme-wonder
yiipress get:theme --url=https://github.com/yiipress/theme-wonder --version=1.2.0 --no-activate
```

Run it in the project root, where you run `build`.

| Option | Meaning |
| --- | --- |
| `--url` | The theme repository, `https://github.com/<owner>/<repo>` (a trailing `.git` or `/` is fine). Required. |
| `--version` | The release to install, `X.Y.Z` without `v`. Without it, the highest compatible release is used. |
| `--no-activate` | Install only; leave `config.yaml` as it is. |
| `--content-dir` | The directory of `config.yaml` (default: `content`). |
| `--engine-version` | The YiiPress version to check releases against. Only for a build that does not know its version, such as a build from source; refused otherwise. |

What happens:

1. **Release.** YiiPress lists the stable releases of the repository. Drafts, pre-releases and tags that are not
   SemVer versions (an optional leading `v` is allowed) are skipped. Without `--version` it reads `extension.yaml` of
   up to 20 releases, newest first, and takes the first theme that supports this YiiPress and this platform. The
   output names the release and why it was chosen:

   ```text
   Selected Wonder 1.2.0 (v1.2.0), highest stable release compatible with YiiPress 0.1.13.
   Installed yiipress/wonder into themes/wonder.
   Activated: "theme" is now "wonder" in content/config.yaml.
   ```

2. **Archive.** The source archive of the release tag is downloaded from GitHub and checked before anything in the
   project changes: one top-level directory holding `extension.yaml` and `package/`, only regular files and
   directories (no links), [portable paths](#path-rules), no two paths that differ only in case, the
   [limits](#installation-limits) below, and a manifest that matches the selected release. No file of the package is
   run.
3. **Destination.** Only `package/` is installed, into `themes/<name>`, where `<name>` is the name part of the
   theme ID (`yiipress/wonder` → `themes/wonder`). An existing `themes/<name>` in any letter case is never replaced.
4. **Activation.** Unless `--no-activate` is given, the top-level `theme` key of `config.yaml` is set to the
   theme name. The file is edited as text, so comments, order and line endings stay. When the key cannot be changed
   safely as one line (for example it appears twice or holds a multi-line value), nothing is installed and the
   message names the line to fix; use `--no-activate` and set `theme` by hand meanwhile.

The theme files, `yiipress.lock` and `config.yaml` change together: if the command fails or is killed, either all
of them change or none.

### Files in the project

- `yiipress.lock` records each installed package: its source, version and tag, the commit, the URL, size and
  SHA-256 of the archive, the destination and the SHA-256 of every installed file. The same installation always
  writes the same bytes. Commit it with the site.
- `.yiipress/` holds the state of a running installation: a lock that keeps two extension commands from running at
  once, a journal and temporary files. Add it to `.gitignore`.

### Interrupted installations

If an installation was killed (power loss, `kill -9`), `.yiipress/journal.json` stays behind and `build` and `serve`
stop with:

```text
An extension operation was interrupted. Run `yiipress recover:extensions`.
```

`yiipress recover:extensions` undoes the unfinished installation, or only cleans up if it had completed. It never
deletes theme files that were changed after the installation; in that case it stops, keeps the journal and the
backups in `.yiipress/`, and asks you to check `themes/`, `yiipress.lock` and `config.yaml` by hand. `get:theme` runs
the same recovery before it starts.

### Installed themes and template fallback

A template missing from the site theme is looked up in the other registered themes. Themes installed by `get:theme`
are left out of that lookup: an installed theme is used only when it is the site theme (or the `theme` of an
entry). Installing a theme with `--no-activate` therefore does not change the built site. If `yiipress.lock` cannot
be read, `build` prints a warning and treats installed themes like any other project theme.

### Requirements

Downloads use PHP's `https` stream wrapper (the `openssl` extension) when PHP has it, otherwise the `curl`
program; Windows 10 version 1803 and later ship `curl.exe`. The distroless Docker image has neither, so `get:theme`
fails there with `http_client_missing`: run it with a PHAR, a static binary or the full image, and commit the
installed theme and `yiipress.lock`.

Only public GitHub repositories are supported, and no token is sent. Listing releases counts against GitHub's API
rate limit for requests without a token; reading manifests and downloading archives does not. When the limit is
reached, the command stops with `rate_limited` and says when to retry.

### Installation limits

These are the current defaults; they may change before the extension catalog is released. They cannot be turned
off.

| Limit | Value |
| --- | --- |
| Archive download | 50 MiB |
| Total extracted size | 200 MiB |
| Single file | 20 MiB |
| Archive entries | 10 000 |
| Release list pages (100 releases each) | 10 |
| Release manifests read when choosing a release | 20 |
| Connect timeout / whole request | 15 s / 300 s |
| Retries on connection failure, HTTP 5xx and 429 | 3, waiting as `Retry-After` asks, up to 60 s |
| Redirects | 5, `https` only, GitHub hosts only |

### Installation errors

Each error is one message naming the value, with a hint when there is one. The exit code is `65` (data error),
`64` for a missing `--url` or a refused `--engine-version`, `75` when another extension command runs in the project
(`mutation_locked`) and `69` for network problems and rate limits.

| Code | Meaning |
| --- | --- |
| `source_invalid` | `--url` is missing or not `https://github.com/<owner>/<repo>`. |
| `source_unsupported` | The URL is not on GitHub. |
| `http_client_missing` | Neither PHP's `https` wrapper nor `curl` is available. |
| `network_failed` | Connection, TLS, timeout, or HTTP 5xx after retries. |
| `rate_limited` | GitHub's rate limit is reached; the message says until when. |
| `redirect_refused` | A redirect to a non-`https` URL or a host other than GitHub. |
| `repository_not_found` | The repository does not exist or is private. |
| `release_none` | The repository has no stable release with a version tag. |
| `release_not_found` | `--version` names no release. |
| `release_prerelease` | `--version` names a pre-release. |
| `release_ambiguous` | Two tags are the same version, for example `1.0.0` and `v1.0.0`. |
| `release_incompatible` | The requested release does not support this YiiPress or platform. |
| `no_compatible_release` | No release is compatible; the message lists each one checked and why. |
| `engine_version_unknown` | This YiiPress does not know its version, or `--engine-version` was refused. |
| `manifest_invalid` | `extension.yaml` has [errors](#errors), is missing, or is not a theme. |
| `manifest_mismatch` | The archive's manifest differs from the release's in ID or version, or the version differs from the tag. |
| `download_too_large` | The archive is over the download limit. |
| `archive_invalid` | Not a valid `.tar.gz`: broken header, checksum or truncation. |
| `archive_layout` | Not one top-level directory, or no `extension.yaml` and `package/` in it. |
| `archive_entry_type`, `archive_link` | An entry that is not a file or directory; links are always refused. |
| `archive_path_invalid` | A path breaks the [path rules](#path-rules). |
| `archive_path_collision` | Two paths differ only in case, or a file and a directory share a path. |
| `archive_too_many_entries`, `archive_file_too_large`, `archive_too_large` | Over a [limit](#installation-limits). |
| `archive_commit_missing` | The archive does not name its commit; only GitHub tag archives are supported. |
| `already_installed` | The theme ID is in `yiipress.lock` already. |
| `name_collision` | Another installed theme uses the same name. |
| `destination_exists` | `themes/<name>` exists, in any letter case. |
| `config_missing` | No `config.yaml` in the content directory. |
| `config_unsupported` | `theme` in `config.yaml` cannot be changed safely, or the content directory is outside the project. |
| `lock_invalid`, `lock_unsupported` | `yiipress.lock` is malformed, or written by a newer YiiPress. |
| `mutation_locked` | Another extension command is running in this project. |
| `recovery_failed` | An interrupted installation cannot be undone safely; see [above](#interrupted-installations). |
| `filesystem_failed` | A file could not be written, moved or deleted, or `.yiipress/`, `.yiipress/staging/` or `.yiipress/backup/` is a symbolic link or lies outside the project; the message names it. |

## Reading manifests in PHP

The classes live in `YiiPress\Extension` (`src/Extension/`):

```php
use YiiPress\Extension\EngineCompatibility;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Manifest\InvalidManifestException;
use YiiPress\Extension\Manifest\ManifestReader;

try {
    $manifest = new ManifestReader()->read('/tmp/wonder', ExtensionType::Theme);
} catch (InvalidManifestException $exception) {
    foreach ($exception->errors() as $error) {
        echo $error->code, ' ', $error->field, ': ', $error->message, "\n";
    }
    return;
}

$problems = new EngineCompatibility()->check($manifest, EngineVersion::current());
```

- `ManifestReader::read()` reads `extension.yaml` from a package root and checks the files it names.
- `ManifestParser::parse()` takes the YAML text only and does not touch the filesystem, for manifests fetched
  from a release.
- `PortablePath::problem()` applies the path rules to any relative path.
