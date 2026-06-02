# Configuration

Most site settings live in `content/config.yaml`. This is the file you edit to change the site title, public URL, languages, theme, pagination, search, assets, and Markdown behavior.

Engine source checkouts also contain a `config/` directory for [Yii3 configuration](https://yiisoft.github.io/docs/guide/concept/configuration.html), [dependency injection](https://yiisoft.github.io/docs/guide/concept/di-container.html), [routing](https://yiisoft.github.io/docs/guide/runtime/routing.html), and framework internals. That is for engine development and is covered in [Engine](engine.md).

## Site config

`content/config.yaml` defines site-wide settings used during the build and by the bundled templates.

```yaml
title: My Site
description: A site built with YiiPress
base_url: https://example.com
languages: [en]
charset: UTF-8

default_author: john-doe

date_format: Y.m.d
entries_per_page: 10

permalink: /:collection/:slug/

taxonomies:
  - tags
  - categories

highlight_theme: "Solarized (dark)"

image: /assets/og-default.png
twitter: "@example"

robots_txt:
  rules:
    - user_agent: "*"
      disallow:
        - /private/

params:
  github_url: https://github.com/example/mysite

assets:
  fingerprint: true

last_updated: true
edit_page: https://github.com/example/mysite/edit/main/content/{path}
report_issue: https://github.com/example/mysite/issues/new?title=Docs:%20{title}&body={url}

editor: code
```

### Fields

- **title** — site title, used in layouts, feeds, and meta tags
- **description** — site description for meta tags and feeds
- **base_url** — full base URL including scheme (used in feeds, sitemaps, canonical URLs)
- **languages** — site language codes. The first language is the default language (e.g., `[en]`, `[en, ru]`)
- **charset** — character encoding (default: `UTF-8`)
- **default_author** — author slug (referencing a file in `content/authors/`), used when entries have no explicit `authors` field
- **date_format** — PHP date format string for displaying dates in templates (e.g., `Y.m.d` for "2026.03.23", `F j, Y` for "March 23, 2026"). See [PHP date format](https://www.php.net/manual/en/datetime.format.php) for all available format characters
- **entries_per_page** — default pagination size (overridden by collection `_collection.yaml`)
- **permalink** — default permalink pattern (overridden by collection or entry)
- **taxonomies** — list of enabled taxonomy types
- **theme** — default theme name for the site (see [Templates](templates.md))
- **highlight_theme** — built-in syntect theme used for fenced code block highlighting. Defaults to `InspiredGitHub`. Available built-in themes include `InspiredGitHub`, `Solarized (dark)`, `Solarized (light)`, `base16-ocean.dark`, `base16-ocean.light`, `base16-eighties.dark`, and `base16-mocha.dark`
- **image** — default Open Graph image URL (absolute, or root-relative path resolved against `base_url`); used as fallback when an entry has no `image` front matter field
- **twitter** — Twitter/X account handle (e.g., `@example`) added to `twitter:site` meta tag on all pages
- **robots_txt** — `robots.txt` generation settings (see below)
- **toc** — generate a table of contents from headings (default: `true`); set to `false` to disable globally. When enabled, heading tags receive `id` attributes and a `$toc` variable is passed to templates
- **search** — opt-in client-side search (see below)
- **related** — opt-in related content suggestions (see below)
- **last_updated** — set to `true` to show each entry source file's last modification time below its content (default: `false`)
- **edit_page** — URL template for an optional "Edit this page" link below entry content (see below)
- **report_issue** — URL template for an optional "Report an issue" link below entry content (see below)
- **assets** — asset pipeline settings (see below)
- **editor** — command used by `yiipress serve` to open the current markdown source from the preview overlay. If omitted, YiiPress uses the platform default opener (`open` on macOS, `xdg-open` on Linux, and `start` through `cmd` on Windows)
- **params** — arbitrary key-value pairs for use in templates
- **markdown** — markdown extensions configuration (see below)

### Search

Client-side search is opt-in. Enable it in `content/config.yaml`:

```yaml
search:
  full_text: false   # index full body text (default: false, summary+tags only)
  results: 10        # max results shown (default: 10)
```

When enabled, the build generates a `search-index.json` file in the output directory and injects a search button into the site header. Clicking the button (or pressing Ctrl+K / ⌘K) opens a search modal with fuzzy matching. No external dependencies — everything is hand-rolled JavaScript.

The `full_text` option controls how much content is indexed:
- `false` — indexes title, summary, and tags (smaller index, faster)
- `true` — additionally indexes the full body text (larger index, more thorough results)

### Related content

Related content suggestions are opt-in. Enable in `content/config.yaml`:

```yaml
related: true
```

Or configure:

```yaml
related:
  limit: 5                     # max related entries per page (default: 5)
  tag_weight: 2                # score per shared tag (default: 2)
  category_weight: 3           # score per shared category (default: 3)
  same_collection_only: true   # restrict suggestions to the same collection (default: true)
```

Templates receive a `$related` variable (list of `YiiPress\Content\Model\RelatedEntry`) ordered
by relevance. See [plugins.md](plugins.md#related-content) for details.

### Page actions

Page action links are optional. Enable a GitHub-style edit link with `edit_page`:

```yaml
edit_page: https://github.com/example/mysite/edit/main/content/{path}
report_issue: https://github.com/example/mysite/issues/new?title=Docs:%20{title}&body={url}
```

Supported placeholders are:

- `{path}` — source path relative to the content directory, URL-encoded with `/` preserved
- `{title}` — entry title, URL-encoded
- `{permalink}` — root-relative permalink, URL-encoded with `/` preserved
- `{url}` — absolute page URL resolved from `base_url`, URL-encoded

### Multilingual support

Declare site languages in `content/config.yaml`. The first language is the default:

```yaml
languages: [en, ru]
```

Entries are tagged with the `language` front matter field. Entries whose language
differs from the first configured site language get their permalink prefixed automatically (e.g.,
`/ru/blog/hello/`); default-language entries keep the plain URL (`/blog/hello/`).
Explicit `permalink:` overrides in front matter bypass the prefix. `languages` is required
and must contain at least one language code.

Group translations of the same article using a shared `translation_key`:

```yaml
---
title: Hello
language: ru
translation_key: hello
---
```

When `translation_key` is absent, translations are grouped by slug within the same
collection. All variants of an entry expose each other as alternates:

- `$translations` — list of `YiiPress\Content\Model\Translation` (language, permalink, title)
  available to templates for rendering a language switcher.
- `$language` — effective language of the current entry; the bundled theme uses it for
  `<html lang="…">`.
- `hreflang` alternate `<link>` tags are emitted in the head automatically, including
  `x-default` pointing to the default-language version.

The bundled `minimal` theme localizes its built-in UI labels (search, archive, related
posts, 404 page, redirects, and similar chrome) from theme translation files in
`themes/minimal/translation/`, for example `en.yaml` and `ru.yaml`.
The current entry language does not drive the UI language. The bundled `minimal` theme renders UI chrome from the site's default language and exposes a header selector that remembers the user's choice in `localStorage`, similar to the theme toggle. Language names in that selector stay in their native form instead of being translated into the current UI language.
If a theme omits a UI key for the current UI language, YiiPress falls back to the site's default language,
then to English, and only then to the key name itself, so theme translation files should define
the full UI vocabulary they need.
Archive month names in the bundled `minimal` theme come from theme translation keys `month.01` through `month.12`, with built-in English and Russian fallbacks. Language selector labels use `language.<code>` translation keys when present and otherwise fall back to built-in names or the uppercased language code. YiiPress does not require the PHP `intl` extension for this UI text.

### Syntax highlighting

Syntax highlighting uses built-in [syntect](https://github.com/trishume/syntect) themes and renders
inline styles during build. To switch the theme globally:

```yaml
highlight_theme: "Solarized (dark)"
```

If `highlight_theme` is omitted, YiiPress uses `InspiredGitHub`.

### Assets

Asset fingerprinting is enabled by default. Disable it in `content/config.yaml` if needed:

```yaml
assets:
  fingerprint: false
```

When enabled, YiiPress renames copied assets to include a content hash, for example:

- `assets/theme/style.css` → `assets/theme/style.4f8d2d5b1c3a.css`
- `blog/assets/hero.png` → `blog/assets/hero.a12b34c56d78.png`

Built-in templates use the fingerprinted URLs automatically, and existing hardcoded `src` / `href`
asset references in rendered HTML are rewritten during build so custom themes continue to work.

### Editor

During `yiipress serve`, HTML pages get a fixed bottom-right **Edit** button. Clicking it asks the preview server to open the markdown source file that produced the current page.

Configure the editor command in `content/config.yaml`:

```yaml
editor: code
```

If the command does not contain `{file}`, YiiPress appends the source file path. For commands that need the path in a specific position, use `{file}`:

```yaml
editor: "code --goto {file}"
```

The same command can be written as a list to avoid shell-style quoting:

```yaml
editor:
  - code
  - --goto
  - "{file}"
```

### robots.txt

A `robots.txt` file is generated by default with a permissive rule (allow all crawlers) and a `Sitemap:` pointer. Configure custom rules via `robots_txt`:

```yaml
robots_txt:
  generate: true   # set to false to disable robots.txt generation entirely
  rules:
    - user_agent: "*"
      disallow:
        - /private/
        - /admin/
      crawl_delay: 5
    - user_agent: "Bingbot"
      allow:
        - /
      disallow: []
```

Each rule supports:
- **user_agent** — crawler name (default: `*`)
- **allow** — list of allowed paths
- **disallow** — list of disallowed paths
- **crawl_delay** — seconds between requests (integer)

### Usage in templates

Templates receive focused variables for the page they render, such as `$siteTitle`, `$entryTitle`, `$content`, `$date`, `$author`, and `$collection`. See [Templates](templates.md) for the full variable list.

### Markdown settings

The `markdown` section controls which Markdown extensions are enabled. All options are boolean.

```yaml
markdown:
  tables: true
  strikethrough: true
  tasklists: true
  url_autolinks: true
  email_autolinks: true
  www_autolinks: true
  collapse_whitespace: true
  latex_math: false
  wikilinks: false
  underline: false
  no_html_blocks: true
  no_html_spans: true
  permissive_atx_headers: false
  no_indented_code_blocks: false
  hard_soft_breaks: true
```

- **tables** — GitHub-style tables (default: `true`)
- **strikethrough** — strikethrough with `~text~` (default: `true`)
- **tasklists** — GitHub-style task lists (default: `true`)
- **url_autolinks** — recognize URLs as auto-links even without `<>` (default: `true`)
- **email_autolinks** — recognize e-mails as auto-links even without `<>` and `mailto:` (default: `true`)
- **www_autolinks** — enable WWW auto-links (even without any scheme prefix, if they begin with 'www.') (default: `true`)
- **collapse_whitespace** — collapse non-trivial whitespace into single space (default: `true`)
- **latex_math** — enable LaTeX math spans `$...$` and `$$...$$` (default: `false`)
- **wikilinks** — enable wiki-style links `[[link]]` (default: `false`)
- **underline** — underscore `_` denotes underline instead of emphasis (default: `false`)
- **no_html_blocks** — disable raw HTML blocks (default: `true`)
- **no_html_spans** — disable inline raw HTML (default: `true`)
- **permissive_atx_headers** — do not require space in ATX headers ( `###header` ) (default: `false`)
- **no_indented_code_blocks** — disable indented code blocks (only fenced code works) (default: `false`)
- **hard_soft_breaks** — force all soft breaks to act as hard breaks (default: `true`)

If the `markdown` section is omitted, all defaults apply.

### Defaults and overrides

Collection `_collection.yaml` fields override content config defaults:

- Collection `entries_per_page` overrides `config.yaml` `entries_per_page`
- Collection `permalink` overrides `config.yaml` `permalink`
- Entry `permalink` overrides collection permalink

Resolution order: entry → collection → content config → engine defaults.
