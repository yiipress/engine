# 0001 appendix: `extension.yaml` reference (manifest version 1)

Reference for [decision 0001](0001-extension-manifest.md). Scope: the manifest, its reader and validator, and the
engine compatibility check. Downloading, archives, install destinations, locking, activation and the catalog
are later steps of #177 and are not defined here.

## Package layout

The package root is the directory that holds `extension.yaml` and `package/` (in a release archive, the archive
root or its single enclosing directory; finding it is the archive step's job). Only `package/` is installed.
Paths in the manifest are relative: entry points to `package/`, catalog files (screenshots, examples) to the
package root.

## Example

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

```yaml
manifest: 1
id: example/emoji
type: processor
name: Emoji
description: Replaces :shortcodes: with emoji.
version: 0.2.0
yiipress: ">=0.1.13 <0.3"
license: MIT
authors: [{name: Example Team}]
processor:
  entry_points:
    - path: emoji.php
      stages: [before_markdown]
```

## Common fields

All keys are lowercase. Unknown keys at any level are an error. `null` counts as missing.

| Field | Type | Required | Rules |
| --- | --- | --- | --- |
| `manifest` | integer | yes | Must be `1`. A higher number: «requires a newer YiiPress». |
| `id` | string | yes | `vendor/name`; each part `^[a-z0-9]+(?:-[a-z0-9]+)*$`, 1–64 chars. No part may be a Windows reserved name (`con`, `prn`, `aux`, `nul`, `com0`–`com9`, `lpt0`–`lpt9`). |
| `type` | string | yes | `theme` or `processor`. `importer` and `plugin` are reserved: valid words, rejected as not supported yet. |
| `name` | string | yes | 1–100 chars after trim, one line, no control characters. |
| `description` | string | yes | 1–500 chars after trim, no control characters except `\n`. |
| `version` | string | yes | SemVer 2.0.0 `MAJOR.MINOR.PATCH[-PRERELEASE]`; no `v` prefix, no build metadata, no leading zeros. |
| `yiipress` | string | yes | Supported YiiPress versions, Composer constraint syntax (see below). |
| `license` | string | yes | SPDX identifier or expression (`MIT`, `MIT OR Apache-2.0`) or `proprietary`; 1–100 chars of `[A-Za-z0-9.+\-() ]`. Not checked against the SPDX list. |
| `authors` | list | yes | 1–20 items; each a mapping with `name` (string, 1–100, required), `email` (valid address, optional), `url` (`https://` URL, optional). |
| `homepage` | string | no | `https://` URL, ≤ 2000 chars. |
| `platforms` | mapping | no | `os`: list of `linux`, `macos`, `windows`; `arch`: list of `amd64`, `arm64`. Each key optional, non-empty and unique if present. Omitted means any. |
| `theme` | mapping | type `theme` | Allowed only when `type: theme`; may be omitted (all defaults). |
| `processor` | mapping | type `processor` | Required when `type: processor`; not allowed otherwise. |

The keys `importer` and `plugin` are reserved for their types and rejected in manifest version 1.

## Theme fields

| Field | Type | Required | Rules |
| --- | --- | --- | --- |
| `fallback` | string | no | `minimal` (default) or `none`. No other theme may be named in version 1. |
| `intended_use` | string | no | 1–200 chars, one line. |
| `screenshots` | list of strings | no | ≤ 10 catalog paths, extensions `png`, `jpg`, `jpeg`, `webp`; unique. |
| `demo` | string | no | `https://` URL. |

The theme's local directory name is the `name` part of `id`; it must not be `minimal` or `local`.

## Processor fields

| Field | Type | Required | Rules |
| --- | --- | --- | --- |
| `entry_points` | list | yes | 1–50 items, each `{path, stages}`; paths unique ignoring case. |
| `entry_points[].path` | string | yes | Entry point path, must end in `.php`. The file follows the existing project processor contract: returns a `ContentProcessorInterface` or a callable. |
| `entry_points[].stages` | list of strings | yes | Non-empty, unique, from `before_markdown`, `after_markdown`. Describes where it may run; does not enable it. |
| `examples` | list | no | ≤ 20 items, each `{input, output}`: catalog paths to Markdown/HTML samples. |

## Path rules

Every manifest path (entry points, screenshots, examples) must:

- use `/` as separator; no leading `/`, no `\`, no drive letter or `:`, no empty, `.` or `..` segment;
- be ≤ 255 bytes in total, each segment ≤ 100 bytes, valid UTF-8, no control characters and none of `<>:"|?*`;
- have no segment that is a Windows reserved name (with or without extension) or ends in a dot or space;
- when read from disk: exist as a regular file whose real path stays inside its base (`package/` for entry
  points, the package root for catalog paths), so a symlink cannot lead outside.

These rules go into one shared class (`PortablePath`), which the archive step reuses for every archive entry.

## Versions and constraints

- `version` is checked with the SemVer 2.0.0 regular expression, not with Composer's normaliser, which also
  accepts `v1.0`, four-part and branch versions. Tag normalisation (`v1.0.0` → `1.0.0`) belongs to release
  selection.
- `yiipress` is parsed with `Composer\Semver\VersionParser::parseConstraints()` (`composer/semver` moves from
  `require-dev` to `require`). Supported: `^`, `~`, `>=`, `<`, `!=`, ranges `a - b`, `,`/space for AND,
  `||` for OR. Rejected: `*` alone, `dev-…` branch constraints and `@stable`-style flags, so a package always
  states a real version range. Recommend `^X.Y.Z` in the docs.
- Engine version (`EngineVersion::current()`):
  1. release binary (no `COMMIT` stamped, root version is SemVer): that version, e.g. `0.1.13`;
  2. nightly binary: the base release stamped at build time (new constant `ApplicationInfo::BASE_VERSION`,
     from `git describe --tags --abbrev=0 --exclude 'nightly-*'`), checked as that release; messages say
     «nightly built on 0.1.13 (commit 80964110a7e8)»;
  3. anything else (source checkout, `dev-master`): unknown; the check fails with `engine_version_unknown`.
     An explicit override flag is the installer's decision, not this step's.
- Platform check: `PHP_OS_FAMILY` `Linux`/`Darwin`/`Windows` → `linux`/`macos`/`windows`; `php_uname('m')`
  `x86_64`/`amd64` → `amd64`, `aarch64`/`arm64` → `arm64`. An unknown local value fails only when the manifest
  declares that list.

## Reading safely

- `extension.yaml` ≤ 64 KiB, UTF-8, exactly one YAML document, top level a mapping.
- Parse with `yaml_parse()` and no callbacks, with `yaml.decode_php` off; abort the walk after 10 000 nodes
  (alias expansion).
- Nothing is included or executed; only `is_file()`/`realpath()` on manifest paths.

## Classes

Namespace `YiiPress\Extension`, directory `src/Extension/`. All classes `final`, value objects `readonly`.

| Class | Public API |
| --- | --- |
| `ExtensionType` (enum) | `Theme`, `Processor`, `Importer`, `Plugin`; `isSupported(): bool` |
| `ExtensionId` | `public string $vendor`, `public string $name`; `static tryFromString(string): ?self`; `__toString()` |
| `PortablePath` | `static problem(string $path): ?string` — `null` when portable, else the reason |
| `EngineVersion` | `static current(): self`; `?string $version`; `?string $commit`; `isNightly(): bool`; `describe(): string` |
| `EngineCompatibility` | `check(ExtensionManifest, EngineVersion, ?Platform = null): list<ManifestError>` |
| `Manifest\ExtensionManifest` | one public property per field; `theme(): ?ThemeMetadata`, `processor(): ?ProcessorMetadata` |
| `Manifest\Author`, `Platforms`, `ThemeMetadata`, `ThemeFallback` (enum), `ProcessorMetadata`, `ProcessorEntryPoint`, `ProcessorStage` (enum), `ProcessorExample` | plain value objects |
| `Manifest\ManifestParser` | `parse(string $yaml): ExtensionManifest` — no filesystem; for the catalog and the reader |
| `Manifest\ManifestReader` | `read(string $packageRoot, ?ExtensionType $expected = null): ExtensionManifest` — reads the file, parses, checks paths and type |
| `Manifest\ManifestError` | `string $code`, `string $field` (`processor.entry_points[0].path`), `string $message` |
| `Manifest\InvalidManifestException` | `RuntimeException` + `FriendlyExceptionInterface` (like `InvalidContentConfigException`); `errors(): list<ManifestError>`; message lists every error |

The parser collects all errors before throwing; it skips type-specific checks when `type` is invalid.

## Error codes

| Code | When |
| --- | --- |
| `file_missing` | No `extension.yaml` in the package root. |
| `file_unreadable` | Not a readable regular file. |
| `file_too_large` | Over 64 KiB, or over 10 000 YAML nodes. |
| `yaml_invalid` | Not valid UTF-8 YAML, or more than one document. |
| `not_mapping` | Top level, or a field that must be a mapping, is not one. |
| `field_missing` | A required field is absent or `null`. |
| `field_type` | Wrong type (string, integer, list, mapping). |
| `field_unknown` | A key not defined for this level, including another type's section. |
| `field_invalid` | Length, characters or list size out of range (`name`, `description`, `license`, `intended_use`, list counts). |
| `field_duplicate` | Repeated value in a list that must be unique. |
| `manifest_unsupported` | `manifest` is not `1`. |
| `id_invalid` | `id` does not match the pattern or uses a reserved name. |
| `id_reserved` | Theme name part is `minimal` or `local`. |
| `type_invalid` | `type` is not one of the four words. |
| `type_unsupported` | `importer` or `plugin`. |
| `type_mismatch` | Declared type differs from the expected one passed to the reader. |
| `version_invalid` | `version` is not SemVer 2.0.0 as above. |
| `constraint_invalid` | `yiipress` does not parse, or is `*`, a branch or has a stability flag. |
| `url_invalid` | Not an `https://` URL. |
| `email_invalid` | Not a valid address (`FILTER_VALIDATE_EMAIL`). |
| `value_invalid` | Value not in the allowed set (`fallback`, `stages`, `os`, `arch`). |
| `path_invalid` | Breaks a path rule. |
| `path_not_found` | Path does not exist as a regular file. |
| `path_outside` | Real path leaves its base directory. |
| `entry_not_php` | Processor entry point does not end in `.php`. |
| `package_dir_missing` | No `package/` directory. |
| `engine_version_unknown` | Engine version cannot be determined (compatibility check). |
| `engine_incompatible` | Engine version does not satisfy `yiipress` (compatibility check). |
| `platform_unsupported` | Local OS or architecture not in `platforms` (compatibility check). |

Messages are full sentences that name the field and the value, for example: «`processor.entry_points[0].path`:
file "emoji.php" does not exist in package/.» Compatibility messages name both sides: «Wonder 1.0.0 supports
YiiPress ^0.2; this is YiiPress 0.1.13.»
