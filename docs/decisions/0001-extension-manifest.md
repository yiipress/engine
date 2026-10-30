# 0001. Extension packages describe themselves in a strict, typed `extension.yaml`

- Status: proposed
- Date: 2026-10-28
- Decided by: team lead (adds a runtime dependency and a public format); proposed by the architect
- Issue: [#177](https://github.com/yiipress/engine/issues/177), step 1 of «Delivery scope»
- Full field reference: [0001-extension-manifest-spec.md](0001-extension-manifest-spec.md)

## Problem

Installers, `restore:extensions` and the catalog all need the same facts about a package: identity, type,
version, supported YiiPress versions and entry points. What does the manifest look like, which classes read
it, and how is a YiiPress version, including a nightly, compared with the package constraint?

## Context and constraints

- Installation and validation must not execute package code (#177). YAML is read with `ext-yaml`, already required.
- Released binaries report the tag (`0.1.13`) as their version. Nightly binaries and source checkouts report a
  commit SHA (`ApplicationInfo::resolveVersion()`), which no constraint can match.
- `composer/semver` 3.5.2 is in `composer.lock`, but only as a dev dependency.
- The catalog reads a manifest from a fetched release without the package files, so parsing must work without
  a filesystem.

## Options

1. **Typed value objects, strict schema** (chosen): one parser turns YAML into immutable objects and returns
   every error at once; unknown keys are errors; new fields come with a new `manifest` number.
2. **JSON Schema file and a generic validator**: one schema shared with the catalog, but a new dependency,
   arrays instead of types, and path and file checks still need code. Rejected.
3. **Lenient schema, unknown keys ignored**: easier for future fields, but typos (`entry_point`) pass silently
   and an older engine misreads a newer manifest. Rejected.

Constraint syntax: move `composer/semver` (MIT, pure PHP) to `require`, or write a small parser for `^`, `~`
and ranges. A hand-written parser saves one package but duplicates a well-tested one, and authors already
know Composer syntax. Chosen: `composer/semver`.

Nightly versions: evaluate as the last stable release they are built on (chosen); treat as always
incompatible; or treat as the next patch `-dev`. The base release is a fact the build knows, every feature of
it is present, and the only risk (an unreleased break) is a nightly risk users accept already.

## Decision

Chosen: option 1 with `composer/semver` constraints and base-release evaluation for nightlies, because it
catches mistakes in the author's manifest before anything is installed and keeps one strict source of truth
for installers and the catalog. Classes live in `src/Extension/` (spec, section «Classes»).

## Consequences

- Better: one reader for every type; all errors in one report; typos fail; constraints authors already know.
- Worse: every new manifest field needs a schema bump and a newer engine; one more runtime package in the
  PHAR and binaries; a source checkout without a known version cannot pass a compatibility check.
- Follow-up: stamp the base release into nightly builds (`build/package-phar.php`, workflows); item for the
  developer next to #258.

## Confirmation

- PHPUnit: one test per error code in the spec, plus a valid theme and a valid processor fixture.
- `composer-dependency-analyser` passes with `composer/semver` in `require`.
- Review point: no class in `src/Extension/Manifest/` does `require`/`include` or reads outside the package root.
