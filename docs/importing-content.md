# Importing content

Content importers convert data from external sources (Telegram, WordPress, Jekyll, REST APIs, databases, etc.) into YiiPress markdown files with front matter. They are invoked via the `yii import` command.

## Importer interface

An importer implements `YiiPress\Import\ContentImporterInterface`:

```php
interface ContentImporterInterface
{
    public function options(): array;
    public function import(array $options, string $targetDirectory, string $collection): ImportResult;
    public function name(): string;
}
```

- **`options()`** — returns a list of `ImporterOption` objects declaring what CLI options this importer accepts. Each option becomes a `--name` flag in the `yii import` command.
- **`import()`** — receives resolved option values as `$options` (keyed by option name), creates `.md` files in `$targetDirectory/$collection/`, copies media to `$targetDirectory/$collection/assets/`, and creates `_collection.yaml` if missing.
- **`name()`** — returns the unique identifier used as the `source` argument in `yii import` (e.g., `telegram`, `wordpress`).

### ImporterOption

Each importer declares its options using `ImporterOption`:

```php
new ImporterOption(
    name: 'directory',
    description: 'Path to the export directory',
    required: true,
    default: null,
    path: true,
)
```

- **`name`** — option name, used as `--name` on the CLI and as key in the `$options` array.
- **`description`** — help text shown in `yii import --help`.
- **`required`** — whether the option must be provided. The command validates this before calling `import()`.
- **`default`** — default value when the option is not provided (only for optional options).
- **`path`** — whether the option value is a filesystem path. Path options are resolved relative to the project root; non-path options such as comma-separated IDs are passed through unchanged.

### ImportResult

`ImportResult` is a value object returned by `import()` containing:

- **`totalMessages()`** — total number of source items found.
- **`importedCount()`** — number of items successfully imported.
- **`importedFiles()`** — list of created file paths.
- **`skippedFiles()`** — list of skipped items with reasons.
- **`warnings()`** — list of warning messages.

## Built-in importers

### TelegramContentImporter

Imports messages from a Telegram Desktop channel export (JSON format).

**Options:**

- `--directory` — Path to the Telegram export directory containing `result.json` (required)
- `--ignore_message_ids` — Comma-separated list of message IDs to skip during import (optional)

See [commands.md](commands.md#yii-import) for usage details.

### JekyllContentImporter

Imports Markdown posts from a Jekyll site `_posts/` directory.

**Options:**

- `--directory` — Path to the Jekyll site directory containing `_posts` (required)

The importer accepts `.md` and `.markdown` posts named `YYYY-MM-DD-slug`, preserves common front matter (`title`, `date`, `permalink`, `tags`, `categories`), and creates a default collection config when one does not exist.

See [commands.md](commands.md#jekyll-import) for usage details.

### HugoContentImporter

Imports Markdown content from a Hugo site.

**Options:**

- `--directory` — Path to the Hugo site directory (required)

The importer scans `content/posts/`, then `content/post/`, then `content/`, accepts `.md` files, supports YAML (`---`) and simple TOML (`+++`) front matter, preserves common fields (`title`, `date`, `url` / `permalink`, `draft`, `tags`, `categories`), and creates a default collection config when one does not exist.

See [commands.md](commands.md#hugo-import) for usage details.

## Writing a custom importer

Create a class implementing `ContentImporterInterface`. Each importer declares its own options — a file-based importer might need a `directory`, while an API-based importer might need `url` and `api-key`.

For example, a REST API importer:

```php
final class RestApiContentImporter implements ContentImporterInterface
{
    public function options(): array
    {
        return [
            new ImporterOption(name: 'url', description: 'API endpoint URL', required: true),
            new ImporterOption(name: 'api-key', description: 'API authentication key', required: true),
            new ImporterOption(name: 'limit', description: 'Max posts to import', default: '100'),
        ];
    }

    public function import(array $options, string $targetDirectory, string $collection): ImportResult
    {
        $url = $options['url'];
        $apiKey = $options['api-key'];
        $limit = (int) ($options['limit'] ?? '100');

        // 1. Fetch data from the API
        // 2. For each post, create a .md file with front matter in $targetDirectory/$collection/
        // 3. Return ImportResult with stats
    }

    public function name(): string
    {
        return 'rest-api';
    }
}
```

Each generated markdown file should follow the standard YiiPress front matter format:

```markdown
---
title: My Post Title
date: 2024-03-15 10:30:00
tags:
  - php
  - tutorial
---

Post content in markdown...
```

To register the importer, add it to the `importers` array in `config/common/di/importer.php`:

```php
use YiiPress\Console\ImportCommand;
use YiiPress\Import\Telegram\TelegramContentImporter;
use YiiPress\Import\RestApiContentImporter;

return [
    ImportCommand::class => [
        '__construct()' => [
            'rootPath' => dirname(__DIR__, 3),
            'importers' => [
                'telegram' => new TelegramContentImporter(),
                'rest-api' => new RestApiContentImporter(),
            ],
        ],
    ],
];
```

The array key must match the value returned by `name()` and is used as the `source` argument:

```bash
yii import rest-api --url=https://api.example.com/posts --api-key=secret
```
