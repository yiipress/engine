<?php

declare(strict_types=1);

namespace YiiPress\Import;

/**
 * Declares an option that a content importer accepts.
 *
 * Each importer returns a list of these from {@see ContentImporterInterface::options()}.
 * The `yii import` command registers them as CLI options and passes resolved values to the importer.
 * Set `$resolvePath` to true only for options that represent filesystem paths.
 */
final readonly class ImporterOption
{
    public function __construct(
        public string $name,
        public string $description,
        public bool $required = false,
        public ?string $default = null,
        public bool $resolvePath = false,
    ) {}
}
