# Templates

Templates are stored in the `templates` directory. Each template is a PHP file.

## Directory structure

```
templates/
├── layouts/
│   ├── main.php               # Default site layout (header, footer, nav)
│   ├── page.php               # Layout for page collection entries
│   └── post.php               # Layout for blog posts (with sidebar, author, etc.)
├── collections/
│   ├── _default.php           # Default collection listing template
│   └── blog.php               # Collection-specific: overrides _default for "blog"
├── entries/
│   ├── _default.php           # Default single entry template
│   └── blog.php               # Entry-specific: overrides _default for "blog" entries
├── taxonomy/
│   ├── tags.php               # Tag listing page (all tags)
│   ├── tag.php                # Single tag archive (entries with this tag)
│   ├── categories.php         # Category listing page (all categories)
│   ├── category.php           # Single category archive
│   └── authors.php            # Author listing page
├── author/
│   └── _default.php           # Single author page
├── archive/
│   ├── yearly.php             # Yearly archive template
│   └── monthly.php            # Monthly archive template
├── partials/
│   ├── head.php               # <head> section
│   ├── navigation.php         # Site navigation (uses content/navigation.yaml)
│   ├── pagination.php         # Pagination controls
│   ├── entry-card.php         # Entry summary card (for listings)
│   ├── author-card.php        # Author info block
│   └── sidebar.php            # Sidebar
├── feed/
│   ├── rss.php                # RSS feed template
│   └── atom.php               # Atom feed template
├── errors/
│   └── 404.php                # 404 error page
├── redirect.php               # Redirect page template (for redirect_to entries)
└── sitemap.php                # Sitemap XML template
```

## Template resolution

Templates are resolved in the following order:

1. **Entry-level** — `layout` front matter field selects a layout from `templates/layouts/`
2. **Collection-level** — collection name matches a template file in `templates/collections/` or `templates/entries/`
3. **Default** — `_default.php` is used as fallback

For example, an entry in the `blog` collection with `layout: post`:
- Layout: `templates/layouts/post.php`
- Entry template: `templates/entries/blog.php` (falls back to `templates/entries/_default.php`)

## Layouts

Layouts wrap page content. The rendered content is available as `$content`.

```php
<!-- templates/layouts/main.php -->
<!DOCTYPE html>
<html lang="<?= $config['language'] ?>">
<head>
    <meta charset="<?= $config['charset'] ?>">
    <title><?= $title ?></title>
</head>
<body>
    <?php include __DIR__ . '/../partials/navigation.php' ?>
    <main><?= $content ?></main>
</body>
</html>
```

## Collection template

The collection content is available in `$collection` object which has the following methods:

- `getTitle()` — collection title
- `getDescription()` — collection description
- `getEntries()` — entries for the current page
- `getPagination()` — pagination object

## Entry template

The entry content is available in `$entry` object which has the following methods:

- `getTitle()` — entry title
- `getHtml()` — entry content as HTML
- `getDate()` — publication date
- `getSummary()` — entry summary/excerpt
- `getTags()` — list of tags
- `getCategories()` — list of categories
- `getAuthors()` — list of author objects
- `getSlug()` — URL slug
- `getPermalink()` — resolved permalink
- `getWeight()` — sort weight
- `getLanguage()` — language code

The collection the entry belongs to is available in `$collection` object.

## Partials

Partials are reusable template fragments stored in `templates/partials/`.
Include them with standard PHP:

```php
<?php include __DIR__ . '/../partials/entry-card.php' ?>
```

## Feed templates

Feed templates render RSS/Atom XML. They receive the same `$collection` object as collection templates.
Feeds are generated only for collections with `feed: true` in their `_collection.yaml`.

## Error templates

- `templates/errors/404.php` — 404 error page

## Template variables

All templates have access to:

- **$config** — site-wide settings from `content/config.yaml` (see [Configuration](config.md))
- **$navigation** — parsed navigation menus from `content/navigation.yaml`

## Themes

A theme is a distributable set of templates. Theme templates are stored in a theme directory
and are overridden by project-level templates in `templates/`.

Resolution order:
1. `templates/` (project)
2. `themes/<active-theme>/templates/` (theme)
