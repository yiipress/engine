# 0002 appendix: `get:theme` and the shared installer (reference)

Reference for [decision 0002](0002-theme-installation.md). Scope: `yiipress get:theme`, the parts it shares with
later commands, the lock file format, recovery, `restore:extensions` (see «Restore») and `update:theme` /
`remove:theme` (see «Update and removal»). `update:*` / `remove:*` for other types, processors and the full fallback
rules are later steps; they appear here only where the lock format must serve them.

## Command

```
yiipress get:theme --url=<github repository URL> [--version=X.Y.Z] [--no-activate] [--content-dir=content]
                   [--engine-version=X.Y.Z]
```

- Project root: the working directory, as for `build` (`getcwd()`). `themes/`, `yiipress.lock` and `.yiipress/`
  live there; `--content-dir` only locates `config.yaml`.
- `--engine-version` exists only if the lead accepts open question 2; it is refused when
  `EngineVersion::current()` knows the version.
- Exit codes: `ExitCode::OK`, `ExitCode::DATAERR` for every error in «Error codes» except `mutation_locked`
  (`ExitCode::TEMPFAIL`), network/rate-limit errors (`ExitCode::UNAVAILABLE`) and command-line usage errors, a
  missing `--url` or a refused `--engine-version` (`ExitCode::USAGE`, 64).
- Output: the selected release and why («Wonder 1.2.0, highest stable release compatible with YiiPress 0.1.13»),
  the destination, the activation result; on error one friendly message per problem.

## Order of work

Every step up to 9 changes no project file. Steps 10–13 run inside the transaction.

1. Parse `--url` into `GitHubRepository` (owner, repo).
2. Take the mutation lock; run recovery if `.yiipress/journal.json` exists (see «Transaction»).
3. Read `yiipress.lock` (absent = empty).
4. List releases (see «Release selection»); choose one.
5. Download the tag tarball into `.yiipress/staging/<tx>/archive.tar.gz` within the limits; compute SHA-256.
6. Read the tarball (see «Archive rules») into `.yiipress/staging/<tx>/root/`; take the commit from the pax header.
7. `ManifestReader::read($root, ExtensionType::Theme)`; its `id` and `version` must equal the manifest used for
   selection (`manifest_mismatch`); run `EngineCompatibility::check()` again.
8. Destination checks (see «Destination»); hash every file of `root/package/`.
9. If activating: compute the new `config.yaml` text (see «Activation») and write it to staging.
10. Write the journal (state `prepared`) with backups of `yiipress.lock` and `config.yaml`.
11. `rename()` `staging/<tx>/root/package` → `themes/<name>`.
12. Write `yiipress.lock`, then `config.yaml`, each as temp file in the same directory + `rename()`.
13. Set the journal to `committed`, delete staging, backups and the journal; release the lock.

No step includes, requires or executes a file from the package.

## Release selection

- Source: `GET https://api.github.com/repos/<owner>/<repo>/releases?per_page=100`, following `Link: rel="next"`
  up to 10 pages. Drafts and `prerelease: true` are dropped.
- Tag normalisation: strip one leading `v`; the rest must pass `SemanticVersion::isValid()` and have no
  pre-release part, else the release is skipped. Two releases with the same normalised version are
  `release_ambiguous` when that version is requested or reached during selection.
