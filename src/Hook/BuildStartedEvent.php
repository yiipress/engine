<?php

declare(strict_types=1);

namespace YiiPress\Hook;

use YiiPress\Content\Model\Author;
use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Navigation;
use YiiPress\Content\Model\SiteConfig;

final readonly class BuildStartedEvent implements HookEventInterface
{
    public const string NAME = 'build.started';

    /**
     * @param array<string, Collection> $collections
     * @param array<string, Author> $authors
     */
    public function __construct(
        public BuildContext $context,
        public SiteConfig $siteConfig,
        public Navigation $navigation,
        public array $collections,
        public array $authors,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }
}
