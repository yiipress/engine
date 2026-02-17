# Templates

Templates are plain PHP files in `src/Render/Template/`. Variables are passed via `require` inside an `ob_start()`/`ob_get_clean()` block, so each template has direct access to its variables as local PHP variables.

## Directory structure

```
src/Render/Template/
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

To customize a template, edit the corresponding file in `src/Render/Template/`. Each template is a self-contained PHP file that produces a full HTML page.