- `--version=X.Y.Z` (no `v`): exactly that normalised version, else `release_not_found`; a pre-release version is
  `release_prerelease` (explicit pre-release install is a #177 follow-up). If its manifest is incompatible:
  `release_incompatible`, no fallback.
- No `--version`: sort by SemVer descending; for each candidate (at most 20) fetch
  `https://raw.githubusercontent.com/<owner>/<repo>/<tag>/extension.yaml` (not counted against the API limit), parse
  it with `ManifestParser`, and take the first one whose `type` is `theme`, whose `version` equals the normalised
  tag and that passes `EngineCompatibility::check()` with `Platform::current()`. Candidates that fail are listed in
  the error. None: `no_compatible_release`, naming the engine version (`EngineVersion::describe()`) and each
  candidate's `yiipress` constraint or reason.
- GitHub's «latest» flag and dates are ignored.

## Archive rules

URL: `https://codeload.github.com/<owner>/<repo>/tar.gz/refs/tags/<tag>` (no redirect). Read as a stream from
`compress.zlib://<file>`, 512-byte ustar headers, by `TarGzReader`; `PharData` is not used.

- Accepted entry types: regular file (`0`, `\0`), directory (`5`), pax extended header (`x`: `path`, `size`
  honoured; others ignored), pax global header (`g`: `comment` is the commit, 40 hex chars). Anything else
  (links `1`/`2`, devices, FIFOs, GNU `L`/`K`, unknown) inside the enclosing directory is `archive_entry_type`;
  a link is reported as `archive_link`. Links are refused even when they point inside the package.
- All entries share one first path segment (the enclosing directory), which is stripped. Root must then hold a
  regular `extension.yaml` and a `package/` directory (`archive_layout`).
- Every stripped path passes `PortablePath::problem()` (`archive_path_invalid`). Two paths equal after
  `mb_strtolower()` are `archive_path_collision`. A file entry under a path already used by a file or directory is
  `archive_path_collision` too.
- Checksum of each header, sizes and the end-of-archive blocks are checked (`archive_invalid`).
- Everything that passes is written under `root/`; only `package/` is installed, the rest serves the manifest
  check (screenshots, examples) and is deleted with staging.
- No pax `comment`: `archive_commit_missing`.

## Limits

One class, `InstallLimits`, constants only; no option disables them. Values are proposals, to be confirmed.

| Limit | Value |
| --- | --- |
| Download size (tarball) | 50 MiB |
| Total extracted size | 200 MiB |
| Single file | 20 MiB |
| Archive entries | 10 000 |
| API pages | 10 |
| Candidate manifests fetched | 20 |
| Connect timeout / whole request | 15 s / 300 s |
| Retries on connect failure, 5xx, 429 | 3, honouring `Retry-After` up to 60 s |
| Redirects | 5, `https` only, hosts `github.com`, `api.github.com`, `codeload.github.com`, `raw.githubusercontent.com`, `objects.githubusercontent.com` |

Size limits are checked while reading the stream for extraction. For download: `curl --max-filesize` (stops a
running transfer since curl 8.4.0, per the curl 8.18 manual read 2026-10-29; older curl stops only on a known `Content-Length`) and, for any
transport, the file size right after download before anything reads it.

## HTTP

`HttpClientInterface::get(string $url, string $destination, int $maxBytes): HttpResponse` (`int $status`,
`array<string, list<string>> $headers` with lowercase names). No exceptions for HTTP status codes; transport
failures throw `InstallException` (`network_failed`).

- `CurlHttpClient`: `curl --proto =https --proto-redir =https --location --max-redirs 5 --max-filesize <n>
  --connect-timeout 15 --max-time 300 --silent --show-error --dump-header <file> --output <file>
  --write-out '%{http_code} %{url_effective}' <url>`; the effective URL's host is checked against the list
  above. Windows 10 1803+ ships `curl.exe`. No credentials are sent; tokens are a follow-up.
- `StreamHttpClient`: used when `in_array('https', stream_get_wrappers())`; `follow_location` off, redirects
  followed by hand with the same host check.
- `AvailableHttpClient` picks the stream client if possible, else `curl`, else `http_client_missing` («install
  curl or run get:theme outside the distroless image»).
- 403/429 with `x-ratelimit-remaining: 0`: `rate_limited`, with the reset time from `x-ratelimit-reset` in local
  time and «retry later».
- `src/Update` keeps its own downloader in this step; moving `self-update` onto `HttpClientInterface` is a later
  clean-up, not part of this step.

## Destination

- Local name: `ExtensionId::$name` (already lowercase, `[a-z0-9-]`, not `minimal`/`local` per 0001).
- `yiipress.lock` holds the same `id`: `already_installed` («use update:theme», which ships later).
- `yiipress.lock` holds another `id` with this local name: `name_collision`.
- Any entry in `themes/` equal to the name after `mb_strtolower()` (file, directory or symlink): `destination_exists`.
  Nothing is renamed or replaced.
- `themes/` is created if missing.

## Activation

Without `--no-activate`, only the top-level `theme` key of `<content-dir>/config.yaml` changes; the file is edited as
text so comments and layout stay.

- Missing `config.yaml`: `config_missing` (checked at step 9, before anything is installed).
- Find lines matching `^theme:[ \t]*(<plain scalar or quoted scalar>)?[ \t]*(#.*)?$` at column 0. Exactly one: replace
  the value, keep the comment. None: append `theme: <name>` (after a newline if the file does not end with one).
  `highlight_theme:` and indented `theme:` keys never match.
- More than one match, a flow mapping at top level, an alias, a tag, a block scalar or a multi-line value:
  `config_unsupported`, with the line to fix by hand; use `--no-activate` meanwhile.
- After editing, `yaml_parse()` of the old and new text must differ only in `theme` (`config_unsupported`
  otherwise). Line endings (`\n`/`\r\n`) and a BOM are kept.
- Per-entry `theme` front matter is not touched.

## Theme fallback in this step

Stop-gap, if the lead accepts open question 1: `ThemeRegistry` marks themes whose directory is a `destination` in
`yiipress.lock` as managed. `TemplateResolver` uses a managed theme only when it is the requested theme; the
loop over «all registered themes» skips managed ones. Unmanaged themes behave as today. Builds read
`yiipress.lock` only for this; a missing or invalid lock means no managed themes, and an invalid lock is a
warning, not a build failure. The `fallback` step replaces this with the full rules.

## Lock file

`yiipress.lock` in the project root, JSON, UTF-8, keys sorted, `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES |
JSON_UNESCAPED_UNICODE`, trailing newline, no timestamps, so the same install gives the same bytes. It is meant to
be committed.

```json
{
    "extensions": {
        "yiipress/wonder": {
            "archive": {
                "kind": "github-tarball",
                "sha256": "afe2cba716f10651c7286f7a794240808ce231e0f7353184af4e458e65c116a6",
                "size": 48213,
                "url": "https://codeload.github.com/yiipress/theme-wonder/tar.gz/refs/tags/v1.0.0"
            },
            "commit": "acf0346e709f2934037914577a98a6ee62a28000",
            "destination": "themes/wonder",
            "files": {
                "assets/style.css": "9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08",
                "layout.php": "60303ae22b998861bce3b28f33eec1be758a213c86c93c076dbe9f558c11c752"
            },
            "manifest_sha256": "2c26b46b68ffc68ff99b453c1d30413413422d706483bfa0f98a5e886266e7ae",
            "name": "wonder",
            "source": "https://github.com/yiipress/theme-wonder",
            "tag": "v1.0.0",
            "type": "theme",
            "version": "1.0.0"
        }
    },
    "lock": 1
}
```

| Field | Rule | Used by |
| --- | --- | --- |
| `lock` | integer, `1`; higher: `lock_unsupported` | all |
| `extensions` | object keyed by extension ID | all |
| `type` | `ExtensionType` value | all |
| `source` | normalised `https://github.com/<owner>/<repo>` | restore, update |
| `version`, `tag` | normalised version and the exact tag it came from | restore, update |
| `commit` | 40 hex chars from the pax header | restore (must match again) |
| `archive.kind` | `github-tarball`; other kinds (release asset) may be added | restore |
| `archive.url`, `archive.sha256`, `archive.size` | what was downloaded | restore |
| `destination` | path relative to the project root, `/` separators | restore, update, remove |
| `name` | theme local name (themes only) | fallback, remove |
| `manifest_sha256` | SHA-256 of `extension.yaml` as installed from | update (detects re-tagged releases) |
| `files` | every regular file under `package/`, path relative to `destination` → SHA-256 | restore, update, remove (local changes) |

Activation state is not in the lock; it lives in `config.yaml`. Reading: unknown keys, wrong types or a path that
fails `PortablePath` are `lock_invalid` naming the field.

## Transaction

Directory `.yiipress/` in the project root (created on demand; docs recommend ignoring it in VCS):
`mutation.lock`, `journal.json`, `staging/<tx>/`, `backup/<tx>/`. Staging sits in the project so the final
`rename()` stays on one filesystem; a failed `rename()` (`EXDEV`) is `filesystem_failed` before any other change.

- Mutation lock: `flock(LOCK_EX | LOCK_NB)` on `.yiipress/mutation.lock`, held for the whole command, released by
  the OS when the process exits. Busy: `mutation_locked`. The file's existence alone means nothing.
- Journal (`journal.json`, written as temp + `rename()`):
  `{"journal": 1, "tx": "<id>", "operation": "install", "state": "prepared|committed",
  "steps": [{"kind": "move_dir", "from": "...", "to": "themes/wonder"},
  {"kind": "replace_file", "path": "yiipress.lock", "backup": "backup/<tx>/yiipress.lock" | null},
  {"kind": "add_file", "from": "...", "to": "themes/wonder/assets/style.css", "hash": "<sha256>"}, ...]}`.
  `backup: null` means the file did not exist. `add_file` (restore only) puts a missing file into an existing
  package directory; `to` must not exist. `{"kind": "retire_dir", "from": "themes/wonder", "backup":
  ".yiipress/backup/<tx>/<n>"}` (update, remove) moves an installed directory, which must be a directory and not a
  link, into the transaction's backup with one `rename()`; commit deletes it with the backups.
- Recovery (start of every mutating command, and `yiipress recover:extensions`):
  `prepared` → undo steps in reverse: restore or delete each file from its backup, delete a moved directory only if
  its files equal the staged hashes recorded in the journal, delete an added file only if it equals its `hash`,
  move a retired directory back when its backup exists and nothing took its place (a `move_dir` to a directory
  whose `retire_dir` never ran is skipped: what is there is the old package); anything else stops with
  `recovery_failed` and keeps journal and backups. `committed` → delete staging, backups and the journal.
- Builds and `serve`: if `.yiipress/journal.json` exists, stop with «An extension operation was interrupted. Run
  `yiipress recover:extensions`.» They never take the mutation lock.

## Restore

`yiipress restore:extensions [--engine-version=X.Y.Z]`, project root as for `get:theme`. It reads `yiipress.lock`
only: it never lists releases, activates, edits `config.yaml` or writes the lock. Exit codes as for `get:theme`.

1. Take the mutation lock; run recovery. Read `yiipress.lock` (absent = nothing to restore).
2. For every entry, sorted by ID, with no network and no change: the type must be `theme` and `destination` must be
   `themes/<name>` (`lock_invalid`); two entries with one destination ignoring case are `name_collision`. Compare
   the destination with `files`, without following links: a file, a link or another-case name in its place is
   `destination_exists`; a file whose SHA-256 differs is `files_modified`; a file, link or other entry not in
   `files` is `files_unexpected`; a missing file is only a repair. Every problem of every entry is reported
   together and nothing is downloaded. All files present and equal: the entry is skipped.
3. Each entry with something missing: `tag` must name `version` and `archive.url` must be the tag archive of
   `source` (`lock_invalid`, before any request). Download that URL (404: `release_not_found`, no other release is
   tried) into `.yiipress/staging/<tx>/<id>/`, read it by «Archive rules». The pax commit, the SHA-256 of
   `extension.yaml`, the manifest `id` and `version` and the hash of every file under `package/` must equal the
   lock (`archive_mismatch`); `ManifestReader` and `EngineCompatibility::check()` run as at install
   (`manifest_invalid`, `release_incompatible`). Network errors stop at once; data problems of all entries are
   reported together, before any change.
4. Archive `sha256` or `size` different while all of step 3 matches: a warning, not an error; the restore goes on
   and the lock is not rewritten. Owner's decision, 2026-10-29, on open question 3 of decision 0002: GitHub may
   regenerate a tag tarball, so its bytes are not stable; the commit and the file hashes are what is locked.
5. One transaction (`operation: "restore"`): a missing destination is a `move_dir` of the staged `package/`, each
   missing file of an existing destination an `add_file`. Nothing is overwritten or deleted.

## Update and removal

`yiipress update:theme <id> [--version=X.Y.Z] [--engine-version=X.Y.Z]` and `yiipress remove:theme <id>
[--content-dir=content]`, project root as for `get:theme`, exit codes as for it (`64` for a malformed ID).
Issue #177 «Updates and removal»; design decisions of the lead on #256 (2026-10-29): one journal step `retire_dir`
for both, content that cannot be parsed blocks removal, the commands live in `src/Extension/Lifecycle/`.

Both commands, before any download or change:

1. Take the mutation lock; run recovery. Read `yiipress.lock`.
2. The ID must have an entry (`not_installed`) of type `theme` (`extension_not_theme`) with destination
   `themes/<name>` (`lock_invalid`).
3. Compare the destination with `files` as restore does (`Restore\InstalledPackage`): a changed, added or deleted
   file, a missing directory or something in its place is `local_changes`, listing them. The solution points to
   `restore:extensions` when files are only missing. Removal runs its reference check (removal step 4) before this
   one, and accepts a directory that is gone entirely (see removal step 5).

Update:

4. Select the release from the entry's `source` with `ReleaseSelector` (`--version` exact, a downgrade allowed;
   without it the newest compatible stable release). Its manifest `id` must be the entry's ID
   (`manifest_mismatch`). Without `--version`, a release that is not newer than the entry's `version` is no change;
   with it, the same version is no change. No change: exit 0, nothing downloaded.
