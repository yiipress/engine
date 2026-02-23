<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Converts content from an external source into YiiPress Markdown files with front matter.
 *
 * Each importer handles a specific source format (e.g., Telegram export, WordPress XML, REST API).
 * Importers are registered in `config/common/di/importer.php` and invoked via the `yii import` command.
 *
 * The importer receives source-specific options (directory path, API URL, credentials, etc.),
 * reads or fetches data accordingly, and writes `.md` files with YAML front matter into
 * the target collection directory.
 */
interface ContentImporterInterface
{
    /**
     * Declares the options this importer accepts.
     *
     * Each option becomes a CLI option in the `yii import` command.
     * For example, a Telegram importer declares a `directory` option,
     * while a REST API importer might declare `url` and `api-key` options.
     *
     * @return list<ImporterOption>
     */
    public function options(): array;

    /**
     * Imports content into the target collection.
     *
     * The importer should:
     * - Read or fetch source data using values from `$options`.
     * - Create Markdown files with front matter in `$targetDirectory/$collection/`.
     * - Copy media assets to `$targetDirectory/$collection/assets/`.
     * - Create `_collection.yaml` if it does not exist.
     *
     * @param array<string, string|null> $options option values keyed by option name as declared in {@see options()}
     * @param string $targetDirectory absolute path to the content directory (e.g., `content/`)
     * @param string $collection target collection name (e.g., `blog`)
     */
    public function import(array $options, string $targetDirectory, string $collection): ImportResult;

    /**
     * Returns the unique identifier for this importer, used as the `source` argument in `yii import`.
     *
     * For example, `telegram`, `wordpress`, `jekyll`.
     */
    public function name(): string;
}
