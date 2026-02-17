# Templates

Templates are plain PHP files. Variables are passed via `require` inside an `ob_start()`/`ob_get_clean()` block, so each template has direct access to its variables as local PHP variables.

## Themes

A theme is a named set of templates. Themes are registered in the Yii3 DI configuration (`config/common/di/theme.php`). The built-in `default` theme ships in the `templates/` directory at the project root.

### Theme resolution order

When resolving a template, the build process checks:

1. **Entry-level theme** — set via `theme` in front matter.
2. **Site-level default theme** — set via `theme` in `config.yaml`.
3. **Built-in `default` theme** — always used as a fallback.

Within a theme, if the requested template file is not found, the `default` theme is checked as a fallback.

### Local theme

If a `templates/` directory exists inside the content directory, it is automatically registered as a theme named `local`. To use it as the site default:

```yaml
theme: local
```

### Per-entry theme

An entry can override the site default theme via front matter:

```yaml
---
title: My Post
theme: custom
---
```

### Registering themes in DI

Themes are registered in `config/common/di/theme.php`:

```php
use App\Build\Theme;
use App\Build\ThemeRegistry;
use Yiisoft\Definitions\DynamicReference;

return [
    ThemeRegistry::class => DynamicReference::to(static function (): ThemeRegistry {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('default', dirname(__DIR__, 3) . '/templates'));
        $registry->register(new Theme('fancy', '/path/to/fancy-theme/templates'));
        return $registry;
    }),
];
```

## Built-in templates

```
templates/
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

### Entry template (`entry.php`)

| Variable | Type | Description |
|----------|------|-------------|
| `$siteTitle` | `string` | Site title from `config.yaml` |
| `$entryTitle` | `string` | Entry title |
| `$content` | `string` | Rendered HTML content |
| `$date` | `string` | Formatted date (`Y-m-d`) or empty string |
| `$author` | `string` | Comma-separated author names |
| `$collection` | `string` | Collection name the entry belongs to |
| `$nav` | `?Navigation` | Navigation object or `null` |

Example:

```php
<article>
    <h1><?= htmlspecialchars($entryTitle) ?></h1>
<?php if ($date !== ''): ?>
    <time datetime="<?= htmlspecialchars($date) ?>"><?= htmlspecialchars($date) ?></time>
<?php endif; ?>
<?php if ($author !== ''): ?>
    <span class="author"><?= htmlspecialchars($author) ?></span>
<?php endif; ?>
    <div class="content"><?= $content ?></div>
</article>
```

### Collection listing template (`collection_listing.php`)

| Variable | Type | Description |
|----------|------|-------------|
| `$siteTitle` | `string` | Site title |
| `$collectionTitle` | `string` | Collection title |
| `$entries` | `list<array{title: string, url: string, date: string, summary: string}>` | Entries for the current page |
| `$pagination` | `array{currentPage: int, totalPages: int, previousUrl: string, nextUrl: string}` | Pagination data |
| `$nav` | `?Navigation` | Navigation object or `null` |

Example:

```php
<h1><?= htmlspecialchars($collectionTitle) ?></h1>
<ul>
<?php foreach ($entries as $entry): ?>
    <li>
        <a href="<?= htmlspecialchars($entry['url']) ?>"><?= htmlspecialchars($entry['title']) ?></a>
<?php if ($entry['date'] !== ''): ?>
        <time><?= htmlspecialchars($entry['date']) ?></time>
<?php endif; ?>
<?php if ($entry['summary'] !== ''): ?>
        <p><?= htmlspecialchars($entry['summary']) ?></p>
<?php endif; ?>
    </li>
<?php endforeach; ?>
</ul>
<?php if ($pagination['totalPages'] > 1): ?>
<nav class="pagination">
<?php if ($pagination['previousUrl'] !== ''): ?>
    <a href="<?= htmlspecialchars($pagination['previousUrl']) ?>" rel="prev">← Previous</a>
<?php endif; ?>
    <span>Page <?= $pagination['currentPage'] ?> of <?= $pagination['totalPages'] ?></span>
<?php if ($pagination['nextUrl'] !== ''): ?>
    <a href="<?= htmlspecialchars($pagination['nextUrl']) ?>" rel="next">Next →</a>
