# Templates

Templates control the HTML around your Markdown content. YiiPress uses plain PHP templates, so you can write normal HTML and add small PHP expressions where dynamic values are needed.

Most sites do not need a full custom theme. Start by overriding one template in `content/templates/`, then add more files only when you need them.

## Quick customization

1. Create `content/templates/`.
2. Set the local theme in `content/config.yaml`:

```yaml
theme: local
```

3. Add a template file such as `content/templates/entry.php`.

A minimal entry template:

```php
<?php
/** @var string $siteTitle */
/** @var string $entryTitle */
/** @var string $content */
/** @var Closure $h */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= $h($entryTitle) ?> - <?= $h($siteTitle) ?></title>
</head>
<body>
    <main>
        <h1><?= $h($entryTitle) ?></h1>
        <?= $content ?>
    </main>
</body>
</html>
```

Use `$h()` for text that should be escaped. Rendered Markdown content in `$content` is already HTML and should not be escaped again.

## Themes

A theme is a named set of template files. YiiPress ships with the built-in `minimal` theme. A project-local `content/templates/` directory is automatically available as the `local` theme.

### Theme resolution order

When YiiPress renders a page, it chooses templates in this order:

1. **Entry-level theme** — set via `theme` in front matter.
2. **Site-level default theme** — set via `theme` in `config.yaml`.
3. **Built-in `minimal` theme** — fallback when a template is missing.

Within a theme, YiiPress uses the requested file when it exists and falls back to other registered themes when it does not. That means a local theme can override only `entry.php` and keep every other page type from `minimal`.

### Local theme

If a `templates/` directory exists inside the content directory, it is automatically registered as `local`. To use it as the site default:

```yaml
theme: local
```

### Per-entry theme

An entry can override the site default theme:

```yaml
---
title: My Post
theme: custom
---
```