5. `Install\PackageStager` downloads, reads and checks the archive as `get:theme` does (shared code).
6. One transaction (`operation: "update"`): `retire_dir` of the destination, `move_dir` of the staged `package/` to
   the same destination, `replace_file` of `yiipress.lock` with the new entry (same `destination` and `name`).
   `config.yaml` is not touched: the name does not change, so activation and entry selections stay valid.

Removal:

4. The theme must not be selected: `theme` in `<content-dir>/config.yaml` (parsed by `SiteConfigParser`) or
   `theme` in the front matter of any entry or standalone page (`ContentParser`, no cache). Every selecting file is
   listed (`theme_in_use`); content that cannot be parsed is `content_unreadable`. Nothing is edited. Theme
   fallbacks cannot name an installed theme (`fallback` is `minimal` or `none`), so there is nothing else to check;
   references from custom PHP are not detected (documented).
5. One transaction (`operation: "remove"`): `retire_dir` of the destination, `replace_file` of `yiipress.lock`
   without the entry. When nothing is at the destination (no directory, file or link, in any letter case), only the
   `replace_file`: the entry is dropped and the command succeeds. Owner's decision, 2026-10-29 (#256 rev 66): a
   theme deleted by hand needs no restore before removal; a partly deleted, changed or added file set is still
   `local_changes`, and a theme that is still selected is still `theme_in_use`.