<?php endif; ?>
</nav>
<?php endif; ?>
```

### Taxonomy index template (`taxonomy_index.php`)

| Variable | Type | Description |
|----------|------|-------------|
| `$siteTitle` | `string` | Site title |
| `$taxonomyName` | `string` | Taxonomy name (e.g. `tags`, `categories`) |
| `$terms` | `list<string>` | All terms in this taxonomy |
| `$nav` | `?Navigation` | Navigation object or `null` |

Example:

```php
<h1><?= htmlspecialchars(ucfirst($taxonomyName)) ?></h1>
<ul>
<?php foreach ($terms as $term): ?>
    <li><a href="/<?= htmlspecialchars($taxonomyName) ?>/<?= htmlspecialchars($term) ?>/"><?= htmlspecialchars($term) ?></a></li>
<?php endforeach; ?>
</ul>
```

### Taxonomy term template (`taxonomy_term.php`)

| Variable | Type | Description |
|----------|------|-------------|
| `$siteTitle` | `string` | Site title |
| `$taxonomyName` | `string` | Taxonomy name |
| `$term` | `string` | Term value |
| `$entries` | `list<array{title: string, url: string, date: string}>` | Entries with this term |
| `$nav` | `?Navigation` | Navigation object or `null` |

### Author page template (`author.php`)

| Variable | Type | Description |
|----------|------|-------------|
| `$siteTitle` | `string` | Site title |
| `$authorTitle` | `string` | Author display name |
| `$authorEmail` | `string` | Author email (may be empty) |
| `$authorUrl` | `string` | Author URL (may be empty) |
| `$authorAvatar` | `string` | Author avatar path (may be empty) |
| `$authorBio` | `string` | Author bio rendered as HTML |
| `$entries` | `list<array{title: string, url: string, date: string}>` | Author's entries |
| `$nav` | `?Navigation` | Navigation object or `null` |

### Author index template (`author_index.php`)

| Variable | Type | Description |
|----------|------|-------------|
| `$siteTitle` | `string` | Site title |
| `$authorList` | `list<array{title: string, url: string, avatar: string}>` | All authors |
| `$nav` | `?Navigation` | Navigation object or `null` |

### Yearly archive template (`archive_yearly.php`)

| Variable | Type | Description |
|----------|------|-------------|
| `$siteTitle` | `string` | Site title |
| `$collectionName` | `string` | Collection name |
| `$collectionTitle` | `string` | Collection title |
| `$year` | `string` | Year |
| `$months` | `list<string>` | Months with entries (descending) |
| `$entries` | `list<array{title: string, url: string, date: string}>` | Entries for this year |
| `$nav` | `?Navigation` | Navigation object or `null` |

### Monthly archive template (`archive_monthly.php`)

| Variable | Type | Description |
|----------|------|-------------|
| `$siteTitle` | `string` | Site title |
| `$collectionName` | `string` | Collection name |
| `$collectionTitle` | `string` | Collection title |
| `$year` | `string` | Year |
| `$month` | `string` | Month number (zero-padded) |
| `$monthName` | `string` | Month name (e.g. `January`) |
| `$entries` | `list<array{title: string, url: string, date: string}>` | Entries for this month |
| `$nav` | `?Navigation` | Navigation object or `null` |

## Navigation

All templates receive `$nav` — a `Navigation` object (or `null` if no `navigation.yaml` exists).

Use `NavigationRenderer` for HTML output:

```php
<?php if ($nav !== null && $nav->menu('main') !== []): ?>
    <?= \App\Render\NavigationRenderer::render($nav, 'main') ?>
<?php endif; ?>
```

This renders a `<nav><ul><li>` structure with nested lists for children. Menu names correspond to top-level keys in `content/navigation.yaml`.

## Customizing templates

To customize a built-in template, create a theme with a file of the same name. The active theme takes priority over the `default` theme.

## Custom layouts

Entries can use a custom layout by setting `layout` in front matter:

```yaml
---
title: My Post
layout: wide
---
```

The build process looks for `wide.php` in the active theme, then falls back to the built-in `entry.php` if not found.

Custom layout templates receive the same variables as the default entry template (`$siteTitle`, `$entryTitle`, `$content`, `$date`, `$author`, `$collection`, `$nav`).

### Example

Create `content/templates/wide.php` (with `theme: local` in config):

```php
<?php
/** @var string $siteTitle */
/** @var string $entryTitle */
/** @var string $content */
/** @var string $date */
/** @var string $author */
/** @var ?\App\Content\Model\Navigation $nav */
?>
<!DOCTYPE html>
<html>
<head><title><?= htmlspecialchars($entryTitle) ?> — <?= htmlspecialchars($siteTitle) ?></title></head>
<body>
<div class="wide-container">
    <h1><?= htmlspecialchars($entryTitle) ?></h1>
    <div class="content"><?= $content ?></div>
</div>
</body>
</html>
```

Then reference it in any entry's front matter with `layout: wide`.