Engine-level theme registration is covered in [Engine](engine.md#theme-registration).

## UI translations

Theme-localized UI labels live in `translation/<language>.yaml` inside the theme directory. The bundled `minimal` theme ships with English and Russian translations.

Use translation files for labels that are part of the theme, such as "Search", "Related posts", pagination controls, and month names. If a key is missing, YiiPress falls back to the site default UI language, then English, then the key name.

## Built-in templates

The built-in theme uses these template files:

```
themes/minimal/
├── entry.php               # Single entry page
├── collection_listing.php  # Collection listing with pagination
├── taxonomy_index.php      # Taxonomy index (all terms)
├── taxonomy_term.php       # Single taxonomy term (entries with this term)
├── author.php              # Single author page
├── author_index.php        # Author listing page
├── archive_yearly.php      # Yearly archive
├── archive_monthly.php     # Monthly archive
```

## Template variables

### Common variables

All built-in page templates receive these additional variables:

| Variable    | Type                    | Description                                                    |
|-------------|-------------------------|----------------------------------------------------------------|
| `$language` | `string`                | Effective page language code used for `<html lang="…">`       |
| `$uiLanguage` | `string`              | Server-rendered default UI language for theme chrome           |
| `$uiLanguages` | `list<string>`       | Available UI languages exposed by the site                    |
| `$uiCatalogs` | `array<string, array<string, string>>` | Theme UI catalogs for client-side switching |
| `$ui`       | `YiiPress\I18n\UiText`       | Injected localized UI-text helper for bundled theme labels    |
| `$h`        | `Closure(string, int, ?string, bool): string` | Injected alias for `htmlspecialchars()` |
| `$t`        | `Closure(string, array): string` | Injected shortcut for `$ui->get()` in templates      |

Example:

```php
<html lang="<?= $h($language) ?>">
<button aria-label="<?= $h($t('search')) ?>">
```

In the bundled `minimal` theme, `$language` is the content language of the current page, while the remembered UI language can differ and is applied client-side after load.
Built-in templates and partials expect `$ui` to be passed by the renderer; `PageTemplateRenderer`, `TemplateContext`, and `EntryRenderer` automatically provide `$t`, and all render paths inject `$h`.

### Entry template (`entry.php`)

| Variable      | Type          | Description                                                      |
|---------------|---------------|------------------------------------------------------------------|
| `$siteTitle`  | `string`      | Site title from `config.yaml`                                    |
| `$entryTitle` | `string`      | Entry title                                                      |
| `$content`    | `string`      | Rendered HTML content                                            |
| `$date`       | `string`      | Formatted date using `date_format` from `config.yaml` or empty   |
| `$dateISO`    | `string`      | ISO 8601 date (`Y-m-d`) for HTML5 `datetime` attribute or empty |
| `$author`     | `string`      | Comma-separated author names                                     |
| `$collection` | `string`      | Collection name the entry belongs to                             |
| `$extra`      | `array<string, mixed>` | Custom front matter under `extra`                         |
| `$showTitle`  | `bool`        | Whether the bundled entry template renders the generated `<h1>`  |
| `$permalink`  | `string`      | Current entry permalink                                          |
| `$nav`        | `?Navigation` | Navigation object or `null`                                      |
| `$toc`        | `list<array>` | Table of contents entries (`{id, text, level}`) or empty list    |
| `$related`    | `list<RelatedEntry>` | Related entries ordered by relevance or empty list        |
| `$language`   | `string`      | Effective language code for the current entry                    |
| `$translations` | `list<Translation>` | Alternate-language versions of the current entry           |
| `$navigationPager` | `?array{previous: ?array, next: ?array}` | Previous/next links resolved from sidebar navigation when enabled |
| `$lastUpdated` | `?array{iso: string, text: string}` | Source file modification time when `last_updated` is enabled |

Example:

```php
<article>
    <h1><?= $h($entryTitle) ?></h1>
<?php if ($date !== ''): ?>
    <time datetime="<?= $h($dateISO) ?>"><?= $h($date) ?></time>
<?php endif; ?>
<?php if ($author !== ''): ?>
    <span class="author"><?= $h($author) ?></span>
<?php endif; ?>
    <div class="content"><?= $content ?></div>
</article>
```

**Note:** Use `$dateISO` for the `datetime` attribute (HTML5 compliance) and `$date` for display text (uses configured format). In the bundled `minimal` theme, set top-level `showTitle: false` to suppress the generated entry `<h1>` while keeping the page title available for metadata and navigation.
The bundled `minimal` theme also uses `$ui` to localize built-in labels such as
"Related posts", "Other languages", "Search", pagination controls, and the remembered UI-language selector in the header.

### Collection listing template (`collection_listing.php`)

| Variable           | Type                                                                             | Description                  |
|--------------------|----------------------------------------------------------------------------------|------------------------------|
| `$siteTitle`       | `string`                                                                         | Site title                   |
| `$collectionTitle` | `string`                                                                         | Collection title             |
| `$entries`         | `list<array{title: string, url: string, date: string, summary: string}>`         | Entries for the current page |
| `$pagination`      | `array{currentPage: int, totalPages: int, previousUrl: string, nextUrl: string}` | Pagination data              |
| `$nav`             | `?Navigation`                                                                    | Navigation object or `null`  |

Example:

```php
<h1><?= $h($collectionTitle) ?></h1>
<ul>
<?php foreach ($entries as $entry): ?>
    <li>
        <a href="<?= $h($entry['url']) ?>"><?= $h($entry['title']) ?></a>
<?php if ($entry['date'] !== ''): ?>
        <time><?= $h($entry['date']) ?></time>
<?php endif; ?>
<?php if ($entry['summary'] !== ''): ?>
        <p><?= $h($entry['summary']) ?></p>
<?php endif; ?>
    </li>
<?php endforeach; ?>
</ul>
<?php if ($pagination['totalPages'] > 1): ?>
<nav class="pagination">
<?php if ($pagination['previousUrl'] !== ''): ?>
    <a href="<?= $h($pagination['previousUrl']) ?>" rel="prev">← Previous</a>
<?php endif; ?>
    <span>Page <?= $pagination['currentPage'] ?> of <?= $pagination['totalPages'] ?></span>
<?php if ($pagination['nextUrl'] !== ''): ?>
    <a href="<?= $h($pagination['nextUrl']) ?>" rel="next">Next →</a>
<?php endif; ?>
</nav>
<?php endif; ?>
```

### Taxonomy index template (`taxonomy_index.php`)

| Variable        | Type           | Description                               |
|-----------------|----------------|-------------------------------------------|
| `$siteTitle`    | `string`       | Site title                                |
| `$taxonomyName` | `string`       | Taxonomy name (e.g. `tags`, `categories`) |
| `$terms`        | `list<string>` | All terms in this taxonomy                |
| `$nav`          | `?Navigation`  | Navigation object or `null`               |

Example:

```php
<h1><?= $h(ucfirst($taxonomyName)) ?></h1>
<ul>
<?php foreach ($terms as $term): ?>
    <li><a href="/<?= $h($taxonomyName) ?>/<?= $h($term) ?>/"><?= $h($term) ?></a></li>
<?php endforeach; ?>
</ul>
```

### Taxonomy term template (`taxonomy_term.php`)

| Variable        | Type                                                    | Description                 |
|-----------------|---------------------------------------------------------|-----------------------------|
| `$siteTitle`    | `string`                                                | Site title                  |
| `$taxonomyName` | `string`                                                | Taxonomy name               |
| `$term`         | `string`                                                | Term value                  |
| `$entries`      | `list<array{title: string, url: string, date: string}>` | Entries with this term      |
| `$nav`          | `?Navigation`                                           | Navigation object or `null` |

### Author page template (`author.php`)

| Variable        | Type                                                    | Description                       |
|-----------------|---------------------------------------------------------|-----------------------------------|
| `$siteTitle`    | `string`                                                | Site title                        |
| `$authorTitle`  | `string`                                                | Author display name               |
| `$authorEmail`  | `string`                                                | Author email (may be empty)       |
| `$authorUrl`    | `string`                                                | Author URL (may be empty)         |
| `$authorAvatar` | `string`                                                | Author avatar path (may be empty) |
| `$authorBio`    | `string`                                                | Author bio rendered as HTML       |
| `$entries`      | `list<array{title: string, url: string, date: string}>` | Author's entries                  |
| `$nav`          | `?Navigation`                                           | Navigation object or `null`       |

### Author index template (`author_index.php`)

| Variable      | Type                                                      | Description                 |
|---------------|-----------------------------------------------------------|-----------------------------|
| `$siteTitle`  | `string`                                                  | Site title                  |
| `$authorList` | `list<array{title: string, url: string, avatar: string}>` | All authors                 |
| `$nav`        | `?Navigation`                                             | Navigation object or `null` |

### Yearly archive template (`archive_yearly.php`)

| Variable           | Type                                                    | Description                      |
|--------------------|---------------------------------------------------------|----------------------------------|
| `$siteTitle`       | `string`                                                | Site title                       |
| `$collectionName`  | `string`                                                | Collection name                  |
| `$collectionTitle` | `string`                                                | Collection title                 |
| `$year`            | `string`                                                | Year                             |
| `$months`          | `list<string>`                                          | Months with entries (descending) |
| `$entries`         | `list<array{title: string, url: string, date: string}>` | Entries for this year            |
| `$nav`             | `?Navigation`                                           | Navigation object or `null`      |

### Monthly archive template (`archive_monthly.php`)

| Variable           | Type                                                    | Description                 |
|--------------------|---------------------------------------------------------|-----------------------------|
| `$siteTitle`       | `string`                                                | Site title                  |
| `$collectionName`  | `string`                                                | Collection name             |
| `$collectionTitle` | `string`                                                | Collection title            |
| `$year`            | `string`                                                | Year                        |
| `$month`           | `string`                                                | Month number (zero-padded)  |
| `$monthName`       | `string`                                                | Month name (e.g. `January`) |
| `$entries`         | `list<array{title: string, url: string, date: string}>` | Entries for this month      |
| `$nav`             | `?Navigation`                                           | Navigation object or `null` |

## Navigation

All templates receive `$nav` — a `Navigation` object (or `null` if no `navigation.yaml` exists).

Use `NavigationRenderer` for HTML output:

```php
<?php if ($nav !== null && $nav->menu('main') !== []): ?>
    <?= \YiiPress\Render\NavigationRenderer::render($nav, 'main') ?>
<?php endif; ?>
```

This renders a `<nav><ul><li>` structure with nested lists for children. Menu names correspond to top-level keys in `content/navigation.yaml`.

Pass the optional class and current URL arguments when rendering sidebars that need active item styling:

```php
<?= \YiiPress\Render\NavigationRenderer::render($nav, 'sidebar', $rootPath, $uiLanguage, $uiLanguage, 'docs-sidebar-nav', $permalink) ?>
```

The renderer adds `aria-current="page"` to the current link, `is-current` to the current `<li>`, and `is-active-ancestor` to parent `<li>` elements.

## Partials

Partials are reusable template fragments stored in a `partials/` subdirectory of a theme. Every template receives a `$partial` helper function that renders a partial with isolated variable scope.

### Usage

```php
<?= $partial('head', ['title' => $entryTitle . ' — ' . $siteTitle]) ?>
```

## Asset helper

Templates and partials should use `Asset::url()` to resolve the final public URL of a copied asset:

```php
<?php

use YiiPress\Build\Asset;
?>
<link rel="stylesheet" href="<?= $h(Asset::url('assets/theme/style.css', $rootPath, $assetManifest)) ?>">
<script src="<?= $h(Asset::url('assets/theme/search.js', $rootPath, $assetManifest)) ?>" defer></script>
```

This is especially useful when `assets.fingerprint: true` is enabled in `content/config.yaml`.
In that mode, `Asset::url('assets/theme/style.css', $rootPath, $assetManifest)` returns the hashed output path rather than the logical one.

The helper accepts logical build-relative paths such as:

- `assets/theme/style.css`
- `assets/plugins/mermaid.css`

### Creating a partial

Create a PHP file in `themes/<name>/partials/`:

```php
<?php
/** @var string $title */
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h($title) ?></title>
    <link rel="stylesheet" href="/assets/theme/style.css">
```

### Variable isolation

Partials receive **only** the variables passed via the second argument. Parent template variables do not leak into partials. This prevents accidental coupling between templates and partials.

### Nesting partials

Partials can include other partials — the `$partial` function is automatically available inside every partial:

```php
<div class="page">
    <?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav]) ?>
    <main><?= $content ?></main>
    <?= $partial('footer', ['nav' => $nav]) ?>
</div>
```

### Built-in partials (minimal theme)

| Partial       | Variables            | Description                                      |
|---------------|----------------------|--------------------------------------------------|
| `head`        | `$title`             | `<meta>` tags, `<title>`, stylesheet link        |
| `header`      | `$siteTitle`, `$nav` | Site header with navigation and dark mode toggle |
| `footer`      | `$nav`               | Footer navigation and dark mode script           |

### Theme resolution

Partials follow the same theme resolution as templates: the active theme is checked first, then other registered themes as fallback.

## Template helper functions

All templates receive the following helper functions as local variables:

| Function   | Signature                                       | Description                                              |
|------------|-------------------------------------------------|----------------------------------------------------------|
| `$partial` | `(string $name, array $variables = []): string` | Render a partial template from the `partials/` directory |
| `$h`       | `(string $string, int $flags = ENT_QUOTES | ENT_SUBSTITUTE, ?string $encoding = 'UTF-8', bool $doubleEncode = true): string` | Escape HTML output |
| `$t`       | `(string $key, array $params = []): string`     | Translate a theme UI-text key via the injected `$ui`     |

Additional helpers available via static methods:

| Helper                         | Usage                                      | Description                                             |
|--------------------------------|--------------------------------------------|---------------------------------------------------------|
| `NavigationRenderer::render()` | `NavigationRenderer::render($nav, 'main')` | Render a navigation menu as nested `<nav><ul><li>` HTML |
| `NavigationRenderer::menuContainsUrl()` | `NavigationRenderer::menuContainsUrl($nav, 'sidebar', $permalink)` | Check whether a menu contains the current page URL |
| `$h()`                         | `$h($text)`                               | Template alias for `htmlspecialchars()`                 |

## Customizing templates

To customize a built-in template, create a theme with a file of the same name. The active theme takes priority over other registered themes.

## Custom layouts

Entries can use a custom layout by setting `layout` in front matter:

```yaml
---
title: My Post
layout: wide
---
```

The build process looks for `wide.php` in the active theme, then falls back to the built-in `entry.php` if not found.

Custom layout templates receive the same variables as the default entry template (`$siteTitle`, `$entryTitle`, `$content`, `$date`, `$author`, `$collection`, `$extra`, `$showTitle`, `$nav`).

### Example

Create `content/templates/wide.php` (with `theme: local` in config):

```php
<?php
/** @var string $siteTitle */
/** @var string $entryTitle */
/** @var string $content */
/** @var string $date */
/** @var string $author */
/** @var ?\YiiPress\Content\Model\Navigation $nav */
?>
<!DOCTYPE html>
<html>
<head><title><?= $h($entryTitle) ?> — <?= $h($siteTitle) ?></title></head>
<body>
<div class="wide-container">
    <h1><?= $h($entryTitle) ?></h1>
    <div class="content"><?= $content ?></div>
</div>
</body>
</html>
```

Then reference it in any entry's front matter with `layout: wide`.