A failure or kill at any point leaves, after recovery, the old directory and the old lock, or (once committed) the
new state; never a mix.

## Classes

All `final`; value objects `readonly`. Names may be adjusted by the developer if the API stays.

| Class | Home | Public API |
| --- | --- | --- |
| `Http\HttpClientInterface` | shared | `get(string $url, string $destination, int $maxBytes): HttpResponse` |
| `Http\HttpResponse` | shared | `int $status`, `headers`, `header(string): ?string` |
| `Http\CurlHttpClient`, `StreamHttpClient`, `AvailableHttpClient` | shared | implement the interface |
| `Release\GitHubRepository` | shared | `static fromUrl(string): self` (throws `InstallException`), `$owner`, `$name`, `url(): string` |
| `Release\Release` | shared | `string $tag`, `string $version` |
| `Release\GitHubReleaseSource` | shared | `releases(GitHubRepository): list<Release>`, `manifest(GitHubRepository, Release): string`, `downloadArchive(GitHubRepository, Release, string $file): DownloadedArchive` |
| `Release\ReleaseSelector` | shared | `select(GitHubRepository, ExtensionType, ?string $version, EngineVersion, Platform): SelectedRelease` |
| `Archive\TarGzReader` | shared | `extract(string $file, string $targetDir, InstallLimits): ExtractedArchive` (`commit`, `root`) |
| `Archive\InstallLimits` | shared | constants of «Limits» |
| `Lock\LockFile` | shared | `static read(string $path): self`, `get(ExtensionId): ?LockedExtension`, `withExtension(ExtensionId, LockedExtension): self`, `withoutExtension(ExtensionId): self`, `toJson(): string`, `destinations(): list<string>` |
| `Lock\LockedExtension` | shared | the fields above |
| `Transaction\MutationLock` | shared | `static acquire(string $projectRoot): self`, `release(): void` |
| `Transaction\Transaction` | shared | `moveDirectory(string $from, string $to, array $hashes)`, `retireDirectory(string $path)`, `replaceFile(string $path, string $contents)`, `commit(): void` |
| `Transaction\Recovery` | shared | `static pending(string $projectRoot): bool`, `recover(string $projectRoot): void` |
| `Install\PackageInstaller` | shared | `install(InstallRequest, DestinationPolicyInterface): InstallResult` (steps 2–13) |
| `Install\DestinationPolicyInterface` | shared | `destination(ExtensionManifest, LockFile, string $projectRoot): string`, `activation(ExtensionManifest, string $localName): ?FileChange` |
| `Install\InstallException` | shared | `RuntimeException` + `FriendlyExceptionInterface`; `string $errorCode`; wraps `InvalidManifestException` as `manifest_invalid` with its errors |
| `Theme\ThemeDestinationPolicy` | theme | «Destination», activation via `ThemeActivator` |
| `Theme\ThemeActivator` | theme | `activate(string $configYaml, string $themeName): string` (pure, testable) |
| `Restore\InstalledPackage` | shared | `static inspect(string $projectRoot, LockedExtension): self` (`exists`, `missing`, `modified`, `unexpected`, `collision`) |
| `Restore\LockedArchive` | shared | `fetch(string $id, LockedExtension, string $stagingDir, EngineVersion, Platform): VerifiedPackage` |
| `Restore\PackageRestorer` | shared | `restore(string $projectRoot, EngineVersion, Platform): RestoreResult`; throws `RestoreRefusedException` with every problem |
| `Install\PackageStager` | shared | `stage(Transaction, InstallRequest, SelectedRelease): StagedPackage` (download, extract, manifest and compatibility checks); `StagedPackage::locked(string $destination, ?string $name): LockedExtension` |
| `Theme\LockedThemeDestination` | theme | `static problem(string $id, LockedExtension): ?InstallException` (`themes/<name>` rule) |
| `Lifecycle\InstalledTheme` | theme | `static find(LockFile, ExtensionId): LockedExtension` (step 2), `static unchanged(string $projectRoot, LockFile, ExtensionId, string $command): LockedExtension` (steps 2–3), `static unchangedOrGone(string $projectRoot, LockedExtension, string $id): bool` (step 3 for removal) |
| `Lifecycle\ThemeUpdater` | theme | `update(string $projectRoot, ExtensionId, ?string $version, EngineVersion, Platform): UpdateResult` |
| `Lifecycle\ThemeRemover`, `Lifecycle\ThemeReferences` | theme | `remove(string $projectRoot, ExtensionId, string $contentDir): RemoveResult`; `find(string $themeName, string $contentDir): list<string>` |
| `Console\GetThemeCommand`, `Console\RecoverExtensionsCommand`, `Console\RestoreExtensionsCommand`, `Console\UpdateThemeCommand`, `Console\RemoveThemeCommand` | console | `get:theme`, `recover:extensions`, `restore:extensions`, `update:theme`, `remove:theme`, registered in `config/console/commands.php` |

