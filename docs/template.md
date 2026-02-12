# Templates

Templates are stored in the `templates` directory.

Each template is a PHP file.

## Layout

Common layout is stored in `layout.php` file.

## Collection template

Collection template is a PHP file that is used to render collection.

The collection content is available in `$collection` object which has the following methods:

- `getTitle()`: returns collection title
- `getEntries()`: returns collection entries
- `getPagination()`: returns collection pagination

## Entry template

Entry template is a PHP file that is used to render individual entry.

The entry content is available in `$entry` object which has the following methods:

- `getTitle()`: returns entry title
- `getHtml()`: returns entry content as HTML

The collection the entry belongs to is available in `$collection` object.

## Error templates

There are additional templates for various error cases.

- `error404.php`: error 404 template

