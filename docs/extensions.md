# Extension packages

An extension package is a theme or a content processor that can be shared between YiiPress sites. Every package
describes itself in an `extension.yaml` manifest: what it is, which version it is, which YiiPress versions it
supports and where its files are.

YiiPress reads and validates the manifest without running any code from the package. Installing packages and the
extension catalog are planned; this page describes the manifest format they use.

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
base directory. A symlink that points outside the package is an error.

## YiiPress versions

`yiipress` uses [Composer constraint syntax](https://getcomposer.org/doc/articles/versions.md):

| Constraint | Meaning |
| --- | --- |
| `^0.1.13` | `>=0.1.13 <0.2.0` (recommended) |
| `~0.1.13` | `>=0.1.13 <0.2.0` |
| `>=0.1.13 <0.3` | a range; a space or `,` means AND |

Two ranges joined with `||` accept either of them, for example `^0.1.13 || ^1.0`.

`*`, branch names such as `dev-master` and stability flags such as `@dev` are not allowed: a package states the
versions it was tested with.

The running YiiPress is checked as follows:

- a release checks its own version, for example `0.1.13`;
- a nightly build checks the release it is built on, for example «nightly built on 0.1.13 (commit 80964110a7e8)»;
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
| `constraint_invalid` | `yiipress` is not a valid constraint, or is `*`, a branch or has a stability flag. |
| `url_invalid` | Not an `https://` URL. |
| `email_invalid` | Not a valid email address. |
| `value_invalid` | A value outside the allowed set (`fallback`, `stages`, `os`, `arch`). |
| `path_invalid` | A path breaks a path rule. |
| `path_not_found` | A path is not a file in the package. |
| `path_outside` | A path resolves to a location outside its base directory. |
| `entry_not_php` | A processor entry point does not end in `.php`. |
| `package_dir_missing` | No `package/` directory. |
| `engine_version_unknown` | The version of the running YiiPress is unknown. |
| `engine_incompatible` | The running YiiPress does not satisfy `yiipress`. |
| `platform_unsupported` | The local operating system or architecture is not in `platforms`. |

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
