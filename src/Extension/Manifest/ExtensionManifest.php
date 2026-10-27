<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\ExtensionType;

/**
 * Validated contents of `extension.yaml`.
 */
final readonly class ExtensionManifest
{
    /**
     * @param int $manifest Manifest format version.
     * @param string $yiipress Composer constraint of supported YiiPress versions.
     * @param list<Author> $authors
     */
    public function __construct(
        public int $manifest,
        public ExtensionId $id,
        public ExtensionType $type,
        public string $name,
        public string $description,
        public string $version,
        public string $yiipress,
        public string $license,
        public array $authors,
        public ?string $homepage = null,
        public Platforms $platforms = new Platforms(),
        private ?ThemeMetadata $theme = null,
        private ?ProcessorMetadata $processor = null,
    ) {}

    /**
     * Theme fields; `null` unless the type is `theme`.
     */
    public function theme(): ?ThemeMetadata
    {
        return $this->theme;
    }

    /**
     * Processor fields; `null` unless the type is `processor`.
     */
    public function processor(): ?ProcessorMetadata
    {
        return $this->processor;
    }
}
