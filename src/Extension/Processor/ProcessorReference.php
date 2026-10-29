<?php

declare(strict_types=1);

namespace YiiPress\Extension\Processor;

final readonly class ProcessorReference implements \Stringable
{
    /**
     * @param string $pipeline `content` or `feed`.
     * @param string $stage `before_markdown` or `after_markdown`.
     * @param string $path The path as `config.yaml` lists it, or as discovery finds it.
     * @param string $packagePath The file it points to, relative to the package directory.
     * @param bool $discovered Whether discovery of `processors/*.php` finds it rather than `processors` listing it.
     */
    public function __construct(
        public string $pipeline,
        public string $stage,
        public string $path,
        public string $packagePath,
        public bool $discovered = false,
    ) {}

    public function __toString(): string
    {
        return $this->discovered
            ? $this->path . ' (discovered)'
            : $this->pipeline . '.' . $this->stage . ': ' . $this->path;
    }
}