Namespaces are under `YiiPress\Extension\` (directory `src/Extension/`), except the commands.

## Error codes

| Code | When |
| --- | --- |
| `source_invalid` | `--url` is not `https://github.com/<owner>/<repo>` (optional `.git`, trailing `/`). |
| `source_unsupported` | Another host. |
| `http_client_missing` | No `https` stream wrapper and no `curl`. |
| `network_failed` | Connection, TLS, timeout or a 5xx after retries. |
| `rate_limited` | GitHub rate limit hit; message gives the reset time. |
| `redirect_refused` | Redirect to a non-`https` URL or a host not in the list. |
| `repository_not_found` | 404 on the releases list. |
| `release_none` | No stable release with a SemVer tag. |
| `release_not_found` | `--version` has no matching release. |
| `release_prerelease` | `--version` names a pre-release. |
| `release_ambiguous` | Two releases normalise to the same version. |
| `release_incompatible` | The requested release does not support this engine or platform. |
| `no_compatible_release` | No candidate is compatible; lists the candidates. |
| `engine_version_unknown` | Engine version unknown and no accepted override. |
| `manifest_invalid` | `extension.yaml` fails 0001 checks (all errors listed). |
| `manifest_mismatch` | Archive manifest `id` or `version` differs from the selected one, or `version` differs from the tag. |
| `download_too_large` | Over the download limit. |
| `archive_invalid` | Not gzip, broken tar header, checksum or truncation. |
| `archive_layout` | Not one enclosing directory, or no `extension.yaml`/`package/`. |
| `archive_entry_type` | Unsupported entry type. |
| `archive_link` | Symbolic or hard link. |
| `archive_path_invalid` | Path fails `PortablePath`. |
| `archive_path_collision` | Paths equal ignoring case, or file/directory clash. |
| `archive_too_many_entries`, `archive_file_too_large`, `archive_too_large` | Limits. |
| `archive_commit_missing` | No commit in the pax global header. |
| `already_installed` | The ID is in `yiipress.lock`. |
| `name_collision` | Another locked ID uses the local name. |
| `destination_exists` | `themes/<name>` exists (any case). |
| `config_missing` | No `config.yaml` to activate in. |
| `config_unsupported` | `theme` cannot be changed safely as text. |
| `lock_invalid`, `lock_unsupported` | `yiipress.lock` unreadable, malformed or newer. |
| `files_modified` | Restore: installed files differ from the lock's `files`. |
| `files_unexpected` | Restore: the destination holds files or links not in `files`. |
| `archive_mismatch` | Restore: the locked archive holds another commit, manifest, ID, version or files than the lock. |
| `id_invalid` | Update, remove: the argument is not a `vendor/name` ID (exit 64). |
| `not_installed` | Update, remove: no lock entry with that ID. |
| `extension_not_theme` | Update, remove: the lock entry is another type. |
| `local_changes` | Update, remove: installed files were changed, added or deleted (update also: the directory is gone). |
| `theme_in_use` | Remove: `config.yaml` or an entry selects the theme. |
| `content_unreadable` | Remove: `config.yaml` or an entry cannot be parsed. |
| `mutation_locked` | Another extension command runs in this project. |
| `recovery_failed` | An interrupted transaction cannot be undone safely. |
| `filesystem_failed` | A write, `rename()` or delete failed, or `.yiipress/`, its `staging/` or `backup/` is a link or lies outside the project; message names the path. |

Messages are full sentences naming the value, e.g. «Release v1.3.0 of yiipress/theme-wonder supports YiiPress
^0.2; this is YiiPress 0.1.13.» They never contain tokens or full response bodies.

## Static binary

Nothing to add for this step: `zlib` (for `compress.zlib://`), `hash`, `json`, `filter` are loaded in nightly
`702e9a5` on Linux. Checks for this step: a packaging test asserts `extension_loaded('zlib')` on the macOS and
Windows binaries; the docs list `curl` as a requirement of `get:theme` and say the distroless image cannot run it.
